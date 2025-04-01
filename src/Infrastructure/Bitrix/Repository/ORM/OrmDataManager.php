<?php
/** @noinspection SqlNoDataSourceInspection */
declare(strict_types=1);

namespace Infrastructure\Bitrix\Repository\ORM;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\Result;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Entity\Base;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\SystemException;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Should be extended by EntityTable classes
 *
 */
class OrmDataManager extends DataManager
{
    private static ?LoggerInterface $logger = null;
    public static string $entityTableClass;
    
    /**
     * @throws Exception
     * @return Result
     */
    public static function dropTable(): Result
    {
        try {
            $sqlQuery = 'DROP TABLE IF EXISTS `' . static::getTableName() . '`';
            $result = static::getEntity()->getConnection()->query($sqlQuery);
        } catch (Exception $e) {
            throw new Exception('Ошибка при удалении таблицы "' . static::getTableName() . '": ' . $e->getMessage());
        }
        return $result;
    }
    
    /**
     * Creates and modifies DB table
     * @param LoggerInterface|null $logger
     * @return void
     * @throws Exception
     */
    public static function checkTable(?LoggerInterface $logger = null): void
    {
        self::$logger = $logger;
        //self::$logger?->debug('checking table `' . Base::getInstance(self::$entityTableClass)->getDBTableName() . '`');
        self::createTable();
        self::updateTable();
    }
    
    /**
     * Created database table if it does not exist
     * @throws Exception
     */
    public static function createTable(): void
    {
        $connectionName = self::getConnectionName();
        $tableName = Base::getInstance(self::$entityTableClass)->getDBTableName();
        
        if ( !Application::getConnection($connectionName)->isTableExists($tableName) ) {
            self::$logger?->info('CREATING TABLE `' . $tableName . '`');
            Base::getInstance(self::$entityTableClass)->createDbTable();
        }
    }
    
    /**
     * Updates table fields
     * @return void
     * @throws SqlQueryException
     * @throws SystemException
     * @throws ArgumentException
     */
    public static function updateTable(): void
    {
        $scalarFields = Base::getInstance(self::$entityTableClass)->getScalarFields();
        
        $connection = Application::getConnection();
        $result = $connection->query('DESCRIBE `' . static::getTableName() . '`');
        
        $actualFields = [];
        
        while ( $tableField = $result->fetch() ) {
            $actualFields[$tableField['Field']] = $tableField;
        }
        
        $base = Base::getInstance(self::$entityTableClass);
        $connection = $base->getConnection();
        
        //self::$logger?->debug('updating table `' . Base::getInstance(self::$entityTableClass)->getDBTableName() . '`');
        
        foreach ( $scalarFields as $field ) {
            self::addField($field, $actualFields, $connection);
            self::alterField($field, $actualFields, $connection);
        }
        
        foreach ( $actualFields as $actualField ) {
            self::deleteField($actualField, $scalarFields, $connection);
        }
    }
    
    /**
     * @param mixed $field
     * @param Connection $connection
     * @return string
     */
    public static function getSqlField(mixed $field, Connection $connection): string
    {
        return $connection->getSqlHelper()->quote($field->getName())
            . ' ' . $connection->getSqlHelper()->getColumnTypeByField($field)
            . ($field->isNullable() ? '' : ' NOT NULL')
            ;
    }
    
    /**
     * @param mixed $field
     * @param array $actualFields
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function addField(mixed $field, array $actualFields, Connection $connection): void
    {
        if ( array_key_exists($field->getName(), $actualFields) ) {
            return;
        }
        
        $sqlField = 'ALTER TABLE `' . static::getTableName() . '` ADD ' . self::getSqlField($field, $connection);
        
        self::$logger?->info($sqlField);
        
        $connection->query($sqlField);
    }
    
    /**
     * @param mixed $field
     * @param array $actualFields
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function alterField(mixed $field, array $actualFields, Connection $connection): void
    {
        if ( !self::columnExists($connection, $field->getName()) ) {
            return;
        }
        
        if ( !$actualFields[$field->getName()]['Type'] ) {
            return;
        }
        
        if ( $field instanceof TextField ) {
            if ( 'text' !== $actualFields[$field->getName()]['Type'] ) {
                
                $sqlField = 'ALTER TABLE `' . static::getTableName() . '` MODIFY ' . self::getSqlField($field, $connection);
                self::$logger?->info($sqlField);
                $connection->query($sqlField);
            } else {
                // class TextField extends StringField
                return;
            }
        }
        
        if ( $field instanceof StringField ) {
            if ( !str_starts_with($actualFields[$field->getName()]['Type'], 'varchar') ) {
                
                $sqlField = 'ALTER TABLE `' . static::getTableName() . '` MODIFY ' . self::getSqlField($field, $connection);
                self::$logger?->info($sqlField);
                $connection->query($sqlField);
            }
        }
        
        if ( $field instanceof IntegerField ) {
            if ( !str_starts_with($actualFields[$field->getName()]['Type'], 'int') ) {
                $sqlField = 'ALTER TABLE `' . static::getTableName() . '` MODIFY ' . self::getSqlField($field, $connection);
                if ( $field->isAutocomplete() ) {
                    $sqlField .= ' AUTO_INCREMENT';
                }
                self::$logger?->info($sqlField);
                $connection->query($sqlField);
            }
        }
        
        // @fixme @todo for other Fields
        
    }
    
    /**
     * @param array $actualField
     * @param mixed $scalarFields
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function deleteField(array $actualField, mixed $scalarFields, Connection $connection): void
    {
        if ( array_key_exists($actualField['Field'], $scalarFields) ) {
            return;
        }
        
        if ( !self::columnExists($connection, $actualField['Field']) ) {
            return;
        }
        
        $sqlField = 'ALTER TABLE `' . static::getTableName() . '` DROP COLUMN `' . $actualField['Field'] . '`';
        
        self::$logger?->info($sqlField);
        
        $connection->query($sqlField);
    }
    
    /**
     *
     * @param Connection $connection
     * @param string $columnName
     * @param string $tableName
     * @return bool
     * @throws SqlQueryException
     */
    public static function columnExists(Connection $connection, string $columnName, string $tableName = ''): bool
    {
        $tableName = $tableName ?: static::getTableName();
        
        $sql = '
        SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE
                TABLE_NAME = \'' . addslashes($tableName) . '\'
                AND COLUMN_NAME = \'' . addslashes($columnName) . '\';
        ';
        
        $result = $connection->query($sql);
        
        if ( !$fetch = $result->fetch() ) {
            return false;
        }
        
        return (bool) $fetch;
    }
}
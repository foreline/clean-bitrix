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
use Bitrix\Main\Entity\TextField;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\SystemException;
use Exception;

/**
 * Should be extended by EntityTable classes
 *
 */
class OrmDataManager extends DataManager
{
    public static string $entityTableClass;
    
    /**
     * @throws SqlQueryException
     * @throws SystemException
     * @return Result
     */
    public static function dropTable(): Result
    {
        $sqlQuery = 'DROP TABLE IF EXISTS `' . static::getTableName() . '`';
        return static::getEntity()->getConnection()->query($sqlQuery);
    }

    /**
     * Creates and modifies DB table
     * @return void
     * @throws ArgumentException
     * @throws SqlQueryException
     * @throws SystemException
     * @throws Exception
     */
    public static function checkTable(): void
    {
        self::createTable();
        self::updateTable();
    }
    
    /**
     * Created database table
     * @throws Exception
     */
    public static function createTable(): void
    {
        $connectionName = self::getConnectionName();
        $tableName = Base::getInstance(self::$entityTableClass)->getDBTableName();
        
        if ( !Application::getConnection($connectionName)->isTableExists($tableName) ) {
            Base::getInstance(self::$entityTableClass)->createDbTable();
        }
    }
    
    /**
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
        if ( $field instanceof TextField ) {
            if ( 'text' !== $actualFields[$field->getName()]['Type'] ) {
                $sqlField = 'ALTER TABLE `' . static::getTableName() . '` MODIFY IF EXISTS ' . self::getSqlField($field, $connection);
                $connection->query($sqlField);
            }
        }
        
        if ( $field instanceof StringField ) {
            if ( 'varchar(255)' !== $actualFields[$field->getName()]['Type'] ) {
                $sqlField = 'ALTER TABLE `' . static::getTableName() . '` MODIFY IF EXISTS ' . self::getSqlField($field, $connection);
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
    
        $sqlField = 'ALTER TABLE `' . static::getTableName() . '` DROP IF EXISTS `' . $actualField['Field'] . '`';
        $connection->query($sqlField);
    }
}
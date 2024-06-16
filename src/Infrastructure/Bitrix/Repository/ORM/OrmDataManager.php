<?php
    declare(strict_types=1);
    
    namespace Infrastructure\Bitrix\Repository\ORM;
    
    use Bitrix\Main\Application;
    use Bitrix\Main\ArgumentException;
    use Bitrix\Main\DB\Result;
    use Bitrix\Main\DB\SqlQueryException;
    use Bitrix\Main\Entity\Base;
    use Bitrix\Main\Entity\DataManager;
    use Bitrix\Main\SystemException;
    use Exception;

    /**
     * Should be extended by EntityTable classes
     *
     */
    class OrmDataManager extends DataManager
    {
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
         */
        public static function checkTable(): void
        {
        
        }
    
        /**
         * Created database table
         * @throws Exception
         */
        public static function createTable(): void
        {
            $connectionName = self::getConnectionName();
            $tableName = Base::getInstance(self::class)->getDBTableName();
    
            if ( !Application::getConnection($connectionName)->isTableExists($tableName) ) {
                Base::getInstance(self::class)->createDbTable();
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
            $scalarFields = Base::getInstance(self::class)->getScalarFields();
        
            $connection = Application::getConnection();
            $result = $connection->query('DESCRIBE `' . self::getTableName() . '`');
        
            $actualFields = [];
        
            while ( $tableField = $result->fetch() ) {
                $actualFields[$tableField['Field']] = $tableField;
            }
        
            $base = Base::getInstance(self::class);
            $connection = $base->getConnection();
        
            foreach ( $scalarFields as $fieldName => $field ) {
                if ( !array_key_exists($fieldName, $actualFields) ) {
                
                    $sqlField = $connection->getSqlHelper()->quote($fieldName)
                        . ' ' . $connection->getSqlHelper()->getColumnTypeByField($field)
                        . ($field->isNullable() ? '' : ' NOT NULL')
                    ;
                
                    $sqlField = 'ALTER TABLE `' . self::getTableName() . '` ADD ' . $sqlField;
                
                    $connection->query($sqlField);
                }
            }
        }
    }
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
use Bitrix\Main\ORM\Fields\DateTimeField;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
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
    private static bool $dryRun = false;
    private static array $detectedChanges = [];
    
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
     * Detect what changes would be made without applying them
     * @param LoggerInterface|null $logger
     * @return array Array of detected changes
     * @throws Exception
     */
    public static function detectChanges(?LoggerInterface $logger = null): array
    {
        self::$logger = $logger;
        self::$dryRun = true;
        self::$detectedChanges = [];
        
        try {
            self::createTable();
            self::updateTable();
        } finally {
            self::$dryRun = false;
        }
        
        return self::$detectedChanges;
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
        self::$dryRun = false;
        self::$detectedChanges = [];
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
            $message = 'CREATING TABLE `' . $tableName . '`';
            self::$logger?->info($message);
            
            if (self::$dryRun) {
                self::$detectedChanges[] = [
                    'type' => 'create_table',
                    'table' => $tableName,
                    'message' => $message,
                ];
            } else {
                Base::getInstance(self::$entityTableClass)->createDbTable();
            }
        }
    }
    
    /**
     * Updates (add, alter and delete) table fields
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
        
        /** @var string[] names of indexes */
        $actualConstraints = [];
        
        $query = '
        SELECT
            TABLE_SCHEMA,
            TABLE_NAME,
            INDEX_NAME,
            COLUMN_NAME,
            SEQ_IN_INDEX,
            CARDINALITY,
            NULLABLE
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE
            TABLE_SCHEMA = "' . $connection->getDatabase() . '"
            AND TABLE_NAME = "' . static::getTableName() . '"
        ORDER BY TABLE_SCHEMA, TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;';
        
        $indexResult = $connection->query($query);
        
        while ( $index = $indexResult->fetch() ) {
            $actualConstraints[$index['INDEX_NAME']] = $index;
        }
        
        $base = Base::getInstance(self::$entityTableClass);
        $connection = $base->getConnection();
        
        //self::$logger?->debug('updating table `' . Base::getInstance(self::$entityTableClass)->getDBTableName() . '`');
        
        foreach ( $scalarFields as $field ) {
            self::addField($field, $actualFields, $connection);
            self::alterField($field, $actualFields, $connection);
            self::addConstraint($field, $actualConstraints, $connection);
        }
        
        foreach ( $actualFields as $actualField ) {
            self::deleteField($actualField, $scalarFields, $connection);
        }
        
        foreach ( $actualConstraints as $actualConstraint ) {
            self::deleteConstraint($actualConstraint, $scalarFields, $connection);
        }
        
        // Handle primary key constraints FIRST
        $actualPrimaryKeys = self::getActualPrimaryKeys($connection);
        self::managePrimaryKeys($scalarFields, $actualPrimaryKeys, $connection);
        
        // Handle AUTO_INCREMENT after primary keys are established
        self::manageAutoIncrement($scalarFields, $actualFields, $connection);
        
        // Handle foreign key indexes AFTER primary keys and constraints
        $allFields = Base::getInstance(self::$entityTableClass)->getFields();
        $foreignKeyFields = self::getForeignKeyFields($allFields);
        self::manageForeignKeyIndexes($foreignKeyFields, $actualConstraints, $connection);
        
        // Handle boolean field indexes for performance optimization
        $booleanFields = self::getBooleanFields($allFields);
        self::manageBooleanFieldIndexes($booleanFields, $actualConstraints, $connection);
        
        // Handle external reference string field indexes for performance optimization
        $externalReferenceFields = self::getExternalReferenceStringFields($allFields);
        self::manageExternalReferenceStringFieldIndexes($externalReferenceFields, $actualConstraints, $connection);
        
        // Handle composite indexes for specific performance optimizations
        self::manageCompositeIndexes($actualConstraints, $connection);
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
        
        if (self::$dryRun) {
            self::$detectedChanges[] = [
                'type' => 'add_field',
                'table' => static::getTableName(),
                'field' => $field->getName(),
                'sql' => $sqlField,
            ];
        } else {
            $connection->query($sqlField);
        }
    }
    
    /**
     * @param mixed $field
     * @param array $actualConstraints
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function addConstraint(mixed $field, array $actualConstraints, Connection $connection): void
    {
        if ( array_key_exists($field->getName(), $actualConstraints) ) {
            return;
        }
        
        if ( !$field->isUnique() ) {
            return;
        }
        
        $sqlField = 'ALTER TABLE `' . static::getTableName() . '` ADD UNIQUE KEY `' . $field->getName() . '` (`' . $field->getName() . '`) ';
        
        self::$logger?->info($sqlField);
        
        if (self::$dryRun) {
            self::$detectedChanges[] = [
                'type' => 'add_constraint',
                'table' => static::getTableName(),
                'field' => $field->getName(),
                'sql' => $sqlField,
            ];
        } else {
            $connection->query($sqlField);
        }
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
        
        if ( !array_key_exists($field->getName(), $actualFields) ) {
            return;
        }
        
        if ( !isset($actualFields[$field->getName()]['Type']) ) {
            return;
        }
        
        $actualType = $actualFields[$field->getName()]['Type'];
        $shouldModify = false;
        
        if ( $field instanceof TextField ) {
            if ( 'text' !== $actualType && 'longtext' !== $actualType ) {
                $shouldModify = true;
            }
        } elseif ( $field instanceof StringField ) {
            if ( !str_starts_with($actualType, 'varchar') && !str_starts_with($actualType, 'char') ) {
                $shouldModify = true;
            }
        } elseif ( $field instanceof IntegerField ) {
            if ( !str_starts_with($actualType, 'int') && !str_starts_with($actualType, 'bigint') && !str_starts_with($actualType, 'smallint') && !str_starts_with($actualType, 'tinyint') ) {
                $shouldModify = true;
            }
        } elseif ( $field instanceof DateTimeField ) {
            if ( 'datetime' !== $actualType && 'timestamp' !== $actualType ) {
                $shouldModify = true;
            }
        } elseif ( $field instanceof BooleanField ) {
            if ( 'varchar(1)' !== $actualType && !str_starts_with($actualType, 'varchar(1)') ) {
                $shouldModify = true;
            }
        } elseif ( $field instanceof FloatField ) {
            if ( !str_starts_with($actualType, 'float') && !str_starts_with($actualType, 'double') && !str_starts_with($actualType, 'decimal') ) {
                $shouldModify = true;
            }
        }
        
        if ( $shouldModify ) {
            $sqlField = 'ALTER TABLE `' . static::getTableName() . '` MODIFY ' . self::getSqlField($field, $connection);
            
            self::$logger?->info($sqlField);
            
            if (self::$dryRun) {
                self::$detectedChanges[] = [
                    'type' => 'modify_field',
                    'table' => static::getTableName(),
                    'field' => $field->getName(),
                    'sql' => $sqlField,
                ];
            } else {
                $connection->query($sqlField);
            }
        }
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
        
        if (self::$dryRun) {
            self::$detectedChanges[] = [
                'type' => 'drop_field',
                'table' => static::getTableName(),
                'field' => $actualField['Field'],
                'sql' => $sqlField,
            ];
        } else {
            $connection->query($sqlField);
        }
    }
    
    /**
     * @param array $actualConstraint
     * @param mixed $scalarFields
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function deleteConstraint(array $actualConstraint, mixed $scalarFields, Connection $connection): void
    {
        if ( array_key_exists($actualConstraint['COLUMN_NAME'], $scalarFields) ) {
            return;
        }
        
        if ( !self::columnExists($connection, $actualConstraint['COLUMN_NAME']) ) {
            return;
        }
        
        $sqlField = 'ALTER TABLE `' . static::getTableName() . '` DROP KEY `' . $actualConstraint['INDEX_NAME'] . '`';
        
        self::$logger?->info($sqlField);
        
        if (self::$dryRun) {
            self::$detectedChanges[] = [
                'type' => 'drop_constraint',
                'table' => static::getTableName(),
                'constraint' => $actualConstraint['INDEX_NAME'],
                'sql' => $sqlField,
            ];
        } else {
            $connection->query($sqlField);
        }
    }
    
    /**
     * Checks and manages primary key constraints for fields
     * @param mixed $scalarFields
     * @param array $actualPrimaryKeys
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function managePrimaryKeys(mixed $scalarFields, array $actualPrimaryKeys, Connection $connection): void
    {
        $expectedPrimaryKeys = [];
        
        // Collect all expected primary key fields
        foreach ( $scalarFields as $field ) {
            if ( $field->isPrimary() ) {
                $expectedPrimaryKeys[] = $field->getName();
            }
        }
        
        // Check if primary key structure has changed
        sort($expectedPrimaryKeys);
        sort($actualPrimaryKeys);
        
        if ( $expectedPrimaryKeys !== $actualPrimaryKeys ) {
            // Clean up invalid data before adding primary key
            self::cleanupPrimaryKeyData($expectedPrimaryKeys, $connection);
            self::updatePrimaryKey($expectedPrimaryKeys, $connection);
        }
    }
    
    /**
     * Cleans up invalid data that would prevent primary key creation
     * @param array $primaryKeyFields
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function cleanupPrimaryKeyData(array $primaryKeyFields, Connection $connection): void
    {
        if ( empty($primaryKeyFields) ) {
            return;
        }
        
        $tableName = static::getTableName();
        
        // For single column primary keys, handle common issues
        if ( count($primaryKeyFields) === 1 ) {
            $pkField = $primaryKeyFields[0];
            
            // Step 1: Fix NULL values in primary key field
            $sqlFixNulls = "UPDATE `{$tableName}` SET `{$pkField}` = 0 WHERE `{$pkField}` IS NULL";
            self::$logger?->info("Fixing NULL values: {$sqlFixNulls}");
            $connection->query($sqlFixNulls);
            
            // Step 2: Handle duplicate values by updating them with unique IDs
            $sqlFindDuplicates = "
                SELECT `{$pkField}`, COUNT(*) as count
                FROM `{$tableName}`
                GROUP BY `{$pkField}`
                HAVING COUNT(*) > 1
                ORDER BY `{$pkField}`
            ";
            
            $duplicatesResult = $connection->query($sqlFindDuplicates);
            
            while ( $duplicate = $duplicatesResult->fetch() ) {
                $duplicateValue = $duplicate[$pkField];
                self::$logger?->info("Found duplicate {$pkField} value: {$duplicateValue}");
                
                // Get the maximum existing ID to start from
                $maxIdResult = $connection->query("SELECT MAX(`{$pkField}`) as max_id FROM `{$tableName}`");
                $maxId = $maxIdResult->fetch()['max_id'] ?? 0;
                
                // Update duplicate rows except the first one
                $sqlUpdateDuplicates = "
                    UPDATE `{$tableName}` t1
                    INNER JOIN (
                        SELECT `{$pkField}`, ROW_NUMBER() OVER (PARTITION BY `{$pkField}` ORDER BY `{$pkField}`) as rn
                        FROM `{$tableName}`
                        WHERE `{$pkField}` = {$duplicateValue}
                    ) t2 ON t1.`{$pkField}` = t2.`{$pkField}`
                    SET t1.`{$pkField}` = {$maxId} + t2.rn
                    WHERE t2.rn > 1
                ";
                
                self::$logger?->info("Fixing duplicates for {$pkField} = {$duplicateValue}");
                
                try {
                    $connection->query($sqlUpdateDuplicates);
                } catch ( SqlQueryException $e ) {
                    // Fallback to simpler approach if ROW_NUMBER is not available
                    self::$logger?->warning("ROW_NUMBER approach failed, using simpler method: " . $e->getMessage());
                    
                    // Simple approach: just increment from max ID
                    $newId = $maxId + 1;
                    $sqlSimpleUpdate = "
                        UPDATE `{$tableName}`
                        SET `{$pkField}` = {$newId}
                        WHERE `{$pkField}` = {$duplicateValue}
                        LIMIT 1, 999999
                    ";
                    $connection->query($sqlSimpleUpdate);
                }
            }
        }
    }
    
    /**
     * Updates the primary key constraint for the table
     * @param array $primaryKeyFields
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function updatePrimaryKey(array $primaryKeyFields, Connection $connection): void
    {
        // Drop existing primary key if it exists
        try {
            $sqlDropPrimary = 'ALTER TABLE `' . static::getTableName() . '` DROP PRIMARY KEY';
            self::$logger?->info($sqlDropPrimary);
            $connection->query($sqlDropPrimary);
        } catch ( SqlQueryException $e ) {
            // Primary key might not exist, which is fine
            self::$logger?->debug('Could not drop primary key (might not exist): ' . $e->getMessage());
        }
        
        // Add new primary key if fields are specified
        if ( !empty($primaryKeyFields) ) {
            $keyFields = array_map(function($field) {
                return '`' . $field . '`';
            }, $primaryKeyFields);
            
            $sqlAddPrimary = 'ALTER TABLE `' . static::getTableName() . '` ADD PRIMARY KEY (' . implode(', ', $keyFields) . ')';
            self::$logger?->info($sqlAddPrimary);
            $connection->query($sqlAddPrimary);
        }
    }
    
    /**
     * Gets the current primary key fields from the database
     * @param Connection $connection
     * @return array
     * @throws SqlQueryException
     */
    public static function getActualPrimaryKeys(Connection $connection): array
    {
        $primaryKeys = [];
        
        $query = '
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            TABLE_SCHEMA = "' . addslashes($connection->getDatabase()) . '"
            AND TABLE_NAME = "' . addslashes(static::getTableName()) . '"
            AND CONSTRAINT_NAME = "PRIMARY"
        ORDER BY ORDINAL_POSITION;';
        
        $result = $connection->query($query);
        
        while ( $row = $result->fetch() ) {
            $primaryKeys[] = $row['COLUMN_NAME'];
        }
        
        return $primaryKeys;
    }
    
    /**
     * Manages AUTO_INCREMENT for fields that need it
     * @param mixed $scalarFields
     * @param array $actualFields
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function manageAutoIncrement(mixed $scalarFields, array $actualFields, Connection $connection): void
    {
        foreach ( $scalarFields as $field ) {
            if ( $field instanceof IntegerField && $field->isAutocomplete() ) {
                self::setAutoIncrement($field, $actualFields, $connection);
            }
        }
    }
    
    /**
     * Sets AUTO_INCREMENT on a specific field
     * @param IntegerField $field
     * @param array $actualFields
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function setAutoIncrement(IntegerField $field, array $actualFields, Connection $connection): void
    {
        if ( !array_key_exists($field->getName(), $actualFields) ) {
            return; // Field doesn't exist
        }
        
        // Check if field already has AUTO_INCREMENT
        $extra = $actualFields[$field->getName()]['Extra'] ?? '';
        if ( str_contains(strtolower($extra), 'auto_increment') ) {
            return; // Already has AUTO_INCREMENT
        }
        
        // Set AUTO_INCREMENT (this requires the field to be a key)
        $sqlField = 'ALTER TABLE `' . static::getTableName() . '` MODIFY ' . self::getSqlField($field, $connection) . ' AUTO_INCREMENT';
        
        self::$logger?->info($sqlField);
        
        try {
            $connection->query($sqlField);
        } catch ( SqlQueryException $e ) {
            // Log the error but don't fail - AUTO_INCREMENT might not be possible
            self::$logger?->error('Could not set AUTO_INCREMENT on field ' . $field->getName() . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Checks if table field exists.
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
    
    /**
     * Detects foreign key fields that need indexes
     * @param array $allFields All fields from getMap()
     * @return array Array of field names that are foreign keys
     */
    public static function getForeignKeyFields(array $allFields): array
    {
        $foreignKeyFields = [];
        
        foreach ($allFields as $field) {
            // Skip if not an IntegerField or StringField (foreign keys can be both)
            if (!($field instanceof IntegerField) && !($field instanceof StringField)) {
                continue;
            }
            
            // Check if there's a corresponding Reference field
            $referenceFieldName = $field->getName() . '_ref';
            foreach ($allFields as $otherField) {
                if ($otherField->getName() === $referenceFieldName &&
                    $otherField instanceof Reference) {
                    $foreignKeyFields[] = $field->getName();
                    break;
                }
            }
        }
        
        return $foreignKeyFields;
    }
    
    /**
     * Detects boolean fields that need indexes for performance optimization
     * @param array $allFields All fields from getMap()
     * @return array Array of field names that are boolean fields
     */
    public static function getBooleanFields(array $allFields): array
    {
        $booleanFields = [];
        
        foreach ($allFields as $field) {
            if ($field instanceof BooleanField) {
                $booleanFields[] = $field->getName();
            }
        }
        
        return $booleanFields;
    }
    
    /**
     * Detects external reference string fields that need indexes for performance optimization
     * @param array $allFields All fields from getMap()
     * @return array Array of field names that are external reference string fields
     */
    public static function getExternalReferenceStringFields(array $allFields): array
    {
        $externalReferenceFields = [];
        
        // Define patterns for external reference fields
        $externalReferencePatterns = ['source_id', 'ext_id'];
        
        foreach ($allFields as $field) {
            if ($field instanceof StringField) {
                $fieldName = $field->getName();
                if (in_array($fieldName, $externalReferencePatterns, true)) {
                    $externalReferenceFields[] = $fieldName;
                }
            }
        }
        
        return $externalReferenceFields;
    }
    
    /**
     * Creates an index for a foreign key field
     * @param string $fieldName
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function addForeignKeyIndex(string $fieldName, Connection $connection): void
    {
        $indexName = 'IDX_' . $fieldName;
        $tableName = static::getTableName();
        
        $sql = "ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` (`{$fieldName}`)";
        
        self::$logger?->info("Adding foreign key index: {$sql}");
        
        try {
            $connection->query($sql);
            self::$logger?->info("Successfully created index {$indexName} on {$tableName}.{$fieldName}");
        } catch (SqlQueryException $e) {
            self::$logger?->error("Failed to create index {$indexName}: " . $e->getMessage());
            // Don't throw exception to prevent breaking table updates for non-critical indexes
        }
    }
    
    /**
     * Creates an index for a boolean field
     * @param string $fieldName
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function addBooleanFieldIndex(string $fieldName, Connection $connection): void
    {
        $indexName = 'IDX_BOOL_' . $fieldName;
        $tableName = static::getTableName();
        
        $sql = "ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` (`{$fieldName}`)";
        
        self::$logger?->info("Adding boolean field index: {$sql}");
        
        try {
            $connection->query($sql);
            self::$logger?->info("Successfully created boolean index {$indexName} on {$tableName}.{$fieldName}");
        } catch (SqlQueryException $e) {
            self::$logger?->error("Failed to create boolean index {$indexName}: " . $e->getMessage());
            // Don't throw exception to prevent breaking table updates for non-critical indexes
        }
    }
    
    /**
     * Creates an index for an external reference string field
     * @param string $fieldName
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function addExternalReferenceStringFieldIndex(string $fieldName, Connection $connection): void
    {
        $indexName = 'IDX_EXT_' . $fieldName;
        $tableName = static::getTableName();
        
        $sql = "ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` (`{$fieldName}`)";
        
        self::$logger?->info("Adding external reference string field index: {$sql}");
        
        try {
            $connection->query($sql);
            self::$logger?->info("Successfully created external reference index {$indexName} on {$tableName}.{$fieldName}");
        } catch (SqlQueryException $e) {
            self::$logger?->error("Failed to create external reference index {$indexName}: " . $e->getMessage());
            // Don't throw exception to prevent breaking table updates for non-critical indexes
        }
    }
    
    /**
     * Manages indexes for foreign key fields
     * @param array $foreignKeyFields
     * @param array $actualIndexes
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function manageForeignKeyIndexes(array $foreignKeyFields, array $actualIndexes, Connection $connection): void
    {
        foreach ($foreignKeyFields as $fieldName) {
            $indexName = 'IDX_' . $fieldName;
            
            // Check if index already exists
            $indexExists = false;
            foreach ($actualIndexes as $actualIndex) {
                if ($actualIndex['INDEX_NAME'] === $indexName &&
                    $actualIndex['COLUMN_NAME'] === $fieldName) {
                    $indexExists = true;
                    break;
                }
            }
            
            // Create index if it doesn't exist
            if (!$indexExists && self::columnExists($connection, $fieldName)) {
                self::addForeignKeyIndex($fieldName, $connection);
            }
        }
    }
    
    /**
     * Manages indexes for boolean fields
     * @param array $booleanFields
     * @param array $actualIndexes
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function manageBooleanFieldIndexes(array $booleanFields, array $actualIndexes, Connection $connection): void
    {
        foreach ($booleanFields as $fieldName) {
            $indexName = 'IDX_BOOL_' . $fieldName;
            
            // Check if index already exists
            $indexExists = false;
            foreach ($actualIndexes as $actualIndex) {
                if ($actualIndex['INDEX_NAME'] === $indexName &&
                    $actualIndex['COLUMN_NAME'] === $fieldName) {
                    $indexExists = true;
                    break;
                }
            }
            
            // Create index if it doesn't exist
            if (!$indexExists && self::columnExists($connection, $fieldName)) {
                self::addBooleanFieldIndex($fieldName, $connection);
            }
        }
    }
    
    /**
     * Manages indexes for external reference string fields
     * @param array $externalReferenceFields
     * @param array $actualIndexes
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function manageExternalReferenceStringFieldIndexes(array $externalReferenceFields, array $actualIndexes, Connection $connection): void
    {
        foreach ($externalReferenceFields as $fieldName) {
            $indexName = 'IDX_EXT_' . $fieldName;
            
            // Check if index already exists
            $indexExists = false;
            foreach ($actualIndexes as $actualIndex) {
                if ($actualIndex['INDEX_NAME'] === $indexName &&
                    $actualIndex['COLUMN_NAME'] === $fieldName) {
                    $indexExists = true;
                    break;
                }
            }
            
            // Create index if it doesn't exist
            if (!$indexExists && self::columnExists($connection, $fieldName)) {
                self::addExternalReferenceStringFieldIndex($fieldName, $connection);
            }
        }
    }
    
    /**
     * Gets composite indexes that should be created for performance optimization
     * Override this method in specific table classes to define table-specific composite indexes
     * @return array Array of composite index definitions
     */
    public static function getCompositeIndexes(): array
    {
        return [];
    }
    
    /**
     * Creates a composite index
     * @param string $indexName
     * @param array $columns
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function addCompositeIndex(string $indexName, array $columns, Connection $connection): void
    {
        $tableName = static::getTableName();
        $columnList = '`' . implode('`, `', $columns) . '`';
        
        $sql = "ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` ({$columnList})";
        
        self::$logger?->info("Adding composite index: {$sql}");
        
        try {
            $connection->query($sql);
            self::$logger?->info("Successfully created composite index {$indexName} on {$tableName}");
        } catch (SqlQueryException $e) {
            self::$logger?->error("Failed to create composite index {$indexName}: " . $e->getMessage());
            // Don't throw exception to prevent breaking table updates for non-critical indexes
        }
    }
    
    /**
     * Manages composite indexes for performance optimization
     * @param array $actualIndexes
     * @param Connection $connection
     * @return void
     * @throws SqlQueryException
     */
    public static function manageCompositeIndexes(array $actualIndexes, Connection $connection): void
    {
        $compositeIndexes = static::getCompositeIndexes();
        
        foreach ($compositeIndexes as $indexDefinition) {
            $indexName = $indexDefinition['name'];
            $columns = $indexDefinition['columns'];
            
            // Check if index already exists by name
            $indexExists = false;
            foreach ($actualIndexes as $actualIndex) {
                if ($actualIndex['INDEX_NAME'] === $indexName) {
                    $indexExists = true;
                    break;
                }
            }
            
            // Verify all columns exist before creating index
            $allColumnsExist = true;
            foreach ($columns as $column) {
                if (!self::columnExists($connection, $column)) {
                    $allColumnsExist = false;
                    break;
                }
            }
            
            // Create index if it doesn't exist and all columns exist
            if (!$indexExists && $allColumnsExist) {
                self::addCompositeIndex($indexName, $columns, $connection);
            }
        }
    }
}
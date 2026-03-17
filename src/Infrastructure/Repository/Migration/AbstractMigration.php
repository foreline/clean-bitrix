<?php
declare(strict_types=1);

namespace Infrastructure\Repository\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\SqlQueryException;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Base class for all migrations providing common functionality
 */
abstract class AbstractMigration implements MigrationInterface
{
    protected ?LoggerInterface $logger = null;
    
    /**
     * List of EntityTable classes to check in this migration
     * @var array<string>
     */
    protected array $entityTables = [];
    
    /**
     * Custom SQL statements to execute AFTER entity table checks
     * @var array<string>
     */
    protected array $customUpSql = [];
    
    /**
     * Custom SQL statements to execute for rollback
     * @var array<string>
     */
    protected array $customDownSql = [];
    
    abstract public function getVersion(): string;
    abstract public function getDescription(): string;
    
    /**
     * Apply migration using EntityTable checkTable method
     * @throws Exception
     */
    public function up(Connection $connection, ?LoggerInterface $logger = null): void
    {
        $this->logger = $logger;
        
        // Pre-migration actions (table renames, data prep, etc.)
        $this->beforeUp($connection);
        
        // Step 1: Apply entity table migrations
        foreach ($this->entityTables as $entityTableClass) {
            $this->logger?->info("Checking table for: {$entityTableClass}");
            
            if (!class_exists($entityTableClass)) {
                throw new Exception("Entity table class not found: {$entityTableClass}");
            }
            
            // Verify it's an ORM table
            if (!method_exists($entityTableClass, 'checkTable')) {
                throw new Exception("Class {$entityTableClass} does not have checkTable method");
            }
            
            $entityTableClass::checkTable($logger);
        }
        
        // Step 2: Execute custom SQL
        foreach ($this->customUpSql as $sql) {
            $this->logger?->info("Executing custom SQL: {$sql}");
            
            try {
                $connection->query($sql);
            } catch (SqlQueryException $e) {
                $this->logger?->error("SQL execution failed: " . $e->getMessage());
                throw $e;
            }
        }
    }
    
    /**
     * Rollback migration
     *
     * @param Connection $connection
     * @param LoggerInterface|null $logger
     * @return void
     * @throws SqlQueryException
     */
    public function down(Connection $connection, ?LoggerInterface $logger = null): void
    {
        $this->logger = $logger;
        
        // Pre-rollback actions
        $this->beforeDown($connection);
        
        // Execute rollback SQL in reverse order
        $downSql = array_reverse($this->customDownSql);
        
        foreach ($downSql as $sql) {
            $this->logger?->info("Executing rollback SQL: {$sql}");
            
            try {
                $connection->query($sql);
            } catch (SqlQueryException $e) {
                $this->logger?->error("Rollback SQL execution failed: " . $e->getMessage());
                throw $e;
            }
        }
        
        $this->logger?->warning(
            "Entity tables are not automatically dropped during rollback. " .
            "Manual intervention may be required."
        );
    }
    
    /**
     * Default validation - override for specific checks
     *
     * @param Connection $connection
     * @return void
     * @throws Exception
     */
    public function validate(Connection $connection): void
    {
        // Verify all entity table classes exist
        foreach ($this->entityTables as $entityTableClass) {
            if (!class_exists($entityTableClass)) {
                throw new Exception("Entity table class not found: {$entityTableClass}");
            }
        }
    }
    
    /**
     * Hook: actions to perform BEFORE entity table checks in up().
     *
     * Override in concrete migrations to rename tables, transform data,
     * or perform any preparatory work before EntityTable::checkTable() runs.
     *
     * This method runs inside the MigrationManager transaction.
     *
     * @param ?Connection $connection
     * @return void
     */
    protected function beforeUp(?Connection $connection = null): void
    {
        // No-op by default. Override in subclasses.
    }
    
    /**
     * Hook: actions to perform BEFORE rollback SQL in down().
     *
     * Override in concrete migrations to reverse any preparatory work
     * done in beforeUp().
     *
     * This method runs inside the MigrationManager transaction.
     *
     * @param ?Connection $connection
     * @return void
     */
    protected function beforeDown(?Connection $connection = null): void
    {
        // No-op by default. Override in subclasses.
    }
    
    /**
     * Helper: Rename a database table safely.
     *
     * Handles four scenarios:
     *   1. Old table exists, new doesn't   → RENAME TABLE (normal case)
     *   2. Old doesn't exist, new exists   → skip (already renamed)
     *   3. Both exist                      → throw exception (conflict)
     *   4. Neither exists                  → skip (nothing to do)
     *
     * @param string $oldTableName
     * @param string $newTableName
     * @param Connection|null $connection
     * @return void
     *
     * @throws Exception When both old and new tables exist (conflict)
     * @throws SqlQueryException On SQL execution failure
     */
    protected function renameTable(
        string $oldTableName,
        string $newTableName,
        ?Connection $connection = null
    ): void {
        $connection = $connection ?? Application::getConnection();
        
        $oldExists = $connection->isTableExists($oldTableName);
        $newExists = $connection->isTableExists($newTableName);
        
        if ($oldExists && $newExists) {
            throw new Exception(
                "Cannot rename table `{$oldTableName}` to `{$newTableName}`: " .
                "both tables already exist. Manual resolution required."
            );
        }
        
        if (!$oldExists && $newExists) {
            $this->logger?->info(
                "Table `{$newTableName}` already exists, skipping rename from `{$oldTableName}`"
            );
            return;
        }
        
        if (!$oldExists && !$newExists) {
            $this->logger?->info(
                "Neither `{$oldTableName}` nor `{$newTableName}` exist, skipping rename"
            );
            return;
        }
        
        // oldExists && !newExists → perform rename
        $sql = "RENAME TABLE `{$oldTableName}` TO `{$newTableName}`";
        $this->logger?->info($sql);
        $connection->query($sql);
    }
    
    /**
     * Helper: Check if table exists
     */
    protected function tableExists(Connection $connection, string $tableName): bool
    {
        return $connection->isTableExists($tableName);
    }
    
    /**
     * Helper: Check if column exists
     *
     * @param Connection $connection
     * @param string $tableName
     * @param string $columnName
     * @return bool
     * @throws SqlQueryException
     */
    protected function columnExists(Connection $connection, string $tableName, string $columnName): bool
    {
        $sql = "
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = '" . addslashes($connection->getDatabase()) . "'
                AND TABLE_NAME = '" . addslashes($tableName) . "'
                AND COLUMN_NAME = '" . addslashes($columnName) . "'
        ";
        
        $result = $connection->query($sql);
        return (bool) $result->fetch();
    }
    
    /**
     * Helper: Check if index exists
     *
     * @param Connection $connection
     * @param string $tableName
     * @param string $indexName
     * @return bool
     * @throws SqlQueryException
     */
    protected function indexExists(Connection $connection, string $tableName, string $indexName): bool
    {
        $sql = "
            SELECT INDEX_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = '" . addslashes($connection->getDatabase()) . "'
                AND TABLE_NAME = '" . addslashes($tableName) . "'
                AND INDEX_NAME = '" . addslashes($indexName) . "'
        ";
        
        $result = $connection->query($sql);
        return (bool) $result->fetch();
    }
}
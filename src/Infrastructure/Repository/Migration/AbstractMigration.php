<?php
declare(strict_types=1);

namespace Infrastructure\Repository\Migration;

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
     */
    public function down(Connection $connection, ?LoggerInterface $logger = null): void
    {
        $this->logger = $logger;
        
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
     * Helper: Check if table exists
     */
    protected function tableExists(Connection $connection, string $tableName): bool
    {
        return $connection->isTableExists($tableName);
    }
    
    /**
     * Helper: Check if column exists
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
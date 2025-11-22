<?php
declare(strict_types=1);

namespace Infrastructure\Repository\Migration;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Bitrix\Main;

/**
 * Core migration engine
 */
class MigrationManager
{
    private Connection $connection;
    private ?LoggerInterface $logger;
    private string $migrationsPath;
    private string $namespace;
    
    /**
     * @throws SystemException
     * @throws ArgumentException
     */
    public function __construct(
        ?Connection $connection = null,
        ?LoggerInterface $logger = null,
        ?string $migrationsPath = null,
        string $namespace = 'Migrations'
    ) {
        $this->connection = $connection ?? Application::getConnection();
        $this->logger = $logger;
        $this->migrationsPath = $migrationsPath ?? __DIR__ . '/../../migrations';
        $this->namespace = $namespace;
        
        $this->ensureMigrationTableExists();
    }
    
    /**
     * Execute all pending migrations
     * @throws Exception
     */
    public function migrate(?int $limit = null): array
    {
        $this->logger?->info("Starting migration process");
        
        $pending = $this->getPendingMigrations();
        
        if (empty($pending)) {
            $this->logger?->info("No pending migrations found");
            return [];
        }
        
        $executed = [];
        $count = 0;
        
        foreach ($pending as $migration) {
            if ($limit !== null && $count >= $limit) {
                break;
            }
            
            try {
                $this->executeMigration($migration);
                $executed[] = $migration->getVersion();
                $count++;
            } catch (Exception $e) {
                $this->logger?->error(
                    "Migration {$migration->getVersion()} failed: " . $e->getMessage()
                );
                throw $e;
            }
        }
        
        $this->logger?->info("Migration process completed. Executed: " . count($executed));
        
        return $executed;
    }
    
    /**
     * Rollback last migration(s)
     * @throws Exception
     */
    public function rollback(int $steps = 1): array
    {
        $this->logger?->info("Starting rollback process (steps: {$steps})");
        
        $applied = $this->getAppliedMigrations();
        
        if (empty($applied)) {
            $this->logger?->info("No migrations to rollback");
            return [];
        }
        
        // Sort descending to rollback in reverse order
        $applied = array_reverse($applied);
        $rolledBack = [];
        
        for ($i = 0; $i < $steps && $i < count($applied); $i++) {
            $version = $applied[$i];
            
            try {
                $migration = $this->loadMigration($version);
                $this->rollbackMigration($migration);
                $rolledBack[] = $version;
            } catch (Exception $e) {
                $this->logger?->error(
                    "Rollback of {$version} failed: " . $e->getMessage()
                );
                throw $e;
            }
        }
        
        $this->logger?->info("Rollback completed. Rolled back: " . count($rolledBack));
        
        return $rolledBack;
    }
    
    /**
     * Get status of all migrations
     *
     * @throws Main\ArgumentException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    public function status(): array
    {
        $all = $this->getAllMigrations();
        $applied = $this->getAppliedVersions();
        
        $status = [];
        
        foreach ($all as $migration) {
            $version = $migration->getVersion();
            $status[] = [
                'version' => $version,
                'description' => $migration->getDescription(),
                'applied' => in_array($version, $applied, true),
                'applied_at' => $this->getAppliedAt($version),
            ];
        }
        
        return $status;
    }
    
    /**
     * Execute a single migration
     * @throws SqlQueryException
     */
    private function executeMigration(MigrationInterface $migration): void
    {
        $version = $migration->getVersion();
        $this->logger?->info("Executing migration: {$version} - {$migration->getDescription()}");
        
        $startTime = microtime(true);
        
        // Begin transaction
        $this->connection->startTransaction();
        
        try {
            // Validate preconditions
            $migration->validate($this->connection);
            
            // Execute migration
            $migration->up($this->connection, $this->logger);
            
            // Record execution
            $executionTime = (int) ((microtime(true) - $startTime) * 1000);
            
            MigrationVersionTable::add([
                'VERSION' => $version,
                'EXECUTED_AT' => new DateTime(),
                'EXECUTION_TIME' => $executionTime,
                'MIGRATION_CLASS' => get_class($migration),
                'DESCRIPTION' => $migration->getDescription(),
                'CHECKSUM' => $this->getFileChecksum($version),
            ]);
            
            $this->connection->commitTransaction();
            
            $this->logger?->info(
                "Migration {$version} executed successfully in {$executionTime}ms"
            );
            
        } catch (Exception $e) {
            $this->connection->rollbackTransaction();
            throw $e;
        }
    }
    
    /**
     * Rollback a single migration
     * @throws SqlQueryException
     */
    private function rollbackMigration(MigrationInterface $migration): void
    {
        $version = $migration->getVersion();
        $this->logger?->info("Rolling back migration: {$version}");
        
        $this->connection->startTransaction();
        
        try {
            // Execute rollback
            $migration->down($this->connection, $this->logger);
            
            // Remove version record
            MigrationVersionTable::delete($version);
            
            $this->connection->commitTransaction();
            
            $this->logger?->info("Migration {$version} rolled back successfully");
            
        } catch (Exception $e) {
            $this->connection->rollbackTransaction();
            throw $e;
        }
    }
    
    /**
     * Get all migrations (from filesystem)
     */
    private function getAllMigrations(): array
    {
        $files = glob($this->migrationsPath . '/Version*.php');
        $migrations = [];
        
        foreach ($files as $file) {
            $className = basename($file, '.php');
            $version = str_replace('Version', '', $className);
            
            try {
                $migration = $this->loadMigration($version);
                $migrations[$version] = $migration;
            } catch (Exception $e) {
                $this->logger?->warning("Could not load migration {$version}: " . $e->getMessage());
            }
        }
        
        ksort($migrations);
        return $migrations;
    }
    
    /**
     * Get migrations that haven't been applied yet
     *
     * @throws Main\ArgumentException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    private function getPendingMigrations(): array
    {
        $all = $this->getAllMigrations();
        $applied = $this->getAppliedVersions();
        
        return array_filter($all, function($migration) use ($applied) {
            return !in_array($migration->getVersion(), $applied, true);
        });
    }
    
    /**
     * Get list of applied migration versions
     *
     * @throws Main\ArgumentException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    private function getAppliedVersions(): array
    {
        $result = MigrationVersionTable::getList([
            'select' => ['VERSION'],
            'order' => ['VERSION' => 'ASC'],
        ]);
        
        $versions = [];
        while ($row = $result->fetch()) {
            $versions[] = $row['VERSION'];
        }
        
        return $versions;
    }
    
    /**
     * Get list of applied migrations (full info)
     *
     * @throws Main\ArgumentException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    private function getAppliedMigrations(): array
    {
        return $this->getAppliedVersions();
    }
    
    /**
     * Get when a migration was applied
     *
     * @throws Main\ArgumentException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    private function getAppliedAt(string $version): ?DateTime
    {
        $result = MigrationVersionTable::getById($version);
        
        if ($row = $result->fetch()) {
            return $row['EXECUTED_AT'];
        }
        
        return null;
    }
    
    /**
     * Load migration instance
     * @throws Exception
     */
    private function loadMigration(string $version): MigrationInterface
    {
        $className = $this->namespace . '\\Version' . $version;
        $file = $this->migrationsPath . '/Version' . $version . '.php';
        
        if (!file_exists($file)) {
            throw new Exception("Migration file not found: {$file}");
        }
        
        require_once $file;
        
        if (!class_exists($className)) {
            throw new Exception("Migration class not found: {$className}");
        }
        
        $migration = new $className();
        
        if (!$migration instanceof MigrationInterface) {
            throw new Exception("Migration must implement MigrationInterface: {$className}");
        }
        
        return $migration;
    }
    
    /**
     * Calculate file checksum for integrity verification
     */
    private function getFileChecksum(string $version): string
    {
        $file = $this->migrationsPath . '/Version' . $version . '.php';
        
        if (file_exists($file)) {
            return md5_file($file);
        }
        
        return '';
    }
    
    /**
     * Ensure migration tracking table exists
     *
     * @throws Main\ArgumentException
     * @throws Main\SystemException
     */
    private function ensureMigrationTableExists(): void
    {
        if (!$this->connection->isTableExists(MigrationVersionTable::getTableName())) {
            $this->logger?->info("Creating migration version tracking table");
            MigrationVersionTable::getEntity()->createDbTable();
        }
    }
}
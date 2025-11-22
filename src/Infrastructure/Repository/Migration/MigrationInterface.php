<?php
declare(strict_types=1);

namespace Infrastructure\Repository\Migration;

use Bitrix\Main\DB\Connection;
use Psr\Log\LoggerInterface;

/**
 * Interface that all migrations must implement
 */
interface MigrationInterface
{
    /**
     * Apply migration (forward)
     */
    public function up(Connection $connection, ?LoggerInterface $logger = null): void;
    
    /**
     * Revert migration (backward)
     */
    public function down(Connection $connection, ?LoggerInterface $logger = null): void;
    
    /**
     * Get migration version identifier
     */
    public function getVersion(): string;
    
    /**
     * Get human-readable description
     */
    public function getDescription(): string;
    
    /**
     * Validate that migration can be applied safely
     * Throws exception if preconditions are not met
     */
    public function validate(Connection $connection): void;
}
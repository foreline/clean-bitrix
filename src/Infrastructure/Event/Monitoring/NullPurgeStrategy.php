<?php
declare(strict_types=1);

namespace Infrastructure\Event\Monitoring;

use Domain\Event\Monitoring\EventPurgeStrategyInterface;

/**
 * No-op strategy — events are never automatically purged.
 *
 * Equivalent to pre-auto-purge behavior; completed events remain
 * in the database until manually purged via CLI:
 *
 *   php event-monitor.php purge --before=2026-01-01
 */
final class NullPurgeStrategy implements EventPurgeStrategyInterface
{
    public function onDispatchCompleted(int $eventStoreId, int $eventDispatchId): void
    {
        // Intentionally empty — no auto-purge
    }
}

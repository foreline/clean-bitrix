<?php
declare(strict_types=1);

namespace Infrastructure\Event\Monitoring;

use Domain\Event\Monitoring\EventPurgeStrategyInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * No-op on dispatch completion — relies on scheduled batch purge.
 *
 * Use this strategy when completed events must be retained for
 * audit, compliance, or debugging purposes. Pair with a scheduled
 * purge command (e.g., cron) that calls purgeCompleted() with
 * a date threshold.
 *
 * Example cron: daily purge of events completed > 7 days ago
 *   php event-monitor.php purge --before="7 days ago"
 */
final class RetentionPurgeStrategy implements EventPurgeStrategyInterface
{
    private LoggerInterface $logger;
    
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }
    
    public function onDispatchCompleted(int $eventStoreId, int $eventDispatchId): void
    {
        // No-op: events will be purged by scheduled batch command
        $this->logger->debug('Dispatch completed, retention strategy active — skipping auto-purge', [
            'event_store_id' => $eventStoreId,
            'event_dispatch_id' => $eventDispatchId,
        ]);
    }
}

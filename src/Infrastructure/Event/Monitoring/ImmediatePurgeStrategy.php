<?php
declare(strict_types=1);

namespace Infrastructure\Event\Monitoring;

use Bitrix\Main\Application;
use Domain\Event\Enum\EventStoreStatusEnum;
use Domain\Event\Monitoring\EventPurgeStrategyInterface;
use Infrastructure\Event\Repository\Bitrix\ORM\EventDispatchTable;
use Infrastructure\Event\Repository\Bitrix\ORM\EventStoreTable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Immediately deletes event data when all dispatches are completed.
 *
 * After a dispatch transitions to COMPLETED, checks if any non-completed
 * dispatches remain for the same event_store_id. If none remain,
 * deletes all dispatch rows and the event store row (the large serialized body).
 *
 * This is the recommended strategy for production environments
 * where completed events have no operational value after processing.
 *
 * Safety guarantees:
 * - Purge failure does NOT affect event processing (best-effort cleanup)
 * - Failed/pending dispatches prevent purge (only fully-completed events are purged)
 * - Concurrent workers completing last dispatches simultaneously cause at most
 *   a harmless redundant DELETE (no data corruption)
 */
final class ImmediatePurgeStrategy implements EventPurgeStrategyInterface
{
    private LoggerInterface $logger;
    
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }
    
    public function onDispatchCompleted(int $eventStoreId, int $eventDispatchId): void
    {
        try {
            // Check if any non-completed dispatches remain for this event
            $nonCompletedCount = EventDispatchTable::getCount([
                '=' . EventDispatchTable::EVENT_STORE_ID => $eventStoreId,
                '!' . EventDispatchTable::STATUS => EventStoreStatusEnum::COMPLETED->value,
            ]);
            
            if ( 0 < $nonCompletedCount ) {
                // Other dispatches still pending/processing/failed вЂ” do not purge
                return;
            }
            
            // Полный набор доставок уже опубликован атомарно. Один DELETE вместо
            // загрузки ID и отдельного ORM DELETE на каждого подписчика.
            $connection = Application::getConnection();
            $connection->startTransaction();
            try {
                $connection->queryExecute('DELETE FROM servicedesk_event_dispatch WHERE event_store_id = ' . $eventStoreId);
                $connection->queryExecute('DELETE FROM servicedesk_event_store WHERE id = ' . $eventStoreId);
                $connection->commitTransaction();
            } catch (Throwable $error) {
                $connection->rollbackTransaction();
                throw $error;
            }
        } catch (Throwable $e) {
            // Purge failure must NOT break event processing.
            // The event was already successfully handled вЂ” purge is best-effort.
            $this->logger->warning('Auto-purge failed (non-critical)', [
                'event_store_id' => $eventStoreId,
                'event_dispatch_id' => $eventDispatchId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

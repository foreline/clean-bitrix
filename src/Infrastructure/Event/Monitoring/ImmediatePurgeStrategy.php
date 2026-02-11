<?php
declare(strict_types=1);

namespace Infrastructure\Event\Monitoring;

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
                // Other dispatches still pending/processing/failed — do not purge
                return;
            }
            
            // All dispatches completed — purge all dispatch rows for this event
            $dispatchRows = EventDispatchTable::getList([
                'filter' => [
                    '=' . EventDispatchTable::EVENT_STORE_ID => $eventStoreId,
                ],
                'select' => [EventDispatchTable::ID],
            ]);
            
            $deletedDispatches = 0;
            
            while ( $row = $dispatchRows->fetch() ) {
                $id = (int)($row[strtoupper(EventDispatchTable::ID)]
                    ?? $row[EventDispatchTable::ID] ?? 0);
                EventDispatchTable::delete($id);
                $deletedDispatches++;
            }
            
            // Delete the event store row (the large serialized body)
            EventStoreTable::delete($eventStoreId);
            
            $this->logger->debug('Auto-purged completed event', [
                'event_store_id' => $eventStoreId,
                'dispatches_deleted' => $deletedDispatches,
            ]);
            
        } catch (Throwable $e) {
            // Purge failure must NOT break event processing.
            // The event was already successfully handled — purge is best-effort.
            $this->logger->warning('Auto-purge failed (non-critical)', [
                'event_store_id' => $eventStoreId,
                'event_dispatch_id' => $eventDispatchId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

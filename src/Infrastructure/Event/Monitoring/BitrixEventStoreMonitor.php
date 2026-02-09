<?php
declare(strict_types=1);

namespace Infrastructure\Event\Monitoring;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime as BitrixDateTime;
use DateTime;
use DateTimeInterface;
use Domain\Event\Enum\EventStoreStatusEnum;
use Domain\Event\Monitoring\EventStoreEntry;
use Domain\Event\Monitoring\EventStoreEntryCollection;
use Domain\Event\Monitoring\EventStoreHealth;
use Domain\Event\Monitoring\EventStoreMonitorInterface;
use Domain\Event\Monitoring\EventStoreStatusSummary;
use Domain\Event\Monitoring\EventStoreThroughput;
use Exception;
use Infrastructure\Event\Repository\Bitrix\ORM\EventDispatchTable;
use Infrastructure\Event\Repository\Bitrix\ORM\EventStoreTable;

/**
 * Bitrix ORM implementation of the Event Store Monitor.
 *
 * Queries the normalized two-table structure:
 *   - EventDispatchTable: dispatch rows with status, timing, errors
 *   - EventStoreTable: shared event bodies (referenced via FK)
 */
class BitrixEventStoreMonitor implements EventStoreMonitorInterface
{
    /** Alias for event class from joined EventStoreTable */
    private const ALIAS_EVENT_CLASS = 'EVENT_TYPE_NAME';
    
    /** Alias for occurred_on from joined EventStoreTable */
    private const ALIAS_OCCURRED_ON = 'EVENT_OCCURRED_ON';
    
    /**
     * @return EventStoreStatusSummary
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getStatusSummary(): EventStoreStatusSummary
    {
        $counts = [];
        
        foreach ( EventStoreStatusEnum::cases() as $status ) {
            $counts[$status->value] = EventDispatchTable::getCount([
                '=' . EventDispatchTable::STATUS => $status->value,
            ]);
        }
        
        return new EventStoreStatusSummary(
            pending:    $counts[EventStoreStatusEnum::PENDING->value] ?? 0,
            processing: $counts[EventStoreStatusEnum::PROCESSING->value] ?? 0,
            completed:  $counts[EventStoreStatusEnum::COMPLETED->value] ?? 0,
            failed:     $counts[EventStoreStatusEnum::FAILED->value] ?? 0
        );
    }
    
    /**
     * @param int $limit
     * @param int $offset
     * @return EventStoreEntryCollection
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getFailedEvents(int $limit = 50, int $offset = 0): EventStoreEntryCollection
    {
        return $this->queryEntries(
            filter: ['=' . EventDispatchTable::STATUS => EventStoreStatusEnum::FAILED->value],
            order:  [EventDispatchTable::ID => 'DESC'],
            limit:  $limit,
            offset: $offset
        );
    }
    
    /**
     * @param int $thresholdSeconds
     * @return EventStoreEntryCollection
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getStuckEvents(int $thresholdSeconds = 300): EventStoreEntryCollection
    {
        $threshold = BitrixDateTime::createFromTimestamp(time() - $thresholdSeconds);
        
        return $this->queryEntries(
            filter: [
                '=' . EventDispatchTable::STATUS                => EventStoreStatusEnum::PROCESSING->value,
                '<' . EventDispatchTable::PROCESSING_STARTED_AT => $threshold,
            ],
            order: [EventDispatchTable::ID => 'ASC']
        );
    }
    
    /**
     * @param int $limit
     * @return EventStoreEntryCollection
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getRecentEvents(int $limit = 50): EventStoreEntryCollection
    {
        return $this->queryEntries(
            filter: [],
            order:  [EventDispatchTable::ID => 'DESC'],
            limit:  $limit
        );
    }
    
    /**
     * @return EventStoreThroughput
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getThroughput(): EventStoreThroughput
    {
        $now = time();
        
        $completedLastMinute = EventDispatchTable::getCount([
            '=' . EventDispatchTable::STATUS       => EventStoreStatusEnum::COMPLETED->value,
            '>=' . EventDispatchTable::PROCESSED_AT => BitrixDateTime::createFromTimestamp($now - 60),
        ]);
        
        $completedLastHour = EventDispatchTable::getCount([
            '=' . EventDispatchTable::STATUS       => EventStoreStatusEnum::COMPLETED->value,
            '>=' . EventDispatchTable::PROCESSED_AT => BitrixDateTime::createFromTimestamp($now - 3600),
        ]);
        
        $completedLast24Hours = EventDispatchTable::getCount([
            '=' . EventDispatchTable::STATUS       => EventStoreStatusEnum::COMPLETED->value,
            '>=' . EventDispatchTable::PROCESSED_AT => BitrixDateTime::createFromTimestamp($now - 86400),
        ]);
        
        $failedLastHour = EventDispatchTable::getCount([
            '=' . EventDispatchTable::STATUS       => EventStoreStatusEnum::FAILED->value,
            '>=' . EventDispatchTable::PROCESSED_AT => BitrixDateTime::createFromTimestamp($now - 3600),
        ]);
        
        $avgTimes = $this->calculateAvgTimes();
        
        return new EventStoreThroughput(
            completedLastMinute:      $completedLastMinute,
            completedLastHour:        $completedLastHour,
            completedLast24Hours:     $completedLast24Hours,
            failedLastHour:           $failedLastHour,
            avgExecutionTimeSeconds:  $avgTimes['execution'],
            avgQueueWaitSeconds:      $avgTimes['queueWait'],
            avgEndToEndSeconds:       $avgTimes['endToEnd']
        );
    }
    
    /**
     * @param int $stuckThresholdSeconds
     * @return EventStoreHealth
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getHealth(int $stuckThresholdSeconds = 300): EventStoreHealth
    {
        $summary    = $this->getStatusSummary();
        $throughput = $this->getThroughput();
        $stuckCount = count($this->getStuckEvents($stuckThresholdSeconds));
        
        return new EventStoreHealth(
            summary:    $summary,
            throughput: $throughput,
            stuckCount: $stuckCount
        );
    }
    
    /**
     * Purge completed dispatch rows and orphaned event store entries.
     *
     * @param DateTimeInterface|null $before
     * @return int
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     * @throws Exception
     */
    public function purgeCompleted(?DateTimeInterface $before = null): int
    {
        $filter = [
            '=' . EventDispatchTable::STATUS => EventStoreStatusEnum::COMPLETED->value,
        ];
        
        if ( null !== $before ) {
            $filter['<' . EventDispatchTable::PROCESSED_AT] = BitrixDateTime::createFromTimestamp($before->getTimestamp());
        }
        
        // Collect event_store_ids before deleting dispatch rows
        $rs = EventDispatchTable::getList([
            'filter' => $filter,
            'select' => [EventDispatchTable::ID, EventDispatchTable::EVENT_STORE_ID],
        ]);
        
        $dispatchIds = [];
        $eventStoreIds = [];
        
        while ( $row = $rs->fetch() ) {
            $id = (int)($row[strtoupper(EventDispatchTable::ID)] ?? $row[EventDispatchTable::ID] ?? 0);
            $esId = (int)($row[strtoupper(EventDispatchTable::EVENT_STORE_ID)] ?? $row[EventDispatchTable::EVENT_STORE_ID] ?? 0);
            $dispatchIds[] = $id;
            $eventStoreIds[$esId] = true;
        }
        
        // Delete dispatch rows
        $count = 0;
        foreach ( $dispatchIds as $id ) {
            EventDispatchTable::delete($id);
            $count++;
        }
        
        // Purge orphaned event_store rows (no remaining dispatch references)
        foreach ( array_keys($eventStoreIds) as $esId ) {
            $remaining = EventDispatchTable::getCount([
                '=' . EventDispatchTable::EVENT_STORE_ID => $esId,
            ]);
            
            if ( 0 === $remaining ) {
                EventStoreTable::delete($esId);
            }
        }
        
        return $count;
    }
    
    /**
     * @param int|null $eventId Dispatch row ID to retry (null = all failed)
     * @return int
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public function retryFailed(?int $eventId = null): int
    {
        $filter = [
            '=' . EventDispatchTable::STATUS => EventStoreStatusEnum::FAILED->value,
        ];
        
        if ( null !== $eventId ) {
            $filter['=' . EventDispatchTable::ID] = $eventId;
        }
        
        $rs = EventDispatchTable::getList([
            'filter' => $filter,
            'select' => [EventDispatchTable::ID],
        ]);
        
        $count = 0;
        
        while ( $row = $rs->fetch() ) {
            $id = (int)($row[strtoupper(EventDispatchTable::ID)] ?? $row[EventDispatchTable::ID] ?? 0);
            EventDispatchTable::update($id, [
                EventDispatchTable::STATUS               => EventStoreStatusEnum::PENDING->value,
                EventDispatchTable::ATTEMPTS             => 0,
                EventDispatchTable::ERROR                => null,
                EventDispatchTable::PROCESSED_AT         => null,
                EventDispatchTable::PROCESSING_STARTED_AT => null,
            ]);
            $count++;
        }
        
        return $count;
    }
    
    // ─── Private helpers ─────────────────────────────────────────────────────
    
    /**
     * Query EventDispatchTable with JOINed EventStoreTable data
     * and convert results to EventStoreEntryCollection.
     *
     * @param array $filter
     * @param array $order
     * @param int $limit
     * @param int $offset
     * @return EventStoreEntryCollection
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    private function queryEntries(
        array $filter,
        array $order = [],
        int $limit = 100,
        int $offset = 0
    ): EventStoreEntryCollection {
        $params = [
            'filter' => $filter,
            'select' => [
                EventDispatchTable::ID,
                EventDispatchTable::EVENT_STORE_ID,
                EventDispatchTable::SUBSCRIBER_CLASS,
                EventDispatchTable::STATUS,
                EventDispatchTable::ATTEMPTS,
                EventDispatchTable::ERROR,
                EventDispatchTable::PROCESSING_STARTED_AT,
                EventDispatchTable::PROCESSED_AT,
                EventDispatchTable::DATE_CREATED,
                // JOINed fields from EventStoreTable via reference
                self::ALIAS_EVENT_CLASS => EventDispatchTable::REL_EVENT_STORE . '.' . EventStoreTable::TYPE_NAME,
                self::ALIAS_OCCURRED_ON => EventDispatchTable::REL_EVENT_STORE . '.' . EventStoreTable::OCCURRED_ON,
            ],
            'limit'  => $limit,
            'offset' => $offset,
        ];
        
        if ( !empty($order) ) {
            $params['order'] = $order;
        }
        
        $rs      = EventDispatchTable::getList($params);
        $entries = [];
        
        while ( $row = $rs->fetch() ) {
            $entries[] = $this->rowToEntry($row);
        }
        
        return new EventStoreEntryCollection(...$entries);
    }
    
    /**
     * Convert a dispatch + event store row to an EventStoreEntry value object.
     *
     * @param array $row
     * @return EventStoreEntry
     * @throws Exception
     */
    private function rowToEntry(array $row): EventStoreEntry
    {
        return new EventStoreEntry(
            id:              (int)($row[strtoupper(EventDispatchTable::ID)] ?? $row[EventDispatchTable::ID] ?? 0),
            eventClass:      (string)($row[self::ALIAS_EVENT_CLASS] ?? ''),
            subscriberClass: (string)($row[strtoupper(EventDispatchTable::SUBSCRIBER_CLASS)] ?? $row[EventDispatchTable::SUBSCRIBER_CLASS] ?? ''),
            status:          EventStoreStatusEnum::from(
                (string)($row[strtoupper(EventDispatchTable::STATUS)] ?? $row[EventDispatchTable::STATUS] ?? 'pending')
            ),
            attempts:        (int)($row[strtoupper(EventDispatchTable::ATTEMPTS)] ?? $row[EventDispatchTable::ATTEMPTS] ?? 0),
            error:           $row[strtoupper(EventDispatchTable::ERROR)] ?? $row[EventDispatchTable::ERROR] ?? null,
            occurredOn:      $this->toDateTime(
                $row[self::ALIAS_OCCURRED_ON] ?? null
            ),
            processedAt:     $this->toNullableDateTime(
                $row[strtoupper(EventDispatchTable::PROCESSED_AT)] ?? $row[EventDispatchTable::PROCESSED_AT] ?? null
            ),
            processingStartedAt: $this->toNullableDateTime(
                $row[strtoupper(EventDispatchTable::PROCESSING_STARTED_AT)] ?? $row[EventDispatchTable::PROCESSING_STARTED_AT] ?? null
            ),
            dateCreated:     $this->toNullableDateTime(
                $row[strtoupper(EventDispatchTable::DATE_CREATED)] ?? $row[EventDispatchTable::DATE_CREATED] ?? null
            )
        );
    }
    
    /**
     * Calculate average execution time, queue wait time, and end-to-end time
     * from the last 100 completed dispatch rows.
     *
     * - Execution time: processing_started_at → processed_at (actual subscriber work)
     * - Queue wait: date_created → processing_started_at (time waiting in queue)
     * - End-to-end: date_created → processed_at (total latency)
     *
     * @return array{execution: ?float, queueWait: ?float, endToEnd: ?float}
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    private function calculateAvgTimes(): array
    {
        $rs = EventDispatchTable::getList([
            'filter' => [
                '=' . EventDispatchTable::STATUS => EventStoreStatusEnum::COMPLETED->value,
                '!' . EventDispatchTable::PROCESSED_AT => null,
                '!' . EventDispatchTable::DATE_CREATED => null,
            ],
            'select' => [
                EventDispatchTable::DATE_CREATED,
                EventDispatchTable::PROCESSING_STARTED_AT,
                EventDispatchTable::PROCESSED_AT,
            ],
            'order' => [EventDispatchTable::ID => 'DESC'],
            'limit' => 100,
        ]);
        
        $totalExecution = 0.0;
        $totalQueueWait = 0.0;
        $totalEndToEnd  = 0.0;
        $execCount      = 0;
        $waitCount      = 0;
        $e2eCount       = 0;
        
        while ( $row = $rs->fetch() ) {
            $created   = $this->toNullableDateTime(
                $row[strtoupper(EventDispatchTable::DATE_CREATED)] ?? $row[EventDispatchTable::DATE_CREATED] ?? null
            );
            $started   = $this->toNullableDateTime(
                $row[strtoupper(EventDispatchTable::PROCESSING_STARTED_AT)] ?? $row[EventDispatchTable::PROCESSING_STARTED_AT] ?? null
            );
            $processed = $this->toNullableDateTime(
                $row[strtoupper(EventDispatchTable::PROCESSED_AT)] ?? $row[EventDispatchTable::PROCESSED_AT] ?? null
            );
            
            // Execution time: started → processed
            if ( null !== $started && null !== $processed ) {
                $totalExecution += (float)($processed->getTimestamp() - $started->getTimestamp());
                $execCount++;
            }
            
            // Queue wait: created → started
            if ( null !== $created && null !== $started ) {
                $totalQueueWait += (float)($started->getTimestamp() - $created->getTimestamp());
                $waitCount++;
            }
            
            // End-to-end: created → processed
            if ( null !== $created && null !== $processed ) {
                $totalEndToEnd += (float)($processed->getTimestamp() - $created->getTimestamp());
                $e2eCount++;
            }
        }
        
        return [
            'execution' => 0 < $execCount ? round($totalExecution / $execCount, 2) : null,
            'queueWait' => 0 < $waitCount ? round($totalQueueWait / $waitCount, 2) : null,
            'endToEnd'  => 0 < $e2eCount  ? round($totalEndToEnd / $e2eCount, 2)   : null,
        ];
    }
    
    /**
     * Convert a Bitrix DateTime or string to PHP DateTime.
     *
     * @param mixed $value
     * @return DateTimeInterface
     * @throws Exception
     */
    private function toDateTime(mixed $value): DateTimeInterface
    {
        if ( $value instanceof DateTimeInterface ) {
            return $value;
        }
        
        if ( $value instanceof BitrixDateTime ) {
            return (new DateTime())->setTimestamp($value->getTimestamp());
        }
        
        if ( is_string($value) && '' !== $value ) {
            return new DateTime($value);
        }
        
        return new DateTime();
    }
    
    /**
     * Convert a Bitrix DateTime or string to nullable PHP DateTime.
     *
     * @param mixed $value
     * @return DateTimeInterface|null
     * @throws Exception
     */
    private function toNullableDateTime(mixed $value): ?DateTimeInterface
    {
        if ( null === $value || '' === $value ) {
            return null;
        }
        
        return $this->toDateTime($value);
    }
}

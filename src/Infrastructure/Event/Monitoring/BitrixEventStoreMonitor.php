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
use Infrastructure\Event\Repository\Bitrix\ORM\EventStoreTable;

/**
 * Bitrix ORM implementation of the Event Store Monitor.
 *
 * Queries the EventStoreTable to provide monitoring data
 * and operational actions (purge, retry).
 */
class BitrixEventStoreMonitor implements EventStoreMonitorInterface
{
    /**
     * @return EventStoreStatusSummary
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getStatusSummary(): EventStoreStatusSummary
    {
        $counts = [];
        
        foreach ( EventStoreStatusEnum::cases() as $status ) {
            $counts[$status->value] = EventStoreTable::getCount([
                '=' . EventStoreTable::STATUS => $status->value,
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
            filter: ['=' . EventStoreTable::STATUS => EventStoreStatusEnum::FAILED->value],
            order:  [EventStoreTable::ID => 'DESC'],
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
                '=' . EventStoreTable::STATUS       => EventStoreStatusEnum::PROCESSING->value,
                '<' . EventStoreTable::DATE_CREATED  => $threshold,
            ],
            order: [EventStoreTable::ID => 'ASC']
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
            order:  [EventStoreTable::ID => 'DESC'],
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
        
        $completedLastMinute = EventStoreTable::getCount([
            '=' . EventStoreTable::STATUS       => EventStoreStatusEnum::COMPLETED->value,
            '>=' . EventStoreTable::PROCESSED_AT => BitrixDateTime::createFromTimestamp($now - 60),
        ]);
        
        $completedLastHour = EventStoreTable::getCount([
            '=' . EventStoreTable::STATUS       => EventStoreStatusEnum::COMPLETED->value,
            '>=' . EventStoreTable::PROCESSED_AT => BitrixDateTime::createFromTimestamp($now - 3600),
        ]);
        
        $completedLast24Hours = EventStoreTable::getCount([
            '=' . EventStoreTable::STATUS       => EventStoreStatusEnum::COMPLETED->value,
            '>=' . EventStoreTable::PROCESSED_AT => BitrixDateTime::createFromTimestamp($now - 86400),
        ]);
        
        $failedLastHour = EventStoreTable::getCount([
            '=' . EventStoreTable::STATUS       => EventStoreStatusEnum::FAILED->value,
            '>=' . EventStoreTable::PROCESSED_AT => BitrixDateTime::createFromTimestamp($now - 3600),
        ]);
        
        $avgProcessingTime = $this->calculateAvgProcessingTime();
        
        return new EventStoreThroughput(
            completedLastMinute:      $completedLastMinute,
            completedLastHour:        $completedLastHour,
            completedLast24Hours:     $completedLast24Hours,
            failedLastHour:           $failedLastHour,
            avgProcessingTimeSeconds: $avgProcessingTime
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
            '=' . EventStoreTable::STATUS => EventStoreStatusEnum::COMPLETED->value,
        ];
        
        if ( null !== $before ) {
            $filter['<' . EventStoreTable::PROCESSED_AT] = BitrixDateTime::createFromTimestamp($before->getTimestamp());
        }
        
        $rs = EventStoreTable::getList([
            'filter' => $filter,
            'select' => [EventStoreTable::ID],
        ]);
        
        $count = 0;
        
        while ( $row = $rs->fetch() ) {
            EventStoreTable::delete($row[strtoupper(EventStoreTable::ID)]);
            $count++;
        }
        
        return $count;
    }
    
    /**
     * @param int|null $eventId
     * @return int
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public function retryFailed(?int $eventId = null): int
    {
        $filter = [
            '=' . EventStoreTable::STATUS => EventStoreStatusEnum::FAILED->value,
        ];
        
        if ( null !== $eventId ) {
            $filter['=' . EventStoreTable::ID] = $eventId;
        }
        
        $rs = EventStoreTable::getList([
            'filter' => $filter,
            'select' => [EventStoreTable::ID],
        ]);
        
        $count = 0;
        
        while ( $row = $rs->fetch() ) {
            EventStoreTable::update($row[strtoupper(EventStoreTable::ID)], [
                EventStoreTable::STATUS       => EventStoreStatusEnum::PENDING->value,
                EventStoreTable::ATTEMPTS     => 0,
                EventStoreTable::ERROR        => null,
                EventStoreTable::PROCESSED_AT => null,
            ]);
            $count++;
        }
        
        return $count;
    }
    
    // ─── Private helpers ─────────────────────────────────────────────────────
    
    /**
     * Query EventStoreTable and convert results to EventStoreEntryCollection.
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
                EventStoreTable::ID,
                EventStoreTable::TYPE_NAME,
                EventStoreTable::SUBSCRIBER_CLASS,
                EventStoreTable::STATUS,
                EventStoreTable::ATTEMPTS,
                EventStoreTable::ERROR,
                EventStoreTable::OCCURRED_ON,
                EventStoreTable::PROCESSED_AT,
                EventStoreTable::DATE_CREATED,
            ],
            'limit'  => $limit,
            'offset' => $offset,
        ];
        
        if ( !empty($order) ) {
            $params['order'] = $order;
        }
        
        $rs      = EventStoreTable::getList($params);
        $entries = [];
        
        while ( $row = $rs->fetch() ) {
            $entries[] = $this->rowToEntry($row);
        }
        
        return new EventStoreEntryCollection(...$entries);
    }
    
    /**
     * Convert a Bitrix ORM row to an EventStoreEntry value object.
     *
     * @param array $row
     * @return EventStoreEntry
     * @throws Exception
     */
    private function rowToEntry(array $row): EventStoreEntry
    {
        return new EventStoreEntry(
            id:              (int)($row[strtoupper(EventStoreTable::ID)] ?? $row[EventStoreTable::ID] ?? 0),
            eventClass:      (string)($row[strtoupper(EventStoreTable::TYPE_NAME)] ?? $row[EventStoreTable::TYPE_NAME] ?? ''),
            subscriberClass: (string)($row[strtoupper(EventStoreTable::SUBSCRIBER_CLASS)] ?? $row[EventStoreTable::SUBSCRIBER_CLASS] ?? ''),
            status:          EventStoreStatusEnum::from(
                (string)($row[strtoupper(EventStoreTable::STATUS)] ?? $row[EventStoreTable::STATUS] ?? 'pending')
            ),
            attempts:        (int)($row[strtoupper(EventStoreTable::ATTEMPTS)] ?? $row[EventStoreTable::ATTEMPTS] ?? 0),
            error:           $row[strtoupper(EventStoreTable::ERROR)] ?? $row[EventStoreTable::ERROR] ?? null,
            occurredOn:      $this->toDateTime(
                $row[strtoupper(EventStoreTable::OCCURRED_ON)] ?? $row[EventStoreTable::OCCURRED_ON] ?? null
            ),
            processedAt:     $this->toNullableDateTime(
                $row[strtoupper(EventStoreTable::PROCESSED_AT)] ?? $row[EventStoreTable::PROCESSED_AT] ?? null
            ),
            dateCreated:     $this->toNullableDateTime(
                $row[strtoupper(EventStoreTable::DATE_CREATED)] ?? $row[EventStoreTable::DATE_CREATED] ?? null
            )
        );
    }
    
    /**
     * Calculate average processing time from the last 100 completed events.
     *
     * @return float|null
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    private function calculateAvgProcessingTime(): ?float
    {
        $rs = EventStoreTable::getList([
            'filter' => [
                '=' . EventStoreTable::STATUS => EventStoreStatusEnum::COMPLETED->value,
                '!' . EventStoreTable::PROCESSED_AT => null,
                '!' . EventStoreTable::DATE_CREATED => null,
            ],
            'select' => [
                EventStoreTable::DATE_CREATED,
                EventStoreTable::PROCESSED_AT,
            ],
            'order' => [EventStoreTable::ID => 'DESC'],
            'limit' => 100,
        ]);
        
        $totalSeconds = 0.0;
        $count        = 0;
        
        while ( $row = $rs->fetch() ) {
            $created   = $this->toNullableDateTime(
                $row[strtoupper(EventStoreTable::DATE_CREATED)] ?? $row[EventStoreTable::DATE_CREATED] ?? null
            );
            $processed = $this->toNullableDateTime(
                $row[strtoupper(EventStoreTable::PROCESSED_AT)] ?? $row[EventStoreTable::PROCESSED_AT] ?? null
            );
            
            if ( null !== $created && null !== $processed ) {
                $totalSeconds += (float)($processed->getTimestamp() - $created->getTimestamp());
                $count++;
            }
        }
        
        if ( 0 === $count ) {
            return null;
        }
        
        return round($totalSeconds / $count, 2);
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

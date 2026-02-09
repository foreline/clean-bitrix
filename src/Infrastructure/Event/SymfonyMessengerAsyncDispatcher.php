<?php
declare(strict_types=1);

namespace Infrastructure\Event;

use Bitrix\Main\Type\DateTime;
use Domain\Event\AsyncDispatcherInterface;
use Domain\Event\AsyncSubscriberInterface;
use Domain\Event\Enum\EventStoreStatusEnum;
use Domain\Event\EventInterface;
use Infrastructure\Event\Repository\Bitrix\ORM\EventDispatchTable;
use Infrastructure\Event\Repository\Bitrix\ORM\EventStoreTable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

/**
 * Concrete AsyncDispatcherInterface implementation backed by normalized EventStore.
 *
 * Architecture (two-table):
 *   EventStoreTable  – immutable event log (one row per event, stores serialized body)
 *   EventDispatchTable – dispatch queue (one row per subscriber, references event body)
 *
 * The event body is written to EventStoreTable ONCE per event object instance
 * (cached by spl_object_id). Each subscriber gets a lightweight dispatch row
 * in EventDispatchTable with a foreign key to the event body.
 *
 * If async dispatch fails (DB write, serialization error, etc.),
 * falls back to synchronous execution to prevent event loss.
 */
final class SymfonyMessengerAsyncDispatcher implements AsyncDispatcherInterface
{
    private LoggerInterface $logger;
    
    /**
     * Cache of event store data keyed by spl_object_id.
     * Stores both serialization data AND the persisted eventStoreId,
     * so the event body is written to DB exactly once per event object.
     *
     * @var array<int, array{eventStoreId: int, class: string}>
     */
    private array $eventCache = [];
    
    public function __construct(
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }
    
    public function dispatch(EventInterface $event, AsyncSubscriberInterface $subscriber): void
    {
        $subscriberClass = get_class($subscriber);
        
        try {
            // Persist event body once, cache the eventStoreId for subsequent subscribers
            $cached = $this->getOrPersistEvent($event);
            
            // Create dispatch row for this subscriber
            $dispatchResult = EventDispatchTable::add([
                EventDispatchTable::EVENT_STORE_ID => $cached['eventStoreId'],
                EventDispatchTable::SUBSCRIBER_CLASS => $subscriberClass,
                EventDispatchTable::STATUS => EventStoreStatusEnum::PENDING->value,
                EventDispatchTable::ATTEMPTS => 0,
                EventDispatchTable::DATE_CREATED => new DateTime(),
            ]);
            
            if ( !$dispatchResult->isSuccess() ) {
                throw new RuntimeException(
                    'Failed to create dispatch row: ' . implode(', ', $dispatchResult->getErrorMessages())
                );
            }
            
            $this->logger->debug('Event dispatched async', [
                'event' => $cached['class'],
                'subscriber' => $subscriberClass,
                'event_store_id' => $cached['eventStoreId'],
                'dispatch_id' => (int)$dispatchResult->getId(),
            ]);
            
        } catch (Throwable $e) {
            $this->logger->error('Async dispatch failed, falling back to sync execution', [
                'event' => get_class($event),
                'subscriber' => $subscriberClass,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback: execute synchronously to prevent event loss
            $subscriber->handle($event);
        }
    }
    
    /**
     * Persists the event body to EventStoreTable (once per event object),
     * and returns the cached eventStoreId and class name.
     *
     * @param EventInterface $event
     * @return array{eventStoreId: int, class: string}
     */
    private function getOrPersistEvent(EventInterface $event): array
    {
        $objectId = spl_object_id($event);
        
        if ( !isset($this->eventCache[$objectId]) ) {
            $eventClass = get_class($event);
            $serialized = serialize($event);
            $occurredOn = DateTime::createFromTimestamp($event->occurredOn()->getTimestamp());
            
            $result = EventStoreTable::add([
                EventStoreTable::TYPE_NAME => $eventClass,
                EventStoreTable::EVENT_BODY => $serialized,
                EventStoreTable::OCCURRED_ON => $occurredOn,
                EventStoreTable::DATE_CREATED => new DateTime(),
            ]);
            
            if ( !$result->isSuccess() ) {
                throw new RuntimeException(
                    'Failed to persist event to store: ' . implode(', ', $result->getErrorMessages())
                );
            }
            
            $this->eventCache[$objectId] = [
                'eventStoreId' => (int)$result->getId(),
                'class'        => $eventClass,
            ];
        }
        
        return $this->eventCache[$objectId];
    }
    
    /**
     * Clears the event cache.
     * Called automatically if needed, or can be invoked manually between publish cycles.
     */
    public function clearCache(): void
    {
        $this->eventCache = [];
    }
}

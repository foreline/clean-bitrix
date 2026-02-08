<?php
declare(strict_types=1);

namespace Infrastructure\Event;

use Bitrix\Main\Type\DateTime;
use Domain\Event\AsyncDispatcherInterface;
use Domain\Event\AsyncSubscriberInterface;
use Domain\Event\Enum\EventStoreStatusEnum;
use Domain\Event\EventInterface;
use Infrastructure\Event\Message\AsyncEventMessage;
use Infrastructure\Event\Repository\Bitrix\ORM\EventStoreTable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Concrete AsyncDispatcherInterface implementation using Symfony Messenger.
 *
 * Persists the event to EventStore, then dispatches an AsyncEventMessage
 * through the Symfony Messenger bus for deferred processing by the worker.
 *
 * If async dispatch fails (transport error, serialization error, etc.),
 * falls back to synchronous execution to prevent event loss.
 */
final class SymfonyMessengerAsyncDispatcher implements AsyncDispatcherInterface
{
    private MessageBusInterface $messageBus;
    private LoggerInterface $logger;
    
    public function __construct(
        MessageBusInterface $messageBus,
        ?LoggerInterface $logger = null,
    ) {
        $this->messageBus = $messageBus;
        $this->logger = $logger ?? new NullLogger();
    }
    
    public function dispatch(EventInterface $event, AsyncSubscriberInterface $subscriber): void
    {
        $eventClass = get_class($event);
        $subscriberClass = get_class($subscriber);
        
        try {
            $serializedEvent = serialize($event);
            
            // Persist to Event Store for audit trail and retry capability
            $result = EventStoreTable::add([
                EventStoreTable::TYPE_NAME => $eventClass,
                EventStoreTable::EVENT_BODY => $serializedEvent,
                EventStoreTable::OCCURRED_ON => DateTime::createFromTimestamp($event->occurredOn()->getTimestamp()),
                EventStoreTable::STATUS => EventStoreStatusEnum::PENDING->value,
                EventStoreTable::SUBSCRIBER_CLASS => $subscriberClass,
                EventStoreTable::ATTEMPTS => 0,
                EventStoreTable::DATE_CREATED => new DateTime(),
            ]);
            
            if ( !$result->isSuccess() ) {
                throw new RuntimeException(
                    'Failed to persist event to store: ' . implode(', ', $result->getErrorMessages())
                );
            }
            
            $eventStoreId = (int)$result->getId();
            
            // Dispatch via Symfony Messenger
            $message = new AsyncEventMessage(
                eventClass: $eventClass,
                serializedEvent: $serializedEvent,
                subscriberClass: $subscriberClass,
                eventStoreId: $eventStoreId,
            );
            
            $this->messageBus->dispatch($message);
            
            $this->logger->debug('Event dispatched async', [
                'event' => $eventClass,
                'subscriber' => $subscriberClass,
                'event_store_id' => $eventStoreId,
            ]);
            
        } catch (Throwable $e) {
            $this->logger->error('Async dispatch failed, falling back to sync execution', [
                'event' => $eventClass,
                'subscriber' => $subscriberClass,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback: execute synchronously to prevent event loss
            $subscriber->handle($event);
        }
    }
}

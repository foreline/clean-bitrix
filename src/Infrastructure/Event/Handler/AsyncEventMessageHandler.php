<?php
declare(strict_types=1);

namespace Infrastructure\Event\Handler;

use Bitrix\Main\Type\DateTime;
use Domain\Event\Enum\EventStoreStatusEnum;
use Domain\Event\EventInterface;
use Domain\Event\Publisher;
use Domain\Events\ExceptionOccurredEvent;
use Infrastructure\Event\Message\AsyncEventMessage;
use Infrastructure\Event\Repository\Bitrix\ORM\EventStoreTable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

/**
 * Symfony Messenger handler that processes async events.
 *
 * Deserializes the event, instantiates the target subscriber,
 * and calls handle(). Updates EventStore status accordingly.
 */
final class AsyncEventMessageHandler
{
    private LoggerInterface $logger;
    
    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }
    
    /**
     * @param AsyncEventMessage $message
     * @return void
     * @throws Throwable
     */
    public function __invoke(AsyncEventMessage $message): void
    {
        $subscriberClass = $message->getSubscriberClass();
        $eventStoreId = $message->getEventStoreId();
        
        try {
            // Deserialize the event
            /** @var EventInterface $event */
            $event = unserialize(
                $message->getSerializedEvent(),
                ['allowed_classes' => true]
            );
            
            if ( false === $event ) {
                throw new RuntimeException(
                    'Failed to deserialize event: ' . $message->getEventClass()
                );
            }
            
            // Instantiate and execute the subscriber
            if ( !class_exists($subscriberClass) ) {
                throw new RuntimeException(
                    'Subscriber class not found: ' . $subscriberClass
                );
            }
            
            $subscriber = new $subscriberClass();
            $subscriber->handle($event);
            
            // Mark as completed with timestamp
            EventStoreTable::update($eventStoreId, [
                EventStoreTable::STATUS       => EventStoreStatusEnum::COMPLETED->value,
                EventStoreTable::PROCESSED_AT => new DateTime(),
            ]);
            
            $this->logger->info('Async event processed', [
                'event' => $message->getEventClass(),
                'subscriber' => $subscriberClass,
                'event_store_id' => $eventStoreId,
            ]);
            
        } catch (Throwable $e) {
            // Mark as failed with error details
            try {
                EventStoreTable::update($eventStoreId, [
                    EventStoreTable::STATUS => EventStoreStatusEnum::FAILED->value,
                    EventStoreTable::ERROR  => mb_substr($e->getMessage(), 0, 5000),
                ]);
            } catch (Throwable $updateError) {
                $this->logger->error('Failed to update event store on error', [
                    'event_store_id' => $eventStoreId,
                    'update_error' => $updateError->getMessage(),
                ]);
            }
            
            $this->logger->error('Async event processing failed', [
                'event' => $message->getEventClass(),
                'subscriber' => $subscriberClass,
                'event_store_id' => $eventStoreId,
                'error' => $e->getMessage(),
            ]);
            
            // Re-publish as exception event for centralized error handling
            Publisher::getInstance()->publish(new ExceptionOccurredEvent($e));
            
            // Re-throw for Symfony Messenger retry mechanism
            throw $e;
        }
    }
}

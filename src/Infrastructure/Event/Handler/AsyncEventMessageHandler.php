<?php
declare(strict_types=1);

namespace Infrastructure\Event\Handler;

use Bitrix\Main\Type\DateTime;
use Domain\Event\Enum\EventStoreStatusEnum;
use Domain\Event\EventInterface;
use Domain\Event\Publisher;
use Domain\Events\ExceptionOccurredEvent;
use Infrastructure\Event\Message\AsyncEventMessage;
use Infrastructure\Event\Repository\Bitrix\ORM\EventDispatchTable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

/**
 * Symfony Messenger handler that processes async events.
 *
 * Deserializes the event, instantiates the target subscriber,
 * and calls handle(). Updates EventDispatchTable status accordingly.
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
        $eventDispatchId = $message->getEventDispatchId();
        
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
            
            // Mark dispatch as completed
            EventDispatchTable::update($eventDispatchId, [
                EventDispatchTable::STATUS       => EventStoreStatusEnum::COMPLETED->value,
                EventDispatchTable::PROCESSED_AT => new DateTime(),
            ]);
            
            $this->logger->info('Async event processed', [
                'event' => $message->getEventClass(),
                'subscriber' => $subscriberClass,
                'dispatch_id' => $eventDispatchId,
                'event_store_id' => $message->getEventStoreId(),
            ]);
            
        } catch (Throwable $e) {
            // Mark dispatch as failed with error details
            try {
                EventDispatchTable::update($eventDispatchId, [
                    EventDispatchTable::STATUS => EventStoreStatusEnum::FAILED->value,
                    EventDispatchTable::ERROR  => mb_substr($e->getMessage(), 0, 5000),
                    EventDispatchTable::PROCESSED_AT => new DateTime(),
                ]);
            } catch (Throwable $updateError) {
                $this->logger->error('Failed to update dispatch on error', [
                    'dispatch_id' => $eventDispatchId,
                    'update_error' => $updateError->getMessage(),
                ]);
            }
            
            $this->logger->error('Async event processing failed', [
                'event' => $message->getEventClass(),
                'subscriber' => $subscriberClass,
                'dispatch_id' => $eventDispatchId,
                'event_store_id' => $message->getEventStoreId(),
                'error' => $e->getMessage(),
            ]);
            
            // Re-publish as exception event for centralized error handling
            Publisher::getInstance()->publish(new ExceptionOccurredEvent($e));
            
            // Re-throw for Symfony Messenger retry mechanism
            throw $e;
        }
    }
}

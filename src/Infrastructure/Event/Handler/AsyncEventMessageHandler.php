<?php
declare(strict_types=1);

namespace Infrastructure\Event\Handler;

use Bitrix\Main\Type\DateTime;
use Domain\Event\Event;
use Domain\Event\Enum\EventStoreStatusEnum;
use Domain\Event\EventInterface;
use Domain\Event\RetryableSubscriberInterface;
use Domain\Event\RetryPolicy;
use Domain\User\Service\GetCurrentUser;
use Exception;
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
 *
 * On failure, applies exponential backoff retry logic:
 * - If subscriber implements RetryableSubscriberInterface, uses its policy
 * - Otherwise, uses the default RetryPolicy from constructor
 * - Retryable dispatches are reset to PENDING with next_retry_at
 * - Exhausted dispatches are marked FAILED
 */
final class AsyncEventMessageHandler
{
    private LoggerInterface $logger;
    private RetryPolicy $defaultRetryPolicy;
    
    /**
     * @param RetryPolicy|null $defaultRetryPolicy Default retry policy for subscribers without RetryableSubscriberInterface
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        ?RetryPolicy $defaultRetryPolicy = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->defaultRetryPolicy = $defaultRetryPolicy ?? new RetryPolicy();
        $this->logger = $logger ?? new NullLogger();
    }
    
    /**
     * @param AsyncEventMessage $message
     * @return void
     * @throws Exception
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
            
            // Restore the original user context from the serialized event.
            // Events capture the authenticated user at publish time (Event::__construct).
            // Without this, permission checks in subscribers fail with NotAuthorizedException
            // because the worker runs as CLI with no web session.
            if ( $event instanceof Event && null !== $event->getUser() ) {
                ( new GetCurrentUser() )->set($event->getUser());
                
                $this->logger->debug('Restored user context for async processing', [
                    'user_id' => $event->getUser()->getId(),
                    'subscriber' => $subscriberClass,
                ]);
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
            $this->handleFailure($message, $e);
        }
    }
    
    /**
     * Handles a failed dispatch with retry logic.
     *
     * Resolves the retry policy (per-subscriber or default), checks if retry
     * is allowed, and either schedules a retry (PENDING + next_retry_at)
     * or marks the dispatch as permanently FAILED.
     *
     * @param AsyncEventMessage $message
     * @param Throwable $e
     * @return void
     * @throws Exception
     */
    private function handleFailure(AsyncEventMessage $message, Throwable $e): void
    {
        $subscriberClass = $message->getSubscriberClass();
        $eventDispatchId = $message->getEventDispatchId();
        
        // Resolve retry policy: per-subscriber or global default
        $retryPolicy = $this->resolveRetryPolicy($subscriberClass);
        
        // Read current attempt count from the dispatch row
        // (already incremented by DatabaseTransport::get())
        $attempts = $this->getCurrentAttempts($eventDispatchId);
        
        if ( $retryPolicy->shouldRetry($attempts) ) {
            $this->scheduleRetry($eventDispatchId, $attempts, $retryPolicy, $message, $e);
        } else {
            $this->markFailed($eventDispatchId, $message, $e);
        }
    }
    
    /**
     * Resolves the retry policy for a subscriber class.
     *
     * If the subscriber implements RetryableSubscriberInterface, uses its policy.
     * Otherwise, returns the default policy from constructor.
     *
     * @param string $subscriberClass
     * @return RetryPolicy
     */
    private function resolveRetryPolicy(string $subscriberClass): RetryPolicy
    {
        if ( !class_exists($subscriberClass) ) {
            return $this->defaultRetryPolicy;
        }
        
        if ( is_subclass_of($subscriberClass, RetryableSubscriberInterface::class) ) {
            /** @var RetryableSubscriberInterface $subscriber */
            $subscriber = new $subscriberClass();
            return $subscriber->getRetryPolicy();
        }
        
        return $this->defaultRetryPolicy;
    }
    
    /**
     * Reads the current attempt count from the dispatch table.
     *
     * @param int $eventDispatchId
     * @return int
     */
    private function getCurrentAttempts(int $eventDispatchId): int
    {
        try {
            $row = EventDispatchTable::getList([
                'filter' => ['=' . EventDispatchTable::ID => $eventDispatchId],
                'select' => [EventDispatchTable::ATTEMPTS],
                'limit' => 1,
            ])->fetch();
            
            if ( false !== $row && null !== $row ) {
                return (int)$row[EventDispatchTable::ATTEMPTS];
            }
        } catch (Throwable $queryError) {
            $this->logger->warning('Failed to read dispatch attempts', [
                'dispatch_id' => $eventDispatchId,
                'error' => $queryError->getMessage(),
            ]);
        }
        
        return 1;
    }
    
    /**
     * Schedules a retry by resetting dispatch to PENDING with next_retry_at.
     *
     * @param int $eventDispatchId
     * @param int $attempts
     * @param RetryPolicy $retryPolicy
     * @param AsyncEventMessage $message
     * @param Throwable $e
     * @return void
     * @throws Exception
     */
    private function scheduleRetry(
        int $eventDispatchId,
        int $attempts,
        RetryPolicy $retryPolicy,
        AsyncEventMessage $message,
        Throwable $e,
    ): void {
        $delayMs = $retryPolicy->calculateDelayMs($attempts);
        $delaySeconds = (int)ceil($delayMs / 1000);
    
        $nextRetryAt = (new DateTime())->add('+' . $delaySeconds . ' seconds');
        
        try {
            EventDispatchTable::update($eventDispatchId, [
                EventDispatchTable::STATUS        => EventStoreStatusEnum::PENDING->value,
                EventDispatchTable::NEXT_RETRY_AT => $nextRetryAt,
                EventDispatchTable::ERROR         => mb_substr($e->getMessage(), 0, 5000),
            ]);
        } catch (Throwable $updateError) {
            $this->logger->error('Failed to schedule retry', [
                'dispatch_id' => $eventDispatchId,
                'update_error' => $updateError->getMessage(),
            ]);
            return;
        }
        
        $this->logger->warning('Async event scheduled for retry', [
            'event' => $message->getEventClass(),
            'subscriber' => $message->getSubscriberClass(),
            'dispatch_id' => $eventDispatchId,
            'event_store_id' => $message->getEventStoreId(),
            'attempt' => $attempts,
            'delay_seconds' => $delaySeconds,
            'next_retry_at' => $nextRetryAt->toString(),
            'error' => mb_substr($e->getMessage(), 0, 500),
        ]);
    }
    
    /**
     * Marks a dispatch as permanently failed (retries exhausted).
     *
     * @param int $eventDispatchId
     * @param AsyncEventMessage $message
     * @param Throwable $e
     * @return void
     */
    private function markFailed(
        int $eventDispatchId,
        AsyncEventMessage $message,
        Throwable $e,
    ): void {
        try {
            EventDispatchTable::update($eventDispatchId, [
                EventDispatchTable::STATUS       => EventStoreStatusEnum::FAILED->value,
                EventDispatchTable::ERROR        => mb_substr($e->getMessage(), 0, 5000),
                EventDispatchTable::PROCESSED_AT => new DateTime(),
            ]);
        } catch (Throwable $updateError) {
            $this->logger->error('Failed to update dispatch on error', [
                'dispatch_id' => $eventDispatchId,
                'update_error' => $updateError->getMessage(),
            ]);
        }
        
        $this->logger->error('Async event processing permanently failed (retries exhausted)', [
            'event' => $message->getEventClass(),
            'subscriber' => $message->getSubscriberClass(),
            'dispatch_id' => $eventDispatchId,
            'event_store_id' => $message->getEventStoreId(),
            'error' => $e->getMessage(),
        ]);
    }
}

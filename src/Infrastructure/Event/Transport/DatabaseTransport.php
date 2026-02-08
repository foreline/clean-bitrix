<?php
declare(strict_types=1);

namespace Infrastructure\Event\Transport;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;
use Domain\Event\Enum\EventStoreStatusEnum;
use Exception;
use Infrastructure\Event\Message\AsyncEventMessage;
use Infrastructure\Event\Repository\Bitrix\ORM\EventStoreTable;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Symfony Messenger transport backed by the EventStoreTable (Bitrix ORM).
 *
 * Uses the `servicedesk_event_store` database table as both persistence
 * layer and message queue. Pending messages are polled by the worker,
 * locked via status transition, and acknowledged/rejected after processing.
 *
 * This eliminates the need for ext-redis or symfony/doctrine-messenger
 * while maintaining full Symfony Messenger Worker compatibility.
 */
final class DatabaseTransport implements TransportInterface
{
    private int $pollingIntervalMs;
    
    /**
     * @param int $pollingIntervalMs Polling interval when no messages are available (default: 1000ms)
     */
    public function __construct(int $pollingIntervalMs = 1000)
    {
        $this->pollingIntervalMs = $pollingIntervalMs;
    }
    
    /**
     * Fetches pending messages from the event store.
     *
     * Atomically transitions status from 'pending' to 'processing'
     * to prevent concurrent workers from picking the same message.
     *
     * @return iterable<Envelope>
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public function get(): iterable
    {
        $row = EventStoreTable::getList([
            'filter' => [
                '=' . EventStoreTable::STATUS => EventStoreStatusEnum::PENDING->value,
            ],
            'order' => [
                EventStoreTable::ID => 'ASC',
            ],
            'limit' => 1,
        ])->fetch();
        
        if ( false === $row || null === $row ) {
            // No pending messages — sleep to avoid busy-waiting
            usleep($this->pollingIntervalMs * 1000);
            return [];
        }
        
        $id = (int)$row[EventStoreTable::ID];
        
        // Atomically claim the message by updating status
        $updateResult = EventStoreTable::update($id, [
            EventStoreTable::STATUS => EventStoreStatusEnum::PROCESSING->value,
            EventStoreTable::ATTEMPTS => (int)$row[EventStoreTable::ATTEMPTS] + 1,
        ]);
        
        if ( !$updateResult->isSuccess() ) {
            // Another worker claimed it — skip
            return [];
        }
        
        $message = new AsyncEventMessage(
            eventClass: $row[EventStoreTable::TYPE_NAME],
            serializedEvent: $row[EventStoreTable::EVENT_BODY],
            subscriberClass: $row[EventStoreTable::SUBSCRIBER_CLASS],
            eventStoreId: $id,
        );
        
        $envelope = new Envelope($message, [
            new TransportMessageIdStamp((string)$id),
        ]);
        
        return [$envelope];
    }
    
    /**
     * Acknowledges successful message processing.
     *
     * No-op: The AsyncEventMessageHandler already updates EventStore
     * status to 'completed' with processed_at timestamp, which has
     * full context (subscriber info, event details). Avoids duplicate DB writes.
     *
     * @param Envelope $envelope
     * @return void
     */
    public function ack(Envelope $envelope): void
    {
        // Status already set to 'completed' by AsyncEventMessageHandler
    }
    
    /**
     * Rejects a message after processing failure.
     *
     * No-op: The AsyncEventMessageHandler already updates EventStore
     * status to 'failed' with error details before re-throwing.
     * Avoids duplicate DB writes.
     *
     * @param Envelope $envelope
     * @return void
     */
    public function reject(Envelope $envelope): void
    {
        // Status already set to 'failed' by AsyncEventMessageHandler
    }
    
    /**
     * Sends a message to the transport (persists to Event Store).
     *
     * Note: In normal flow, SymfonyMessengerAsyncDispatcher already persists
     * to EventStoreTable before dispatching. This method serves as the
     * Messenger-compatible send entry point when used via the bus.
     *
     * @param Envelope $envelope
     * @return Envelope
     * @throws Exception
     */
    public function send(Envelope $envelope): Envelope
    {
        /** @var AsyncEventMessage $message */
        $message = $envelope->getMessage();
        
        if ( !$message instanceof AsyncEventMessage ) {
            throw new InvalidArgumentException(
                'DatabaseTransport only supports AsyncEventMessage, got: ' . get_class($envelope->getMessage())
            );
        }
        
        // The message should already be persisted by SymfonyMessengerAsyncDispatcher.
        // If it has an eventStoreId > 0, it's already in the DB — just return.
        if ( 0 < $message->getEventStoreId() ) {
            return $envelope->with(
                new TransportMessageIdStamp((string)$message->getEventStoreId())
            );
        }
        
        // Fallback: persist now if not yet stored
        $result = EventStoreTable::add([
            EventStoreTable::TYPE_NAME => $message->getEventClass(),
            EventStoreTable::EVENT_BODY => $message->getSerializedEvent(),
            EventStoreTable::OCCURRED_ON => new DateTime(),
            EventStoreTable::STATUS => EventStoreStatusEnum::PENDING->value,
            EventStoreTable::SUBSCRIBER_CLASS => $message->getSubscriberClass(),
            EventStoreTable::ATTEMPTS => 0,
            EventStoreTable::DATE_CREATED => new DateTime(),
        ]);
        
        if ( !$result->isSuccess() ) {
            throw new RuntimeException(
                'Failed to persist message: ' . implode(', ', $result->getErrorMessages())
            );
        }
        
        return $envelope->with(
            new TransportMessageIdStamp((string)$result->getId())
        );
    }
    
    /**
     * Extracts the event store ID from an envelope's TransportMessageIdStamp.
     *
     * @param Envelope $envelope
     * @return int|null
     */
    private function extractId(Envelope $envelope): ?int
    {
        /** @var TransportMessageIdStamp|null $stamp */
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        
        if ( null === $stamp ) {
            return null;
        }
        
        return (int)$stamp->getId();
    }
}

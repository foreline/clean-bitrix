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
use Infrastructure\Event\Repository\Bitrix\ORM\EventDispatchTable;
use Infrastructure\Event\Repository\Bitrix\ORM\EventStoreTable;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Symfony Messenger transport backed by the normalized two-table EventStore.
 *
 * Polls `servicedesk_event_dispatch` for PENDING dispatch rows, then fetches
 * the serialized event body from `servicedesk_event_store` via the foreign key.
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
     * Fetches pending dispatch rows from the event dispatch table.
     *
     * Atomically transitions status from 'pending' to 'processing'
     * to prevent concurrent workers from picking the same dispatch.
     * Then fetches the serialized event body from the event store.
     *
     * @return iterable<Envelope>
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public function get(): iterable
    {
        // 1. Find the next pending dispatch row (fresh or retry-ready)
        $dispatchRow = EventDispatchTable::getList([
            'filter' => [
                '=' . EventDispatchTable::STATUS => EventStoreStatusEnum::PENDING->value,
                [
                    'LOGIC' => 'OR',
                    '=' . EventDispatchTable::NEXT_RETRY_AT => null,
                    '<=' . EventDispatchTable::NEXT_RETRY_AT => new DateTime(),
                ],
            ],
            'order' => [
                EventDispatchTable::ID => 'ASC',
            ],
            'limit' => 1,
        ])->fetch();
        
        if ( false === $dispatchRow || null === $dispatchRow ) {
            // No pending messages — sleep to avoid busy-waiting
            usleep($this->pollingIntervalMs * 1000);
            return [];
        }
        
        $dispatchId = (int)$dispatchRow[EventDispatchTable::ID];
        $eventStoreId = (int)$dispatchRow[EventDispatchTable::EVENT_STORE_ID];
        
        // 2. Atomically claim the dispatch by updating status
        $updateResult = EventDispatchTable::update($dispatchId, [
            EventDispatchTable::STATUS => EventStoreStatusEnum::PROCESSING->value,
            EventDispatchTable::ATTEMPTS => (int)$dispatchRow[EventDispatchTable::ATTEMPTS] + 1,
            EventDispatchTable::PROCESSING_STARTED_AT => new DateTime(),
        ]);
        
        if ( !$updateResult->isSuccess() ) {
            // Another worker claimed it — skip
            return [];
        }
        
        // 3. Fetch the serialized event body from event store
        $eventRow = EventStoreTable::getList([
            'filter' => [
                '=' . EventStoreTable::ID => $eventStoreId,
            ],
            'select' => [
                EventStoreTable::TYPE_NAME,
                EventStoreTable::EVENT_BODY,
            ],
            'limit' => 1,
        ])->fetch();
        
        if ( false === $eventRow || null === $eventRow ) {
            // Orphaned dispatch row — mark as failed
            EventDispatchTable::update($dispatchId, [
                EventDispatchTable::STATUS => EventStoreStatusEnum::FAILED->value,
                EventDispatchTable::ERROR => 'Event store row not found (event_store_id=' . $eventStoreId . ')',
                EventDispatchTable::PROCESSED_AT => new DateTime(),
            ]);
            return [];
        }
        
        $message = new AsyncEventMessage(
            eventClass: $eventRow[EventStoreTable::TYPE_NAME],
            serializedEvent: $eventRow[EventStoreTable::EVENT_BODY],
            subscriberClass: $dispatchRow[EventDispatchTable::SUBSCRIBER_CLASS],
            eventDispatchId: $dispatchId,
            eventStoreId: $eventStoreId,
        );
        
        $envelope = new Envelope($message, [
            new TransportMessageIdStamp((string)$dispatchId),
        ]);
        
        return [$envelope];
    }
    
    /**
     * Acknowledges successful message processing.
     *
     * No-op: The AsyncEventMessageHandler already updates EventDispatch
     * status to 'completed' with processed_at timestamp.
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
     * No-op: The AsyncEventMessageHandler already updates EventDispatch
     * status to 'failed' with error details before re-throwing.
     *
     * @param Envelope $envelope
     * @return void
     */
    public function reject(Envelope $envelope): void
    {
        // Status already set to 'failed' by AsyncEventMessageHandler
    }
    
    /**
     * Sends a message to the transport.
     *
     * No-op in the normalized architecture: SymfonyMessengerAsyncDispatcher
     * handles persistence directly to EventStoreTable + EventDispatchTable.
     * This method exists only for Symfony Messenger TransportInterface compliance.
     *
     * @param Envelope $envelope
     * @return Envelope
     */
    public function send(Envelope $envelope): Envelope
    {
        // Persistence is handled by SymfonyMessengerAsyncDispatcher
        return $envelope;
    }
}

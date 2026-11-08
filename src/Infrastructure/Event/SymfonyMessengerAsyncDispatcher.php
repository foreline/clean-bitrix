<?php
declare(strict_types=1);

namespace Infrastructure\Event;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Domain\Event\AsyncSubscriberInterface;
use Domain\Event\BatchAsyncDispatcherInterface;
use Domain\Event\Enum\EventStoreStatusEnum;
use Domain\Event\EventInterface;
use Infrastructure\Event\Repository\Bitrix\ORM\EventDispatchTable;
use Infrastructure\Event\Repository\Bitrix\ORM\EventStoreTable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

/** Атомарно сохраняет одно тело события и все его доставки. */
final class SymfonyMessengerAsyncDispatcher implements BatchAsyncDispatcherInterface
{
    private LoggerInterface $logger;

    /** Логгер ошибок публикации. */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /** Совместимый одиночный вызов создаёт независимую публикацию. */
    public function dispatch(EventInterface $event, AsyncSubscriberInterface $subscriber): void
    {
        $this->dispatchBatch($event, [$subscriber]);
    }

    /** @param list<AsyncSubscriberInterface> $subscribers */
    public function dispatchBatch(EventInterface $event, array $subscribers): void
    {
        if ( [] === $subscribers ) {
            return;
        }
        // Не сохраняем ID PHP-объектов: после уничтожения они используются повторно.
        $serialized = serialize($event);
        $connection = Application::getConnection();
        $connection->startTransaction();
        try {
            $result = EventStoreTable::add([
                EventStoreTable::TYPE_NAME => $event::class,
                EventStoreTable::EVENT_BODY => $serialized,
                EventStoreTable::OCCURRED_ON => DateTime::createFromTimestamp($event->occurredOn()->getTimestamp()),
                EventStoreTable::DATE_CREATED => new DateTime(),
            ]);
            if ( !$result->isSuccess() ) {
                throw new RuntimeException(implode(', ', $result->getErrorMessages()));
            }
            foreach ( $subscribers as $subscriber ) {
                if ( 255 < strlen($subscriber::class) ) {
                    throw new RuntimeException('Subscriber class exceeds the queue column limit');
                }
                $dispatch = EventDispatchTable::add([
                    EventDispatchTable::EVENT_STORE_ID => (int)$result->getId(),
                    EventDispatchTable::SUBSCRIBER_CLASS => $subscriber::class,
                    EventDispatchTable::STATUS => EventStoreStatusEnum::PENDING->value,
                    EventDispatchTable::ATTEMPTS => 0,
                    EventDispatchTable::DATE_CREATED => new DateTime(),
                ]);
                if ( !$dispatch->isSuccess() ) {
                    throw new RuntimeException(implode(', ', $dispatch->getErrorMessages()));
                }
            }
            $connection->commitTransaction();
        } catch (Throwable $error) {
            $connection->rollbackTransaction();
            $this->logger->error('Atomic event publication failed', ['event' => $event::class, 'exception' => $error]);
            // Ошибка должна дойти до вызывающей операции. Sync fallback после
            // частичной/неопределённой записи скрывает потерю и дублирует эффекты.
            throw $error;
        }
    }

    /** @deprecated Кэш идентичности больше не используется. */
    public function clearCache(): void
    {
    }
}

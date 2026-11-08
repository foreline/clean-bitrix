<?php
declare(strict_types=1);

namespace Tests\Infrastructure\Event;

use Bitrix\Main\Application;
use DateTimeImmutable;
use Domain\Event\AsyncSubscriberInterface;
use Domain\Event\EventInterface;
use Infrastructure\Event\EventQueueSchema;
use Infrastructure\Event\Monitoring\ImmediatePurgeStrategy;
use Infrastructure\Event\Repository\Bitrix\ORM\EventDispatchTable;
use Infrastructure\Event\Repository\Bitrix\ORM\EventStoreTable;
use Infrastructure\Event\SymfonyMessengerAsyncDispatcher;
use Infrastructure\Event\Transport\DatabaseTransport;
use PHPUnit\Framework\TestCase;

/** Реальная БД: тело, доставки и очистка; все тестовые записи откатываются. */
final class AtomicEventQueueTest extends TestCase
{
    protected function setUp(): void
    {
        if ( !method_exists(Application::class, 'getConnection') ) {
            self::markTestSkipped('Requires a real development Bitrix bootstrap and database.');
        }
        Application::getConnection()->startTransaction();
    }
    protected function tearDown(): void
    {
        if ( method_exists(Application::class, 'getConnection') ) {
            Application::getConnection()->rollbackTransaction();
        }
    }
    public function testBatchStoresOneBodyAndPurgeWaitsForAllDeliveries(): void
    {
        $dispatcher = new SymfonyMessengerAsyncDispatcher();
        $event = new QueueTestEvent('first');
        $dispatcher->dispatchBatch($event, [new QueueTestSubscriber(), new QueueOtherSubscriber()]);
        $row = EventStoreTable::getList(['filter'=>['=type_name'=>QueueTestEvent::class], 'order'=>['id'=>'DESC'], 'limit'=>1])->fetch();
        self::assertNotFalse($row);
        $id = (int)$row['id'];
        $deliveries = EventDispatchTable::getList(['filter'=>['=event_store_id'=>$id], 'order'=>['id'=>'ASC']])->fetchAll();
        self::assertCount(2, $deliveries);
        self::assertSame('first', unserialize($row['event_body'])->value);
        EventDispatchTable::update($deliveries[0]['id'], ['status'=>'completed']);
        $purge = new ImmediatePurgeStrategy();
        $purge->onDispatchCompleted($id, (int)$deliveries[0]['id']);
        self::assertSame(1, EventStoreTable::getCount(['=id'=>$id]));
        EventDispatchTable::update($deliveries[1]['id'], ['status'=>'completed']);
        $purge->onDispatchCompleted($id, (int)$deliveries[1]['id']);
        self::assertSame(0, EventStoreTable::getCount(['=id'=>$id]));
        self::assertSame(0, EventDispatchTable::getCount(['=event_store_id'=>$id]));
    }
    public function testObjectIdReuseCannotReferenceDeletedBody(): void
    {
        $dispatcher = new SymfonyMessengerAsyncDispatcher();
        $subscriber = new QueueTestSubscriber();
        for ($i=0; $i<5; $i++) {
            $event = new QueueTestEvent((string)$i);
            $dispatcher->dispatch($event, $subscriber);
            unset($event);
        }
        $rows = EventStoreTable::getList(['filter'=>['=type_name'=>QueueTestEvent::class], 'order'=>['id'=>'DESC'], 'limit'=>5])->fetchAll();
        self::assertCount(5,$rows);
        self::assertSame(['4','3','2','1','0'], array_map(static fn(array $r): string => unserialize($r['event_body'])->value,$rows));
    }
    public function testInvalidSecondDeliveryRollsBackWholeBatch(): void
    {
        $before = EventStoreTable::getCount(['=type_name'=>QueueTestEvent::class]);
        try {
            (new SymfonyMessengerAsyncDispatcher())->dispatchBatch(new QueueTestEvent('rollback'),
                [new QueueTestSubscriber(), new InvalidSubscriberXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX()]);
            self::fail('Invalid subscriber name must reject publication');
        } catch (\Throwable $error) {
            self::assertSame($before, EventStoreTable::getCount(['=type_name'=>QueueTestEvent::class]));
            self::assertStringNotContainsString('Invalid subscriber name must reject', $error->getMessage());
        }
    }
    public function testTransportClaimsOnlyPendingAndDefersFutureRetries(): void
    {
        // Isolate selection inside this transaction without changing durable queue data.
        Application::getConnection()->queryExecute("UPDATE servicedesk_event_dispatch SET status='failed' WHERE status='pending'");
        (new SymfonyMessengerAsyncDispatcher())->dispatch(new QueueTestEvent('claim'),new QueueTestSubscriber());
        $transport = new DatabaseTransport(0);
        $messages = $transport->get();
        self::assertCount(1,$messages);
        $message = $messages[0]->getMessage();
        $row = EventDispatchTable::getByPrimary($message->getEventDispatchId())->fetch();
        self::assertSame('processing',$row['status']);
        self::assertSame(1,(int)$row['attempts']);
        self::assertSame([],$transport->get());
        EventDispatchTable::update($row['id'], ['status'=>'pending','next_retry_at'=>(new \Bitrix\Main\Type\DateTime())->add('+1 hour')]);
        self::assertSame([],$transport->get());
    }
}

final class QueueTestEvent implements EventInterface
{
    public function __construct(public string $value) {}
    public function occurredOn(): DateTimeImmutable { return new DateTimeImmutable(); }
}
class QueueTestSubscriber implements AsyncSubscriberInterface
{
    public static function getSubscribedEvents(): array { return [QueueTestEvent::class]; }
    public function isSubscribedTo(EventInterface $event): bool { return $event instanceof QueueTestEvent; }
    public function isHandled(EventInterface $event): bool { return true; }
    public function handle(EventInterface $event): void {}
}
final class QueueOtherSubscriber extends QueueTestSubscriber {}

final class InvalidSubscriberXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX extends QueueTestSubscriber {}

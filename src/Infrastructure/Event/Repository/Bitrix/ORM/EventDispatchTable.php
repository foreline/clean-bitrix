<?php
declare(strict_types=1);

namespace Infrastructure\Event\Repository\Bitrix\ORM;

use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\SystemException;
use Domain\Event\Enum\EventStoreStatusEnum;
use Exception;
use Infrastructure\Bitrix\Repository\ORM\OrmDataManager;
use Psr\Log\LoggerInterface;

/**
 * Bitrix ORM entity for async event dispatch queue.
 *
 * Each row represents a single (event, subscriber) dispatch.
 * References EventStoreTable for the serialized event body,
 * avoiding duplicate storage of large event payloads.
 */
class EventDispatchTable extends OrmDataManager
{
    public const ID = 'id';
    public const EVENT_STORE_ID = 'event_store_id';
    public const SUBSCRIBER_CLASS = 'subscriber_class';
    public const STATUS = 'status';
    public const ATTEMPTS = 'attempts';
    public const PROCESSING_STARTED_AT = 'processing_started_at';
    public const PROCESSED_AT = 'processed_at';
    public const ERROR = 'error';
    public const NEXT_RETRY_AT = 'next_retry_at';
    public const DATE_CREATED = 'date_created';
    
    // Relation alias for JOINs
    public const REL_EVENT_STORE = 'EVENT_STORE';
    
    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return 'servicedesk_event_dispatch';
    }
    
    /**
     * @return array
     * @throws SystemException
     */
    public static function getMap(): array
    {
        $map = [
            (new IntegerField(self::ID))
                ->configurePrimary(true)
                ->configureAutocomplete(true),
            (new IntegerField(self::EVENT_STORE_ID))
                ->configureRequired(true),
            (new StringField(self::SUBSCRIBER_CLASS, [
                'size' => 500,
            ]))->configureRequired(true),
            (new StringField(self::STATUS, [
                'size' => 20,
            ]))->configureRequired(true)
                ->configureDefaultValue(EventStoreStatusEnum::PENDING->value),
            (new IntegerField(self::ATTEMPTS))
                ->configureDefaultValue(0),
            (new DatetimeField(self::PROCESSING_STARTED_AT))
                ->configureNullable(true),
            (new DatetimeField(self::PROCESSED_AT))
                ->configureNullable(true),
            (new TextField(self::ERROR))
                ->configureNullable(true),
            (new DatetimeField(self::NEXT_RETRY_AT))
                ->configureNullable(true),
            (new DatetimeField(self::DATE_CREATED))
                ->configureNullable(true),
            
            // Relation to EventStoreTable
            new Reference(
                self::REL_EVENT_STORE,
                EventStoreTable::class,
                Join::on('this.' . self::EVENT_STORE_ID, 'ref.' . EventStoreTable::ID)
            ),
        ];
        
        $map = array_merge($map, self::getAdditionalFields());
        
        return $map;
    }
    
    /**
     * @return array
     */
    public static function getAdditionalFields(): array
    {
        return [];
    }
    
    /**
     * @param LoggerInterface|null $logger
     * @return void
     * @throws Exception
     */
    public static function checkTable(?LoggerInterface $logger = null): void
    {
        self::$entityTableClass = self::class;
        parent::checkTable($logger);
        \Infrastructure\Event\EventQueueSchema::ensureIndexes();
    }
}

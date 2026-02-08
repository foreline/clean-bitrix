<?php
declare(strict_types=1);

namespace Infrastructure\Event\Repository\Bitrix\ORM;

use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\SystemException;
use Domain\Event\Enum\EventStoreStatusEnum;
use Exception;
use Infrastructure\Bitrix\Repository\ORM\OrmDataManager;
use Psr\Log\LoggerInterface;

/**
 * Bitrix ORM entity for async event store.
 * Stores serialized domain events for async processing.
 */
class EventStoreTable extends OrmDataManager
{
    public const ID = 'id';
    public const TYPE_NAME = 'type_name';
    public const EVENT_BODY = 'event_body';
    public const OCCURRED_ON = 'occurred_on';
    public const STATUS = 'status';
    public const SUBSCRIBER_CLASS = 'subscriber_class';
    public const ATTEMPTS = 'attempts';
    public const PROCESSED_AT = 'processed_at';
    public const ERROR = 'error';
    public const DATE_CREATED = 'date_created';
    
    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return 'servicedesk_event_store';
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
            (new StringField(self::TYPE_NAME, [
                'size' => 500,
            ]))->configureRequired(true),
            (new TextField(self::EVENT_BODY))
                ->configureRequired(true)
                ->configureLong(true),
            (new DatetimeField(self::OCCURRED_ON))
                ->configureRequired(true),
            (new StringField(self::STATUS, [
                'size' => 20,
            ]))->configureRequired(true)
                ->configureDefaultValue(EventStoreStatusEnum::PENDING->value),
            (new StringField(self::SUBSCRIBER_CLASS, [
                'size' => 500,
            ]))->configureNullable(true),
            (new IntegerField(self::ATTEMPTS))
                ->configureDefaultValue(0),
            (new DatetimeField(self::PROCESSED_AT))
                ->configureNullable(true),
            (new TextField(self::ERROR))
                ->configureNullable(true),
            (new DatetimeField(self::DATE_CREATED))
                ->configureNullable(true),
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
    }
}

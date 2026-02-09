<?php
declare(strict_types=1);

namespace Infrastructure\Event\Repository\Bitrix\ORM;

use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\SystemException;
use Exception;
use Infrastructure\Bitrix\Repository\ORM\OrmDataManager;
use Psr\Log\LoggerInterface;

/**
 * Bitrix ORM entity for async event store.
 * Stores serialized domain events as immutable log entries.
 * Subscriber dispatch tracking is handled by EventDispatchTable.
 */
class EventStoreTable extends OrmDataManager
{
    public const ID = 'id';
    public const TYPE_NAME = 'type_name';
    public const EVENT_BODY = 'event_body';
    public const OCCURRED_ON = 'occurred_on';
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

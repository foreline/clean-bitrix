<?php
declare(strict_types=1);

namespace Infrastructure\Event;

use Bitrix\Main\Application;

/** Индексы очереди; вызывается установщиком/миграцией, не рабочим циклом. */
final class EventQueueSchema
{
    /** Повторный запуск не перестраивает существующие индексы. */
    public static function ensureIndexes(): void
    {
        $connection = Application::getConnection();
        $indexes = [];
        $rows = $connection->query('SHOW INDEX FROM servicedesk_event_dispatch');
        while ( $row = $rows->fetch() ) {
            $indexes[$row['Key_name']] = true;
        }
        foreach ( [
            'ix_event_dispatch_pending' => 'status, id, next_retry_at',
            'ix_event_dispatch_event_status' => 'event_store_id, status',
        ] as $name => $columns ) {
            if ( !isset($indexes[$name]) ) {
                $connection->queryExecute('CREATE INDEX ' . $name . ' ON servicedesk_event_dispatch (' . $columns . ')');
            }
        }
    }
}

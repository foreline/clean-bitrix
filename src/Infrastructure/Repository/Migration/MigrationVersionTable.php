<?php
declare(strict_types=1);

namespace Infrastructure\Repository\Migration;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DateTimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

/**
 * Tracks applied database migrations
 * This table is self-managed and never deleted by migrations
 */
class MigrationVersionTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'servicedesk_migration_versions';
    }
    
    public static function getMap(): array
    {
        return [
            (new StringField('VERSION'))
                ->configurePrimary(true)
                ->configureSize(14), // YYYYMMDDHHmmss format
            
            (new DateTimeField('EXECUTED_AT'))
                ->configureRequired(true),
            
            (new IntegerField('EXECUTION_TIME'))
                ->configureRequired(true)
                ->configureTitle('Execution time in milliseconds'),
            
            (new StringField('MIGRATION_CLASS'))
                ->configureRequired(true)
                ->configureSize(255),
            
            (new TextField('DESCRIPTION'))
                ->configureNullable(true),
            
            (new StringField('CHECKSUM'))
                ->configureNullable(true)
                ->configureSize(32)
                ->configureTitle('MD5 checksum of migration file'),
        ];
    }
}
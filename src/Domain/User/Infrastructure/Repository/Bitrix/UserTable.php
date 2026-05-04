<?php
declare(strict_types=1);

namespace Domain\User\Infrastructure\Repository\Bitrix;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\FileTable;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\SystemException;
use Domain\User\Infrastructure\Repository\UserRepositoryInterface;

/**
 *
 */
class UserTable extends \Bitrix\Main\UserTable
{
    /**
     * @return string[]
     * @throws ArgumentException
     * @throws SystemException
     */
    public static function getMap(): array
    {
        $map = parent::getMap();
        
        $map[] =
            (new Reference(
                UserRepositoryInterface::AVATAR . '_ref',
                FileTable::class,
                Join::on('this.' . UserRepositoryInterface::AVATAR, 'ref.ID')
            ))->configureJoinType('left');
        
        return $map;
    }
}
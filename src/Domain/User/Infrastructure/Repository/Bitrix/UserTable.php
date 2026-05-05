<?php
declare(strict_types=1);

namespace Domain\User\Infrastructure\Repository\Bitrix;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\FileTable;
use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserGroupTable;
use Domain\User\Infrastructure\Repository\UserRepositoryInterface;

/**
 *
 */
class UserTable extends \Bitrix\Main\UserTable
{
    /** Имя отношения групп пользователя (LEFT JOIN) */
    public const USER_GROUPS_RELATION = 'USER_GROUPS';
    
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
        
        // LEFT JOIN на группы пользователя (не исключает пользователей без групп, в отличие от INNER JOIN 'GROUPS')
        $map[] =
            (new OneToMany(self::USER_GROUPS_RELATION, UserGroupTable::class, 'USER'))
                ->configureJoinType(Join::TYPE_LEFT);
        
        return $map;
    }
}
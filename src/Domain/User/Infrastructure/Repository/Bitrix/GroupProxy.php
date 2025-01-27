<?php
declare(strict_types=1);

namespace Domain\User\Infrastructure\Repository\Bitrix;

use Domain\User\Entity\GroupEntity;
use Domain\User\Infrastructure\Repository\GroupRepositoryInterface;

/**
 *
 */
class GroupProxy
{
    /**
     * Конвертирует сущность в массив для сохранения в БД
     * @param GroupEntity $group
     * @return array
     */
    public function entityToArray(GroupEntity $group): array
    {
        return [
            GroupRepositoryInterface::ID            => $group->getId(),
            GroupRepositoryInterface::ACTIVE        => ( $group->isActive() ? 'Y' : 'N' ),
            GroupRepositoryInterface::NAME          => $group->getName(),
            GroupRepositoryInterface::DESCRIPTION   => $group->getDescription(),
            GroupRepositoryInterface::CODE          => $group->getCode(),
            GroupRepositoryInterface::SORT          => $group->getSort(),
            GroupRepositoryInterface::ANONYMOUS     => ( $group->isAnonymous() ? 'Y' : 'N' ),
            GroupRepositoryInterface::PRIVILEGED    => ( $group->isPrivileged() ? 'Y' : 'N' ),
        ];
    }
    
    
}
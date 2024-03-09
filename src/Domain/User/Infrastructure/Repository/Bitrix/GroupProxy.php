<?php
    declare(strict_types=1);
    
    namespace Domain\User\Infrastructure\Repository\Bitrix;
    
    use Domain\User\Entity\GroupEntity;

    /**
     *
     */
    class GroupProxy
    {
        public const ID = 'id';
        public const NAME = 'name';
        public const CODE = 'string_id';
        public const ACTIVE = 'active';
        public const DESCRIPTION = 'description';
        public const SORT = 'c_sort';
        public const ANONYMOUS = 'anonymous';
        public const PRIVILEGED = 'privileged';
    
        /**
         * Конвертирует сущность в массив для сохранения в БД
         * @param GroupEntity $group
         * @return array
         */
        public function entityToArray(GroupEntity $group): array
        {
            return [
                self::ID    => $group->getId(),
                self::ACTIVE    => ( $group->isActive() ? 'Y' : 'N' ),
                self::NAME      => $group->getName(),
                self::DESCRIPTION   => $group->getDescription(),
                self::CODE          => $group->getCode(),
                self::SORT      => $group->getSort(),
                self::ANONYMOUS => ( $group->isAnonymous() ? 'Y' : 'N' ),
                self::PRIVILEGED    => ( $group->isPrivileged() ? 'Y' : 'N' ),
            ];
        }
        
        
    }
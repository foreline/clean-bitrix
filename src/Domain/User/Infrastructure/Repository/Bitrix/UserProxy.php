<?php
    declare(strict_types=1);
    
    namespace Domain\User\Infrastructure\Repository\Bitrix;
    
    use Bitrix\Main\ArgumentException;
    use Bitrix\Main\ObjectPropertyException;
    use Bitrix\Main\SystemException;
    use Bitrix\Main\UserFieldTable;
    use Bitrix\Main\UserTable;
    use Domain\User\Aggregate\User;
    use Domain\User\Aggregate\UserInterface;
    use Domain\User\UseCase\GroupManager;
    use Domain\User\ValueObject\Role;
    use Exception;
    
    /**
     * Data Transfer Object for User
     */
    class UserProxy
    {
        public const ID = 'id';
        public const LOGIN = 'login';
        public const EMAIL = 'email';
        public const PASSWORD = 'password';
        public const CONFIRM_PASSWORD = 'confirm_password';
        public const NAME = 'name';
        public const LAST_NAME = 'last_name';
        public const ACTIVE = 'active';
        public const PHONE = 'personal_phone';
        public const DEPARTMENT = 'work_department';
        public const POSITION = 'work_position';
        public const GROUPS = 'group_id';
        public const CONFIRM_CODE = 'confirm_code';
        
        /**
         * Конвертирует сущность в массив для сохранения в БД
         * @param UserInterface|User $user
         * @return array
         * @throws Exception
         */
        public function entityToArray(UserInterface|User $user): array
        {
            $data = [
                self::ID        => $user->getId(),
                self::LOGIN     => $user->getLogin(),
                self::NAME      => $user->getFirstName(),
                self::LAST_NAME => $user->getLastName(),
                self::EMAIL         => $user->getEmail(),
                self::PHONE         => $user->getPhone(),
                self::ACTIVE    => ( $user->isActive() ? 'Y' : 'N' ),
                self::DEPARTMENT   => $user->getDepartment(),
                self::POSITION     => $user->getPosition(),
                self::CONFIRM_CODE => $user->getConfirmationCode(),
                
                // @fixme
                //self::GROUPS => $user->getGroups()?->getIds(),
                self::GROUPS  =>
                    $user->getRoles()
                        ?
                        GroupManager::getInstance()->addFilter(self::GROUPS,
                            array_map(
                                fn(Role $role): string => $role->getRole(),
                                $user->getRoles()->getCollection()
                            )
                        )->find()?->getIds()
                        :
                        null,
            
            ];
            
            if ( !empty($user->getPassword()) ) {
                $data[self::PASSWORD] = $user->getPassword();
            }
            
            if ( !empty($user->getConfirmPassword()) ) {
                $data[self::CONFIRM_PASSWORD] = $user->getConfirmPassword();
            }
            
            return $data;
        }
        
        /**
         * @param mixed $obj
         * @return UserInterface
         */
        public function objectToEntity(mixed $obj): UserInterface
        {
            $user = new User();
            
            if ( null !== $obj->get(self::ID) ) {
                $user->setId((int)$obj->get(self::ID));
            }
            
            if ( null !== $obj->get(self::LOGIN) ) {
                $user->setLogin((string)$obj->get(self::LOGIN));
            }
            
            if ( null !== $obj->get(self::EMAIL) ) {
                $user->setEmail((string)$obj->get(self::EMAIL));
            }
            
            if ( null !== $obj->get(self::NAME) ) {
                $user->setFirstName((string)$obj->get(self::NAME));
            }
            
            if ( null !== $obj->get(self::LAST_NAME) ) {
                $user->setLastName((string)$obj->get(self::LAST_NAME));
            }
            
            if ( null !== $obj->get(self::ACTIVE) ) {
                $user->setActive((bool)$obj->get(self::ACTIVE));
            }
            
            if ( null !== $obj->get(self::PHONE) ) {
                $user->setPhone((string)$obj->get(self::PHONE));
            }
            
            if ( null !== $obj->get(self::DEPARTMENT) ) {
                $user->setDepartment((string)$obj->get(self::DEPARTMENT));
            }
            
            if ( null !== $obj->get(self::POSITION) ) {
                $user->setPosition((string)$obj->get(self::POSITION));
            }
            
            if ( null !== $obj->get(self::CONFIRM_CODE) ) {
                $user->setConfirmationCode((string)$obj->get(self::CONFIRM_CODE));
            }
            
            /*if ( null !== $obj->get(self::GROUPS) ) {
                $ormGroups = $obj->get(self::GROUPS)?->get(self::GROUPS);
                // @todo
            }*/
            
            // @fixme
            if ( $groupsId = UserTable::getUserGroupIds($user->getId()) ) {
                $groups = GroupManager::getInstance()
                    ->filterById($groupsId)
                    ->find();
                
                foreach ( $groups as $group ) {
                    $user->addGroup($group);
                    $user->addRole(new Role($group->getCode()));
                }
            }
            
            $this->getUserFields($user, $obj);
            
            return $user;
        }
    
        /**
         * @param User $user
         * @param mixed $obj
         * @return $this
         */
        private function getUserFields(User $user, mixed $obj): self
        {
            
            $resFields = UserFieldTable::getList(
                [
                    'filter' => [
                        'ENTITY_ID'                     =>   'USER',
                    ]
                ]
            );
            
            while ( $field = $resFields->fetch() ) {
                $fieldName = $field['FIELD_NAME'];
                if ( null !== $obj->get($fieldName) ) {
                    $userFieldName = str_replace('UF_', '', $fieldName);
                    $user->addUserField($userFieldName, $obj->get($fieldName));
                }
            }
            
            return $this;
        }
    }
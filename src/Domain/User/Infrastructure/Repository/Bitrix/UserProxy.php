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
    use Domain\User\Infrastructure\Repository\UserRepositoryInterface;
    use Domain\User\UseCase\GroupManager;
    use Domain\User\ValueObject\Role;
    use Exception;
    
    /**
     * Data Transfer Object for User
     */
    class UserProxy
    {
        /*public const ID = 'id';
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
        public const CONFIRM_CODE = 'confirm_code';*/
        
        /**
         * Конвертирует сущность в массив для сохранения в БД
         * @param UserInterface|User $user
         * @return array
         * @throws Exception
         */
        public function entityToArray(UserInterface|User $user): array
        {
            $data = [
                UserRepositoryInterface::ID        => $user->getId(),
                UserRepositoryInterface::LOGIN     => $user->getLogin(),
                UserRepositoryInterface::NAME      => $user->getFirstName(),
                UserRepositoryInterface::LAST_NAME => $user->getLastName(),
                UserRepositoryInterface::EMAIL     => $user->getEmail(),
                UserRepositoryInterface::PHONE     => $user->getPhone(),
                UserRepositoryInterface::ACTIVE    => ( $user->isActive() ? 'Y' : 'N' ),
                UserRepositoryInterface::DEPARTMENT   => $user->getDepartment(),
                UserRepositoryInterface::POSITION     => $user->getPosition(),
                UserRepositoryInterface::CONFIRM_CODE => $user->getConfirmationCode(),
                
                // @fixme
                //UserRepositoryInterface::GROUPS => $user->getGroups()?->getIds(),
                UserRepositoryInterface::GROUPS  =>
                    $user->getRoles()
                        ?
                        GroupManager::getInstance()->addFilter(UserRepositoryInterface::GROUPS,
                            array_map(
                                fn(Role $role): string => $role->getRole(),
                                $user->getRoles()->getCollection()
                            )
                        )->find()?->getIds()
                        :
                        null,
            
            ];
            
            if ( !empty($user->getPassword()) ) {
                $data[UserRepositoryInterface::PASSWORD] = $user->getPassword();
            }
            
            if ( !empty($user->getConfirmPassword()) ) {
                $data[UserRepositoryInterface::CONFIRM_PASSWORD] = $user->getConfirmPassword();
            }
            
            return $data;
        }
    
        /**
         * @param mixed $obj
         * @return UserInterface
         * @throws ArgumentException
         * @throws ObjectPropertyException
         * @throws SystemException
         */
        public function objectToEntity(mixed $obj): UserInterface
        {
            $user = new User();
            
            if ( null !== $obj->get(UserRepositoryInterface::ID) ) {
                $user->setId((int)$obj->get(UserRepositoryInterface::ID));
            }
            
            if ( null !== $obj->get(UserRepositoryInterface::LOGIN) ) {
                $user->setLogin((string)$obj->get(UserRepositoryInterface::LOGIN));
            }
            
            if ( null !== $obj->get(UserRepositoryInterface::EMAIL) ) {
                $user->setEmail((string)$obj->get(UserRepositoryInterface::EMAIL));
            }
            
            if ( null !== $obj->get(UserRepositoryInterface::NAME) ) {
                $user->setFirstName((string)$obj->get(UserRepositoryInterface::NAME));
            }
            
            if ( null !== $obj->get(UserRepositoryInterface::LAST_NAME) ) {
                $user->setLastName((string)$obj->get(UserRepositoryInterface::LAST_NAME));
            }
            
            if ( null !== $obj->get(UserRepositoryInterface::ACTIVE) ) {
                $user->setActive((bool)$obj->get(UserRepositoryInterface::ACTIVE));
            }
            
            if ( null !== $obj->get(UserRepositoryInterface::PHONE) ) {
                $user->setPhone((string)$obj->get(UserRepositoryInterface::PHONE));
            }
            
            if ( null !== $obj->get(UserRepositoryInterface::DEPARTMENT) ) {
                $user->setDepartment((string)$obj->get(UserRepositoryInterface::DEPARTMENT));
            }
            
            if ( null !== $obj->get(UserRepositoryInterface::POSITION) ) {
                $user->setPosition((string)$obj->get(UserRepositoryInterface::POSITION));
            }
            
            if ( null !== $obj->get(UserRepositoryInterface::CONFIRM_CODE) ) {
                $user->setConfirmationCode((string)$obj->get(UserRepositoryInterface::CONFIRM_CODE));
            }
            
            /*if ( null !== $obj->get(UserRepositoryInterface::GROUPS) ) {
                $ormGroups = $obj->get(UserRepositoryInterface::GROUPS)?->get(UserRepositoryInterface::GROUPS);
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
         * @throws ArgumentException|ObjectPropertyException|SystemException
         */
        private function getUserFields(User $user, mixed $obj): self
        {
            $resFields = UserFieldTable::getList(
                [
                    'filter' => [
                        'ENTITY_ID' => 'USER',
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
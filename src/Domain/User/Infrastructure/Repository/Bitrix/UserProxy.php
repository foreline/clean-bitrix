<?php
declare(strict_types=1);

namespace Domain\User\Infrastructure\Repository\Bitrix;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserFieldTable;
use Bitrix\Main\UserTable;
use Domain\File\UseCase\GetFile;
use Domain\User\Aggregate\User;
use Domain\User\Aggregate\UserInterface;
use Domain\User\Infrastructure\Repository\UserRepositoryInterface;
use Domain\User\UseCase\GroupManager;
use Domain\User\ValueObject\Role;
use Exception;
use Infrastructure\Bitrix\Repository\ORM\Repository;

/**
 * Data Transfer Object for User
 */
class UserProxy extends Repository
{
    private static ?Role $role = null;
    
    /**
     *
     */
    public function __construct()
    {
        if ( null === self::$role ) {
            self::$role = ( new Role() );
        }
    }
    
    /**
     * @param Role $role
     * @return void
     */
    public static function registerRoleObject(Role $role): void
    {
        self::$role = $role;
    }
    
    /**
     * Конвертирует сущность в массив для сохранения в БД
     * @param UserInterface|User $user
     * @return array
     * @throws Exception
     */
    public function entityToArray(UserInterface|User $user): array
    {
        $data = [
            UserRepositoryInterface::ID         => $user->getId(),
            UserRepositoryInterface::LOGIN      => $user->getLogin(),
            UserRepositoryInterface::NAME       => $user->getFirstName(),
            UserRepositoryInterface::LAST_NAME  => $user->getLastName(),
            UserRepositoryInterface::SECOND_NAME  => $user->getSecondName(),
            UserRepositoryInterface::EMAIL      => $user->getEmail(),
            UserRepositoryInterface::PHONE      => $user->getPhone(),
            UserRepositoryInterface::ACTIVE     => ( $user->isActive() ? 'Y' : 'N' ),
            UserRepositoryInterface::DEPARTMENT     => $user->getDepartment(),
            UserRepositoryInterface::POSITION       => $user->getPosition(),
            UserRepositoryInterface::CONFIRM_CODE   => $user->getConfirmationCode(),
            UserRepositoryInterface::EXT_ID         => $user->getExtId(),
            UserRepositoryInterface::AVATAR         => $user->getAvatar()?->getId(),
            
            // @fixme
            //UserRepositoryInterface::GROUPS => $user->getGroups()?->getIds(),
            UserRepositoryInterface::GROUPS  =>
                $user->getRoles()
                    ?
                    (new GroupManager())->filterByCode(
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
        
        if ( null !== $id = $obj->get(UserRepositoryInterface::ID) ) {
            $user->setId((int)$id);
        }
        
        if ( null !== $login = $obj->get(UserRepositoryInterface::LOGIN) ) {
            $user->setLogin((string)$login);
        }
        
        if ( null !== $email = $obj->get(UserRepositoryInterface::EMAIL) ) {
            $user->setEmail((string)$email);
        }
        
        if ( null !== $name = $obj->get(UserRepositoryInterface::NAME) ) {
            $user->setFirstName((string)$name);
        }
        
        if ( null !== $lastName = $obj->get(UserRepositoryInterface::LAST_NAME) ) {
            $user->setLastName((string)$lastName);
        }
    
        if ( null !== $secondName = $obj->get(UserRepositoryInterface::SECOND_NAME) ) {
            $user->setSecondName((string)$secondName);
        }
        
        if ( null !== $active = $obj->get(UserRepositoryInterface::ACTIVE) ) {
            $user->setActive((bool)$active);
        }
        
        if ( null !== $phone = $obj->get(UserRepositoryInterface::PHONE) ) {
            $user->setPhone((string)$phone);
        }
        
        if ( null !== $department = $obj->get(UserRepositoryInterface::DEPARTMENT) ) {
            $user->setDepartment((string)$department);
        }
        
        if ( null !== $position = $obj->get(UserRepositoryInterface::POSITION) ) {
            $user->setPosition((string)$position);
        }
        
        if ( null !== $confirmCode = $obj->get(UserRepositoryInterface::CONFIRM_CODE) ) {
            $user->setConfirmationCode((string)$confirmCode);
        }
        
        /*if ( null !== $obj->get(UserRepositoryInterface::GROUPS) ) {
            $ormGroups = $obj->get(UserRepositoryInterface::GROUPS)?->get(UserRepositoryInterface::GROUPS);
            // @todo
        }*/
        
        // @fixme
        if ( $groupsId = UserTable::getUserGroupIds($user->getId()) ) {
            $groups = (new GroupManager())
                ->filterById($groupsId)
                ->find();
            
            foreach ( $groups as $group ) {
                $user->addGroup($group);
                //$user->addRole(new Role($group->getCode()));
                $role = new self::$role($group->getCode());
                $user->addRole($role);
            }
        }
    
        if ( null !== ($extId = $obj->get(UserRepositoryInterface::EXT_ID)) ) {
            $user->setExtId($extId);
        }
    
        if ( null !== ($avatarId = $obj->get(UserRepositoryInterface::AVATAR)) ) {
            if ( $avatar = (new GetFile())->get($avatarId) ) {
                $user->setAvatar($avatar);
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
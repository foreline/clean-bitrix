<?php
declare(strict_types=1);

namespace Domain\User\Infrastructure\Repository\Bitrix;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserFieldTable;
use CFile;
use Domain\Exception\NotAuthorizedException;
use Domain\File\Infrastructure\Repository\Bitrix\FileProxy;
use Domain\File\UseCase\GetFile;
use Domain\User\Aggregate\Group;
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
            //UserRepositoryInterface::AVATAR         => $user->getAvatar()?->getId(),
            
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
        
        if ( $user->getAvatar() ) {
            $data[UserRepositoryInterface::AVATAR] = CFile::MakeFileArray($user->getAvatar()->getSource());
        }
        
        return $data;
    }
    
    /**
     * @param mixed $obj
     * @return UserInterface
     * @throws NotAuthorizedException
     * @throws Exception
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
        
        // Option C: ORM JOIN for Group Codes.
        // When find() uses fetchCollection(), $obj->get(USER_GROUPS_RELATION) returns a
        // fully-populated OneToMany Collection (empty = user has no groups, non-null).
        // When fetchObject() is called directly (e.g. getCurrentUser), the field is still
        // in the SELECT but may only carry one group row — objectToEntity() is safe because
        // find() is the canonical multi-entity path and always uses fetchCollection().
        // Fallback to getUserGroupIds() when USER_GROUPS was not included in the query
        // (e.g. standard \Bitrix\Main\UserTable used without the local extension).
        $ormGroups = $obj->entity->hasField(UserTable::USER_GROUPS_RELATION)
            ? $obj->get(UserTable::USER_GROUPS_RELATION)
            : null;
        
        if ( null !== $ormGroups ) {
            // ORM collection path: zero extra DB queries
            foreach ( $ormGroups as $ormUserGroup ) {
                $ormGroup = $ormUserGroup->get('GROUP');
                if ( null === $ormGroup ) {
                    continue;
                }
                // BooleanField with configureValues('N','Y') returns PHP bool via fetchCollection()
                if ( !$ormGroup->get('ACTIVE') ) {
                    continue;
                }
                $code = (string)$ormGroup->get('STRING_ID');
                if ( '' === $code ) {
                    continue;
                }
                $group = new Group();
                $group->setId((int)$ormUserGroup->get('GROUP_ID'));
                $group->setCode($code);
                $group->setActive(true);
                $user->addGroup($group);
                $user->addRole(new self::$role($code));
            }
        } elseif ( $groupsId = UserTable::getUserGroupIds($user->getId()) ) {
            // Fallback: USER_GROUPS not in schema/query — use legacy two-query approach
            $groups = (new GroupManager())
                ->filterById($groupsId)
                ->find();
            foreach ( $groups as $group ) {
                $user->addGroup($group);
                $user->addRole(new self::$role($group->getCode()));
            }
        }
        
        if ( null !== ($extId = $obj->get(UserRepositoryInterface::EXT_ID)) ) {
            $user->setExtId($extId);
        }
        
        if ( $obj->entity->hasField(UserRepositoryInterface::AVATAR . '_ref') ) {
            // Custom \Domain\User\Infrastructure\Repository\Bitrix\UserTable: avatar loaded via JOIN — do not make a separate DB query
            if ( null !== ( $avatarObj = $obj->get(UserRepositoryInterface::AVATAR . '_ref') ) ) {
                $user->setAvatar((new FileProxy())->objectToEntity($avatarObj));
            }
        } elseif ( null !== ($avatarId = $obj->get(UserRepositoryInterface::AVATAR)) ) {
            // Standard \Bitrix\Main\UserTable (via foreign reference): _ref field unavailable, fall back to separate query
            if ( $avatar = (new GetFile())->get($avatarId) ) {
                $user->setAvatar($avatar);
            }
        }
        
        //$this->getUserFields($user, $obj);
        
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
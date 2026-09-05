<?php
declare(strict_types=1);

namespace Domain\User\Infrastructure\Repository\Bitrix;

use Bitrix\Main\DB\Result;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\UserGroupTable;
use Domain\User\Infrastructure\Repository\Bitrix\UserTable;
use CUser;
use Domain\Repository\FieldsInterface;
use Domain\Repository\FilterInterface;
use Domain\Repository\GroupInterface;
use Domain\Repository\LimitInterface;
use Domain\Repository\SortInterface;
use Domain\Service\ServiceInterface;
use Domain\User\Aggregate\User;
use Domain\User\Aggregate\UserInterface;
use Domain\User\Aggregate\UserCollection;
use Domain\User\Aggregate\UsersInterface;
use Domain\User\Infrastructure\Repository\UserFields;
use Domain\User\Infrastructure\Repository\UserFilter;
use Domain\User\Infrastructure\Repository\UserGroup;
use Domain\User\Infrastructure\Repository\UserLimit;
use Domain\User\Infrastructure\Repository\UserRepositoryInterface;
use Domain\User\Infrastructure\Repository\UserSort;
use Exception;
use JetBrains\PhpStorm\Deprecated;
use ReflectionClass;
use RuntimeException;

/**
 * Репозиторий для работы с пользователями Bitrix D7
 */
class UserRepository extends UserProxy implements UserRepositoryInterface
{
    /** @var array<int, User> */
    private static array $cache = [];
    
    protected ?Result $result = null;
    
    public FilterInterface|UserFilter|null $filter;
    public FieldsInterface|UserFields|null $fields;
    public LimitInterface|UserLimit|null $limit;
    public SortInterface|UserSort|null $sort;
    public GroupInterface|UserGroup|null $group;
    
    /**
     *
     */
    public function __construct(?ServiceInterface $service = null)
    {
        $this->sort     = new UserSort($service);
        $this->filter   = new UserFilter($service);
        $this->limit    = new UserLimit($service);
        $this->fields   = new UserFields($service);
        $this->group    = new UserGroup($service);
        
        parent::__construct();
    }
    
    /**
     * Returns class constants
     * @return array<string, string>
     */
    public static function getFields(): array
    {
        $reflection = new ReflectionClass(__CLASS__);
        return $reflection->getConstants();
    }
    
    /**
     * @param array $filter
     * @param array $sort
     * @param array $limit
     * @param array $fields
     * @return ?self
     * @throws Exception
     * @noinspection PhpTooManyParametersInspection
     */
    public function query(#[Deprecated]array $filter = [], #[Deprecated]array $sort = [], #[Deprecated]array $limit = [], #[Deprecated]array $fields = []): ?self
    {
        // @fixme
        $fields = ( 0 < count($fields) ? $fields : ['*', 'UF*'] );
        
        $params = [
            // @fixme
            //'select' => ( 0 < count($fields) ? $fields : ['*', 'UF_*'] ),
            'select' => ( 0 < count($fields) ? $fields : ['*', 'UF*'] ),
            'cache' => [
                'ttl'   => 60, // @fixme
                'cache_joins'   => true,
            ],
            //'count_total'   => true,
        ];
        
        if ( 0 < count($filter) ) {
            $filter = array_change_key_case($filter, CASE_UPPER);
            
            if ( array_key_exists('GROUP_ID', $filter) ) {
                // Pre-resolve user IDs belonging to the requested group.
                // Using a JOIN filter (UserGroupTable:USER.GROUP_ID) shares the same
                // b_user_group join alias with our USER_GROUPS OneToMany relation, causing
                // the WHERE condition to restrict the relation collection to only the
                // filtered group — all other groups for the user are silently dropped.
                // A separate lookup + =ID IN (...) avoids this conflict entirely.
                $rows = UserGroupTable::getList([
                    'select' => ['USER_ID'],
                    'filter' => ['=GROUP_ID' => (int)$filter['GROUP_ID']],
                ])->fetchAll();
                
                $filter['=ID'] = !empty($rows)
                    ? array_column($rows, 'USER_ID')
                    : [-1]; // no users in this group → guaranteed empty result
                
                unset($filter['GROUP_ID']);
            }
        }
        
        /*if ( 0 < count($sort) ) {
            $sort = array_change_key_case($sort, CASE_UPPER);
            $params['order'] = $sort;
            
            if ( array_key_exists('RAND', $sort) ) {
                // @fixme переделать на registerRuntimeField
                //$query->registerRuntimeField('RAND', ['reference' => 'RAND()']);
                $params['cache']['ttl'] = 0; // Иначе ORM кеширует запрос
                $params['runtime'] = [
                    new ExpressionField('RAND', 'RAND()')
                ];
            }
        }*/
        
        /*if ( 0 < (int)$limit['page_size'] ) {
            $params['limit'] = (int)$limit['page_size'];
            
            if ( 0 < (int)$limit['page_num'] ) {
                $params['offset'] = (int)$limit['page_size'] * ( (int)$limit['page_num'] - 1 );
            }
        }*/
        
        // LIMITS
        
        /*$lim = (int) ( $limit['page_size'] ?: $limit['limit']);
        
        if ( 0 < $lim ) {
            $params['limit'] = $lim;
            
            if ( 0 < (int)$limit['page_num'] ) {
                $params['offset'] = (int)$limit['limit'] * ( (int)$limit['page_num'] - 1 );
            } elseif ( 0 < (int)$limit['offset'] ) {
                $params['offset'] = (int)$limit['offset'];
            }
        }*/
        
        // @fixme выбирать группы в основном запросе
        /*$params['runtime'] = [
            'GROUP_ID' => [
                'data_type' => '\Bitrix\Main\UserGroupTable',
                'reference' => [
                    '=this.ID' => 'ref.USER_ID',
                ],
                'join_type' => 'left',
            ]
        ];*/
        
        $params = $this->getParams($filter, $sort, $limit, $fields);
        
        // Inject USER_GROUPS fields for Option C: ORM JOIN for Group Codes.
        // These are added after getParams() because prepareSelectFields() would strip unknown relation names.
        // fetchCollection() in find() will correctly accumulate the OneToMany collection per user entity.
        $params['select'] = array_merge($params['select'] ?? ['*'], [
            UserTable::USER_GROUPS_RELATION . '.GROUP_ID',
            UserTable::USER_GROUPS_RELATION . '.GROUP.STRING_ID',
            UserTable::USER_GROUPS_RELATION . '.GROUP.ACTIVE',
        ]);
        
        // Bitrix b_user table columns are uppercase
        if (
            array_key_exists('order', $params)
            && is_array($params['order'])
        ) {
            $params['order'] = array_change_key_case($params['order'], CASE_UPPER);
        }
        
        if ( !$this->result = UserTable::getList($params) ) {
            return null;
        }
        
        return $this;
    }
    
    /**
     * @param int|null $id
     * @return ?UserInterface
     * @throws Exception
     */
    public function findById(int $id = null): ?UserInterface
    {
        if ( 0 >= $id ) {
            return null;
        }
        
        /*if ( 0 >= $id ) {
            throw new InvalidArgumentException('Не задан ID пользователя');
        }*/
        
        $this->filter->filterById($id);
        
        return self::$cache[$id] ?: $this->find()?->current();
    }
    
    /**
     * @return ?UsersInterface
     * @throws Exception
     */
    public function find(): ?UsersInterface
    {
        if ( !$this->query(
            $this->filter->get(),
            $this->sort->get(),
            $this->limit->getLimits(),
            $this->fields->get()
        ) ) {
            return null;
        }
        
        if ( null === $this->result ) {
            return null;
        }
        
        // Use fetchCollection() instead of a fetchObject() loop so that Bitrix ORM
        // correctly merges multiple SQL rows (one per group) into a single entity
        // with a fully populated USER_GROUPS OneToMany collection.
        $ormCollection = $this->result->fetchCollection();
        
        $users = new UserCollection();
        
        foreach ( $ormCollection as $ormObject ) {
            $user = $this->objectToEntity($ormObject);
            self::$cache[$user->getId()] = $user;
            $users->addItem($user);
        }
        
        return $users;
    }
    
    /**
     * @return UserInterface|null
     * @throws Exception
     */
    public function fetchObject(): ?UserInterface
    {
        if ( !$this->result ) {
            return null;
        }
        
        if ( !$ormObject = $this->result->fetchObject() ) {
            return null;
        }
        
        return $this->objectToEntity($ormObject);
    }
    
    /**
     * @param UserInterface $user
     * @return UserInterface
     * @throws Exception
     */
    public function persist(UserInterface $user): UserInterface
    {
        if ( 0 < $user->getId() ) {
            $this->update($user);
        } else {
            $userId = $this->create($user);
            $user->setId($userId);
        }
        
        unset(self::$cache[$user->getId()]);
        
        return $this->findById($user->getId());
    }
    
    /**
     * @param UserInterface $user
     * @return int
     * @throws Exception
     */
    private function create(UserInterface $user): int
    {
        global $USER;
        
        if ( !$USER ) {
            $USER = new CUser();
        }
        
        $userData = $this->entityToArray($user);
        
        $userData = array_change_key_case($userData, CASE_UPPER);
        
        if ( !$userId = $USER->Add($userData) ) {
            throw new RuntimeException($USER->LAST_ERROR);
        }
        
        $user->setId($userId);
        
        $this->updateGroups($user);
        
        return $userId;
    }
    
    /**
     * @param User|UserInterface $user
     * @return void
     * @throws Exception
     */
    private function updateGroups(User|UserInterface $user): void
    {
        if ( !$groups = $user->getGroups() ) {
            return;
        }
        
        // Удаляем из групп
        foreach ( UserGroupTable::getList(['filter' => ['USER_ID' => $user->getId()]])->fetchAll() as $currentUserGroups ) {
            $groupId = (int) $currentUserGroups['GROUP_ID'];
            if ( in_array($groupId, $user->getGroups()->getIds()) ) {
                continue;
            }
            $deleteResult = UserGroupTable::delete(['USER_ID' => $user->getId(), 'GROUP_ID' => $groupId]);
            if ( !$deleteResult->isSuccess() ) {
                throw new Exception('Ошибка при удалении пользователя ' . $user->getId() . ' из группы ' . $groupId);
            }
        }
        
        // Добавляем в группы
        foreach ( $groups as $group ) {
            
            $data = [
                'USER_ID'   => $user->getId(),
                'GROUP_ID'  => $group->getId(),
            ];
            
            if ( UserGroupTable::getList(['filter' => $data])->fetch() ) {
                continue;
            }
            
            $result = UserGroupTable::add($data);
            
            if ( !$result->isSuccess() ) {
                throw new Exception($result->getErrorMessages()[0]);
            }
        }
    }
    
    /**
     * @param UserInterface $user
     * @return bool
     * @throws Exception
     */
    private function update(UserInterface $user): bool
    {
        global $USER;
        
        if ( !$USER ) {
            $USER = new CUser();
        }
        
        $userData = $this->entityToArray($user);
        
        $userData = array_change_key_case($userData, CASE_UPPER);
        
        $avatarKey = strtoupper(UserRepositoryInterface::AVATAR);
        
        if ( is_array($userData[$avatarKey] ?? null) ) {
            $currentPhotoId = (int)( UserTable::getList([
                'select' => [$avatarKey],
                'filter' => ['=ID' => $user->getId()],
            ])->fetch()[$avatarKey] ?? 0 );
            
            if ( 0 < $currentPhotoId && $currentPhotoId === $user->getAvatar()?->getId() ) {
                // Аватар не менялся — не пересохраняем PERSONAL_PHOTO, чтобы не плодить копии файла
                unset($userData[$avatarKey]);
            } elseif ( 0 < $currentPhotoId ) {
                // При замене аватара CFile::SaveFile удалит прежний файл PERSONAL_PHOTO
                $userData[$avatarKey]['del'] = 'Y';
                $userData[$avatarKey]['old_file'] = $currentPhotoId;
            }
        }
        
        if ( !$result = $USER->Update($user->getId(), $userData) ) {
            throw new RuntimeException($USER->LAST_ERROR);
        }
        
        $this->updateGroups($user);
        
        UserTable::cleanCache();
        UserProxy::clearUserGroupsCache($user->getId());
        
        return true;
    }
    
    /**
     * @param int $id
     * @return bool
     * @throws Exception
     */
    public function delete(int $id): bool
    {
        if ( !$result = CUser::Delete($id) ) {
            return false;
        }
        
        unset(self::$cache[$id]);
        
        UserTable::cleanCache();
        UserProxy::clearUserGroupsCache($id);
        
        return true;
        
        // @fixme ждем имплементации метода через ORM
        /*$result = UserTable::delete($id);
        
        if ( $result->isSuccess() ) {
            UserTable::getEntity()->cleanCache();
            return true;
        }
        
        return false;*/
    }
    
    /**
     * @param array $userFields
     * @return UserInterface|null
     * @throws Exception
     */
    public function getCurrentUser(array $userFields = []): ?UserInterface
    {
        $currentUserId = (int)CurrentUser::get()->getId();
        return $this->findById($currentUserId/*, $userFields*/);
    }
    
    /**
     * @return bool
     */
    public function isAdmin(): bool
    {
        return CurrentUser::get()->isAdmin();
    }
    
    /**
     * @return bool
     */
    public function isAuthorized(): bool
    {
        // @fixme переделать проверку
        return 0 < CurrentUser::get()->getId();
    }
    
    /**
     * @return int
     * @throws ObjectPropertyException
     */
    public function getCount(): int
    {
        return $this->result->getCount();
    }
    
    /**
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->result->getSelectedRowsCount();
    }
}
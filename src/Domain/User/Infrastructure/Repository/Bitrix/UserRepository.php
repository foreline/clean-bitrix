<?php
    declare(strict_types=1);
    
    namespace Domain\User\Infrastructure\Repository\Bitrix;
    
    use Bitrix\Main\DB\Result;
    use Bitrix\Main\Engine\CurrentUser;
    use Bitrix\Main\Entity\ExpressionField;
    use Bitrix\Main\ObjectPropertyException;
    use Bitrix\Main\UserTable;
    use CUser;
    use Domain\User\Aggregate\User;
    use Domain\User\Aggregate\UserInterface;
    use Domain\User\Aggregate\UserCollection;
    use Domain\User\Aggregate\UsersInterface;
    use Domain\User\Infrastructure\Repository\UserRepositoryInterface;
    use Exception;
    use RuntimeException;
    
    /**
     * Репозиторий для работы с пользователями Bitrix D7
     */
    class UserRepository extends UserProxy implements UserRepositoryInterface
    {
        /** @var array<int, User> */
        private static array $cache = [];
        
        protected ?Result $result = null;
        
        /**
         *
         */
        public function __construct()
        {
        
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
        public function query(array $filter = [], array $sort = [], array $limit = [], array $fields = []): ?self
        {
            $params = [
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
                    /*$params['runtime'][] = new ReferenceField('GROUP', GroupTable::class,
                        Join::on('this.GROUP_ID', 'ref.ID')
                    );*/
                    
                    /*$params['runtime'][] = new ReferenceField('GROUP_ID', UserGroupTable::class,
                        //\Bitrix\Main\Entity\Query\Join::on('this.ID', 'ref.USER_ID')
                        Join::on('this.ID', 'ref.USER_ID')
                        ->where('ref.GROUP_ID', 'this.GROUP')
                    );*/
                    
                    $filter['Bitrix\Main\UserGroupTable:USER.GROUP_ID'] = (int)$filter['GROUP_ID'];
                    
                    $params['data_doubling'] = false;
                    
                    unset($filter['GROUP_ID']);
                }
                
                $params['filter'] = $filter;
            }
            
            if ( 0 < count($sort) ) {
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
            }
            
            /*if ( 0 < (int)$limit['page_size'] ) {
                $params['limit'] = (int)$limit['page_size'];
                
                if ( 0 < (int)$limit['page_num'] ) {
                    $params['offset'] = (int)$limit['page_size'] * ( (int)$limit['page_num'] - 1 );
                }
            }*/
            
            // LIMITS
            
            $lim = (int) ( $limit['page_size'] ?: $limit['limit']);
            
            if ( 0 < $lim ) {
                $params['limit'] = $lim;
                
                if ( 0 < (int)$limit['page_num'] ) {
                    $params['offset'] = (int)$limit['limit'] * ( (int)$limit['page_num'] - 1 );
                } elseif ( 0 < (int)$limit['offset'] ) {
                    $params['offset'] = (int)$limit['offset'];
                }
            }
            
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
            
            if ( !$this->result = UserTable::getList($params) ) {
                return null;
            }
            
            return $this;
        }
        
        /**
         * @param int|null $id
         * @param array $fields
         * @return ?UserInterface
         * @throws Exception
         */
        public function findById(int $id = null, array $fields = []): ?UserInterface
        {
            if ( 0 >= $id ) {
                return null;
            }
            
            /*if ( 0 >= $id ) {
                throw new InvalidArgumentException('Не задан ID пользователя');
            }*/
            
            return self::$cache[$id] ?: $this->find(filter: ['=id' => $id], fields: $fields)?->current();
        }
        
        /**
         * @param array $filter
         * @param array $sort
         * @param array $limit
         * @param array $fields
         * @return ?UsersInterface
         * @throws Exception
         * @noinspection PhpTooManyParametersInspection
         */
        public function find(array $filter = [], array $sort = [], array $limit = [], array $fields = []): ?UsersInterface
        {
            if ( !$this->query($filter, $sort, $limit, $fields) ) {
                return null;
            }
            
            if ( null === $this->result ) {
                return null;
            }
            
            $users = new UserCollection();
            
            while ( $user = $this->fetchObject() ) {
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
            $roles = $user->getRoles();
            
            // @fixme реализовать метод
        }
        
        /**
         * @param UserInterface $user
         * @return bool
         * @throws Exception
         */
        private function update(UserInterface $user): bool
        {
            global $USER;
            
            $userData = $this->entityToArray($user);
            
            $userData = array_change_key_case($userData, CASE_UPPER);
            
            if ( !$result = $USER->Update($user->getId(), $userData) ) {
                throw new RuntimeException($USER->LAST_ERROR);
            }
            
            $this->updateGroups($user);
            
            UserTable::cleanCache();
            
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
            return $this->findById($currentUserId, $userFields);
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
<?php
    declare(strict_types=1);
    
    namespace Infrastructure\Bitrix;
    
    use Bitrix\Iblock\Iblock;
    use Bitrix\Iblock\IblockSiteTable;
    use Bitrix\Iblock\IblockTable;
    use Bitrix\Iblock\ORM\Fields\PropertyOneToMany;
    use Bitrix\Iblock\PropertyTable;
    use Bitrix\Iblock\SectionTable;
    use Bitrix\Iblock\TypeTable;
    use Bitrix\Main\Application;
    use Bitrix\Main\ArgumentException;
    use Bitrix\Main\DB\Result;
    use Bitrix\Main\DB\SqlQueryException;
    use Bitrix\Main\Engine\CurrentUser;
    use Bitrix\Main\Entity\ExpressionField;
    use Bitrix\Main\Event;
    use Bitrix\Main\Loader;
    use Bitrix\Main\ObjectPropertyException;
    use Bitrix\Main\ORM\Objectify\EntityObject;
    use Bitrix\Main\SystemException;
    use Bitrix\Main\Type\DateTime;
    use CIBlockElement;
    use CIBlockPropertyEnum;
    use Domain\Aggregate\AggregateInterface;
    use Exception;
    use InvalidArgumentException;
    use RuntimeException;
    
    /**
     * Класс для работы с инфоблоками 2.0 Битрикс на D7 ORM.
     * <pre>
     * EntityRepository extends (EntityDTO extends Bxd7 implements Bxd7ProxyInterface) implements EntityRepositoryInterface
     * </pre>
     * * Имитирует стандартные события инфоблоков
     * * Работает только с инфоблоками 2.0
     * * Необходимо задать API-код для инфоблока
     * * Не рекомендуется использовать обязательные свойства
     */
    abstract class Bxd7
    {
        // Типы полей и свойств элемента. Используются для проверки фильтров и сортировок
        private const FIELD_TYPE_INT = 'int';
        private const FIELD_TYPE_STRING = 'string';
        private const FIELD_TYPE_DATETIME = 'datetime';
        private const FIELD_TYPE_DATE = 'date';
        private const FIELD_TYPE_USER = 'user';
        private const FIELD_TYPE_FILE = 'file';
        private const FIELD_TYPE_ELEMENT = 'element';
        private const FIELD_TYPE_SECTION = 'section';
        private const FIELD_TYPE_ENUM = 'enum';
        private const FIELD_TYPE_BOOL = 'bool';
        
        /** @var array|\string[][] Типы полей инфоблока */
        private array $fields = [
            'ID' => [
                'type' => self::FIELD_TYPE_INT,
                'code' => 'ID',
            ],
            'NAME' => [
                'type'  => self::FIELD_TYPE_STRING,
                'code'  => 'NAME',
            ],
            'SORT' => [
                'type'  => self::FIELD_TYPE_INT,
                'code'  => 'SORT',
            ],
            'ACTIVE' => [
                'type'  => self::FIELD_TYPE_BOOL,
                'code'  => 'ACTIVE',
            ],
            'CODE' => [
                'type'  => self::FIELD_TYPE_STRING,
                'code'  => 'CODE',
            ],
            'PREVIEW_TEXT' => [
                'type'  => self::FIELD_TYPE_STRING,
                'code'  => 'PREVIEW_TEXT',
            ],
            'PREVIEW_PICTURE' => [
                'type'  => self::FIELD_TYPE_FILE,
                'code'  => 'PREVIEW_PICTURE',
            ],
            'DETAIL_TEXT' => [
                'type'  => self::FIELD_TYPE_STRING,
                'code'  => 'DETAIL_TEXT',
            ],
            'DETAIL_PICTURE' => [
                'type'  => self::FIELD_TYPE_FILE,
                'code'  => 'DETAIL_PICTURE',
            ],
            'DATE_CREATE' => [
                'type'  => self::FIELD_TYPE_DATETIME,
                'code'  => 'DATE_CREATE',
            ],
            'CREATED_BY' => [
                'type'  => self::FIELD_TYPE_USER,
                'code'  => 'CREATED_BY',
            ],
            'TIMESTAMP_X' => [
                'type'  => self::FIELD_TYPE_DATETIME,
                'code'  => 'TIMESTAMP_X',
            ],
            'MODIFIED_BY' => [
                'type'  => self::FIELD_TYPE_USER,
                'code'  => 'MODIFIED_BY',
            ],
            'DETAIL_PAGE_URL' => [
                'type'  => self::FIELD_TYPE_STRING,
                'code'  => 'DETAIL_PAGE_URL',
            ],
            'LIST_PAGE_URL' => [
                'type'  => self::FIELD_TYPE_STRING,
                'code'  => 'LIST_PAGE_URL',
            ],
        ];
        /** @var array Типы свойств инфоблока */
        private array $properties = [];
        
        private ?Result $result = null;
        
        protected string $iblockCode = '';
        protected string $iblockTypeId = '';
        protected string $iblockApiCode = '';
        
        protected int $iblockId = 0;
        
        private int $count = 0;
        private int $selectedRowsCount = 0;
        
        protected array $select = ['*'];
        public array $enumProperties = [];
        public array $userProperties = [];
        public array $fileProperties = [];
        
        /** @var string[] Коды полей таблицы (не коды свойств) */
        private array $iblockElementTableFields = [
            'ID', 'IBLOCK_ID',
            'IBLOCK_SECTION_ID',
            'NAME', 'ACTIVE', 'SORT', 'CODE',
            'PREVIEW_TEXT', 'PREVIEW_PICTURE',
            'DETAIL_TEXT', 'DETAIL_PICTURE',
            'DATE_CREATE', 'CREATED_BY',
            'TIMESTAMP_X', 'MODIFIED_BY',
            'DETAIL_PAGE_URL',
            'LIST_PAGE_URL',
        ];
        
        /** @var string[] Специальные ключи для фильтра, которые не нужно обрабатывать как коды полей */
        private array $specialFilterKeys = [
            'LOGIC'
        ];
        
        /**
         * @param array $selectFields Полный набор символьных кодов полей и свойств для выборки
         * @param array $enumProperties Набор символьных кодов свойств типа список
         * @throws Exception
         */
        public function __construct(array $selectFields = ['*'], array $enumProperties = [], array $fileProperties = [])
        {
            $this->select = $selectFields;
            
            if ( 0 < count($enumProperties) ) {
                $this->enumProperties = $enumProperties;
            }
            
            if ( 0 < count($fileProperties) ) {
                $tmpArray = [];
                
                foreach ( $fileProperties as $fileProperty ) {
                    $tmpArray[] = mb_strtoupper($fileProperty);
                }
                $this->fileProperties = $tmpArray;
            }
            
            if ( empty($this->iblockCode) ) {
                throw new InvalidArgumentException('Не задан код информационного блока');
            }
            
            Loader::includeModule('iblock');
            
            $result = IblockTable::getList(
                [
                    'filter' => [ '=CODE' => $this->iblockCode ],
                    'select' => [ 'id', 'code', 'name', 'api_code', 'version', 'iblock_type_id', 'list_page_url', 'detail_page_url' ],
                ]
            );
            
            if ( !$iblock = $result->fetchObject() ) {
                // Trying to create IBlock
                
                try {
                    $iblock = $this->createIblock();
                } catch (Exception $e) {
                    throw $e;
                    throw new Exception(
                        sprintf('Информационный блок с кодом "%s" не найден', $this->iblockCode)
                    );
                }
            }
            
            if ( empty($iblock->get('api_code')) ) {
                throw new Exception(
                    sprintf('Не задан API код для инфоблока "%s"', $iblock->get('NAME'))
                );
            }
            
            // Поддержка только инфоблоков версии 2.0
            if ( 2 !== (int)$iblock->get('version') ) {
                throw new Exception(
                    sprintf('Поддерживаются только инфоблоки 2.0 (код инфоблока: %s)', $iblock->get('CODE'))
                );
            }
            
            if ( 1 < $result->getSelectedRowsCount() ) {
                throw new RuntimeException(
                    sprintf('Найдено несколько инфоблоков с кодом "%s"', $this->iblockCode)
                );
            }
            
            $this->iblockId = (int)$iblock->get('id');
            $this->setIblockTypeId((string)$iblock->get('iblock_type_id'));
            
            // Свойства и элементы инфоблока
            $this->fillIblockProperties();
        }
        
        /**
         * @return array
         * @throws ArgumentException
         * @throws ObjectPropertyException
         * @throws SystemException
         */
        private function fillIblockProperties(): void
        {
            $result = PropertyTable::getList([
                'filter' => ['IBLOCK_ID' => $this->getIblockId()],
                'select' => ['ID', 'NAME', 'ACTIVE', 'SORT', 'CODE', 'PROPERTY_TYPE'],
            ]);
            
            $iblockProperties = [];
            
            while ( $property = $result->fetchObject() ) {
                $code = mb_strtoupper($property->getCode());
                
                $this->properties[$code] = [
                    'id'    => $property->getId(),
                    'name'  => $property->getName(),
                    'active'=> $property->getActive(),
                    'sort'  => $property->getSort(),
                    'code'  => $code,
                    'type'  => $this->propertyTypeToFieldType($property->getPropertyType()),
                ];
            }
        }
        
        /**
         * @param string $propertyType
         * @return string
         */
        private function propertyTypeToFieldType(string $propertyType): string
        {
            switch ( $propertyType ) {
                case PropertyTable::TYPE_NUMBER:
                    $fieldType = self::FIELD_TYPE_INT;
                    break;
                case PropertyTable::TYPE_STRING:
                    $fieldType = self::FIELD_TYPE_STRING;
                    break;
                case PropertyTable::TYPE_ELEMENT:
                    $fieldType = self::FIELD_TYPE_ELEMENT;
                    break;
                case PropertyTable::TYPE_FILE:
                    $fieldType = self::FIELD_TYPE_FILE;
                    break;
                case PropertyTable::TYPE_LIST:
                    $fieldType = self::FIELD_TYPE_ENUM;
                    break;
                case PropertyTable::USER_TYPE_DATETIME:
                    $fieldType = self::FIELD_TYPE_DATETIME;
                    break;
                case PropertyTable::USER_TYPE_DATE:
                    $fieldType = self::FIELD_TYPE_DATE;
                    break;
                case PropertyTable::USER_TYPE_USER:
                    $fieldType = self::FIELD_TYPE_USER;
                    break;
                default:
                    // uncomment when debug
                    //throw new Exception('UNKNOWN PROPERTY TYPE: ' . $propertyType);
                    $fieldType = self::FIELD_TYPE_STRING;
            }
            return $fieldType;
        }
        
        /**
         * @return array
         */
        public function getProperties(): array
        {
            return $this->properties;
        }
        
        /**
         * @return Iblock
         * @throws ArgumentException
         * @throws ObjectPropertyException
         * @throws SystemException
         * @throws Exception
         */
        private function createIblock(): Iblock
        {
            // @fixme select default site
            $siteId = IblockSiteTable::getList()
                ->fetchObject()
                ->get('site_id')
            ;
            
            $fields = [
                'name'  => $this->getIblockCode(), // @fixme
                'code'  => $this->getIblockCode(),
                'iblock_type_id' => $this->getIblockType($this->getIblockTypeId()),
                'version'   => 2,
                'site_id'   => [$siteId],
                'lid'       => [$siteId],
                'api_code'  => $this->getIblockApiCode(),
                'active'    => 'Y',
                'sort'      => 50,
                'index_element' => 'N',
                'index_section' => 'N',
            ];
            
            /*$result = IblockTable::add([
                'fields' => $fields,
            ]);*/
            
            $iblock = new \CIBlock();
            if ( !$iblockId = $iblock->Add(array_change_key_case($fields, CASE_UPPER)) ) {
                throw new Exception('Ошибка при создании инфоблока:' . $iblock->LAST_ERROR);
            }
            
            return IblockTable::getById($iblockId)->fetchObject();
        }
        
        /**
         * @param string $typeCode
         * @return string
         * @throws ArgumentException
         * @throws ObjectPropertyException
         * @throws SystemException
         */
        private function getIblockType(string $typeCode = ''): string
        {
            $typeResult = TypeTable::getList([
                'filter' => [
                    'ID' => $typeCode,
                ]
            ]);
            
            if ( 0 === count($typeResult->fetchAll()) ) {
                TypeTable::add([
                    'ID' => $typeCode,
                    'SECTIONS'  => 'N',
                    'IN_RSS'    => 'N',
                    'LANG'  => [
                        'ru'    => [
                            'NAME' => $typeCode,
                        ],
                        'en'    => [
                            'NAME' => $typeCode,
                        ],
                        // @fixme get all language packs
                    ]
                ]);
            }
            
            return $typeCode;
        }
        
        /**
         * @param array $enumProperties
         * @return self
         */
        public function setEnumProperties(array $enumProperties): self
        {
            $this->enumProperties = $enumProperties;
            return $this;
        }
        
        /**
         * @param array $filter
         * @param array $sort
         * @param array $limit
         * @param array $fields
         * @throws InvalidArgumentException
         * @throws RuntimeException
         * @return ?self
         * @noinspection PhpTooManyParametersInspection
         */
        protected function query(array $filter = [], array $sort = [], array $limit = [], array $fields = []): ?self
        {
            $select = 0 < count($fields) ? $fields : $this->select;
            
            $params = [
                'select' => ['id'],
                'cache' => [
                    'ttl'   => 3600,
                    'cache_joins'   => true,
                ],
                'count_total'   => true,
            ];
            
            if ( 0 < count($filter) ) {
                
                $filter = array_change_key_case($filter);
                
                foreach ( $filter as $key => $value ) {
                    // @legacy ключ 'condition' использовался ранее
                    if ( 'condition' === $key ) {
                        unset($filter[$key]);
                        $filter[] = $value;
                    }
                }
                $filter = $this->prepareFilter($filter);
                $filter = $this->checkFilterKeysHasValue($filter);
                
                // Выбираем все подразделы
                if ( array_key_exists('iblock_section_id', $filter) ) {
                    
                    $resultSectionsId = [];
                    
                    $iblockSectionIdFilterValue = $filter['iblock_section_id'];
                    
                    if ( is_array($iblockSectionIdFilterValue) ) {
                        foreach ( $iblockSectionIdFilterValue as $filterSectionId ) {
                            if ( $subSectionsId = $this->getSubsectionsId((int)$filterSectionId) ) {
                                $resultSectionsId = array_merge($resultSectionsId, $subSectionsId);
                            }
                        }
                    } else {
                        $resultSectionsId = $this->getSubsectionsId((int)$iblockSectionIdFilterValue);
                    }
                    
                    if ( !is_countable($resultSectionsId) || 0 === count($resultSectionsId) ) {
                        if ( is_array($iblockSectionIdFilterValue) ) {
                            $resultSectionsId = array_merge($resultSectionsId, $iblockSectionIdFilterValue);
                        } else {
                            $resultSectionsId[] = (int) $iblockSectionIdFilterValue;
                        }
                    }
                    
                    $filter['iblock_section_id'] = $resultSectionsId;
                }
                
                $params['filter'] = $filter;
            }
            
            if ( 0 < count($sort) ) {
                $sort = array_change_key_case($sort, CASE_LOWER);
                $sort = $this->prepareSort($sort);
                
                $sort = array_change_key_case($sort, CASE_UPPER);
                $params['order'] = $sort;
                
                if ( array_key_exists('RAND', $sort) ) {
                    //$query->registerRuntimeField('RAND', ['reference' => 'RAND()']); // @fixme переделать на registerRuntimeField
                    $params['cache']['ttl'] = 0; // Иначе ORM кеширует запрос
                    $params['runtime'] = [
                        new ExpressionField('RAND', 'RAND()')
                    ];
                }
            }
            
            $lim = (int) ( $limit['page_size'] ?: $limit['limit']);
            
            if ( 0 < $lim ) {
                $params['limit'] = $lim;
                
                if ( 0 < (int)$limit['page_num'] ) {
                    $params['offset'] = (int)$limit['limit'] * ( (int)$limit['page_num'] - 1 );
                } elseif ( 0 < (int)$limit['offset'] ) {
                    $params['offset'] = (int)$limit['offset'];
                }
            }
            
            if ( 'Y' === $_GET['clear_cache'] ) {
                try {
                    Iblock::wakeUp($this->iblockId)->getEntityDataClass()::getEntity()->cleanCache();
                } catch (ArgumentException $e) {
                    throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e->getPrevious());
                } catch (SystemException $e) {
                    throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
                }
            }
            
            try {
                /** @var IblockTable $entityDataClass */
                $entityDataClass = Iblock::wakeUp($this->iblockId)->getEntityDataClass();
                
                if ( !$this->result = $entityDataClass::getList($params) ) {
                    return null;
                }
                
                //$entityDataClass->flushCache();
                //$entityDataClass::getEntity()->writeToCache();
                
                $select = $this->prepareSelect($select);
                
                $this->count = $this->result->getCount();
                $this->selectedRowsCount = $this->result->getSelectedRowsCount();
                
            } catch (ArgumentException $e) {
                throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e->getPrevious());
            } catch (ObjectPropertyException|SystemException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
            }
            
            // @performance Если выбирается только id, то повторный запрос делать не нужно
            if ( 1 === count($fields) && 'id' === mb_strtolower($fields[0]) ) {
                return $this;
            }
            
            // Далее делаем повторный запрос по полученным ID уже со всеми выбираемыми полями
            $itemsId = $this->result->fetchAll();
            
            // @fixme Протестировать: выбирать только уникальные ID (исключить повторы)
            $itemsId = array_unique($itemsId, SORT_REGULAR);
            
            // Для свойств типа ENUM добавляем возможность выборки ->get('ENUM_CODE')->getItem()->...
            if ( 0 < count($this->enumProperties) ) {
                foreach ( $this->enumProperties as $propertyCode ) {
                    if ( !empty($propertyCode) ) {
                        $select[] = $propertyCode . '.ITEM';
                    }
                }
            }
            
            $params['select'] = $select;
            $params['filter'] = ['=id' => array_map(static function($item){return $item['ID'];}, $itemsId)];
            
            unset($params['limit']);
            unset($params['offset']);
            unset($params['count_total']);
            
            // Необходимо протестировать. Предыдущим запросом получаем уже отсортированные ID и задавать сортировку не нужно
            //unset($params['order']);
            // Необходимо протестировать.
            //unset($params['runtime']);
            
            if ( 'Y' === $_GET['clear_cache'] ) {
                try {
                    Iblock::wakeUp($this->iblockId)->getEntityDataClass()::getEntity()->cleanCache();
                } catch (ArgumentException $e) {
                    throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e->getPrevious());
                } catch (SystemException $e) {
                    throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
                }
            }
            
            try {
                if ( !$this->result = Iblock::wakeUp($this->iblockId)->getEntityDataClass()::getList($params) ) {
                    return null;
                }
            } catch (ObjectPropertyException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
            } catch (ArgumentException $e) {
                throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e->getPrevious());
            } catch (SystemException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
            }
            
            return $this;
        }
        
        /**
         * Для фильтрации по свойствам в инфоблоках 2.0 необходимо добавлять '.VALUE' к коду свойства
         * @param array $filter
         * @return array $filter
         */
        private function checkFilterKeysHasValue(array $filter): array
        {
            foreach ( $filter as $key => $value ) {
                
                $key = (string)$key;
                
                $cleanKey = str_replace(['=', '>', '<', '!', '?', '%'], '', $key);
                $cleanKey = mb_strtoupper($cleanKey);
                
                // Пропускаем коды полей (не коды свойств)
                if ( in_array($cleanKey, $this->iblockElementTableFields) ) {
                    continue;
                }
                
                // Пропускаем специальные служебные коды
                if ( in_array(mb_strtoupper($key), $this->specialFilterKeys) ) {
                    continue;
                }
                
                // Если ключ значения - число, скорее всего это множественные значения фильтра
                if ( is_iterable($value) ) {
                    $filter[$key] = $this->checkFilterKeysHasValue($value);
                    if ( !is_int(array_key_first($value)) ) {
                        continue;
                    }
                }
                
                $keyInLowerCase = mb_strtolower($key);
                
                // Если в ключе содержится точка, скорее всего там "все нормально"
                if ( 0 < strpos($keyInLowerCase, '.') ) {
                    continue;
                }
                
                // Ко всем остальным свойствам (не полям таблицы) добавляем '.value'
                if ( !str_contains($keyInLowerCase, '.value') ) {
                    unset($filter[$key]);
                    $key .= '.value';
                    $filter[$key] = $value;
                }
            }
            
            return $filter;
        }
        
        /**
         * @param array $filter
         * @return array
         */
        private function prepareFilter(array $filter): array
        {
            $filter = $this->replaceRenamed($filter);
            
            if ( 0 >= count($this->enumProperties) && 0 >= count($this->userProperties) ) {
                return $filter;
            }
            
            $enumProperties = array_flip(array_change_key_case(array_flip($this->enumProperties)));
            
            foreach ( $filter as $key => $value ) {
                $key = (string) $key;
                if ( 'name' === mb_strtolower($key) ) {
                    unset($filter[$key]);
                    $filter[$key] = htmlentities($value);
                } elseif (in_array(mb_strtolower($key), $enumProperties, true)) {
                    // ENUM
                    unset($filter[$key]);
                    if (is_array($value)) {
                        $filterValue = [];
                        foreach ($value as $v) {
                            $filterValue[] = $this->getPropertyEnumId(mb_strtoupper($key), $v);
                        }
                        $filter['=' . mb_strtolower($key) . '.value'] = $filterValue;
                    } else {
                        $filter['=' . mb_strtolower($key) . '.value'] = $this->getPropertyEnumId(
                            mb_strtoupper($key),
                            $value
                        );
                    }
                } elseif (in_array(mb_strtolower($key), $this->userProperties, true)) {
                    // USER
                    unset($filter[$key]);
                    if ( is_array($value) ) {
                        $filterValue = [];
                        foreach ( $value as $v ) {
                            $filterValue[] = $v;
                        }
                        $filter['=' . mb_strtolower($key) . '.value'] = $filterValue;
                    } else {
                        $filter['=' . mb_strtolower($key) . '.value'] = $value;
                    }
                } elseif (in_array(mb_strtolower($key), $this->fileProperties, true)) {
                    // FILE
                    unset($filter[$key]);
                    if ( is_array($value) ) {
                        $filterValue = [];
                        foreach ( $value as $v ) {
                            $filterValue[] = $v;
                        }
                        $filter['=' . mb_strtolower($key) . '.file'] = $filterValue;
                    } else {
                        $filter['=' . mb_strtolower($key) . '.file'] = $value;
                    }
                } elseif (is_array($value)) {
                    // ARRAY RECURSIVE
                    unset($filter[$key]);
                    $filter[$key] = $this->prepareFilter($value);
                    
                }
                
            }
            
            return $filter;
        }
        
        /**
         * Переименовывает свойства, заданные как "имя свойства => код свойства"
         * @param array $filter
         * @return array
         */
        private function replaceRenamed(array $filter): array
        {
            foreach ( $filter as $filterKey => $filterValue ) {
                foreach ( $this->select as $selectKey => $selectValue ) {
                    if ( is_array($selectValue) ) {
                        $renamed = key($selectValue);
                        $actual = current($selectValue);
                        if ( str_contains((string)$filterKey, $actual) ) {
                            unset($filter[$filterKey]);
                            $filterKey = str_replace($actual, $renamed, $filterKey);
                            $filter[$filterKey] = $filterValue;
                        }
                    }
                }
            }
            
            return $filter;
        }
        
        /**
         * @param array $sort
         * @return array
         */
        private function prepareSort(array $sort): array
        {
            $sort = $this->replaceRenamed($sort);
            
            $enumProperties = array_flip(array_change_key_case(array_flip($this->enumProperties)));
            
            
            $sortablesFieldsCodes = $this->getSortableFieldsCodes();
            
            foreach ( $sort as $field => $order ) {
                
                if ( !in_array(mb_strtoupper($field), $sortablesFieldsCodes) ) {
                    continue;
                }
                
                if ( !$fieldType = $this->getFieldType($field) ) {
                    continue;
                }
                
                $isField = $this->isField($field);
                $isProperty = $this->isProperty($field);
                
                switch ( $fieldType ) {
                    case self::FIELD_TYPE_ELEMENT:
                        $preparedField = $field . '.value';
                        break;
                    case self::FIELD_TYPE_DATETIME:
                        $preparedField = $field;
                        break;
                    case self::FIELD_TYPE_STRING:
                        if ( $isProperty ) {
                            $preparedField = $field . '.value';
                        } else {
                            $preparedField = $field;
                        }
                        break;
                    case self::FIELD_TYPE_INT:
                        if ( $isProperty ) {
                            $preparedField = $field . '.value';
                        } else {
                            $preparedField = $field;
                        }
                        break;
                    case self::FIELD_TYPE_BOOL:
                        if ( $isField ) {
                            $preparedField = $field;
                        }
                        break;
                    case self::FIELD_TYPE_USER:
                        if ( $isField ) {
                            // @fixme Normal fields can be only the last in chain, `CREATED_BY` Bitrix\Main\ORM\Fields\IntegerField is not the last
                            //$preparedField = $field . '.user.last_name';
                            $preparedField = $field;
                        } else {
                            $preparedField = $field . '.user.last_name';
                        }
                        break;
                    default:
                        throw new \Exception('UNKNOWN FIELD TYPE: ' . $fieldType);
                        $preparedField = $field;
                }
                
                unset($sort[$field]);
                $sort[$preparedField] = $order;
            }
            
            return $sort;
        }
        
        /**
         * Возвращает коды полей (в верхнем регистре), доступных для сортировки
         * @return string[]
         */
        private function getSortableFieldsCodes(): array
        {
            $fields = array_keys($this->fields);
            $properties = array_keys($this->properties);
            return array_merge($fields, $properties);
        }
        
        /**
         * Возвращает тип поля
         * @param string $fieldCode Код поля или свойства
         * @return string|false
         */
        private function getFieldType(string $fieldCode): string|false
        {
            $fieldCode = mb_strtoupper($fieldCode);
            return array_merge($this->fields, $this->properties)[$fieldCode]['type'] ?? false;
        }
        
        /**
         * Является ли полем инфоблока
         * @param string $fieldCode
         * @return bool
         */
        private function isField(string $fieldCode): bool
        {
            $fieldCode = mb_strtoupper($fieldCode);
            return array_key_exists($fieldCode, $this->fields);
        }
        
        /**
         * Является ли свойством инфоблока
         * @param string $fieldCode
         * @return bool
         */
        private function isProperty(string $fieldCode): bool
        {
            $fieldCode = mb_strtoupper($fieldCode);
            return array_key_exists($fieldCode, $this->properties);
        }
        
        /**
         * @param array $select
         * @return array
         */
        private function prepareSelect(array $select): array
        {
            foreach ( $select as $key => $value ) {
                if ( is_array($value) ) {
                    $renamed = key($value);
                    //$actual = current($value);
                    unset($select[$key]);
                    $select[$key] = $renamed;
                } elseif ( 'detail_page_url' === mb_strtolower((string)$value) ) {
                    unset($select[$key]);
                    $select[$key] = 'IBLOCK.DETAIL_PAGE_URL';
                }
            }
            return $select;
        }
        
        /**
         * @return mixed
         */
        protected function fetchObject(): mixed
        {
            if ( !$this->result || !$ormObject = $this->result->fetchObject() ) {
                return null;
            }
            
            return $this->objectToEntity($ormObject);
        }
        
        /**
         * Возвращает количество элементов таблицы БД, попавших под выборку
         * @return int
         */
        public function getSelectedRowsCount(): int
        {
            if ( !$this->result ) {
                return 0;
            }
            return $this->selectedRowsCount;
        }
        
        /**
         * Возвращает общее количество элементов таблицы БД
         * @return int
         */
        public function getTotalCount(): int
        {
            if ( !$this->result ) {
                return 0;
            }
            return $this->count;
        }
        
        /**
         * Создание элемента инфоблока. Создает элемент с минимальным количеством полей, затем вызывает метод update
         *
         * @param array $data
         *
         * @return int
         * @throws Exception
         */
        public function create(array $data): int
        {
            $data = array_change_key_case($data, CASE_UPPER);
            
            $iblock = Iblock::wakeUp($this->iblockId);
            $entity = $iblock->getEntityDataClass();
            /** @var EntityObject $obj */
            $obj = $entity::createObject();
            
            // Save "empty" element, then call update method
            
            $obj->set('NAME', $data['NAME']);
            if ( !isset($data['CREATED_BY']) ) {
                $obj->set('CREATED_BY', CurrentUser::get()->getId());
            }
            $obj->set('MODIFIED_BY', CurrentUser::get()->getId());
            
            // @fixme если свойство является обязательным, необходимо его сохранить при создании
            
            $result = $obj->save();
            
            /*try {
                $result = $obj->save();
            } catch (\Bitrix\Main\DB\SqlQueryException $e) {
                // Trying to create iblock element property
                $obj;
            } finally {
                throw $e;
            }*/
            
            if ( !$result->isSuccess() ) {
                throw new RuntimeException($result->getErrorMessages()[0]);
            }
            
            $parameters = $data;
            $parameters['ID'] = $result->getPrimary();
            $parameters['IBLOCK_ID']    = $this->iblockId;
            $event = new Event('iblock', 'OnOrmAfterIBlockElementAdd', $parameters);
            $event->send();
            
            $data['ID'] = $result->getId();
            $this->update($data);
            
            return $result->getId();
        }
        
        /**
         * Обновление элемента инфоблока.
         *
         * @param array $data
         * @param bool $raiseEvents Вызывать события Битрикс (OnOrmAfterIBlockElementUpdate)
         * @return int
         * @throws ArgumentException
         * @throws SystemException
         * @throws Exception
         */
        public function update(array $data, bool $raiseEvents = true): int
        {
            $data = array_change_key_case($data, CASE_UPPER);
            
            $iblock = Iblock::wakeUp($this->iblockId);
            $entity = $iblock->getEntityDataClass();
            //$obj = $entity::createObject();
            try {
                if ( !$obj = $entity::getByPrimary((int) $data['ID'])->fetchObject() ) {
                    throw new Exception('Элемент не найден');
                }
            } catch (ObjectPropertyException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
            } catch (ArgumentException $e) {
                throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e->getPrevious());
            } catch (SystemException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
            }
            
            $sysEntity = $obj->sysGetEntity();
            
            foreach ( $data as $key => $value ) {
                
                // ID нельзя менять
                if ( 'ID' === $key ) {
                    continue;
                }
                
                try {
                    $sysEntity->getField($key);
                } catch (ArgumentException $e) {
                    // @fixme if field does not exist
                    //$this->createField($sysEntity, $key);
                    continue;
                }
                
                // @fixme Ждем пока в iblock ORM добавят поддержку множественных свойств, поэтому пока fallback на \CIBlockElement
                if ( $sysEntity->getField($key) instanceof PropertyOneToMany ) {
                    
                    // @fixme Удаление множественного свойства типа "Файл"
                    if ( in_array(strtoupper($key), $this->fileProperties) ) {
                        $propertyId = (int)$sysEntity->getField($key)?->getIblockElementProperty()?->get('ID');
                        $propertyValues = [];
                        $storedFilesId = [];
                        $storedPropertyValuesId = [];
                        
                        $res = CIBlockElement::GetProperty($this->iblockId, $obj->get('ID'), 'sort', 'asc', ['CODE' => $key]);
                        
                        while ( $arProp = $res->Fetch() ) {
                            $propertyValueId = $arProp['PROPERTY_VALUE_ID'];
                            $propertyValue = $arProp['VALUE'];
                            
                            $storedFilesId[] = $propertyValue;
                            $storedPropertyValuesId[$propertyValue] = $propertyValueId;
                        }
                        
                        $storedFilesId = array_unique($storedFilesId);
                        
                        if ( 0 < count($storedFilesId) ) {
                            
                            // Если в $value не содержится уже сохраненного файла, его нужно удалить
                            foreach ( $storedFilesId as $k => $storedFileId ) {
                                if ( !in_array($storedFileId, (array)$value) ) {
                                    $propertyValues[$storedPropertyValuesId[$storedFileId]] = [
                                        'VALUE' => [
                                            'del'   => 'Y',
                                        ]
                                    ];
                                    unset($storedFilesId[$k]);
                                }
                            }
                            
                            // Случай добавления новых файлов
                            foreach ( $value as $fileId ) {
                                if ( !in_array($fileId, $storedFilesId) ) {
                                    $propertyValues[] = $fileId;
                                }
                            }
                            
                        } else {
                            $propertyValues = $value;
                        }
                        
                        if ( 0 < count($propertyValues) ) {
                            CIBlockElement::SetPropertyValues($obj->get('ID'), $this->iblockId, $propertyValues, $key);
                        }
                        
                    } else {
                        CIBlockElement::SetPropertyValues($obj->get('ID'), $this->iblockId, $value, $key);
                    }
                    
                    continue;
                }
                
                $obj->set($key, $value);
                
                $field = $sysEntity->getField($key);
                
                // Только для свойств
                //if ( in_array(get_class($field), ['Bitrix\Iblock\ORM\Fields\PropertyReference'], false) ) {
                if ( str_contains(get_class($field), 'Property') ) {
                    
                    $parameters = [
                        'elementId' => $data['ID'],
                        'iblockId'  => $this->iblockId,
                        'propertyValues'    => $value,
                        'propertyCode'      => $key,
                        'arProp'            => [
                            $key    => [
                                'ID'    => $field->getIblockElementProperty()->get('ID')
                            ]
                        ],
                    ];
                    $event = new Event('iblock', 'OnOrmIBlockElementSetPropertyValues', $parameters);
                    $event->send();
                }
            }
            
            $obj->set('MODIFIED_BY', CurrentUser::get()->getId());
            $obj->set('TIMESTAMP_X', new DateTime());
            
            $result = $obj->save();
            
            // Очистка кеша
            $obj->sysGetEntity()->cleanCache();
            //\Bitrix\Iblock\Iblock::wakeup($this->iblockId)->getEntityDataClass()::getEntity()->cleanCache();
            
            if ( !$result->isSuccess() ) {
                throw new RuntimeException($result->getErrorMessages()[0]);
            }
            
            if ( $raiseEvents ) {
                $parameters = $data;
                $parameters['IBLOCK_ID']    = $this->iblockId;
                $event = new Event('iblock', 'OnOrmAfterIBlockElementUpdate', $parameters);
                $event->send();
            }
            
            return $obj->getId();
        }
        
        /**
         * Удаление элемента инфоблока.
         *
         * @param int $id
         * @return bool $result
         * @throws Exception
         */
        public function delete(int $id): bool
        {
            $iblock = Iblock::wakeUp($this->iblockId);
            $entity = $iblock->getEntityDataClass();
            
            if ( !$obj = $entity::getByPrimary($id)->fetchObject() ) {
                throw new Exception('Элемент не найден');
            }
            
            $result = $obj->delete();
            
            if ( !$result->isSuccess() ) {
                return false;
            }
            
            // @fixme вызывать события удаления элемента и свойств
            
            // @fixme Очистка кеша
            //\Bitrix\Iblock\Iblock::wakeup($this->iblockId)->getEntityDataClass()::getEntity()->cleanCache();
            
            return true;
        }
        
        /**
         * Проверяет есть ли у пользователя права на редактирование (изменение) (в том числе полный доступ) элемента инфоблока.
         * Проверка осуществляется при расширенных правах доступа на инфоблок.
         * Учитываются права заданные на конкретного пользователя, так и на группы, в которых состоит пользователь.
         *
         * @param int $elementId ID элемента инфоблока
         * @param int $userId ID пользователя, если не задан, то проверка осуществляется для текущего пользователя
         *
         * @return bool $userCanEdit
         *
         * @fixme Переписать на D7
         */
        public function userCanEdit(int $elementId, int $userId = USER_ID): bool
        {
            if ( 0 >= $elementId ) {
                return false;
            }
            
            global $USER;
            
            // @fixme CUser::GetUserGroupArray работает только с текущим пользователем
            $arGroups = $USER->GetUserGroupArray();
            
            $groupCode = '(' . '"U' . $userId . '", "G' . implode('", "G', $arGroups) . '"' . ')';
            
            global $DB;
            
            $query = '
            SELECT *
            FROM `b_iblock_element_right` as IER
                INNER JOIN `b_iblock_right` as IR ON IR.`ID` = IER.`RIGHT_ID`
                INNER JOIN `b_task` as T on T.`ID` = IR.`TASK_ID`
            WHERE
                IR.`ENTITY_TYPE` IN (\'element\', \'iblock\')
                AND IER.`IBLOCK_ID` = ' . $this->iblockId . '
                AND IER.`ELEMENT_ID` = ' . $elementId . '
                AND IR.`GROUP_CODE` IN ' . $groupCode . '
                AND T.`NAME` IN (\'iblock_full_edit\', \'iblock_edit\', \'iblock_full\')
            ';
            
            $res = $DB->Query($query, true);
            
            if ( $res->Fetch() ) {
                return true;
            }
            
            return false;
        }
        
        /**
         * Возвращает ID значения свойства типа "список" по заданному коду свойства и заданному коду XML_ID значения свойства
         * @param string $propertyCode Символьный код свойства
         * @param ?string $xmlId XML_ID код значения свойства
         * @return ?int $propertyId ID значения свойства
         */
        public function getPropertyEnumId(string $propertyCode, ?string $xmlId): ?int
        {
            if ( !$xmlId ) {
                return null;
            }
            
            if (!$arProperty = $this->getPropertyEnum($propertyCode, $xmlId)) {
                return null;
            }
            
            return (int)$arProperty['ID'];
        }
        
        /**
         * Возвращает массив, описывающий свойство типа "список" по заданному коду свойства и заданному коду XML_ID
         * @param string $propertyCode Символьный код свойства
         * @param string $xmlId XML_ID код значения свойства
         * @return ?array $arPropertyEnum ID значения свойства
         */
        public function getPropertyEnum(string $propertyCode, string $xmlId): ?array
        {
            if ( empty($propertyCode) || empty($xmlId) ) {
                return null;
            }
            
            $res = CIBlockPropertyEnum::GetList(
                [],
                [
                    'XML_ID' => $xmlId,
                    'CODE' => $propertyCode,
                    'IBLOCK_ID' => $this->iblockId,
                ]
            );
            
            if ( !$arPropertyEnum = $res->Fetch() ) {
                return null;
            }
            
            return $arPropertyEnum;
        }
        
        /**
         * Возвращает текстовое значение свойства типа "список" по заданному коду свойства и заданному коду XML_ID
         * @param string $propertyCode Символьный код свойства
         * @param string $xmlId XML_ID код значения свойства
         * @return string|bool $propertyValue текстовое значение свойства
         */
        public function getPropertyEnumValue(string $propertyCode, string $xmlId): bool|string
        {
            if ( !$arProperty = $this->getPropertyEnum($propertyCode, $xmlId) ) {
                return false;
            }
            
            return $arProperty['VALUE'];
        }
        
        /**
         * Возвращает код XML_ID свойства типа список (enum)
         * @param string $propertyCode
         * @param int $enumId
         * @return string|null
         */
        public function getPropertyEnumXmlId(string $propertyCode, int $enumId): ?string
        {
            if ( empty($propertyCode) ) {
                return null;
            }
            
            if ( 0 >= $enumId ) {
                return null;
            }
            
            $res = CIBlockPropertyEnum::GetList(
                [],
                [
                    'ID'  => $enumId,
                    'CODE' => $propertyCode,
                    'IBLOCK_ID' => $this->iblockId,
                ]
            );
            
            if ( !$arPropertyEnum = $res->Fetch() ) {
                return null;
            }
            
            return $arPropertyEnum['XML_ID'];
        }
        
        /**
         * Возвращает ID всех подразделов заданного раздела
         * @param int $sectionId
         * @return ?int[]
         * @throws RuntimeException
         * @fixme убрать SQL запрос из метода
         */
        public function getSubsectionsId(int $sectionId): ?array
        {
            $sectionsId = [];
            
            $connection = Application::getConnection();
            
            $sql = sprintf('SELECT cs.ID FROM %1$s AS ps
                        INNER JOIN %1$s AS cs
                        ON ps.LEFT_MARGIN <= cs.LEFT_MARGIN AND ps.RIGHT_MARGIN >= cs.RIGHT_MARGIN AND ps.IBLOCK_ID = cs.IBLOCK_ID
                        WHERE ps.ID = %2$d AND ps.IBLOCK_ID = %3$d',
                SectionTable::getTableName(),
                $sectionId,
                $this->iblockId
            );
            
            try {
                $result = $connection->query($sql);
            } catch (SqlQueryException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
            }
            
            while ( $section = $result->fetch() ) {
                $sectionsId[] = (int)$section['ID'];
            }
            
            return 0 < count($sectionsId) ? $sectionsId : null;
        }
        
        /**
         * @return void
         * @throws Exception
         */
        public function startTransaction(): void
        {
            Application::getConnection()->startTransaction();
        }
        
        /**
         * @return void
         * @throws Exception
         */
        public function commitTransaction(): void
        {
            Application::getConnection()->commitTransaction();
        }
        
        /**
         * @return void
         * @throws Exception
         */
        public function rollbackTransaction(): void
        {
            Application::getConnection()->rollbackTransaction();
        }
        
        /**
         * @return int
         */
        public function getIblockId(): int
        {
            return $this->iblockId;
        }
        
        /**
         * @return string
         */
        public function getIblockCode(): string
        {
            return $this->iblockCode;
        }
        
        public function setIblockApiCode(string $iblockApiCode): self
        {
            $this->iblockApiCode = $iblockApiCode;
            return $this;
        }
        
        public function getIblockApiCode(): string
        {
            return $this->iblockApiCode;
        }
        
        public function setIblockTypeId(string $iblockTypeId): self
        {
            $this->iblockTypeId = $iblockTypeId;
            return $this;
        }
        
        public function getIblockTypeId(): string
        {
            return $this->iblockTypeId;
        }
        
        abstract public function objectToEntity(mixed $obj): ?AggregateInterface;
    }
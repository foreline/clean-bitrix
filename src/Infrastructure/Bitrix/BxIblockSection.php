<?php
declare(strict_types=1);

namespace Infrastructure\Bitrix;

use Bitrix\Iblock\Model\Section;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\DB\Result;
use Bitrix\Main\Entity\ExpressionField;
use CIBlockSection;
use Domain\Aggregate\AggregateInterface;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Класс для работы с разделами инфоблоков 2.0 Битрикс на D7 ORM.
 */

abstract class BxIblockSection extends Bxd7
{
    private ?Result $result = null;
    
    /**
     * @param array $filter
     * @param array $sort
     * @param array $limits
     * @param array $fields
     * @param array $options
     * @return ?self
     * @throws Exception
     * @noinspection PhpTooManyParametersInspection
     */
    public function query(array $filter = [], array $sort = [], array $limits = [], array $fields = [], array $options = []): ?self
    {
        $select = 0 < count($fields) ? $fields : $this->select;
        
        $params = [
            'select'    => ['id'],
            'cache' => [
                //'ttl'   => 3600,
                'ttl'   => 1,
                'cache_joins'   => true,
            ],
            'count_total'   => true,
        ];
        
        if ( 0 < count($filter) ) {
            $filter = array_change_key_case($filter);
            $params['filter'] = $filter;
        }
        
        //$params['filter']['iblock_id'] = $this->getIblockId();
        
        // Сортировка
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
        
        // Ограничение выборки
        $limit = (int) ( $limits['page_size'] ?: $limits['limit']);
        
        if ( 0 < $limit ) {
            $params['limit'] = $limit;
            if ( 0 < (int)$limits['page_num'] ) {
                $params['offset'] = (int)$limits['page_size'] * ( (int)$limits['page_num'] - 1 );
            } elseif ( 0 < (int)$limits['offset'] ) {
                $params['offset'] = (int)$limits['offset'];
            }
        }
        
        //if ( !$this->result = SectionTable::getList($params) ) {
        if ( !$this->result = Section::compileEntityByIblock($this->getIblockId())::getList($params) ) {
            return null;
        }
        
        //$this->count = $this->result->getCount();
        //$this->getSelectedRowsCount = $this->result->getSelectedRowsCount();
        
        // Если выбирается только id, то повторный запрос делать не нужно
        if ( 1 === count($fields) && 'id' === mb_strtolower($fields[0]) ) {
            return $this;
        }
        
        $sectionsId = $this->result->fetchAll();
        
        $select = $this->prepareSelect($select);
        
        // @fixme duplicate sections
        $options = array_change_key_case($options);
        /*if ( in_array('sections_cnt' ,$options) ) {
            $params['runtime'] =  [
                    new ExpressionField('SECTIONS_CNT', 'COUNT(*)'),
                        //new \Bitrix\Main\Entity\ExpressionField('ELEMENTS_CNT', 'COUNT(*)'),
                        'SECTION' => [
                    'data_type' => '\Bitrix\Iblock\SectionTable',
                    'reference' => [
                        '=this.ID' => 'ref.IBLOCK_SECTION_ID'
                    ],
                    'join_type' => 'left'
                ],
            ];
        }*/
        
        $params['select'] = $select;
        
        $params['filter'] = [
            '=id'   => array_map(
                static function($section)
                {
                    return $section['ID'];
                },
                $sectionsId
            )
        ];
        
        unset($params['limit']);
        unset($params['offset']);
        unset($params['count_total']);
        
        $sectionEntity = Section::compileEntityByIblock($this->getIblockId());
        
        //if ( !$this->result = SectionTable::getList($params) ) {
        if ( !$this->result = $sectionEntity::getList($params) ) {
            return null;
        }
        
        return $this;
        
        
        
        $this->result = SectionTable::getList(
            [
                'filter' => $arFilter,
                'order'   => $arSort,
                'select'    => [
                    '*',
                    //'ID',
                    //'NAME',
                    //'ELEMENTS_CNT',
                    'SECTIONS_CNT',
                    //'SUBSECTIONS_CNT',
                    'SECTION_PAGE_URL' => 'IBLOCK.SECTION_PAGE_URL',
                ],
                //'group' => [ 'ELEMENTS_CNT' ],
                'runtime'   => [
                    new ExpressionField('SECTIONS_CNT', 'COUNT(*)'),
                    //new \Bitrix\Main\Entity\ExpressionField('ELEMENTS_CNT', 'COUNT(*)'),
                    'SECTION' => [
                        'data_type' => '\Bitrix\Iblock\SectionTable',
                        'reference' => [
                            '=this.ID' => 'ref.IBLOCK_SECTION_ID'
                        ],
                        'join_type' => 'left'
                    ],
                    /*'ELEMENT' => [
                        'data_type' => '\Bitrix\Iblock\SectionElementTable',
                        'reference' => [
                            //'=this.IBLOCK_SECTION_ID' => 'ref.ID'
                            '=this.ID' => 'ref.IBLOCK_SECTION_ID',
                        ],
                        //'filter'    => ['ACTIVE' => 'Y'],
                        'join_type' => 'left'
                    ],*/
                ],
            ]
        );
        
        if ( !$this->result ) {
            return null;
        }
        
        return $this;
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
                $actual = current($value);
                unset($select[$key]);
                $select[$key] = $renamed;
            } elseif ( 'section_page_url' === mb_strtolower($value) ) {
                unset($select[$key]);
                $select[$key] = 'IBLOCK.SECTION_PAGE_URL';
            }
        }
        return $select;
    }
    
    /**
     * @return AggregateInterface|null
     * @throws Exception
     */
    public function fetchObject(): ?AggregateInterface
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
     * @param array $data
     * @return int
     * @fixme Методы add, delete и update (наследуемые от класса DataManager) в классе SectionTable заблокированы
     * @link https://dev.1c-bitrix.ru/api_d7/bitrix/iblock/sectiontable/index.php
     * @throws Exception
     */
    public function create(array $data): int
    {
        //$section = SectionTable::wakeUpObject($this->getIblockId());
        
        $data = array_change_key_case($data, CASE_UPPER);
        
        $sectionName = !empty($data['NAME']) ? $data['NAME'] : '';
        
        if ( empty($sectionName) ) {
            throw new InvalidArgumentException('Не задано название раздела');
        }
        //$section->setName($sectionName);
        
        $sectionCode = !empty($data['CODE']) ? $data['CODE'] : '';
        //$section->setCode($sectionCode);
        
        $parentID = !empty($data['PARENT_ID']) && 0 < (int)$data['PARENT_ID'] ? (int)$data['PARENT_ID'] : 0;
        // @fixme
        //$section->setParentSection();
        
        $sectionDescription = !empty($data['DESCRIPTION']) ? $data['DESCRIPTION'] : '';
        //$section->setDescription($sectionDescription);
        
        $sectionActive = !empty($data['ACTIVE']) && in_array($data['ACTIVE'], ['on', 'Y']) ? 'Y' : 'N';
        //$section->setActive($sectionActive); // @fixme
        
        $sectionSort = !empty($data['SORT']) && 0 < (int)$data['SORT'] ? (int)$data['SORT'] : 0;
        //$section->setSort($sectionSort);
        
        $iblockSection = new CIBlockSection();
        
        $arSectionFields = [
            'NAME'              => $sectionName,
            'CODE'              => $sectionCode,
            'ACTIVE'            => $sectionActive,
            'SORT'              => $sectionSort,
            'IBLOCK_SECTION_ID' => $parentID,
            'DESCRIPTION'       => $sectionDescription,
            'IBLOCK_ID'         => $this->getIblockId(),
        ];
        
        if ( !$sectionId = $iblockSection->Add($arSectionFields, true, false) ) {
            throw new RuntimeException('Ошибка при создании категории: ' . $iblockSection->LAST_ERROR);
        }
        
        //$section->save();
        
        //return $section->getId();
        return $sectionId;
    }
    
    /**
     * @param array $data
     * @param bool $raiseEvents
     * @return int
     */
    public function update(array $data, bool $raiseEvents = true): int
    {
        //$section = SectionTable::wakeUpObject($this->getIblockId());
        
        $data = array_change_key_case($data, CASE_UPPER);
        
        //$section = SectionTable::wakeUpObject((int)$data['ID']);
        //$section = SectionTable::getByPrimary((int)$data['ID'])?->fetchObject();
        
        $sectionName = !empty($data['NAME']) ? $data['NAME'] : '';
        
        if ( empty($sectionName) ) {
            throw new InvalidArgumentException('Не задано название раздела');
        }
        //$section->setName($sectionName);
        
        $sectionCode = !empty($data['CODE']) ? $data['CODE'] : '';
        //$section->setCode($sectionCode);
        
        $parentId = !empty($data['IBLOCK_SECTION_ID']) && 0 < (int)$data['IBLOCK_SECTION_ID'] ? (int)$data['IBLOCK_SECTION_ID'] : 0;
        // @fixme
        //$section->setParentSection($parentId);
        
        $sectionDescription = !empty($data['DESCRIPTION']) ? $data['DESCRIPTION'] : '';
        //$section->setDescription($sectionDescription);
        
        $sectionActive = !empty($data['ACTIVE']) && in_array($data['ACTIVE'], ['on', 'Y']) ? 'Y' : 'N';
        //$section->setActive($sectionActive); // @fixme
        
        $sectionSort = !empty($data['SORT']) && 0 < (int)$data['SORT'] ? (int)$data['SORT'] : 0;
        //$section->setSort($sectionSort);
        
        $iblockSection = new CIBlockSection();
        
        $arSectionFields = [
            'NAME'              => $sectionName,
            'CODE'              => $sectionCode,
            'ACTIVE'            => $sectionActive,
            'SORT'              => $sectionSort,
            'IBLOCK_SECTION_ID' => $parentId,
            'DESCRIPTION'       => $sectionDescription,
            'IBLOCK_ID'         => $this->getIblockId(),
        ];
        
        if ( !$iblockSection->Update($data['ID'], $arSectionFields, true, false) ) {
            throw new RuntimeException('Ошибка при изменении категории: ' . $iblockSection->LAST_ERROR);
        }
        
        //$section->save();
        
        //$obj = $section::getByPrimary((int)$data['ID'])->fetchObject();
        
        //$sysEntity = $obj->sysGetEntity();
        //$sysEntity->cleanCache();
        
        SectionTable::cleanCache();
        
        //return $section->getId();
        return (int)$data['ID'];
    }
    
    /**
     * @param int $id
     * @return bool
     * @throws Exception
     */
    public function delete(int $id): bool
    {
        $obj = SectionTable::wakeUpObject(['ID' => $id]);
        
        $result = $obj->delete();
        
        if ( !$result->isSuccess() ) {
            return false;
        }
        return true;
    }
}
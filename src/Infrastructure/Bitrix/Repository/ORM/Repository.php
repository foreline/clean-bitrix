<?php
declare(strict_types=1);

namespace Infrastructure\Bitrix\Repository\ORM;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\Result;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Domain\Repository\FieldsInterface;
use Domain\Repository\FilterInterface;
use Domain\Repository\GroupInterface;
use Domain\Repository\LimitInterface;
use Domain\Repository\SortInterface;
use JetBrains\PhpStorm\Deprecated;
use ReflectionClass;

/**
 * ORM Repository class
 */
abstract class Repository
{
    public ?FilterInterface $filter = null;
    public ?SortInterface $sort = null;
    public ?LimitInterface $limit = null;
    public ?FieldsInterface $fields = null;
    public ?GroupInterface $group = null;
    
    public ?Result $res;
    
    /**
     * Returns class constants
     * @return array<string, string>
     */
    public static function getFields(): array
    {
        $reflection = new ReflectionClass(static::class);
        return $reflection->getConstants();
    }
    
    /**
     * Returns class constants referencing other Entities
     * @return array<string, string>
     */
    public static function getReferenceFields(): array
    {
        return [];
    }
    
    /**
     * Returns allowed fields, i.e. 'count'
     * @return array
     */
    public static function getAllowedFields(): array
    {
        return [];
    }
    
    /**
     * @param iterable $filter
     * @param iterable $sort
     * @param iterable $limit
     * @param iterable $fields
     * @return array
     * @noinspection PhpTooManyParametersInspection
     */
    public function getParams(#[Deprecated]iterable $filter = [], #[Deprecated]iterable $sort = [], #[Deprecated]iterable $limit = [], #[Deprecated]iterable $fields = []): array
    {
        $params = [
            'cache' => [
                'ttl'   => 3600,
                'cache_joins'   => true,
            ],
            'count_total'   => true,
        ];
        
        $params['filter'] = $this->getFilter($filter);
        
        if ( 0 < count($sort) ) {
            //$sort = array_change_key_case($sort, CASE_LOWER);
            //$sort = array_change_key_case($sort, CASE_UPPER);
            $params['order'] = $sort;
            
            if ( array_key_exists('rand', $sort) || array_key_exists('RAND', $sort) ) {
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
        
        if ( empty($fields) ) {
            $fields = ['*'];
        }
        
        $params['select'] = $fields;
        
        if ( $this->group ) {
            $params['group'] = $this->group->get();
        }
        
        return $params;
    }
    
    /**
     * @param iterable $filter
     * @return array
     */
    private function getFilter(iterable $filter = []): array
    {
        $preparedFilter = [];
        
        foreach ( $filter as $key => $value ) {
            
            if ( 'condition' === $key || 'CONDITION' === $key ) {
                
                $logic = mb_strtoupper($value['LOGIC']);
                unset($value['LOGIC']);
                
                $cond = [];
                $cond['LOGIC'] = $logic;
                
                foreach ( $value as $k => $v ) {
                    $cond[$k] = $v;
                }
                
                $preparedFilter[] = $cond;
                
                continue;
            }
            
            $preparedFilter[$key] = $value;
        }
        
        return $preparedFilter;
    }
    
    /**
     * @param array $selectFields
     * @return array
     */
    public function prepareSelectFields(array $selectFields): array
    {
        $preparedFields = [];
        
        $fieldsMap = static::getFields();
        $referenceFields = static::getReferenceFields();
        $allowedFields = static::getAllowedFields();
        
        foreach ( $selectFields as $key => $field ) {
            
            if ( str_contains($field, '.') ) {
                $fieldName = explode('.', $field)[0];
            } else {
                $fieldName = $field;
            }
            
            if ( '*' === $fieldName ) {
                $preparedFields[] = '*';
                continue;
            }
            
            if (
                !in_array($fieldName, $fieldsMap)
                && !in_array($fieldName, $allowedFields)
            ) {
                continue;
            }
            
            if ( in_array($fieldName, $referenceFields) ) {
                $fieldName .= '_ref';
            }
            
            if ( str_contains($field, '.') ) {
                $fieldName .= '.' . explode('.', $field)[1];
            }
            
            if ( !is_int($key) ) {
                $preparedFields[$key] = $fieldName;
            } else {
                $preparedFields[] = $fieldName;
            }
        }
        
        return $preparedFields;
    }
    
}
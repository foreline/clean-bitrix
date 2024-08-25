<?php
declare(strict_types=1);

namespace Infrastructure\Bitrix\Repository\ORM;

use App\Servicedesk\Location\Infrastructure\Repository\Bitrix\ORM\LocationTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\Result;
use Bitrix\Main\ORM\Fields\ExpressionField;

/**
 * ORM Repository class
 */
class Repository
{
    public ?Result $res;
    
    /**
     * @param iterable $filter
     * @param iterable $sort
     * @param iterable $limit
     * @param iterable $fields
     * @return array
     * @throws ArgumentException
     * @noinspection PhpTooManyParametersInspection
     */
    public function getParams(iterable $filter = [], iterable $sort = [], iterable $limit = [], iterable $fields = []): array
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
            
            if ( array_key_exists('RAND', $sort) ) {
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
        
        return $params;
    }
    
    /**
     * @param iterable $filter
     * @return array
     * @throws ArgumentException
     */
    private function getFilter(iterable $filter = []): array
    {
        $preparedFilter = [];
        
        foreach ( $filter as $key => $value ) {
            
            if ( 'condition' === $key ) {
                
                $query = \Bitrix\Main\Entity\Query::filter()
                    ->logic($value['LOGIC'])
                    ->where([
                        ['%name', 'test'],
                        ['%description', 'test']
                    ]);
                
                $preparedFilter[] = $query;
                
                continue;
            }
            
            $preparedFilter[$key] = $value;
        }
        
        return $preparedFilter;
    }
    
}
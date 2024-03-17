<?php
    declare(strict_types=1);
    
    namespace Infrastructure\Bitrix\Repository\ORM;

    use App\Servicedesk\Location\Infrastructure\Repository\Bitrix\ORM\LocationTable;
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
        
            $params['filter'] = $filter;
        
            if ( 0 < count($sort) ) {
                $sort = array_change_key_case($sort, CASE_LOWER);
                $sort = array_change_key_case($sort, CASE_UPPER);
                $params['order'] = $sort;
            
                if ( array_key_exists('RAND', $sort) ) {
                    $params['cache']['ttl'] = 0; // Иначе ORM кеширует запрос
                    $params['runtime'] = [
                        new ExpressionField('RAND', 'RAND()')
                    ];
                }
            }
        
            $params['limit'] = $limit;
            $params['select'] = ['*'];
        
            return $params;
        }
        
    }
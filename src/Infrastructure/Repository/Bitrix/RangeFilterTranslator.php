<?php

declare(strict_types=1);

namespace Infrastructure\Repository\Bitrix;

use Domain\Repository\Enum\FilterOperator;
use Domain\Repository\Enum\RangeFilterKey;
use Infrastructure\Repository\Bitrix\Enum\BitrixFilterOperator;

/**
 * Translates domain range filters to Bitrix ORM filter format
 *
 * Domain format: {'field': {'op': 'gte', 'val': 5}}
 * Bitrix format: ['>=field' => 5]
 */
class RangeFilterTranslator
{
    /**
     * Translate a single range filter to Bitrix ORM format
     *
     * @param string $field Field name
     * @param array{op: string, val?: int|float, min?: int|float, max?: int|float} $rangeData
     * @return array Bitrix ORM filter array
     */
    public static function translate(string $field, array $rangeData): array
    {
        $operator = FilterOperator::from($rangeData[RangeFilterKey::OPERATOR->value]);
        
        // Handle BETWEEN operator specially (requires two conditions)
        if (FilterOperator::BETWEEN === $operator) {
            return [
                BitrixFilterOperator::GREATER_OR_EQUAL->prefixField($field)
                => $rangeData[RangeFilterKey::MIN->value],
                BitrixFilterOperator::LESS_OR_EQUAL->prefixField($field)
                => $rangeData[RangeFilterKey::MAX->value],
            ];
        }
        
        // Standard operators
        $bitrixOperator = BitrixFilterOperator::fromDomainOperator($operator);
        $value = $rangeData[RangeFilterKey::VALUE->value];
        
        return [$bitrixOperator->prefixField($field) => $value];
    }
    
    /**
     * Check if a filter value represents a range filter
     *
     * @param mixed $value
     * @return bool
     */
    public static function isRangeFilter(mixed $value): bool
    {
        return is_array($value)
            && isset($value[RangeFilterKey::OPERATOR->value])
            && null !== FilterOperator::tryFrom($value[RangeFilterKey::OPERATOR->value]);
    }
    
    /**
     * Process entire filter array, translating range filters
     *
     * @param array $filter Domain filter array
     * @return array Bitrix ORM filter array
     */
    public static function processFilter(array $filter): array
    {
        $result = [];
        
        foreach ($filter as $field => $value) {
            if (self::isRangeFilter($value)) {
                // Range filter - translate to Bitrix format
                $translated = self::translate($field, $value);
                $result = array_merge($result, $translated);
            } else {
                // Simple filter - pass through
                $result[$field] = $value;
            }
        }
        
        return $result;
    }
}

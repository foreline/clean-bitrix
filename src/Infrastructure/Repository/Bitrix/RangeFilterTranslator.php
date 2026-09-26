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
     * @param array{op: string, val?: int|float|string, min?: int|float, max?: int|float} $rangeData
     * @return array Bitrix ORM filter array; может содержать группы условий
     *               (нумерованные списки массивов условий) для составных операторов
     */
    public static function translate(string $field, array $rangeData): array
    {
        $operator = FilterOperator::from($rangeData[RangeFilterKey::OPERATOR->value]);
        
        // Строковые операторы обрабатываются отдельно до общей ветки,
        // так как формируют LIKE-паттерны и составные условия
        
        // FILLED: поле заполнено — IS NOT NULL и не пустая строка
        // (неявный AND через нумерованный список одноэлементных массивов)
        if (FilterOperator::FILLED === $operator) {
            return [
                ['!' . $field => null],
                ['!' . $field => ''],
            ];
        }
        
        // UNFILLED: поле не заполнено — NULL или пустая строка (OR-группа)
        if (FilterOperator::UNFILLED === $operator) {
            return [
                [
                    'LOGIC' => 'OR',
                    ['=' . $field => null],
                    ['=' . $field => ''],
                ],
            ];
        }
        
        // CONTAINS / STARTS_WITH / ENDS_WITH: префикс '%' сам обрамляет
        // значение wildcard-ами `%...%` и экранирует служебные символы
        // (LIKE ... ESCAPE '!'), поэтому значение передаётся в сыром виде.
        // STARTS_WITH / ENDS_WITH префиксом не выразимы и деградируют до
        // contains (прежнее поведение маппинга на LIKE).
        if ( in_array($operator, [FilterOperator::CONTAINS, FilterOperator::STARTS_WITH, FilterOperator::ENDS_WITH], true) ) {
            return [
                BitrixFilterOperator::LIKE->prefixField($field)
                => $rangeData[RangeFilterKey::VALUE->value],
            ];
        }
        
        // NOT_CONTAINS: префикс '!%' (NOT LIKE с тем же обрамлением)
        if (FilterOperator::NOT_CONTAINS === $operator) {
            return [
                BitrixFilterOperator::NOT_LIKE->prefixField($field)
                => $rangeData[RangeFilterKey::VALUE->value],
            ];
        }
        
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

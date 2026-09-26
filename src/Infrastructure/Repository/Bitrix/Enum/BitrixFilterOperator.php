<?php

declare(strict_types=1);

namespace Infrastructure\Repository\Bitrix\Enum;

use Domain\Repository\Enum\FilterOperator;
use InvalidArgumentException;

/**
 * Bitrix ORM filter operator prefixes
 *
 * This enum acts as an adapter between domain FilterOperator and Bitrix ORM
 * filter syntax. Bitrix ORM uses prefix notation for comparison operators.
 *
 * @see https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/query/filter.php
 */
enum BitrixFilterOperator: string
{
    /** Equal (no prefix in Bitrix) */
    case EQUALS = '';
    
    /** Not equal */
    case NOT_EQUALS = '!';
    
    /** Greater than */
    case GREATER_THAN = '>';
    
    /** Greater than or equal */
    case GREATER_OR_EQUAL = '>=';
    
    /** Less than */
    case LESS_THAN = '<';
    
    /** Less than or equal */
    case LESS_OR_EQUAL = '<=';
    
    /** LIKE pattern match */
    case LIKE = '%';
    
    /** NOT LIKE pattern match */
    case NOT_LIKE = '!%';
    
    /**
     * Convert domain FilterOperator to Bitrix ORM prefix
     *
     * @param FilterOperator $operator Domain filter operator
     * @return self Bitrix ORM operator
     * @throws InvalidArgumentException If operator cannot be mapped
     */
    public static function fromDomainOperator(FilterOperator $operator): self
    {
        return match ($operator) {
            FilterOperator::EQUALS => self::EQUALS,
            FilterOperator::NOT_EQUALS => self::NOT_EQUALS,
            FilterOperator::GREATER_THAN => self::GREATER_THAN,
            FilterOperator::GREATER_OR_EQUAL => self::GREATER_OR_EQUAL,
            FilterOperator::LESS_THAN => self::LESS_THAN,
            FilterOperator::LESS_OR_EQUAL => self::LESS_OR_EQUAL,
            FilterOperator::CONTAINS,
            FilterOperator::STARTS_WITH,
            FilterOperator::ENDS_WITH => self::LIKE,
            FilterOperator::NOT_CONTAINS => self::NOT_LIKE,
            default => throw new InvalidArgumentException(
                "Operator {$operator->value} cannot be directly mapped to Bitrix ORM"
            ),
        };
    }
    
    /**
     * Build Bitrix ORM filter key with this operator prefix
     *
     * @param string $field Field name
     * @return string Prefixed field name for Bitrix ORM filter
     */
    public function prefixField(string $field): string
    {
        return $this->value . $field;
    }
}

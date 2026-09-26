<?php
declare(strict_types=1);

namespace Presentation\HTTP\Form;

use Domain\Repository\Enum\FilterOperator;
use Domain\Repository\Enum\RangeFilterKey;
use Domain\Repository\FilterInterface;
use Infrastructure\Repository\Bitrix\FilterTransformer;

/**
 * Abstract DTO for transforming HTML form filter data to FilterInterface domain object
 *
 * Responsibilities:
 * - Converts HTML form types to domain types ('Y' → true, string IDs → int)
 * - Validates filter values
 * - Handles nested filters
 * - Removes filter prefixes (filter[...])
 */
abstract class FilterFormDto
{
    public const BOOL_FIELDS = [];
    public const INT_FIELDS = [];
    public const STRING_FIELDS = [];
    
    /**
     * Range filter field definitions
     *
     * Fields listed here will be processed as range filters.
     * Format: ['field_name', 'nested.field_name']
     */
    public const RANGE_INT_FIELDS = [];
    public const RANGE_FLOAT_FIELDS = [];
    public const RANGE_DATE_FIELDS = [];
    
    /**
     * String fields that support operator filtering (contains, not_contains, filled, unfilled)
     *
     * Format: ['field_name', 'nested.field_name']
     */
    public const RANGE_STRING_FIELDS = [];
    
    /**
     * Converts HTML form data to FilterInterface domain object
     *
     * @param array $data Raw form data with filter[...] prefixes
     * @return FilterInterface Clean domain filter object
     */
    abstract public static function fromFormData(array $data): FilterInterface;
    /*{
        // Remove prefixes (filter[active] → active)
        $normalized = FilterTransformer::normalizeFromUi($data);
        
        // Convert booleans ('Y'/'on' → true)
        $normalized = self::convertBooleans($normalized);
        
        // Convert INTs (string → int) (due to all form data being strings)
        $normalized = self::convertIntegers($normalized);
        
        // Add STRING wildcards
        $normalized = self::addStringWildcards($normalized);
        
        // Create EntityFilter from normalized data
        return FilterInterface::fromArray(['filter' => $normalized]);
    }*/
    
    /**
     * Converts JSON form data to FilterInterface domain object
     *
     * @param string $json
     * @return FilterInterface
     */
    abstract public static function fromJsonFormData(string $json): FilterInterface;
    /*{
        $data = json_decode($json, true);
        if ( !is_array($data) ) {
            throw new InvalidArgumentException('Invalid JSON format for form data');
        }
        return static::fromFormData($data);
    }*/
    
    /**
     * Converts EntityFilter to HTML form data (for form population)
     *
     * @param FilterInterface $filter Domain filter object
     * @return array Form data with filter[...] prefixes
     */
    public static function toFormData(FilterInterface $filter): array
    {
        $data = $filter->get();
        
        // Convert booleans to 'on'/'off' for html form checkboxes
        $data = self::processBooleanFields($data, static::BOOL_FIELDS);
        
        // Remove LIKE wildcards from string fields
        $data = self::processStringFields($data, static::STRING_FIELDS);
        
        // Process range filter fields for form display
        $allRangeFields = array_merge(
            static::RANGE_INT_FIELDS,
            static::RANGE_FLOAT_FIELDS,
            static::RANGE_DATE_FIELDS,
            static::RANGE_STRING_FIELDS
        );
        $data = self::processRangeFilterFields($data, $allRangeFields);
        
        // Add filter prefixes
        return FilterTransformer::denormalizeToUi($data);
    }
    
    /**
     * Recursively processes boolean fields, converting true/false to 'on'/'off'
     * Supports nested field definitions with dot notation
     *
     * @param array $data Data array to process (flat with dot notation)
     * @param array $fields Field definitions (simple or nested)
     * @return array Processed data
     */
    protected static function processBooleanFields(array $data, array $fields): array
    {
        foreach ( $fields as $key => $field ) {
            if ( is_int($key) ) {
                // Simple field: 0 => 'active'
                if ( isset($data[$field]) && is_bool($data[$field]) ) {
                    $data[$field] = $data[$field] ? 'on' : 'off';
                }
            } elseif ( is_string($key) && is_array($field) ) {
                // Nested field: 'services' => ['active', 'impact']
                // Handle dot notation: 'services.active'
                foreach ( $field as $nestedKey => $nestedField ) {
                    if ( is_int($nestedKey) ) {
                        // Simple nested field
                        $dotKey = $key . '.' . $nestedField;
                        if ( isset($data[$dotKey]) && is_bool($data[$dotKey]) ) {
                            $data[$dotKey] = $data[$dotKey] ? 'on' : 'off';
                        }
                    } elseif ( is_string($nestedKey) && is_array($nestedField) ) {
                        // Deeper nesting
                        $data = self::processNestedBooleanFields($data, $nestedField, $key . '.' . $nestedKey);
                    }
                }
            }
        }
        return $data;
    }
    
    /**
     * Recursively processes boolean fields in nested structures using dot notation
     */
    protected static function processNestedBooleanFields(array $data, array $fields, string $prefix): array
    {
        foreach ( $fields as $key => $field ) {
            if ( is_int($key) ) {
                // Simple field
                $dotKey = $prefix . '.' . $field;
                if ( isset($data[$dotKey]) && is_bool($data[$dotKey]) ) {
                    $data[$dotKey] = $data[$dotKey] ? 'on' : 'off';
                }
            } elseif ( is_string($key) && is_array($field) ) {
                // Deeper nesting
                $data = self::processNestedBooleanFields($data, $field, $prefix . '.' . $key);
            }
        }
        return $data;
    }
    
    /**
     * Recursively processes string fields, removing LIKE wildcards
     * Supports nested field definitions with dot notation
     *
     * @param array $data Data array to process (flat with dot notation)
     * @param array $fields Field definitions (simple or nested)
     * @return array Processed data
     */
    protected static function processStringFields(array $data, array $fields): array
    {
        foreach ( $fields as $key => $field ) {
            if ( is_int($key) ) {
                // Simple field: 0 => 'title'
                if ( isset($data[$field]) && is_string($data[$field]) ) {
                    $data[$field] = trim($data[$field], '%');
                }
            } elseif ( is_string($key) && is_array($field) ) {
                // Nested field: 'services' => ['title', 'description']
                // Handle dot notation: 'services.title'
                foreach ( $field as $nestedKey => $nestedField ) {
                    if ( is_int($nestedKey) ) {
                        // Simple nested field
                        $dotKey = $key . '.' . $nestedField;
                        if ( isset($data[$dotKey]) && is_string($data[$dotKey]) ) {
                            $data[$dotKey] = trim($data[$dotKey], '%');
                        }
                    } elseif ( is_string($nestedKey) && is_array($nestedField) ) {
                        // Deeper nesting
                        $data = self::processNestedStringFields($data, $nestedField, $key . '.' . $nestedKey);
                    }
                }
            }
        }
        return $data;
    }
    
    /**
     * Recursively processes string fields in nested structures using dot notation
     */
    protected static function processNestedStringFields(array $data, array $fields, string $prefix): array
    {
        foreach ( $fields as $key => $field ) {
            if ( is_int($key) ) {
                // Simple field
                $dotKey = $prefix . '.' . $field;
                if ( isset($data[$dotKey]) && is_string($data[$dotKey]) ) {
                    $data[$dotKey] = trim($data[$dotKey], '%');
                }
            } elseif ( is_string($key) && is_array($field) ) {
                // Deeper nesting
                $data = self::processNestedStringFields($data, $field, $prefix . '.' . $key);
            }
        }
        return $data;
    }
    
    /**
     * Converts boolean fields from HTML form values to bool
     * 'Y' → true, 'N' → false, 'on' → true, '' → false
     * Supports nested field definitions with dot notation
     */
    protected static function convertBooleans(array $data): array
    {
        foreach ( static::BOOL_FIELDS as $key => $field ) {
            if ( is_int($key) ) {
                // Simple field: 0 => 'active'
                if ( array_key_exists($field, $data) ) {
                    $data[$field] = in_array($data[$field], ['Y', 'on', true, 1, '1'], true);
                }
            } elseif ( is_string($key) && is_array($field) ) {
                // Nested field: 'services' => ['active', 'impact']
                // Handle dot notation: 'services.active'
                foreach ( $field as $nestedKey => $nestedField ) {
                    if ( is_int($nestedKey) ) {
                        // Simple nested field
                        $dotKey = $key . '.' . $nestedField;
                        if ( array_key_exists($dotKey, $data) ) {
                            $data[$dotKey] = in_array($data[$dotKey], ['Y', 'on', true, 1, '1'], true);
                        }
                    } elseif ( is_string($nestedKey) && is_array($nestedField) ) {
                        // Deeper nesting
                        $data = self::convertNestedBooleans($data, $nestedField, $key . '.' . $nestedKey);
                    }
                }
            }
        }
        return $data;
    }
    
    /**
     * Recursively converts boolean fields in nested arrays using dot notation
     */
    protected static function convertNestedBooleans(array $data, array $fields, string $prefix): array
    {
        foreach ( $fields as $key => $field ) {
            if ( is_int($key) ) {
                // Simple field
                $dotKey = $prefix . '.' . $field;
                if ( array_key_exists($dotKey, $data) ) {
                    $data[$dotKey] = in_array($data[$dotKey], ['Y', 'on', true, 1, '1'], true);
                }
            } elseif ( is_string($key) && is_array($field) ) {
                // Deeper nesting
                $data = self::convertNestedBooleans($data, $field, $prefix . '.' . $key);
            }
        }
        return $data;
    }
    
    /**
     * Converts ID fields from string to int (or int[])
     * Supports nested field definitions with dot notation
     */
    protected static function convertIntegers(array $data): array
    {
        foreach ( static::INT_FIELDS as $key => $field ) {
            if ( is_int($key) ) {
                // Simple field: 0 => 'id'
                if ( isset($data[$field]) ) {
                    if (is_array($data[$field])) {
                        $data[$field] = array_map('intval', $data[$field]);
                    } elseif (is_numeric($data[$field])) {
                        $data[$field] = (int)$data[$field];
                    }
                }
            } elseif ( is_string($key) && is_array($field) ) {
                // Nested field: 'services' => ['id', 'priority']
                // Handle dot notation: 'services.id'
                foreach ( $field as $nestedKey => $nestedField ) {
                    if ( is_int($nestedKey) ) {
                        // Simple nested field
                        $dotKey = $key . '.' . $nestedField;
                        if ( isset($data[$dotKey]) ) {
                            if (is_array($data[$dotKey])) {
                                $data[$dotKey] = array_map('intval', $data[$dotKey]);
                            } elseif (is_numeric($data[$dotKey])) {
                                $data[$dotKey] = (int)$data[$dotKey];
                            }
                        }
                    } elseif ( is_string($nestedKey) && is_array($nestedField) ) {
                        // Deeper nesting
                        $data = self::convertNestedIntegers($data, $nestedField, $key . '.' . $nestedKey);
                    }
                }
            }
        }
        return $data;
    }
    
    /**
     * Recursively converts integer fields in nested arrays using dot notation
     */
    protected static function convertNestedIntegers(array $data, array $fields, string $prefix): array
    {
        foreach ( $fields as $key => $field ) {
            if ( is_int($key) ) {
                // Simple field
                $dotKey = $prefix . '.' . $field;
                if ( isset($data[$dotKey]) ) {
                    if (is_array($data[$dotKey])) {
                        $data[$dotKey] = array_map('intval', $data[$dotKey]);
                    } elseif (is_numeric($data[$dotKey])) {
                        $data[$dotKey] = (int)$data[$dotKey];
                    }
                }
            } elseif ( is_string($key) && is_array($field) ) {
                // Deeper nesting
                $data = self::convertNestedIntegers($data, $field, $prefix . '.' . $key);
            }
        }
        return $data;
    }
    
    /**
     * Adds % wildcards for STRING fields
     * 'test' → '%test%'
     * Supports nested field definitions with dot notation
     */
    protected static function addStringWildcards(array $data): array
    {
        foreach ( static::STRING_FIELDS as $key => $field ) {
            if ( is_int($key) ) {
                // Simple field: 0 => 'title'
                if (
                    isset($data[$field])
                    && is_string($data[$field])
                    && '' !== trim($data[$field])
                    && !str_contains($data[$field], '%')  // Don't add if already has wildcards
                ) {
                    $data[$field] = '%' . $data[$field] . '%';
                }
            } elseif ( is_string($key) && is_array($field) ) {
                // Nested field: 'services' => ['title', 'description']
                // Handle dot notation: 'services.title'
                foreach ( $field as $nestedKey => $nestedField ) {
                    if ( is_int($nestedKey) ) {
                        // Simple nested field
                        $dotKey = $key . '.' . $nestedField;
                        if (
                            isset($data[$dotKey])
                            && is_string($data[$dotKey])
                            && '' !== trim($data[$dotKey])
                            && !str_contains($data[$dotKey], '%')
                        ) {
                            $data[$dotKey] = '%' . $data[$dotKey] . '%';
                        }
                    } elseif ( is_string($nestedKey) && is_array($nestedField) ) {
                        // Deeper nesting
                        $data = self::addNestedStringWildcards($data, $nestedField, $key . '.' . $nestedKey);
                    }
                }
            }
        }
        return $data;
    }
    
    /**
     * Recursively adds wildcards to string fields in nested arrays using dot notation
     */
    protected static function addNestedStringWildcards(array $data, array $fields, string $prefix): array
    {
        foreach ( $fields as $key => $field ) {
            if ( is_int($key) ) {
                // Simple field
                $dotKey = $prefix . '.' . $field;
                if (
                    isset($data[$dotKey])
                    && is_string($data[$dotKey])
                    && '' !== trim($data[$dotKey])
                    && !str_contains($data[$dotKey], '%')
                ) {
                    $data[$dotKey] = '%' . $data[$dotKey] . '%';
                }
            } elseif ( is_string($key) && is_array($field) ) {
                // Deeper nesting
                $data = self::addNestedStringWildcards($data, $field, $prefix . '.' . $key);
            }
        }
        return $data;
    }
    
    /**
     * Converts range filter fields from HTML form values to RangeFilterValue storage format
     *
     * Handles both nested array format and dot notation format:
     *
     * Input format (nested array from form):
     * 'fieldName' => ['min' => '10', 'max' => '100']
     * 'fieldName' => ['op' => 'gte', 'val' => '50']
     *
     * Input format (dot notation after FilterTransformer):
     * 'fieldName.op' => 'gte', 'fieldName.val' => '50'
     * 'fieldName.min' => '10', 'fieldName.max' => '100'
     *
     * Output format (for Filter):
     * 'fieldName' => ['op' => 'gte', 'val' => 50] (typed values)
     * 'fieldName' => ['op' => 'between', 'min' => 10, 'max' => 100]
     *
     * @param array $data Data array to process
     * @return array Processed data with range filters converted
     */
    protected static function convertRangeFilters(array $data): array
    {
        // First, collect range filter data from dot notation and convert to nested arrays
        $data = self::collectRangeFilterDataFromDotNotation($data, static::RANGE_INT_FIELDS);
        $data = self::collectRangeFilterDataFromDotNotation($data, static::RANGE_FLOAT_FIELDS);
        $data = self::collectRangeFilterDataFromDotNotation($data, static::RANGE_DATE_FIELDS);
        $data = self::collectRangeFilterDataFromDotNotation($data, static::RANGE_STRING_FIELDS);
        
        // Process integer range fields
        foreach ( static::RANGE_INT_FIELDS as $field ) {
            if ( isset($data[$field]) && is_array($data[$field]) ) {
                $converted = self::convertIntRangeFilter($data[$field]);
                if ( null !== $converted ) {
                    $data[$field] = $converted;
                } else {
                    unset($data[$field]);
                }
            }
        }
        
        // Process float range fields
        foreach ( static::RANGE_FLOAT_FIELDS as $field ) {
            if ( isset($data[$field]) && is_array($data[$field]) ) {
                $converted = self::convertFloatRangeFilter($data[$field]);
                if ( null !== $converted ) {
                    $data[$field] = $converted;
                } else {
                    unset($data[$field]);
                }
            }
        }
        
        // Process date range fields
        foreach ( static::RANGE_DATE_FIELDS as $field ) {
            if ( isset($data[$field]) && is_array($data[$field]) ) {
                $converted = self::convertDateRangeFilter($data[$field]);
                if ( null !== $converted ) {
                    $data[$field] = $converted;
                } else {
                    unset($data[$field]);
                }
            }
        }
        
        // Обработка строковых операторных полей (contains, not_contains, filled, unfilled)
        foreach ( static::RANGE_STRING_FIELDS as $field ) {
            if ( isset($data[$field]) && is_array($data[$field]) ) {
                $converted = self::convertStringRangeFilter($data[$field]);
                if ( null !== $converted ) {
                    $data[$field] = $converted;
                } else {
                    unset($data[$field]);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Collects range filter data from dot notation keys into nested arrays
     *
     * Converts:
     * ['actions_count.op' => 'eq', 'actions_count.val' => '5']
     * To:
     * ['actions_count' => ['op' => 'eq', 'val' => '5']]
     *
     * @param array $data Data array with potential dot notation keys
     * @param array $rangeFields List of range field names to process
     * @return array Data array with range filter data collected into nested arrays
     */
    protected static function collectRangeFilterDataFromDotNotation(array $data, array $rangeFields): array
    {
        $rangeKeys = [
            RangeFilterKey::OPERATOR->value,
            RangeFilterKey::VALUE->value,
            RangeFilterKey::MIN->value,
            RangeFilterKey::MAX->value,
        ];
        
        foreach ( $rangeFields as $field ) {
            // Skip if already exists as nested array
            if ( isset($data[$field]) && is_array($data[$field]) ) {
                continue;
            }
            
            $rangeData = [];
            
            // Collect data from dot notation keys
            foreach ( $rangeKeys as $rangeKey ) {
                $dotKey = $field . '.' . $rangeKey;
                if ( isset($data[$dotKey]) ) {
                    $rangeData[$rangeKey] = $data[$dotKey];
                    unset($data[$dotKey]);
                }
            }
            
            // If we collected any range data, store it as nested array
            if ( [] !== $rangeData ) {
                $data[$field] = $rangeData;
            }
        }
        
        return $data;
    }
    
    /**
     * Converts integer range filter data from form values
     *
     * @param array $rangeData Raw range data from form
     * @return array|null Converted range filter or null if empty
     */
    protected static function convertIntRangeFilter(array $rangeData): ?array
    {
        $opKey = RangeFilterKey::OPERATOR->value;
        $valKey = RangeFilterKey::VALUE->value;
        $minKey = RangeFilterKey::MIN->value;
        $maxKey = RangeFilterKey::MAX->value;
        
        // Check for min/max format (between)
        $hasMin = isset($rangeData[$minKey]) && '' !== $rangeData[$minKey] && is_numeric($rangeData[$minKey]);
        $hasMax = isset($rangeData[$maxKey]) && '' !== $rangeData[$maxKey] && is_numeric($rangeData[$maxKey]);
        
        if ( $hasMin && $hasMax ) {
            return [
                $opKey => FilterOperator::BETWEEN->value,
                $minKey => (int) $rangeData[$minKey],
                $maxKey => (int) $rangeData[$maxKey],
            ];
        }
        
        if ( $hasMin ) {
            return [
                $opKey => FilterOperator::GREATER_OR_EQUAL->value,
                $valKey => (int) $rangeData[$minKey],
            ];
        }
        
        if ( $hasMax ) {
            return [
                $opKey => FilterOperator::LESS_OR_EQUAL->value,
                $valKey => (int) $rangeData[$maxKey],
            ];
        }
        
        // Check for operator/value format
        if ( isset($rangeData[$opKey], $rangeData[$valKey]) && '' !== $rangeData[$valKey] && is_numeric($rangeData[$valKey]) ) {
            return [
                $opKey => $rangeData[$opKey],
                $valKey => (int) $rangeData[$valKey],
            ];
        }
        
        return null;
    }
    
    /**
     * Converts float range filter data from form values
     *
     * @param array $rangeData Raw range data from form
     * @return array|null Converted range filter or null if empty
     */
    protected static function convertFloatRangeFilter(array $rangeData): ?array
    {
        $opKey = RangeFilterKey::OPERATOR->value;
        $valKey = RangeFilterKey::VALUE->value;
        $minKey = RangeFilterKey::MIN->value;
        $maxKey = RangeFilterKey::MAX->value;
        
        // Check for min/max format (between)
        $hasMin = isset($rangeData[$minKey]) && '' !== $rangeData[$minKey] && is_numeric($rangeData[$minKey]);
        $hasMax = isset($rangeData[$maxKey]) && '' !== $rangeData[$maxKey] && is_numeric($rangeData[$maxKey]);
        
        if ( $hasMin && $hasMax ) {
            return [
                $opKey => FilterOperator::BETWEEN->value,
                $minKey => (float) $rangeData[$minKey],
                $maxKey => (float) $rangeData[$maxKey],
            ];
        }
        
        if ( $hasMin ) {
            return [
                $opKey => FilterOperator::GREATER_OR_EQUAL->value,
                $valKey => (float) $rangeData[$minKey],
            ];
        }
        
        if ( $hasMax ) {
            return [
                $opKey => FilterOperator::LESS_OR_EQUAL->value,
                $valKey => (float) $rangeData[$maxKey],
            ];
        }
        
        // Check for operator/value format
        if ( isset($rangeData[$opKey], $rangeData[$valKey]) && '' !== $rangeData[$valKey] && is_numeric($rangeData[$valKey]) ) {
            return [
                $opKey => $rangeData[$opKey],
                $valKey => (float) $rangeData[$valKey],
            ];
        }
        
        return null;
    }
    
    /**
     * Converts date range filter data from form values
     *
     * @param array $rangeData Raw range data from form
     * @return array|null Converted range filter or null if empty
     */
    protected static function convertDateRangeFilter(array $rangeData): ?array
    {
        $opKey = RangeFilterKey::OPERATOR->value;
        $valKey = RangeFilterKey::VALUE->value;
        $minKey = RangeFilterKey::MIN->value;
        $maxKey = RangeFilterKey::MAX->value;
        
        // Check for min/max format (between)
        $hasMin = isset($rangeData[$minKey]) && '' !== $rangeData[$minKey];
        $hasMax = isset($rangeData[$maxKey]) && '' !== $rangeData[$maxKey];
        
        if ( $hasMin && $hasMax ) {
            return [
                $opKey => FilterOperator::BETWEEN->value,
                $minKey => $rangeData[$minKey],
                $maxKey => $rangeData[$maxKey],
            ];
        }
        
        if ( $hasMin ) {
            return [
                $opKey => FilterOperator::GREATER_OR_EQUAL->value,
                $valKey => $rangeData[$minKey],
            ];
        }
        
        if ( $hasMax ) {
            return [
                $opKey => FilterOperator::LESS_OR_EQUAL->value,
                $valKey => $rangeData[$maxKey],
            ];
        }
        
        // Check for operator/value format
        if ( isset($rangeData[$opKey], $rangeData[$valKey]) && '' !== $rangeData[$valKey] ) {
            return [
                $opKey => $rangeData[$opKey],
                $valKey => $rangeData[$valKey],
            ];
        }
        
        return null;
    }
    
    /**
     * Converts string operator filter data from form values
     *
     * FILLED/UNFILLED не требуют значения; остальные операторы
     * (contains, not_contains) требуют непустую строку в val.
     *
     * @param array $rangeData Raw range data from form
     * @return array|null Converted range filter or null if empty
     */
    protected static function convertStringRangeFilter(array $rangeData): ?array
    {
        $opKey = RangeFilterKey::OPERATOR->value;
        $valKey = RangeFilterKey::VALUE->value;
        
        if ( !isset($rangeData[$opKey]) ) {
            return null;
        }
        
        // Операторы без значения (заполнено / не заполнено)
        if (
            FilterOperator::FILLED->value === $rangeData[$opKey]
            || FilterOperator::UNFILLED->value === $rangeData[$opKey]
        ) {
            return [$opKey => $rangeData[$opKey]];
        }
        
        // Операторы со значением: значение должно быть непустой строкой
        if (
            isset($rangeData[$valKey])
            && is_string($rangeData[$valKey])
            && '' !== $rangeData[$valKey]
        ) {
            return [
                $opKey => $rangeData[$opKey],
                $valKey => $rangeData[$valKey],
            ];
        }
        
        return null;
    }
    
    /**
     * Processes range filter fields for form output (toFormData)
     * Converts RangeFilterValue storage format back to form-friendly format
     * Preserves operator and value for proper UI restoration
     *
     * @param array $data Data array to process
     * @param array $fields Range field names
     * @return array Processed data with range filters for form display
     */
    protected static function processRangeFilterFields(array $data, array $fields): array
    {
        $opKey = RangeFilterKey::OPERATOR->value;
        $valKey = RangeFilterKey::VALUE->value;
        $minKey = RangeFilterKey::MIN->value;
        $maxKey = RangeFilterKey::MAX->value;
        
        foreach ( $fields as $field ) {
            if ( !isset($data[$field]) || !is_array($data[$field]) ) {
                continue;
            }
            
            $rangeData = $data[$field];
            
            // Skip if no operator defined
            if ( !isset($rangeData[$opKey]) ) {
                continue;
            }
            
            $op = FilterOperator::tryFrom($rangeData[$opKey]);
            if ( null === $op ) {
                continue;
            }
            
            // If it's a between operation, preserve min/max with operator
            if ( FilterOperator::BETWEEN === $op ) {
                $data[$field] = [
                    $opKey => $op->value,
                    $minKey => $rangeData[$minKey] ?? '',
                    $maxKey => $rangeData[$maxKey] ?? '',
                ];
                continue;
            }
            
            // Операторы без значения (filled/unfilled): сохраняем только оператор
            if ( !$op->requiresValue() ) {
                $data[$field] = [$opKey => $op->value];
                continue;
            }
            
            // For other operations, preserve op/val for UI restoration
            if ( isset($rangeData[$valKey]) ) {
                $data[$field] = [
                    $opKey => $op->value,
                    $valKey => $rangeData[$valKey],
                ];
            }
        }
        
        return $data;
    }
}
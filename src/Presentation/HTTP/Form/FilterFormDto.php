<?php
declare(strict_types=1);

namespace Presentation\HTTP\Form;

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
}
<?php
declare(strict_types=1);

namespace Infrastructure\Repository\Bitrix;

/**
 * Transforms filter arrays between UI representation and infrastructure representation
 */
class FilterTransformer
{
    /** Default prefix used in UI forms */
    public const DEFAULT_PREFIX = 'filter';
    
    /**
     * Normalizes filter array from UI format (with custom prefix) to domain format
     *
     * Input:  ['filter[active]' => 'on', 'filter[service][impact]' => [30, 31]]
     * Output: ['active' => 'on', 'service.impact' => [30, 31]]
     *
     * Input:  ['asset_filter[active]' => 'on', 'asset_filter[status]' => [1, 2]]
     * Output: ['active' => 'on', 'status' => [1, 2]]
     *
     * @param array $filterArray Filter array with UI prefixes
     * @param string $prefix Prefix to remove (default: 'filter')
     * @return array Normalized filter array
     */
    public static function normalizeFromUi(array $filterArray, string $prefix = self::DEFAULT_PREFIX): array
    {
        $normalized = [];
        $prefixPattern = $prefix . '[';
        
        foreach ( $filterArray as $key => $value ) {
            // Skip non-string keys
            if ( !is_string($key) ) {
                $normalized[$key] = $value;
                continue;
            }
            
            // Check if key starts with the specified prefix
            if ( str_starts_with($key, $prefixPattern) ) {
                $cleanKey = self::removePrefix($key, $prefix);
                $normalized = self::setNestedValue($normalized, $cleanKey, $value);
            } else {
                $normalized[$key] = $value;
            }
        }
        
        // Convert any remaining nested arrays to dot notation
        $normalized = self::convertArrayToDotNotation($normalized);
        
        return $normalized;
    }
    
    /**
     * Converts nested associative arrays to dot notation
     *
     * Input:  ['active' => 'on', 'service' => ['impact' => [30, 31], 'active' => 'on']]
     * Output: ['active' => 'on', 'service.impact' => [30, 31], 'service.active' => 'on']
     *
     * @param array $array Array with potential nested structures
     * @return array Flattened array with dot notation keys
     */
    public static function convertArrayToDotNotation(array $array): array
    {
        $result = [];
        
        foreach ( $array as $key => $value ) {
            if ( is_array($value) && !self::isIndexedArray($value) ) {
                // Nested associative array - flatten it
                $flattened = self::flattenArray($value, $key);
                foreach ( $flattened as $flatKey => $flatValue ) {
                    $result[$flatKey] = $flatValue;
                }
            } else {
                // Scalar value or indexed array - keep as is
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Denormalizes filter array from domain format to UI format (with custom prefix)
     *
     * default prefix example:
     * Input:  ['active' => 'on', 'service.impact' => [30, 31]]]
     * Output: ['filter[active]' => 'on', 'filter[service][impact]' => [30, 31]]
     *
     * custom prefix example:
     * Input:  ['active' => 'on', 'status' => [1, 2], 'service.impact' => [30, 31]]
     * Output: ['asset_filter[active]' => 'on', 'asset_filter[status]' => [1, 2], 'asset_filter[service][impact]' => [30, 31]]
     *
     * @param array $filterArray Clean filter array (domain format)
     * @param string $prefix Prefix to add (default: 'filter')
     * @return array Filter array with UI prefixes
     */
    public static function denormalizeToUi(array $filterArray, string $prefix = self::DEFAULT_PREFIX): array
    {
        $denormalized = [];
        
        foreach ( $filterArray as $key => $value ) {
            // Skip non-string keys
            if ( !is_string($key) ) {
                $denormalized[$key] = $value;
                continue;
            }
            
            if ( is_array($value) && !self::isIndexedArray($value) ) {
                // Nested associative array - flatten recursively
                $nested = self::flattenArray($value, $key);
                foreach ( $nested as $nestedKey => $nestedValue ) {
                    $uiKey = $prefix . '[' . str_replace('.', '][', $nestedKey) . ']';
                    $denormalized[$uiKey] = $nestedValue;
                }
            } else {
                // Simple value or indexed array - add prefix and convert dots to brackets
                $uiKey = $prefix . '[' . str_replace('.', '][', $key) . ']';
                $denormalized[$uiKey] = $value;
            }
        }
        
        return $denormalized;
    }
    
    /**
     * Checks if array is indexed (not associative)
     *
     * @param array $array Array to check
     * @return bool True if indexed array
     */
    private static function isIndexedArray(array $array): bool
    {
        if ( empty($array) ) {
            return true;
        }
        
        return array_keys($array) === range(0, count($array) - 1);
    }
    
    /**
     * Flattens nested array to dot notation
     *
     * ['service' => ['impact' => [30, 31], 'active' => 'on']]
     * becomes
     * ['service.impact' => [30, 31], 'service.active' => 'on']
     *
     * @param array $array Array to flatten
     * @param string $prefix Current prefix for keys
     * @return array Flattened array with dot notation keys
     */
    private static function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        
        foreach ( $array as $key => $value ) {
            $newKey = '' === $prefix ? $key : $prefix . '.' . $key;
            
            if ( is_array($value) && !self::isIndexedArray($value) ) {
                // Nested associative array - recurse
                $result = array_merge($result, self::flattenArray($value, $newKey));
            } else {
                // Scalar value or indexed array - store
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Removes custom prefix and converts notation to nested array notation
     *
     * 'filter[active]' => 'active'
     * 'filter[service][impact]' => 'service.impact'
     * 'asset_filter[status]' => 'status'
     * 'my_form[field][subfield]' => 'field.subfield'
     *
     * @param string $key Key with prefix
     * @param string $prefix Prefix to remove (e.g., 'filter', 'asset_filter')
     * @return string Clean key with dot notation for nesting
     */
    private static function removePrefix(string $key, string $prefix): string
    {
        // Remove custom prefix (e.g., 'filter[' or 'asset_filter[') and all closing brackets
        $pattern = '/^' . preg_quote($prefix, '/') . '\[/';
        $clean = preg_replace($pattern, '', $key);
        $clean = str_replace('][', '.', $clean);
        $clean = rtrim($clean, ']');
        
        return $clean;
    }
    
    /**
     * Sets value in nested array using dot notation
     *
     * @param array $array Target array
     * @param string $key Dot-notated key (e.g., 'service.impact')
     * @param mixed $value Value to set
     * @return array Modified array
     */
    private static function setNestedValue(array $array, string $key, mixed $value): array
    {
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ( $keys as $i => $k ) {
            if ( $i === count($keys) - 1 ) {
                // Last key - set the value
                if ( isset($current[$k]) && is_array($current[$k]) && is_array($value) ) {
                    // Merge if both are arrays
                    $current[$k] = array_merge($current[$k], $value);
                } else {
                    $current[$k] = $value;
                }
            } else {
                // Intermediate key - ensure it's an array
                if ( !isset($current[$k]) || !is_array($current[$k]) ) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
        
        return $array;
    }
    
    /**
     * Checks if filter array has UI-style prefixes and detects the prefix name
     *
     * @param array $filterArray Filter array to check
     * @return string|null Detected prefix name (e.g., 'filter', 'asset_filter') or null if no prefix found
     */
    public static function detectPrefix(array $filterArray): ?string
    {
        foreach ( array_keys($filterArray) as $key ) {
            if ( is_string($key) && preg_match('/^([a-z_]+)\[/', $key, $matches) ) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Checks if filter array has UI-style prefixes
     *
     * @param array $filterArray Filter array to check
     * @param string|null $prefix Specific prefix to check (null = any prefix)
     * @return bool True if contains prefixes
     */
    public static function hasUiPrefixes(array $filterArray, ?string $prefix = null): bool
    {
        if ( null === $prefix ) {
            return null !== self::detectPrefix($filterArray);
        }
        
        $prefixPattern = $prefix . '[';
        foreach ( array_keys($filterArray) as $key ) {
            if ( is_string($key) && str_starts_with($key, $prefixPattern) ) {
                return true;
            }
        }
        
        return false;
    }
}
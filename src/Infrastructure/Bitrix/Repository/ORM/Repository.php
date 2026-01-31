<?php
declare(strict_types=1);

namespace Infrastructure\Bitrix\Repository\ORM;

use Bitrix\Main\DB\Result;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Domain\Repository\FieldsInterface;
use Domain\Repository\FilterInterface;
use Domain\Repository\GroupInterface;
use Domain\Repository\LimitInterface;
use Domain\Repository\SortInterface;
use Infrastructure\Repository\Bitrix\RangeFilterTranslator;
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
     * @var int
     * Maximum depth for recursive reference fetching
     * Can be overridden in child repositories
     */
    protected static int $maxNestingDepth = 1;
    
    /**
     * @var string[]
     * Entities to exclude from nested fetching to prevent circular dependencies
     * Can be overridden in child repositories
     * @example ['User', 'Profile']
     */
    protected static array $excludedNestedEntities = [];
    
    /**
     * Globally disable nested fetching for this entity
     */
    protected static bool $nestedFetchEnabled = true;
    
    /**
     * @return int
     */
    public static function getMaxNestingDepth(): int
    {
        return self::$maxNestingDepth;
    }
    
    /**
     * @param int $maxNestingDepth
     */
    public static function setMaxNestingDepth(int $maxNestingDepth): void
    {
        self::$maxNestingDepth = $maxNestingDepth;
    }
    
    /**
     * @return bool
     */
    public static function isNestedFetchEnabled(): bool
    {
        return self::$nestedFetchEnabled;
    }
    
    /**
     * @param bool $nestedFetchEnabled
     */
    public static function setNestedFetchEnabled(bool $nestedFetchEnabled): void
    {
        self::$nestedFetchEnabled = $nestedFetchEnabled;
    }
    
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
     * This method is expected to be overridden in child classes
     *
     * @return array<string, string>
     */
    public static function getReferenceFields(): array
    {
        return [];
    }
    
    /**
     * Returns class constants referencing Collections (OneToMany, ManyToMany)
     * This method is expected to be overridden in child classes
     *
     * @return array
     */
    public static function getCollectionReferenceFields(): array
    {
        return [];
    }
    
    /**
     * Returns allowed fields, i.e. 'count'
     * This method is expected to be overridden in child classes
     *
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
        
        if ( empty($fields = $this->mergeFields($fields)) ) {
            $fields = ['*'];
        }
        
        $params['select'] = $this->prepareSelectFields($fields, $sort);
        
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
                
                //if ( is_array($value) ) {
                if ( !isset($value['LOGIC']) ) {
                    
                    foreach ( $value as $conditionValue ) {
                        $logic = mb_strtoupper($conditionValue['LOGIC']);
                        unset($conditionValue['LOGIC']);
                        
                        $cond = [];
                        $cond['LOGIC'] = $logic;
                        
                        foreach ( $conditionValue as $k => $v ) {
                            $cond[$k] = $v;
                        }
                        
                        $preparedFilter[] = $cond;
                    }
                    
                } else {
                    $logic = mb_strtoupper($value['LOGIC']);
                    unset($value['LOGIC']);
                    
                    $cond = [];
                    $cond['LOGIC'] = $logic;
                    
                    foreach ( $value as $k => $v ) {
                        $cond[$k] = $v;
                    }
                    
                    $preparedFilter[] = $cond;
                }
                
                continue;
                
            }
    
            // Handle range filters (e.g., {'op': 'gte', 'val': 5})
            if ( RangeFilterTranslator::isRangeFilter($value) ) {
                $translated = RangeFilterTranslator::translate($key, $value);
                $preparedFilter = array_merge($preparedFilter, $translated);
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
    public function prepareSelectFields1(array $selectFields): array
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
                // @fixme this approach does not allow to fetch Collections (OneToMany and ManyToMany references)
                /*$refFields = !empty(static::getReferenceFields()) ? array_map(
                    fn($refField) => $refField . '_ref',
                    static::getReferenceFields()
                ) : [];
                $preparedFields = array_merge($preparedFields, $refFields);
                continue;*/
            }
            
            if (
                !in_array($fieldName, $fieldsMap)
                && !in_array($fieldName, $allowedFields)
            ) {
                continue;
            }
            
            if ( in_array($fieldName, $referenceFields) ) {
                //$fieldName .= '_ref';
                $fieldName .= '_ref';
            }
            
            if ( str_contains($field, '.') ) {
                $fieldName .= '.' . explode('.', $field)[1];
            }
            
            if ( !is_int($key) ) {
                $preparedFields[$key] = $fieldName;
            } else {
                $preparedFields[] = $fieldName;
                if ( str_ends_with($fieldName, '_ref') ) {
                    $preparedFields[] = $fieldName . '.*';
                }
            }
        }
        
        /*
         * If the sort field is a reference, we need to select all reference fields with `_ref.*`.
         * Otherwise only sorted reference field will be selected.
         */
        foreach ( $this->sort->get() as $sortField => $sortOrder ) {
            if ( str_contains($sortField, '.') ) {
                $sortFieldName = explode('.', $sortField)[0];
            } else {
                $sortFieldName = $sortField;
            }
            
            $sortFieldName = str_replace('_ref', '', $sortFieldName);
            
            if ( in_array($sortFieldName, $referenceFields) ) {
                $refFieldSelect = $sortFieldName . '_ref.*';
                
                if ( !in_array($refFieldSelect, $preparedFields, true) ) {
                    $preparedFields[] = $refFieldSelect;
                }
            }
        }
        
        return $preparedFields;
    }
    
    /**
     * Enhanced prepareSelectFields with nested reference support
     *
     * @param array $selectFields
     * @param bool $includeNestedReferences
     * @return array
     */
    public function prepareSelectFields2(array $selectFields, bool $includeNestedReferences = true): array
    {
        $preparedFields = [];
        
        $fieldsMap = static::getFields();
        $referenceFields = static::getReferenceFields();
        $allowedFields = static::getAllowedFields();
        
        // Track if asterisk was used
        $hasAsterisk = false;
        
        foreach ( $selectFields as $key => $field ) {
            
            if ( str_contains($field, '.') ) {
                $fieldName = explode('.', $field)[0];
            } else {
                $fieldName = $field;
            }
            
            if ( '*' === $fieldName ) {
                $preparedFields[] = '*';
                $hasAsterisk = true;
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
                if (str_ends_with($fieldName, '_ref')) {
                    $preparedFields[] = $fieldName . '.*';
                }
            }
        }
        
        // Add nested references if asterisk was used and feature is enabled
        if (
            $hasAsterisk
            && $includeNestedReferences
            && !empty($referenceFields)
        ) {
            $nestedSelects = static::buildNestedSelects($referenceFields);
            $preparedFields = array_merge($preparedFields, $nestedSelects);
        }
        
        /*
         * If the sort field is a reference, we need to select all reference fields with `_ref.*`.
         * Otherwise only sorted reference field will be selected.
         */
        foreach ( $this->sort->get() as $sortField => $sortOrder ) {
            if (str_contains($sortField, '.')) {
                $sortFieldName = explode('.', $sortField)[0];
            } else {
                $sortFieldName = $sortField;
            }
            
            $sortFieldName = str_replace('_ref', '', $sortFieldName);
            
            if (in_array($sortFieldName, $referenceFields)) {
                $refFieldSelect = $sortFieldName . '_ref.*';
                
                if (!in_array($refFieldSelect, $preparedFields, true)) {
                    $preparedFields[] = $refFieldSelect;
                }
            }
        }
        
        sort($preparedFields);
        
        return array_unique($preparedFields);
    }
    
    /**
     * Prepare select fields with intelligent nested reference expansion
     *
     * @param array $fields
     * @param array $sort
     * @return array
     */
    protected function prepareSelectFields(array $fields = [], array $sort = []): array
    {
        // Detect reference requirements
        $detection = $this->detectRequestedReferences($fields);
        $hasAsterisk = $detection['hasAsterisk'];
        $explicitRefs = $detection['explicitRefs'];
        
        // Apply basic field preparation (existing logic)
        $select = [];
        
        if ( empty($fields) ) {
            $fields = ['*'];
            $hasAsterisk = true;
        }
        
        foreach ( $fields as $alias => $field ) {
            if ( is_numeric($alias) ) {
                $select[] = $field;
            } else {
                $select[$alias] = $field;
            }
        }
        
        // Add reference suffixes for direct references (existing logic)
        $referenceFields = static::getReferenceFields();
        
        foreach ( $referenceFields as $refField ) {
            if ($hasAsterisk || in_array($refField, $fields, true)) {
                $select[] = $refField . '_ref';
            }
        }
        
        // Add collections if asterisk is used (existing logic)
        $collectionFields = static::getCollectionReferenceFields();
        
        if ( $hasAsterisk ) {
            foreach ( $collectionFields as $collField ) {
                $select[] = $collField;
            }
        }
        
        // Add sort fields (existing logic)
        foreach ( $sort as $field => $direction ) {
            if ( !in_array($field, $select, true) ) {
                $select[] = $field;
            }
        }
        
        // Apply nested select expansion
        if ( $this->isNestedFetchEnabled() ) {
            if ( $hasAsterisk ) {
                // Expand ALL references
                $select = $this->expandAllNestedReferences($select);
            } elseif (!empty($explicitRefs)) {
                // Expand ONLY explicitly requested references
                $select = $this->expandExplicitNestedReferences($select, $explicitRefs);
            }
            // Otherwise, no nested expansion (scalar fields only)
        }
        
        return array_unique($select);
    }
    
    /**
     * Detect which references were explicitly requested in the field list
     *
     * @param array $fields Raw field list from caller
     * @return array{hasAsterisk: bool, explicitRefs: array}
     */
    protected function detectRequestedReferences(array $fields): array
    {
        $hasAsterisk = in_array('*', $fields, true);
        $explicitRefs = [];
        
        if ($hasAsterisk) {
            // Asterisk means all references should be expanded
            return ['hasAsterisk' => true, 'explicitRefs' => []];
        }
        
        // Get all reference field names from repository metadata
        $referenceFields = static::getReferenceFields();
        $collectionFields = static::getCollectionReferenceFields();
        $allRefFields = array_merge($referenceFields, $collectionFields);
        
        foreach ($fields as $field) {
            // Check if field is a direct reference name
            if (in_array($field, $allRefFields, true)) {
                $explicitRefs[] = $field;
                continue;
            }
            
            // Check if field is already a reference alias (e.g., 'entity_ref')
            if (str_ends_with($field, '_ref')) {
                $baseField = substr($field, 0, -4);
                if (in_array($baseField, $allRefFields, true)) {
                    $explicitRefs[] = $baseField;
                    continue;
                }
            }
            
            // Check for nested reference requests (e.g., 'entity_ref.entity_ref')
            if (str_contains($field, '.')) {
                $parts = explode('.', $field);
                $rootField = str_ends_with($parts[0], '_ref')
                    ? substr($parts[0], 0, -4)
                    : $parts[0];
                
                if (in_array($rootField, $allRefFields, true)) {
                    $explicitRefs[] = $rootField;
                }
            }
        }
        
        return [
            'hasAsterisk' => false,
            'explicitRefs' => array_unique($explicitRefs)
        ];
    }
    
    /**
     * @param array $filterFields
     * @return array
     */
    public function prepareFilterFields(array $filterFields): array
    {
        $preparedFields = [];
        
        $fieldsMap = static::getFields();
        $referenceFields = static::getReferenceFields();
        $allowedFields = static::getAllowedFields();
        
        foreach ( $filterFields as $field => $fieldValue ) {
            
            if ( str_contains($field, '.') ) {
                $fieldName = explode('.', $field)[0];
            } else {
                $fieldName = $field;
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
            
            $preparedFields[$fieldName] = $fieldValue;
        }
        
        return $preparedFields;
    }
    
    /**
     * Temporary method for merging fields from FieldsInterface and method parameter
     * This method should be removed when `getParams` stops accepting fields parameter
     *
     * @param array $fields
     * @return array
     * @see getParams
     */
    private function mergeFields(array $fields): array
    {
        return array_merge((array)$this->fields->get(), $fields);
    }
    
    /**
     * Get repository class by entity field name
     * Must be implemented by child repositories that support nested fetching
     *
     * @param string $fieldName
     * @return string|null Repository class name or null if not found
     */
    protected static function getRepositoryClassByField(string $fieldName): ?string
    {
        return null;
    }
    
    /**
     * Recursively build nested reference selects
     *
     * @param array $referenceFields List of reference fields to expand
     * @param string $prefix Current path prefix (e.g., 'ticket_ref')
     * @param int $currentDepth Current recursion depth
     * @param array $visitedEntities Track visited entities to prevent circular dependencies
     * @return array Array of select field strings
     */
    protected static function buildNestedSelects(
        array $referenceFields,
        string $prefix = '',
        int $currentDepth = 0,
        array $visitedEntities = []
    ): array {
        if ($currentDepth >= static::$maxNestingDepth) {
            return [];
        }
        
        $selects = [];
        $currentEntity = static::class;
        
        // Prevent circular dependencies
        if (in_array($currentEntity, $visitedEntities)) {
            return [];
        }
        
        $visitedEntities[] = $currentEntity;
        
        foreach ($referenceFields as $fieldName) {
            // Skip excluded entities
            if (in_array($fieldName, static::$excludedNestedEntities)) {
                continue;
            }
            
            // Build current level select
            $currentSelect = $prefix ? "{$prefix}.{$fieldName}_ref" : "{$fieldName}_ref";
            $selects[] = $currentSelect;
            
            // Get repository for this reference field
            $repositoryClass = static::getRepositoryClassByField($fieldName);
            
            if (!$repositoryClass || !method_exists($repositoryClass, 'getReferenceFields')) {
                continue;
            }
            
            // Get nested references
            $nestedReferences = $repositoryClass::getReferenceFields();
            
            if (empty($nestedReferences)) {
                continue;
            }
            
            // Recursively build nested selects
            $nestedSelects = $repositoryClass::buildNestedSelects(
                $nestedReferences,
                $currentSelect,
                $currentDepth + 1,
                $visitedEntities
            );
            
            $selects = array_merge($selects, $nestedSelects);
        }
        
        return $selects;
    }
    
    /**
     * Expand all entity references when asterisk is used
     *
     * @param array $select Current select array
     * @return array Enhanced select with all nested references
     */
    protected function expandAllNestedReferences(array $select): array
    {
        $referenceFields = static::getReferenceFields();
        $collectionFields = static::getCollectionReferenceFields();
        $allRefs = array_merge($referenceFields, $collectionFields);
        
        $maxDepth = $this->getMaxNestingDepth();
        $visited = [static::class => true];
        
        foreach ($allRefs as $refField) {
            $repoClass = static::getRepositoryClassByField($refField);
            
            if (!$repoClass || isset($visited[$repoClass])) {
                continue;
            }
            
            // Build nested selects for this reference
            $nestedSelects = $this->buildNestedSelectsForReference(
                $refField,
                $repoClass,
                $maxDepth,
                $visited
            );
            
            $select = array_merge($select, $nestedSelects);
        }
        
        return $select;
    }
    
    /**
     * Expand only explicitly requested references
     *
     * @param array $select Current select array
     * @param array $explicitRefs List of explicitly requested reference fields
     * @return array Enhanced select with expanded explicit references
     */
    protected function expandExplicitNestedReferences(array $select, array $explicitRefs): array
    {
        $maxDepth = $this->getMaxNestingDepth();
        $visited = [static::class => true];
        
        foreach ($explicitRefs as $refField) {
            // Ensure the base reference is in select
            if (!in_array($refField . '_ref', $select, true)) {
                $select[] = $refField . '_ref';
            }
            
            $repoClass = static::getRepositoryClassByField($refField);
            
            if (!$repoClass || isset($visited[$repoClass])) {
                continue;
            }
            
            // Build nested selects for this specific reference
            $nestedSelects = $this->buildNestedSelectsForReference(
                $refField,
                $repoClass,
                $maxDepth,
                $visited
            );
            
            $select = array_merge($select, $nestedSelects);
        }
        
        return $select;
    }
    
    /**
     * Recursively build nested select statements for a reference field
     *
     * @param string $refField Reference field name
     * @param string $repoClass Repository class for the reference
     * @param int $maxDepth Maximum nesting depth
     * @param array $visited Circular dependency tracker
     * @param string $prefix Current nesting prefix
     * @param int $currentDepth Current recursion depth
     * @return array Nested select statements
     */
    protected function buildNestedSelectsForReference(
        string $refField,
        string $repoClass,
        int $maxDepth,
        array &$visited,
        string $prefix = '',
        int $currentDepth = 0
    ): array {
        if ($currentDepth >= $maxDepth) {
            return [];
        }
        
        if (isset($visited[$repoClass])) {
            return []; // Circular dependency detected
        }
        
        $visited[$repoClass] = true;
        $selects = [];
        
        // Build current level prefix
        $currentPrefix = $prefix ? "{$prefix}.{$refField}_ref" : "{$refField}_ref";
        
        // Add wildcard for current reference level
        $selects[] = "{$currentPrefix}.*";
        
        // Get nested references from target repository
        if (!method_exists($repoClass, 'getReferenceFields')) {
            unset($visited[$repoClass]);
            return $selects;
        }
        
        $nestedRefs = $repoClass::getReferenceFields();
        $nestedCollections = method_exists($repoClass, 'getCollectionReferenceFields')
            ? $repoClass::getCollectionReferenceFields()
            : [];
        
        // Recursively process nested references
        foreach ($nestedRefs as $nestedRef) {
            // ALWAYS add the direct reference first (e.g., ticket_ref.created_by_ref)
            $nestedRefSelect = "{$currentPrefix}.{$nestedRef}_ref";
            //$selects[] = $nestedRefSelect;
            
            // ALWAYS add the wildcard for the reference (e.g., ticket_ref.created_by_ref.*)
            $selects[] = "{$nestedRefSelect}.*";
            
            // Now try to get the repository for deeper recursion
            $nestedRepoClass = $repoClass::getRepositoryClassByField($nestedRef);
            
            // Only recurse if we have a valid repo and haven't hit circular dependency
            if ($nestedRepoClass && !isset($visited[$nestedRepoClass])) {
                $nestedSelects = $this->buildNestedSelectsForReference(
                    $nestedRef,
                    $nestedRepoClass,
                    $maxDepth,
                    $visited,
                    $currentPrefix,
                    $currentDepth + 1
                );
                
                $selects = array_merge($selects, $nestedSelects);
            }
        }
        
        // Add collections at current level (no recursion for collections)
        foreach ($nestedCollections as $collField) {
            $selects[] = "{$currentPrefix}.{$collField}";
            // Bitrix requires at least one field from ManyToMany relations
            $selects[] = "{$currentPrefix}.{$collField}.id";
        }
        
        unset($visited[$repoClass]);
        return $selects;
    }
}
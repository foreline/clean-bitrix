<?php
declare(strict_types=1);

namespace Infrastructure\DI\Bridge;

use CIBlockElement;
use RuntimeException;

/**
 * Helper class for IBlock operations
 */
class BitrixIBlockHelper
{
    public function getElement(int $id): ?array
    {
        if (!class_exists('CIBlockElement')) {
            return null;
        }
        
        $result = CIBlockElement::GetByID($id);
        return $result ? $result->Fetch() : null;
    }
    
    public function getElementsByFilter(array $filter): array
    {
        if (!class_exists('CIBlockElement')) {
            return [];
        }
        
        $elements = [];
        $result = CIBlockElement::GetList([], $filter);
        
        while ($element = $result->Fetch()) {
            $elements[] = $element;
        }
        
        return $elements;
    }
    
    public function addElement(array $fields): int
    {
        if (!class_exists('CIBlockElement')) {
            throw new RuntimeException('IBlock module not available');
        }
        
        $element = new CIBlockElement();
        $id = $element->Add($fields);
        
        if (!$id) {
            throw new RuntimeException('Failed to add IBlock element: ' . $element->LAST_ERROR);
        }
        
        return $id;
    }
    
    public function updateElement(int $id, array $fields): bool
    {
        if (!class_exists('CIBlockElement')) {
            throw new RuntimeException('IBlock module not available');
        }
        
        $element = new CIBlockElement();
        return $element->Update($id, $fields);
    }
}
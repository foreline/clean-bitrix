<?php
declare(strict_types=1);

namespace Unit\Infrastructure\Repository\Bitrix;

use Domain\Repository\Enum\FilterOperator;
use Infrastructure\Repository\Bitrix\RangeFilterTranslator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Infrastructure\Repository\Bitrix\RangeFilterTranslator
 */
class RangeFilterTranslatorTest extends TestCase
{
    /**
     * @return void
     */
    public function testTranslateContains(): void
    {
        // Arrange
        $rangeData = ['op' => FilterOperator::CONTAINS->value, 'val' => 'foo'];
        
        // Act
        $result = RangeFilterTranslator::translate('title', $rangeData);
        
        // Assert (prefix '%' сам обрамляет значение wildcard-ами — передаём сырое)
        $this->assertSame(['%title' => 'foo'], $result);
    }
    
    /**
     * @return void
     */
    public function testTranslateNotContains(): void
    {
        // Arrange
        $rangeData = ['op' => FilterOperator::NOT_CONTAINS->value, 'val' => 'foo'];
        
        // Act
        $result = RangeFilterTranslator::translate('title', $rangeData);
        
        // Assert (prefix '!%' сам обрамляет значение wildcard-ами — передаём сырое)
        $this->assertSame(['!%title' => 'foo'], $result);
    }
    
    /**
     * @return void
     */
    public function testTranslateStartsWith(): void
    {
        // Arrange
        $rangeData = ['op' => FilterOperator::STARTS_WITH->value, 'val' => 'foo'];
        
        // Act
        $result = RangeFilterTranslator::translate('title', $rangeData);
        
        // Assert (STARTS_WITH деградирует до contains — префикс '%' не выражает начало строки)
        $this->assertSame(['%title' => 'foo'], $result);
    }
    
    /**
     * @return void
     */
    public function testTranslateEndsWith(): void
    {
        // Arrange
        $rangeData = ['op' => FilterOperator::ENDS_WITH->value, 'val' => 'foo'];
        
        // Act
        $result = RangeFilterTranslator::translate('title', $rangeData);
        
        // Assert (ENDS_WITH деградирует до contains — префикс '%' не выражает конец строки)
        $this->assertSame(['%title' => 'foo'], $result);
    }
    
    /**
     * @return void
     */
    public function testTranslateFilled(): void
    {
        // Arrange
        $rangeData = ['op' => FilterOperator::FILLED->value];
        
        // Act
        $result = RangeFilterTranslator::translate('title', $rangeData);
        
        // Assert
        $this->assertSame([['!title' => null], ['!title' => '']], $result);
    }
    
    /**
     * @return void
     */
    public function testTranslateUnfilled(): void
    {
        // Arrange
        $rangeData = ['op' => FilterOperator::UNFILLED->value];
        
        // Act
        $result = RangeFilterTranslator::translate('title', $rangeData);
        
        // Assert
        $this->assertSame(
            [['LOGIC' => 'OR', ['=title' => null], ['=title' => '']]],
            $result
        );
    }
    
    /**
     * @return void
     */
    public function testTranslateBetween(): void
    {
        // Arrange
        $rangeData = ['op' => FilterOperator::BETWEEN->value, 'min' => 10, 'max' => 100];
        
        // Act
        $result = RangeFilterTranslator::translate('count', $rangeData);
        
        // Assert
        $this->assertSame(['>=count' => 10, '<=count' => 100], $result);
    }
    
    /**
     * @return void
     */
    public function testTranslateStandardOperator(): void
    {
        // Arrange
        $rangeData = ['op' => FilterOperator::GREATER_OR_EQUAL->value, 'val' => 5];
        
        // Act
        $result = RangeFilterTranslator::translate('count', $rangeData);
        
        // Assert
        $this->assertSame(['>=count' => 5], $result);
    }
    
    /**
     * @return void
     */
    public function testIsRangeFilterAcceptsNewStringOperators(): void
    {
        // Arrange
        $filled = ['op' => FilterOperator::FILLED->value];
        $unfilled = ['op' => FilterOperator::UNFILLED->value];
        $notContains = ['op' => FilterOperator::NOT_CONTAINS->value, 'val' => 'foo'];
        
        // Act
        $isFilled = RangeFilterTranslator::isRangeFilter($filled);
        $isUnfilled = RangeFilterTranslator::isRangeFilter($unfilled);
        $isNotContains = RangeFilterTranslator::isRangeFilter($notContains);
        
        // Assert
        $this->assertSame(true, $isFilled);
        $this->assertSame(true, $isUnfilled);
        $this->assertSame(true, $isNotContains);
    }
    
    /**
     * @return void
     */
    public function testProcessFilterMergesFilledConditionGroup(): void
    {
        // Arrange
        $filter = [
            'title' => ['op' => FilterOperator::FILLED->value],
            'active' => true,
        ];
        
        // Act
        $result = RangeFilterTranslator::processFilter($filter);
        
        // Assert
        $this->assertSame(
            [
                ['!title' => null],
                ['!title' => ''],
                'active' => true,
            ],
            $result
        );
    }
}

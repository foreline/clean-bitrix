<?php
declare(strict_types=1);

namespace Infrastructure\Bitrix\Repository\Iblock;

use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\PropertyTable;
use CIBlock;
use CIBlockProperty;
use Domain\Exception\NotFoundException;
use Exception;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

/**
 *
 */
class Iblock
{
    /**
     * Create IBlock from JSON file
     * @param string $filePath
     * @return void
     * @throws Exception
     */
    public static function load(string $filePath): void
    {
        if ( !file_exists($filePath) ) {
            throw new NotFoundException('File "' . $filePath . '" does not exist');
        }
        
        $iblockJson = file_get_contents($filePath);
        
        $iblockData = json_decode($iblockJson, true);
        
        unset($iblockData['ID']);
        
        // Create Iblock
        $iblock = new CIBlock();
        
        $res = CIBlock::GetList(false, ['CODE' => $iblockData['CODE'], 'IBLOCK_TYPE_ID' => $iblockData['IBLOCK_TYPE_ID']]);
        
        if ( $res && $iblockResult = $res->Fetch() ) {
            $iblockId = $iblockResult['ID'];
        } elseif ( !$iblockId = $iblock->Add($iblockData) ) {
            throw new Exception('Ошибка при создании инфоблока "' . $iblockData['NAME'] . '" ' . $iblock->LAST_ERROR);
        }
        
        // Create Iblock Properties
        foreach ( $iblockData['PROPERTIES'] as $propertyData ) {
            unset($propertyData['ID']);
            
            $propertyData['IBLOCK_ID'] = $iblockId;
            
            $iblockProperty = new CIBlockProperty();
            
            $res = CIBlockProperty::GetList(false, ['IBLOCK_ID' => $iblockId, 'CODE' => $propertyData['CODE']]);
            
            if ( 'E' === $propertyData['PROPERTY_TYPE'] && !empty($propertyData['XML_ID']) ) {
                
                try {
                    $linkIblockId = self::getIblockByCode((string)$propertyData['XML_ID'])['ID'];
                } catch ( NotFoundException $e ) {
                    $linkIblockFilePath = str_replace(
                        pathinfo($filePath)['filename'],
                        (string)$propertyData['XML_ID'],
                        $filePath
                    );
                    // Создаем (загружаем) зависимый инфоблок
                    self::load($linkIblockFilePath);
                    // Повторно пытаемся определить ID зависимого инфоблока
                    $linkIblockId = self::getIblockByCode((string)$propertyData['XML_ID'])['ID'];
                }
                
                $propertyData['LINK_IBLOCK_ID'] = (int)$linkIblockId;
            }
            
            if ( $res && $propertyResult = $res->Fetch() ) {
                if ( !$iblockProperty->Update($propertyResult['ID'], $propertyData) ) {
                    throw new Exception($iblockProperty->LAST_ERROR);
                }
            } elseif ( !$iblockProperty->Add($propertyData) ) {
                throw new Exception($iblockProperty->LAST_ERROR);
            }
        }
        
    }
    
    /**
     * Сохраняет структуру инфоблока (без данных) в JSON файл
     * @param int $iblockId
     * @param string $path
     * @return void
     * @throws Exception
     */
    public static function dump(int $iblockId, string $path): void
    {
        $res = IblockTable::getById($iblockId);
        
        $iblock = $res->fetch();
        
        $filePath = $path . '/' . $iblock['CODE'] . '.json';
        
        // @todo Dump Iblock Properties
        
        $json = json_encode($iblock);
        
        file_put_contents($filePath, $json);
    }
    
    /**
     * Returns JSON representation of Bitrix IBlock structure
     * @param string $iblockCode
     * @return string
     * @throws Exception
     */
    public static function getJson(string $iblockCode): string
    {
        Assert::notEmpty($iblockCode, 'Iblock Code is empty');
        
        $iblock = self::getIblockByCode($iblockCode);
        
        $propertiesRes = PropertyTable::getList([
            'filter' => [
                'IBLOCK_ID' => $iblock['ID']
            ],
            'order' => [
                'SORT' => 'asc'
            ]
        ]);
        
        while ( $property = $propertiesRes->fetch() ) {
            
            if ( 'E' === $property['PROPERTY_TYPE'] ) {
                
                Assert::notEmpty($property['LINK_IBLOCK_ID'], 'LINK_IBLOCK_ID of property "' . $property['CODE'] . '" is empty');
                Assert::greaterThan((int)$property['LINK_IBLOCK_ID'], 0, 'LINK_IBLOCK_ID of property "' . $property['CODE'] . '" must be greater than 0');
                
                $propertyIblockCode = self::getIblockCode((int)$property['LINK_IBLOCK_ID']);
                $property['XML_ID'] = $propertyIblockCode;
            }
            
            $iblock['PROPERTIES'][] = $property;
        }
        
        return json_encode($iblock, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    /**
     * @param int $iblockId
     * @return string
     * @throws Exception
     */
    public static function getIblockCode(int $iblockId): string
    {
        $iblock = self::getIblockById($iblockId);
        return $iblock['CODE'];
    }
    
    /**
     * @param int $iblockId
     * @return array $iblock
     * @throws Exception|InvalidArgumentException
     */
    public static function getIblockById(int $iblockId): array
    {
        Assert::greaterThan($iblockId, 0, 'IBlock ID must be greater than 0. Given ID is "' . $iblockId . '"');
        
        $res = IblockTable::getList([
            'filter' => [
                'ID' => $iblockId,
            ],
            'select' => ['*'],
        ]);
        
        if ( !$res ) {
            throw new NotFoundException('Iblock with ID "' . $iblockId . '" not found');
        }
        
        if ( !$iblock = $res->fetch() ) {
            throw new Exception('Iblock with ID "' . $iblockId . '" could not be fetched');
        }
        
        return $iblock;
    }
    
    /**
     * @param string $iblockCode
     * @return array $iblock
     * @throws Exception
     */
    public static function getIblockByCode(string $iblockCode): array
    {
        Assert::notEmpty($iblockCode, 'Iblock code is empty');
        
        $res = IblockTable::getList([
            'filter' => [
                'CODE' => $iblockCode,
            ],
            'select' => ['*'],
        ]);
        
        if ( !$iblock = $res?->fetch() ) {
            throw new NotFoundException('Iblock with code "' . $iblockCode . '" not found');
        }
        
        return $iblock;
    }
}
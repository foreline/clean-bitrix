<?php
    declare(strict_types=1);
    
    namespace Infrastructure\Bitrix\Repository\Iblock;
    
    use Bitrix\Iblock\IblockTable;
    use Bitrix\Iblock\PropertyTable;
    use Bitrix\Main\ArgumentException;
    use Bitrix\Main\ObjectPropertyException;
    use Bitrix\Main\SystemException;
    use CIBlock;
    use CIBlockProperty;
    use Domain\Exception\NotFoundException;
    use Exception;
    
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
                throw new NotFoundException('Файл "' . $filePath . '" не существует');
            }
            
            $iblockJson = file_get_contents($filePath);
            
            $iblockData = json_decode($iblockJson, true);
            
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
                $propertyData['IBLOCK_ID'] = $iblockId;
                
                $iblockProperty = new CIBlockProperty();
                
                $res = CIBlockProperty::GetList(false, ['IBLOCK_ID' => $iblockId, 'CODE' => $propertyData['CODE']]);
    
                if ( 'E' === $propertyData['PROPERTY_TYPE'] && !empty($propertyData['XML_ID']) ) {
                    $linkIblockId = self::getIblockByCode((string)$propertyData['XML_ID'])['ID'];
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
         * @return array
         * @throws Exception
         */
        public static function getIblockById(int $iblockId): array
        {
            $res = IblockTable::getList([
                'filter' => [
                    'ID' => $iblockId,
                ],
                'select' => ['*'],
            ]);
    
            if ( !$res ) {
                throw new Exception('Инфоблок с ID ' . $iblockId . ' не найден');
            }
    
            return $res->fetch();
        }
    
        /**
         * @param string $iblockCode
         * @return array
         * @throws Exception
         */
        public static function getIblockByCode(string $iblockCode): array
        {
            $res = IblockTable::getList([
                'filter' => [
                    'CODE' => $iblockCode,
                ],
                'select' => ['*'],
            ]);
    
            if ( !$res ) {
                throw new Exception('Инфоблок с кодом ' . $iblockCode . ' не найден');
            }
    
            return $res->fetch();
        }
    }
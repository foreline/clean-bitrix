<?php
    declare(strict_types=1);
    
    namespace Infrastructure\Bitrix\Repository\Iblock;

    use Bitrix\Iblock\IblockTable;
    use Bitrix\Iblock\ElementTable;
    use Domain\Exception\NotFoundException;
    use Exception;

    /**
     *
     */
    class IblockElement
    {
        /**
         * @param string $filePath
         * @param string $iblockCode
         * @return void
         * @throws NotFoundException
         */
        public static function load(string $filePath, string $iblockCode): void
        {
            if ( !file_exists($filePath) ) {
                throw new NotFoundException('Файл "' . $filePath . '" не существует');
            }
    
            $iblockJson = file_get_contents($filePath);
    
            $iblockData = json_decode($iblockJson, true);
    
            // Get Entity By Iblock Code
            
        }
    
        /**
         * @param int $iblockId
         * @param string $path
         * @return void
         */
        public static function dump(int $iblockId, string $path): void
        {
        
        }
    
        /**
         * @param string $iblockCode
         * @return string
         * @throws Exception
         */
        public static function getJson(string $iblockCode): string
        {
            $res = IblockTable::getList([
                'filter' => [
                    'CODE' => $iblockCode,
                ]
            ]);
    
            if ( !$res ) {
                throw new Exception('Инфоблок с кодом ' . $iblockCode . ' не найден');
            }
    
            $iblock = $res->fetch();
    
            $elementsRes = ElementTable::getList([
                'filter' => [
                    'IBLOCK_ID' => $iblock['ID']
                ],
                'order' => [
                    'SORT' => 'asc',
                    'ID' => 'asc',
                ]
            ]);
    
            $elements = [];
    
            while ( $element = $elementsRes->fetch() ) {
                $elements[] = $element;
            }
    
            return json_encode($elements, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    }
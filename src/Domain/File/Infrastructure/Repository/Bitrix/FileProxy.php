<?php
    declare(strict_types=1);
    
    namespace Domain\File\Infrastructure\Repository\Bitrix;
    
    use Domain\File\Aggregate\File;
    
    class FileProxy
    {
        public const ID = 'ID';
        public const NAME = 'NAME';
        public const DESCRIPTION = 'DESCRIPTION';
        public const ORIGINAL_NAME = 'ORIGINAL_NAME';
        public const FILE_SIZE = 'FILE_SIZE';
        public const FILE_NAME = 'FILE_NAME';
        
        /**
         * @param mixed $obj
         * @return File
         */
        public function objectToEntity(mixed $obj): File
        {
            $file = new File();
            $file
                ->setId((int)$obj->get(self::ID))
                ->setFileName((string)$obj->get(self::NAME))
                ->setOriginalName((string)$obj->get(self::ORIGINAL_NAME));
            return $file;
        }
        
        /**
         * @param array $data
         * @return File
         */
        public function arrayToEntity(array $data): File
        {
            $file = new File();
            
            if ( array_key_exists(self::ID, $data) ) {
                $file->setId((int)$data[self::ID]);
            }
            if ( array_key_exists(self::FILE_SIZE, $data) ) {
                $file->setSize((int)$data[self::FILE_SIZE]);
            }
            if ( array_key_exists(self::FILE_NAME, $data) ) {
                $file->setFileName((string)$data[self::FILE_NAME]);
            }
            if ( array_key_exists(self::ORIGINAL_NAME, $data) ) {
                $file->setOriginalName((string)$data[self::ORIGINAL_NAME]);
            }
            if ( !empty($data['SUBDIR']) ) {
                $source = '/' . $this->getUploadDir() . '/' . $data['SUBDIR'] . '/' . $file->getFileName();
                $file->setSource($source);
                $file->setPath($_SERVER['DOCUMENT_ROOT'] . '/' . $file->getSource());
            }
            if ( array_key_exists(self::DESCRIPTION, $data) ) {
                $file->setDescription((string)$data[self::DESCRIPTION]);
            }
            
            //$file->setSlug();
            
            return $file;
        }
    }
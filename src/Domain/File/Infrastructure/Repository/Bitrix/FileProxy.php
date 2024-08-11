<?php
    declare(strict_types=1);
    
    namespace Domain\File\Infrastructure\Repository\Bitrix;
    
    use Domain\File\Aggregate\File;
    use Domain\File\Infrastructure\Repository\FileRepositoryInterface;

    class FileProxy
    {
        /**
         * @param mixed $obj
         * @return File
         */
        public function objectToEntity(mixed $obj): File
        {
            $file = new File();
            $file
                ->setId((int)$obj->get(FileRepositoryInterface::ID))
                ->setFileName((string)$obj->get(FileRepositoryInterface::NAME))
                ->setOriginalName((string)$obj->get(FileRepositoryInterface::ORIGINAL_NAME));
            return $file;
        }
        
        /**
         * @param array $data
         * @return File
         */
        public function arrayToEntity(array $data): File
        {
            $file = new File();
            
            if ( array_key_exists(FileRepositoryInterface::ID, $data) ) {
                $file->setId((int)$data[FileRepositoryInterface::ID]);
            }
            if ( array_key_exists(FileRepositoryInterface::FILE_SIZE, $data) ) {
                $file->setSize((int)$data[FileRepositoryInterface::FILE_SIZE]);
            }
            if ( array_key_exists(FileRepositoryInterface::FILE_NAME, $data) ) {
                $file->setFileName((string)$data[FileRepositoryInterface::FILE_NAME]);
            }
            if ( array_key_exists(FileRepositoryInterface::ORIGINAL_NAME, $data) ) {
                $file->setOriginalName((string)$data[FileRepositoryInterface::ORIGINAL_NAME]);
            }
            if ( !empty($data['SUBDIR']) ) {
                $source = '/' . $this->getUploadDir() . '/' . $data['SUBDIR'] . '/' . $file->getFileName();
                $file->setSource($source);
                $file->setPath($_SERVER['DOCUMENT_ROOT'] . '/' . $file->getSource());
            }
            if ( array_key_exists(FileRepositoryInterface::DESCRIPTION, $data) ) {
                $file->setDescription((string)$data[FileRepositoryInterface::DESCRIPTION]);
            }
            
            //$file->setSlug();
            
            return $file;
        }
    }
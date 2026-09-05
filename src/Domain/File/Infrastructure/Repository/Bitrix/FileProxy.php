<?php
declare(strict_types=1);

namespace Domain\File\Infrastructure\Repository\Bitrix;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\EO_File;
use Bitrix\Main\FileTable;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use COption;
use DateTimeImmutable;
use Domain\File\Aggregate\File;
use Domain\File\Infrastructure\Repository\FileRepositoryInterface;
use Exception;

/**
 *
 */
class FileProxy
{
    /**
     * Преобразует File в ORM объект EO_File
     * @param File $file
     * @return EO_File
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function entityToOrmObject(File $file): EO_File
    {
        if ( 0 < $file->getId() ) {
            // Load existing ORM object with ALL actual values from DB.
            // This enables ORM-native dirty-checking: only changed fields will be included in UPDATE.
            $ormObject = FileTable::getById($file->getId())->fetchObject();
        } else {
            $ormObject = new EO_File();
        }
        
        // MODULE_ID
        if ( null !== $file->getModuleId() ) {
            $ormObject->setModuleId($file->getModuleId());
        }
        
        // HEIGHT
        if ( null !== $file->getHeight() ) {
            $ormObject->setHeight($file->getHeight());
        }
        
        // WIDTH
        if ( null !== $file->getWidth() ) {
            $ormObject->setWidth($file->getWidth());
        }
        
        // FILE_SIZE
        if ( null !== $file->getSize() ) {
            $ormObject->setFileSize($file->getSize());
        }
        
        // CONTENT_TYPE
        if ( null !== $file->getContentType() ) {
            $ormObject->setContentType($file->getContentType());
        }
        
        // SUBDIR
        if ( null !== $file->getSubdir() ) {
            $ormObject->setSubdir($file->getSubdir());
        }
        
        // FILE_NAME
        if ( null !== $file->getFileName() ) {
            $ormObject->setFileName($file->getFileName());
        }
        
        // ORIGINAL_NAME
        if ( null !== $file->getOriginalName() ) {
            $ormObject->setOriginalName($file->getOriginalName());
        }
        
        // DESCRIPTION
        if ( null !== $file->getDescription() ) {
            $ormObject->setDescription($file->getDescription());
        }
        
        // HANDLER_ID
        if ( null !== $file->getHandlerId() ) {
            $ormObject->setHandlerId($file->getHandlerId());
        }
        
        // EXT_ID
        if ( null !== $file->getExtId() ) {
            $ormObject->setExternalId($file->getExtId());
        }
        
        return $ormObject;
    }
    
    /**
     * @param mixed $obj
     * @return File
     * @throws Exception
     */
    public function objectToEntity(mixed $obj): File
    {
        $file = new File();
        
        // ID
        if ( null !== ( $id = $obj->get(FileRepositoryInterface::ID) ) ) {
            $file->setId((int)$id);
        }
        
        // DATE_CREATED
        if ( null !== ( $dateCreated = $obj->get(FileRepositoryInterface::DATE_CREATED) ) ) {
            if ( !empty((string)$dateCreated->format('Y-m-d H:i:s')) ) {
                $file->setDateCreated((new DateTimeImmutable((string)$dateCreated->format('Y-m-d H:i:s'))));
            }
        }
        
        // MODULE_ID
        if ( null !== ( $moduleId = $obj->get(FileRepositoryInterface::MODULE_ID) ) ) {
            $file->setModuleId((string)$moduleId);
        }
        
        // HEIGHT
        if ( null !== ( $height = $obj->get(FileRepositoryInterface::HEIGHT) ) ) {
            $file->setHeight((int)$height);
        }
        
        // WIDTH
        if ( null !== ( $width = $obj->get(FileRepositoryInterface::WIDTH) ) ) {
            $file->setWidth((int)$width);
        }
        
        // FILE_SIZE
        if ( null !== ( $fileSize = $obj->get(FileRepositoryInterface::FILE_SIZE) ) ) {
            $file->setSize((int)$fileSize);
        }
        
        // CONTENT_TYPE
        if ( null !== ( $contentType = $obj->get(FileRepositoryInterface::CONTENT_TYPE) ) ) {
            $file->setContentType((string)$contentType);
        }
        
        // SUBDIR
        if ( null !== ( $subdir = $obj->get(FileRepositoryInterface::SUBDIR) ) ) {
            $file->setSubdir((string)$subdir);
        }
        
        // FILE_NAME
        if ( null !== ( $fileName = $obj->get(FileRepositoryInterface::FILE_NAME) ) ) {
            $file->setFileName((string)$fileName);
        }
        
        // SOURCE / PATH — необходимы для чтения содержимого файла (File::getContent()/getHash())
        if ( '' !== $file->getSubdir() && '' !== $file->getFileName() ) {
            $source = '/' . $this->getUploadDir() . '/' . $file->getSubdir() . '/' . $file->getFileName();
            $file->setSource($source);
            $file->setPath($_SERVER['DOCUMENT_ROOT'] . $source);
        }
        
        // ORIGINAL_NAME
        if ( null !== ( $originalName = $obj->get(FileRepositoryInterface::ORIGINAL_NAME) ) ) {
            $file->setOriginalName((string)$originalName);
        }
        
        // DESCRIPTION
        if ( null !== ( $description = $obj->get(FileRepositoryInterface::DESCRIPTION) ) ) {
            $file->setDescription((string)$description);
        }
        
        // HANDLER_ID
        if ( null !== ( $handlerId = $obj->get(FileRepositoryInterface::HANDLER_ID) ) ) {
            $file->setHandlerId((string)$handlerId);
        }
        
        // EXT_ID
        if ( null !== ( $extId = $obj->get(FileRepositoryInterface::EXT_ID) ) ) {
            $file->setExtId((string)$extId);
        }
        
        return $file;
    }
    
    /**
     * @param array $data
     * @return File
     */
    public function arrayToEntity(array $data): File
    {
        $data = array_change_key_case($data, CASE_LOWER);
        
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
        if ( !empty($data['subdir']) ) {
            $source = '/' . $this->getUploadDir() . '/' . $data['subdir'] . '/' . $file->getFileName();
            $file->setSource($source);
            $file->setPath($_SERVER['DOCUMENT_ROOT'] . '/' . $file->getSource());
        }
        if ( array_key_exists(FileRepositoryInterface::DESCRIPTION, $data) ) {
            $file->setDescription((string)$data[FileRepositoryInterface::DESCRIPTION]);
        }
        if ( array_key_exists(FileRepositoryInterface::EXT_ID, $data) ) {
            $file->setExtId((string)$data[FileRepositoryInterface::EXT_ID]);
        }
        
        return $file;
    }
    
    /**
     * Директория загрузки Bitrix (относительно DOCUMENT_ROOT)
     * @return string
     */
    protected function getUploadDir(): string
    {
        return COption::GetOptionString('main', 'upload_dir', 'upload');
    }
}
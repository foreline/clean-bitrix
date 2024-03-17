<?php
    declare(strict_types=1);
    
    namespace Domain\File\Infrastructure\Repository\Bitrix;
    
    use Bitrix\Main\DB\Result;
    use Bitrix\Main\Entity\ExpressionField;
    use Bitrix\Main\FileTable;
    use CFile;
    use COption;
    use Domain\File\Aggregate\File;
    use Domain\File\Aggregate\FileCollection;
    use Domain\File\Infrastructure\Repository\FileRepositoryInterface;
    use Exception;
    
    /**
     * File Repository
     */
    class FileRepository extends FileProxy implements FileRepositoryInterface
    {
        /** @var Result|null  */
        private ?Result $result = null;
        
        /** @var string  */
        private string $uploadDir;
        
        /**
         *
         */
        public function __construct()
        {
            $this->uploadDir = COption::GetOptionString('main', 'upload_dir', 'upload');
        }
        
        /**
         * @return string
         */
        public function getUploadDir(): string
        {
            return $this->uploadDir;
        }
        
        /**
         * @param array $filter
         * @param array $sort
         * @param array $limit
         * @param array $fields
         * @return $this
         * @throws Exception
         * @noinspection PhpTooManyParametersInspection
         * @noinspection PhpSameParameterValueInspection
         */
        private function query(array $filter = [], array $sort = [], array $limit = [], array $fields = []): self
        {
            $select = 0 < count($fields) ? $fields : ['*'];
            
            $params = [
                'select'    => $select,
                'cache' => [
                    'ttl'   => 3600,
                    'cache_joins'   => true,
                ],
                'count_total'   => true
            ];
            
            if ( 0 < count($filter) ) {
                $params['filter'] = $filter;
            }
            
            if ( 0 < count($sort) ) {
                $sort = array_change_key_case($sort, CASE_UPPER);
                $params['order'] = $sort;
                
                if ( array_key_exists('RAND', $sort) ) {
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
            
            $this->result = FileTable::getList($params);
            
            return $this;
        }
        
        /**
         * @param File $file
         * @return File
         * @throws Exception
         */
        public function persist(File $file): File
        {
            if ( 0 < $file->getId() ) {
                $this->update($file);
            } else {
                $fileId = $this->create($file);
                $file->setId($fileId);
            }
            return $this->findById($file->getId());
        }
        
        /**
         * @param File $file
         * @return int
         * @throws Exception
         */
        private function create(File $file): int
        {
            $data = [
                'name'          => $file->getName(),
                'size'          => $file->getSize(),
                'tmp_name'      => $file->getTmpName(),
                'description'   => $file->getDescription(),
            ];
            
            if ( $file->isRemoved() ) {
                // @fixme
                $data['del'] = 'Y';
                $data['old_file'] = $file->getId();
            }
            
            if ( !$fileId = CFile::SaveFile($data, 'files'/*, checkDuplicates: false*/) ) {
                throw new Exception('Ошибка при регистрации файла');
            }
            
            return (int)$fileId;
        }
        
        /**
         * @param File $file
         * @return bool
         */
        private function update(File $file): bool
        {
            // @fixme
            CFile::UpdateDesc($file->getId(), $file->getDescription());
            
            return true;
        }
        
        /**
         * @param array $filter
         * @param array $sort
         * @param array $limit
         * @return ?FileCollection
         * @throws Exception
         */
        public function find(array $filter = [], array $sort = [], array $limit = []): ?FileCollection
        {
            if ( !$this->query($filter, $sort, $limit) ) {
                return null;
            }
            
            if ( !$this->result ) {
                return null;
            }
            
            $files = new FileCollection();
            
            while ( $file = $this->fetch() ) {
                $files->addItem($file);
            }
            
            if ( 0 === $files->getCount() ) {
                return null;
            }
            
            return $files;
        }
        
        /**
         * @return File|null
         */
        private function fetch(): ?File
        {
            if ( !$this->result ) {
                return null;
            }
            
            if ( !$data = $this->result->fetch() ) {
                return null;
            }
            
            return $this->arrayToEntity($data);
        }
        
        /**
         * @param int $fileId
         * @return File|null
         * @throws Exception
         */
        public function findById(int $fileId): ?File
        {
            if ( !$files = $this->find(['=id' => $fileId]) ) {
                return null;
            }
            return $files->current();
        }
        
        /**
         * @param int $fileId
         * @return bool
         * @throws Exception
         */
        public function delete(int $fileId): bool
        {
            $result = FileTable::delete($fileId);
            
            if ( !$result->isSuccess() ) {
                return false;
            }
            return true;
        }
        
        /**
         * @return void
         */
        public function startTransaction(): void
        {
            // TODO: Implement startTransaction() method.
        }
        
        /**
         * @return void
         */
        public function commitTransaction(): void
        {
            // TODO: Implement commitTransaction() method.
        }
        
        /**
         * @return void
         */
        public function rollbackTransaction(): void
        {
            // TODO: Implement rollbackTransaction() method.
        }
        
        /**
         * @return int
         */
        public function getTotalCount(): int
        {
            // TODO: Implement getTotalCount() method.
            return 0; // @fixme
        }
    }
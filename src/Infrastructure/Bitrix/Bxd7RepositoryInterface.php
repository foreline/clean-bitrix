<?php
    declare(strict_types=1);
    
    namespace Infrastructure\Bitrix;

    use Exception;

    /**
     * Интерфейс репозитория Bxd7
     */
    interface Bxd7RepositoryInterface
    {
        /**
         * @param array $filter
         * @param array $sort
         * @param array $limit
         * @param array $fields
         * @return mixed
         * @noinspection PhpMissingReturnTypeInspection
         */
        public function find(array $filter = [], array $sort = [], array $limit = [], array $fields = []);
    
        /**
         * @param array $fields
         * @param array $filter
         * @param array $sort
         * @param array $limit
         * @return mixed
         * @noinspection PhpMissingReturnTypeInspection
         */
        public function findFields(array $fields = [], array $filter = [], array $sort = [], array $limit = []);
    
        /**
         * @param int $id
         * @param array $fields
         * @return mixed
         */
        public function findById(int $id, array $fields = []);
    
        /**
         * Вызывает метод create($entity) или update($entity) в зависимости от значения $entity->getId()
         * @param mixed $entity
         * @return mixed
         */
        public function persist(mixed $entity);
    
        /**
         * Возвращает количество элементов попавших под фильтр
         * @return int|null
         * @throws Exception
         */
        public function getSelectedRowsCount(): ?int;
    
        /**
         * Возвращает общее количество элементов
         * @return int|null
         */
        public function getTotalCount(): ?int;
        
    }
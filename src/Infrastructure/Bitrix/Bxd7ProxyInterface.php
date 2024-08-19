<?php
declare(strict_types=1);

namespace Infrastructure\Bitrix;

use Domain\Entity\EntityInterface;

/**
 * Интерфейс для Repository Proxy
 */
interface Bxd7ProxyInterface
{
    /*public const ID = 'id';
    public const NAME = 'name';
    public const SORT = 'sort';
    public const CODE = 'code';
    public const ACTIVE = 'active';
    public const CREATED_BY = 'created_by';
    public const DATE_CREATED = 'date_create';
    public const MODIFIED_BY = 'modified_by';
    public const DATE_MODIFIED = 'timestamp_x';*/
    
    public function __construct();
    /**
     * Преобразует сущность в массив для сохранения в БД
     * @return array $data
     */
    public function entityToArray(): array;

    /**
     * Преобразует объект, полученный из ORM в сущность
     * @param mixed $obj ORM object
     * @return ?EntityInterface $entity
     */
    public function objectToEntity(mixed $obj): ?EntityInterface;
}
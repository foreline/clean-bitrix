<?php
declare(strict_types=1);

namespace Infrastructure\Bitrix\Repository\ORM;

use Domain\Entity\EntityInterface;

/**
 *
 */
interface ProxyInterface
{
    /*public const ID = 'id';
    public const NAME = 'name';
    public const SORT = 'sort';
    public const CODE = 'code';
    public const ACTIVE = 'active';
    public const CREATED_BY = 'created_by';
    public const DATE_CREATED = 'date_created';
    public const MODIFIED_BY = 'modified_by';
    public const DATE_MODIFIED = 'date_modified';*/

    /**
     *
     */
    //public function __construct();
    
    /**
     * Преобразует сущность в объект ORM
     */
    public function entityToOrmObject(EntityInterface $entity);

    /**
     * Преобразует объект, полученный из ORM в сущность
     * @param mixed $obj ORM object
     * @return ?EntityInterface $entity
     */
    public function objectToEntity(mixed $obj): ?EntityInterface;
}
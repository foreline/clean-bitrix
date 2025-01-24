<?php
declare(strict_types=1);

namespace Bitrix\Repository\ORM;

use Infrastructure\Bitrix\Repository\ORM\Repository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class ConcreteRepository extends Repository
{
    public const ID = 'id';
    public const REFERENCE_ENTITY = 'reference_entity';
    
    public static function getReferenceFields(): array
    {
        return [
            self::REFERENCE_ENTITY,
        ];
    }
    
    
}

/**
 *
 */
class RepositoryTest extends TestCase
{
    private ConcreteRepository $repository;
    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->repository = new ConcreteRepository();
        parent::setUp();
    }
    
    /**
     * @return void
     */
    public function testPrepareSelectFields(): void
    {
        // Arrange
        $selectFields = [
            ConcreteRepository::ID,
            ConcreteRepository::REFERENCE_ENTITY,
            ConcreteRepository::REFERENCE_ENTITY . '.name',
            'NON_EXISTENT',
        ];
        
        $expectedFields = [
            ConcreteRepository::ID,
            ConcreteRepository::REFERENCE_ENTITY . '_ref',
            ConcreteRepository::REFERENCE_ENTITY . '_ref' . '.name'
        ];
        
        // Act
        $preparedFields = $this->repository->prepareSelectFields($selectFields);
        
        // Assert
        static::assertEquals($expectedFields, $preparedFields);
    }
}

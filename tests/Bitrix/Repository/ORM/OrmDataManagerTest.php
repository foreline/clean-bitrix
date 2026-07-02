<?php
declare(strict_types=1);

namespace Bitrix\Repository\ORM;

use Bitrix\Main\DB\Connection;
use Infrastructure\Bitrix\Repository\ORM\OrmDataManager;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Stubs for Bitrix ORM field classes.
 * They are aliased into Bitrix namespaces so instanceof checks in OrmDataManager work.
 */
class StubScalarField
{
    public function __construct(
        protected string $name,
        protected bool $nullable = false,
        protected mixed $defaultValue = null
    ) {
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function isNullable(): bool
    {
        return $this->nullable;
    }
    
    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }
    
    public function configureDefaultValue(mixed $value): static
    {
        $this->defaultValue = $value;
        return $this;
    }
    
    public function configureNullable(bool $nullable): static
    {
        $this->nullable = $nullable;
        return $this;
    }
}

class StubBooleanField extends StubScalarField
{
}

class StubIntegerField extends StubScalarField
{
}

class StubStringField extends StubScalarField
{
}

class StubFloatField extends StubScalarField
{
}

class_alias(StubScalarField::class, 'Bitrix\\Main\\ORM\\Fields\\ScalarField');
class_alias(StubBooleanField::class, 'Bitrix\\Main\\ORM\\Fields\\BooleanField');
class_alias(StubIntegerField::class, 'Bitrix\\Main\\ORM\\Fields\\IntegerField');
class_alias(StubStringField::class, 'Bitrix\\Main\\ORM\\Fields\\StringField');
class_alias(StubFloatField::class, 'Bitrix\\Main\\ORM\\Fields\\FloatField');

/**
 * Test model with default values.
 */
class TestDefaultModel
{
    private bool $active = true;
    private bool $isDeleted = false;
    private int $count = 0;
    private int $sort = 100;
    private string $status = 'pending';
    private string $code = '';
    private ?string $description = null;
}

/**
 * Test OrmDataManager subclass exposing protected helpers.
 */
class TestOrmDataManager extends OrmDataManager
{
    protected static ?string $modelClass = TestDefaultModel::class;
    protected static array $fieldPropertyMap = [];
    
    public static function getTableName(): string
    {
        return 'test_orm_data_manager';
    }
    
    public static function publicGetFieldDefaultValue(mixed $field): mixed
    {
        return static::getFieldDefaultValue($field);
    }
    
    public static function publicNormalizeName(string $name): string
    {
        return static::normalizeName($name);
    }
    
    public static function publicFormatDefaultValue(mixed $value, mixed $field): string
    {
        return static::formatDefaultValue($value, $field);
    }
    
    public static function publicDefaultsEqual(mixed $actualDefault, mixed $expectedValue, mixed $field): bool
    {
        return static::defaultsEqual($actualDefault, $expectedValue, $field);
    }
    
    public static function publicGetSqlField(mixed $field, mixed $connection): string
    {
        return static::getSqlField($field, $connection);
    }
    
    public static function publicSetFieldPropertyMap(array $map): void
    {
        static::$fieldPropertyMap = $map;
    }
    
    public static function publicResetFieldPropertyMap(): void
    {
        static::$fieldPropertyMap = [];
    }
    
    public static function publicSetDryRun(bool $dryRun): void
    {
        $reflection = new ReflectionProperty(OrmDataManager::class, 'dryRun');
        $reflection->setAccessible(true);
        $reflection->setValue(null, $dryRun);
    }
    
    public static function publicResetDetectedChanges(): void
    {
        $reflection = new ReflectionProperty(OrmDataManager::class, 'detectedChanges');
        $reflection->setAccessible(true);
        $reflection->setValue(null, []);
    }
    
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function publicGetDetectedChanges(): array
    {
        $reflection = new ReflectionProperty(OrmDataManager::class, 'detectedChanges');
        $reflection->setAccessible(true);
        /** @var array<int, array<string, mixed>> $value */
        $value = $reflection->getValue();
        return $value;
    }
}

/**
 * Stub SQL helper for getSqlField tests.
 */
class StubSqlHelper
{
    public function quote(string $identifier): string
    {
        return '`' . $identifier . '`';
    }
    
    public function getColumnTypeByField(mixed $field): string
    {
        if ($field instanceof StubBooleanField) {
            return 'varchar(1)';
        }
        if ($field instanceof StubIntegerField) {
            return 'int';
        }
        if ($field instanceof StubStringField) {
            return 'varchar(255)';
        }
        if ($field instanceof StubFloatField) {
            return 'double';
        }
        return 'varchar(255)';
    }
}

/**
 * Stub database connection for getSqlField tests.
 */
class StubConnection extends Connection
{
    public function getSqlHelper(): StubSqlHelper
    {
        return new StubSqlHelper();
    }
}

/**
 *
 */
class OrmDataManagerTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        TestOrmDataManager::publicResetFieldPropertyMap();
        TestOrmDataManager::publicSetDryRun(false);
        TestOrmDataManager::publicResetDetectedChanges();
    }
    
    /**
     * @return void
     */
    public function testNormalizeNameMatchesVariousNamingConventions(): void
    {
        static::assertSame('active', TestOrmDataManager::publicNormalizeName('ACTIVE'));
        static::assertSame('active', TestOrmDataManager::publicNormalizeName('active'));
        static::assertSame('datecreated', TestOrmDataManager::publicNormalizeName('date_created'));
        static::assertSame('datecreated', TestOrmDataManager::publicNormalizeName('dateCreated'));
        static::assertSame('isdeleted', TestOrmDataManager::publicNormalizeName('IS_DELETED'));
    }
    
    /**
     * @return void
     */
    public function testGetFieldDefaultValueReturnsBooleanTrueAsY(): void
    {
        $field = new StubBooleanField('ACTIVE');
        
        $default = TestOrmDataManager::publicGetFieldDefaultValue($field);
        
        static::assertSame('Y', $default);
    }
    
    /**
     * @return void
     */
    public function testGetFieldDefaultValueReturnsBooleanFalseAsN(): void
    {
        $field = new StubBooleanField('IS_DELETED');
        
        $default = TestOrmDataManager::publicGetFieldDefaultValue($field);
        
        static::assertSame('N', $default);
    }
    
    /**
     * @return void
     */
    public function testGetFieldDefaultValueReturnsIntegerZero(): void
    {
        $field = new StubIntegerField('COUNT');
        
        $default = TestOrmDataManager::publicGetFieldDefaultValue($field);
        
        static::assertSame(0, $default);
    }
    
    /**
     * @return void
     */
    public function testGetFieldDefaultValueReturnsStringDefault(): void
    {
        $field = new StubStringField('STATUS');
        
        $default = TestOrmDataManager::publicGetFieldDefaultValue($field);
        
        static::assertSame('pending', $default);
    }
    
    /**
     * @return void
     */
    public function testGetFieldDefaultValueReturnsNullForNullableModelProperty(): void
    {
        $field = new StubStringField('DESCRIPTION');
        
        $default = TestOrmDataManager::publicGetFieldDefaultValue($field);
        
        static::assertNull($default);
    }
    
    /**
     * @return void
     */
    public function testGetFieldDefaultValueUsesExplicitPropertyMap(): void
    {
        TestOrmDataManager::publicSetFieldPropertyMap(['USER_ACTIVE' => 'active']);
        $field = new StubBooleanField('USER_ACTIVE');
        
        $default = TestOrmDataManager::publicGetFieldDefaultValue($field);
        
        static::assertSame('Y', $default);
    }
    
    /**
     * @return void
     */
    public function testGetFieldDefaultValueFallsBackToFieldConfiguredDefault(): void
    {
        $field = (new StubStringField('EXTERNAL_CODE'))->configureDefaultValue('unknown');
        
        $default = TestOrmDataManager::publicGetFieldDefaultValue($field);
        
        static::assertSame('unknown', $default);
    }
    
    /**
     * @return void
     */
    public function testFormatDefaultValueForBoolean(): void
    {
        static::assertSame("'Y'", TestOrmDataManager::publicFormatDefaultValue('Y', new StubBooleanField('ACTIVE')));
        static::assertSame("'N'", TestOrmDataManager::publicFormatDefaultValue('N', new StubBooleanField('ACTIVE')));
    }
    
    /**
     * @return void
     */
    public function testFormatDefaultValueForInteger(): void
    {
        static::assertSame('0', TestOrmDataManager::publicFormatDefaultValue(0, new StubIntegerField('COUNT')));
        static::assertSame('100', TestOrmDataManager::publicFormatDefaultValue(100, new StubIntegerField('SORT')));
    }
    
    /**
     * @return void
     */
    public function testFormatDefaultValueForString(): void
    {
        static::assertSame("'pending'", TestOrmDataManager::publicFormatDefaultValue('pending', new StubStringField('STATUS')));
        static::assertSame("'it\\'s ok'", TestOrmDataManager::publicFormatDefaultValue("it's ok", new StubStringField('STATUS')));
    }
    
    /**
     * @return void
     */
    public function testDefaultsEqualForBoolean(): void
    {
        $field = new StubBooleanField('ACTIVE');
        
        static::assertTrue(TestOrmDataManager::publicDefaultsEqual('Y', 'Y', $field));
        static::assertTrue(TestOrmDataManager::publicDefaultsEqual('N', 'N', $field));
        static::assertFalse(TestOrmDataManager::publicDefaultsEqual('N', 'Y', $field));
        static::assertFalse(TestOrmDataManager::publicDefaultsEqual(null, 'Y', $field));
    }
    
    /**
     * @return void
     */
    public function testDefaultsEqualForInteger(): void
    {
        $field = new StubIntegerField('COUNT');
        
        static::assertTrue(TestOrmDataManager::publicDefaultsEqual('0', 0, $field));
        static::assertTrue(TestOrmDataManager::publicDefaultsEqual('100', 100, $field));
        static::assertFalse(TestOrmDataManager::publicDefaultsEqual('0', 100, $field));
    }
    
    /**
     * @return void
     */
    public function testGetSqlFieldIncludesDefaultForBoolean(): void
    {
        $field = new StubBooleanField('ACTIVE');
        $connection = new StubConnection();
        
        $sql = TestOrmDataManager::publicGetSqlField($field, $connection);
        
        static::assertStringContainsString('`ACTIVE`', $sql);
        static::assertStringContainsString('NOT NULL', $sql);
        static::assertStringContainsString("DEFAULT 'Y'", $sql);
    }
    
    /**
     * @return void
     */
    public function testGetSqlFieldDoesNotAddDefaultForNullableField(): void
    {
        $field = (new StubBooleanField('ACTIVE'))->configureNullable(true);
        $connection = new StubConnection();
        
        $sql = TestOrmDataManager::publicGetSqlField($field, $connection);
        
        static::assertStringContainsString('DEFAULT NULL', $sql);
        static::assertStringNotContainsString("DEFAULT 'Y'", $sql);
    }
    
    /**
     * @return void
     */
    public function testGetSqlFieldIncludesDefaultForInteger(): void
    {
        $field = new StubIntegerField('COUNT');
        $connection = new StubConnection();
        
        $sql = TestOrmDataManager::publicGetSqlField($field, $connection);
        
        static::assertStringContainsString('`COUNT`', $sql);
        static::assertStringContainsString('NOT NULL', $sql);
        static::assertStringContainsString('DEFAULT 0', $sql);
    }
    
    /**
     * @return void
     */
    public function testGetSqlFieldIncludesDefaultForString(): void
    {
        $field = new StubStringField('STATUS');
        $connection = new StubConnection();
        
        $sql = TestOrmDataManager::publicGetSqlField($field, $connection);
        
        static::assertStringContainsString('`STATUS`', $sql);
        static::assertStringContainsString('NOT NULL', $sql);
        static::assertStringContainsString("DEFAULT 'pending'", $sql);
    }
    
    /**
     * @return void
     */
    public function testManageDefaultDetectsChangedBooleanDefault(): void
    {
        TestOrmDataManager::publicSetDryRun(true);
        TestOrmDataManager::publicResetDetectedChanges();
        
        $field = new StubBooleanField('ACTIVE');
        $actualFields = [
            'ACTIVE' => ['Field' => 'ACTIVE', 'Type' => 'varchar(1)', 'Null' => 'NO', 'Default' => 'N'],
        ];
        $connection = new StubConnection();
        
        TestOrmDataManager::manageDefault([$field], $actualFields, $connection);
        
        $changes = TestOrmDataManager::publicGetDetectedChanges();
        static::assertCount(1, $changes);
        static::assertSame('modify_field_default', $changes[0]['type']);
        static::assertSame('ACTIVE', $changes[0]['field']);
        static::assertStringContainsString("DEFAULT 'Y'", (string) $changes[0]['sql']);
    }
    
    /**
     * @return void
     */
    public function testManageDefaultSkipsMatchingDefault(): void
    {
        TestOrmDataManager::publicSetDryRun(true);
        TestOrmDataManager::publicResetDetectedChanges();
        
        $field = new StubBooleanField('ACTIVE');
        $actualFields = [
            'ACTIVE' => ['Field' => 'ACTIVE', 'Type' => 'varchar(1)', 'Null' => 'NO', 'Default' => 'Y'],
        ];
        $connection = new StubConnection();
        
        TestOrmDataManager::manageDefault([$field], $actualFields, $connection);
        
        $changes = TestOrmDataManager::publicGetDetectedChanges();
        static::assertCount(0, $changes);
    }
}

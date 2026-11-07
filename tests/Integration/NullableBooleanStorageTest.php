<?php
declare(strict_types=1);

namespace Tests\Integration;

use Bitrix\Main\Application;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Infrastructure\Bitrix\Repository\ORM\NullableBooleanField;
use Infrastructure\Bitrix\Repository\ORM\OrmDataManager;
use PHPUnit\Framework\TestCase;

/** Реальная временная таблица, не затрагивающая данные приложения. */
class NullableBooleanFixtureTable extends OrmDataManager
{
    public static function getTableName(): string { return 'test_nullable_boolean_storage'; }
    public static function getMap(): array
    {
        return [
            (new IntegerField('id'))->configurePrimary(true),
            new NullableBooleanField('flag'),
            (new BooleanField('active'))->configureValues(false, true),
            new \Bitrix\Main\ORM\Fields\Relations\Reference('linked', self::class, \Bitrix\Main\ORM\Query\Join::on('this.id', 'ref.id')),
        ];
    }
}

/** Проверяет сохранение, выборки, UNIQUE и формат дампа Bitrix на реальной СУБД. */
final class NullableBooleanStorageTest extends TestCase
{
    protected function setUp(): void
    {
        if (!method_exists(Application::class, 'getConnection')) {
            self::markTestSkipped('Requires a real Bitrix bootstrap and development database.');
        }
        Application::getConnection()->queryExecute('CREATE TEMPORARY TABLE test_nullable_boolean_storage (id INT PRIMARY KEY, flag VARCHAR(1) NULL DEFAULT NULL, active VARCHAR(1), UNIQUE KEY uq_flag(flag))');
        foreach ([[1, false], [2, true], [3, false]] as [$id, $flag]) {
            $result = NullableBooleanFixtureTable::add(['id' => $id, 'flag' => $flag, 'active' => true]);
            self::assertTrue($result->isSuccess(), implode('; ', $result->getErrorMessages()));
        }
    }

    protected function tearDown(): void
    {
        if (method_exists(Application::class, 'getConnection')) {
            Application::getConnection()->queryExecute('DROP TEMPORARY TABLE IF EXISTS test_nullable_boolean_storage');
            Application::getConnection()->queryExecute('DROP TEMPORARY TABLE IF EXISTS test_nullable_boolean_restore');
        }
    }

    public function testPersistenceAndLegacyFilters(): void
    {
        self::assertNull(Application::getConnection()->query('SELECT flag FROM test_nullable_boolean_storage WHERE id=1')->fetch()['flag']);
        self::assertNull(NullableBooleanFixtureTable::getByPrimary(1)->fetchObject()->get('flag'));
        self::assertSame(true, NullableBooleanFixtureTable::getByPrimary(2)->fetchObject()->get('flag'));
        foreach ([false, [false], [0], ['0']] as $value) {
            self::assertSame([1, 3], $this->ids(['flag' => $value]));
        }
        self::assertSame([2], $this->ids(['flag' => true]));
        self::assertSame([1, 2, 3], $this->ids(['flag' => [false, true]]));
        self::assertSame([], $this->ids(['flag' => []]));
        self::assertSame([1, 3], $this->ids(['!flag' => true]));
        self::assertSame([2], $this->ids(['!flag' => false]));
        self::assertSame([], $this->ids(['!@flag' => [false, true]]));
        self::assertSame([1, 3], $this->ids(['LOGIC' => 'OR', ['flag' => false], ['id' => 999]]));
        self::assertSame(2, NullableBooleanFixtureTable::getCount(['flag' => [false]]));
        self::assertTrue(NullableBooleanFixtureTable::update(2, ['flag' => false])->isSuccess());
        self::assertTrue(NullableBooleanFixtureTable::update(1, ['flag' => true])->isSuccess());
        self::assertSame([1], $this->ids(['flag' => true]));
    }

    public function testModernFiltersAndObjectSave(): void
    {
        self::assertSame(['1', '3'], array_column(NullableBooleanFixtureTable::query()->setSelect(['id'])->whereIn('flag', [false])->setOrder(['id' => 'ASC'])->exec()->fetchAll(), 'id'));
        self::assertSame(2, count(NullableBooleanFixtureTable::query()->setSelect(['id'])->where('flag', '!=', true)->exec()->fetchAll()));
        self::assertSame(3, count(NullableBooleanFixtureTable::query()->setSelect(['id'])->whereIn('flag', [true, false])->exec()->fetchAll()));
        $object = NullableBooleanFixtureTable::getByPrimary(2)->fetchObject();
        $object->set('flag', false);
        self::assertTrue($object->save()->isSuccess());
        self::assertNull(Application::getConnection()->query('SELECT flag FROM test_nullable_boolean_storage WHERE id=2')->fetch()['flag']);
    }

    public function testUniqueConstraintRejectsSecondDefault(): void
    {
        $this->expectException(\Bitrix\Main\DB\SqlQueryException::class);
        Application::getConnection()->queryExecute("INSERT INTO test_nullable_boolean_storage VALUES (4, '1', '1')");
    }

    public function testRelatedFieldsAndOrdinaryBooleans(): void
    {
        self::assertSame([1, 3], $this->ids(['linked.flag' => [false]]));
        self::assertSame([1, 2, 3], $this->ids(['active' => [false, true]]));
        self::assertSame([], $this->ids(['active' => false]));
        self::assertSame([1, 2, 3], $this->ids(['active' => true]));
        $query = NullableBooleanFixtureTable::query()->setSelect(['id'])->where(
            \Bitrix\Main\ORM\Query\Query::filter()->negative()->where('flag', true)
        );
        self::assertSame(2, count($query->exec()->fetchAll()));
        self::assertSame($query->getQuery(), $query->getQuery());
    }

    public function testPositionalDumpCanBeRestored(): void
    {
        $connection = Application::getConnection();
        $connection->queryExecute('CREATE TEMPORARY TABLE test_nullable_boolean_restore LIKE test_nullable_boolean_storage');
        $rows = $connection->query('SELECT * FROM test_nullable_boolean_storage ORDER BY id')->fetchAll();
        $tuples = [];
        foreach ($rows as $row) {
            $tuples[] = '(' . implode(', ', array_map(static fn($value): string => null === $value ? 'NULL' : "'" . $connection->getSqlHelper()->forSql((string) $value) . "'", $row)) . ')';
        }
        $connection->queryExecute('INSERT INTO test_nullable_boolean_restore VALUES ' . implode(', ', $tuples));
        self::assertSame($rows, $connection->query('SELECT * FROM test_nullable_boolean_restore ORDER BY id')->fetchAll());
    }

    private function ids(array $filter): array
    {
        return array_map('intval', array_column(NullableBooleanFixtureTable::getList(['select' => ['id'], 'filter' => $filter, 'order' => ['id' => 'ASC']])->fetchAll(), 'id'));
    }
}

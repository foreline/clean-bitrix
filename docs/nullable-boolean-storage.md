# Nullable boolean storage

`NullableBooleanField` stores true as `1` and false as SQL `NULL` in an ordinary nullable `VARCHAR(1)` column. A normal unique index can enforce at most one true row while allowing any number of false rows. It does not enforce existence of a true row.

Use this field only when NULL is the physical representation of false, not a third domain state:

```php
new NullableBooleanField('default_status')
```

`OrmDataManager` uses `NullableBooleanQuery` to preserve scalar, array, negative and nested boolean filters in both legacy filter arrays and modern `ConditionTree` queries. String field paths, including related fields, resolve through Bitrix's field chains. The translation is limited to `NullableBooleanField`; ordinary booleans retain their storage and filtering behavior. Boolean comparisons use a typed `COALESCE(field, 0)` expression, so queries against large tables should be assessed for index use.

Bitrix ORM objects can expose the physical NULL. Domain proxies should keep their existing boolean default or explicitly convert it to false; a nullable domain property is not required. Direct SQL writers must use `1`/`NULL`, not `1`/`0`. The unique index alone does not validate all possible non-NULL values.

This representation supports positional data INSERTs such as those produced by Bitrix backup, without generated columns or changes to vendor code. Existing dumps with the old generated-column schema still need separate recovery handling.

## Verification

`tests/Integration/NullableBooleanStorageTest.php` requires a real Bitrix bootstrap and a development database. Run it with `phpunit --no-configuration --bootstrap <application-bootstrap.php> <test-path>`. It uses temporary tables and covers physical NULL storage, ORM object saves, boolean filtering, unique-index rejection, and positional dump/restore. Under the package's stub bootstrap these tests explicitly skip.

Verified with PHP 8.2 and MariaDB 10.4. MySQL runtime verification remains separate. The 36 applicable existing unit tests pass. The pre-existing `RepositoryTest::testPrepareSelectFields` failure (calling a protected method) remains unchanged.

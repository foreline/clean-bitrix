---
name: 'PHPUnit Testing Conventions'
description: 'PHPUnit 9.6 test patterns and assertions for clean-bitrix'
applyTo: 'tests/**/*.php'
---

# PHPUnit Testing Conventions

## Test file organization
- **Location:** `tests/{Feature|Unit}/{Domain}/{EntityName}Test.php`
- **Naming:** Each test class must match pattern: `{ClassName}Test`
- **Example:** Tests for `UserRepository` go in `tests/Bitrix/Repository/ORM/RepositoryTest.php`

## Test class structure

```php
<?php
declare(strict_types=1);

namespace Tests\Bitrix\Repository\ORM;

use PHPUnit\Framework\TestCase;
use Domain\User\Aggregate\User;

class RepositoryTest extends TestCase
{
    private UserRepository $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository();
    }
    
    /**
     * @test
     */
    public function testFindUserById(): void
    {
        // Arrange
        $userId = 1;
        
        // Act
        $user = $this->repository->findById($userId);
        
        // Assert
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($userId, $user->id());
    }
}
```

## Naming test methods
- Use `testMethod{Scenario}` or `test{Method}_should{Expected}` pattern
- Decorate with `@test` annotation OR prefix with `test`
- Use `@dataProvider` for parameterized tests

```php
/**
 * @test
 * @dataProvider userDataProvider
 */
public function testFindUser_withValidId_returnsUser(int $id, string $name): void
{
    // ...
}

public function userDataProvider(): array
{
    return [
        [1, 'John'],
        [2, 'Jane'],
    ];
}
```

## Test structure (AAA pattern)
1. **Arrange:** Set up test data and fixtures
2. **Act:** Invoke the method under test
3. **Assert:** Verify the result

Keep arrange/act/assert sections clear with comments.

## Assertions
Use precise assertions:
- `$this->assertTrue()`, `$this->assertFalse()` — boolean conditions
- `$this->assertEquals()` — value equality
- `$this->assertSame()` — strict equality (identity)
- `$this->assertInstanceOf()` — type checking
- `$this->assertThrows()` — exception handling
- `$this->assertCount()` — collection sizes
- `$this->assertContains()` — collection membership

## Mocking & Fixtures
- Use `PHPUnit\Framework\MockObject` for mocking
- Mock only external dependencies (Bitrix ORM, Redis, etc.)
- Keep domain logic unmocked
- Use PHPUnit's `createMock()` or `createStub()`

## Test database setup
- Use Bitrix `ResetQuery()` or transaction rollback between tests if needed
- Mock `Bitrix\Main\DB\Result` for unit tests
- Keep integration tests isolated from Bitrix database

## Type hints in tests
Just like production code, test methods must have return type `: void`:

```php
public function testSomething(): void
{
    // ...
}
```

## Code coverage
- Aim for >80% code coverage on core Domain layer
- Coverage for Infrastructure adapters is secondary
- Do NOT test PHPStan-proven null handling, only business logic


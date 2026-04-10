---
description: 'Run failing tests, analyze errors, and generate or fix test cases'
agent: agent
tools:
  - run_in_terminal
  - read_file
  - grep_search
  - semantic_search
  - file_search
---

# Fix Tests & Generate Missing Cases

Use this prompt to run tests, debug failures, and write or repair test cases following PHPUnit conventions.

## Running tests

```powershell
# Run all tests
vendor/bin/phpunit

# Run specific test class
vendor/bin/phpunit tests/Bitrix/Repository/ORM/RepositoryTest.php

# Run with coverage report
vendor/bin/phpunit --coverage-html=coverage/

# Run with verbose output
vendor/bin/phpunit --verbose

# Stop on first failure
vendor/bin/phpunit --stop-on-failure
```

## Test workflow

1. **Identify failing test(s)**
   ```powershell
   vendor/bin/phpunit 2>&1 | grep -A 5 "FAILURES\|ERRORS"
   ```

2. **Read the failure message** — Look for assertion errors, exceptions, or setup issues

3. **Examine the test code** — Understand what it's testing and why it fails

4. **Fix or enhance:**
   - If logic is broken, fix the implementation
   - If test assumptions are wrong, update the test
   - If no test exists, write one

5. **Run again to verify**

## Common test failures

### 1. Assertion failed
```
Failed asserting that [actual] equals [expected]
```

**Diagnosis:**
- Method returned wrong value
- Setup data was incorrect
- Mock didn't behave as expected

**Fix:** Update implementation or test setup

### 2. Exception thrown
```
Error: Call to undefined method getMockedMethod()
```

**Diagnosis:** Method doesn't exist on mocked class

**Fix:** Check method signature or adjust mock expectations

### 3. Database setup issue
```
Error: Could not connect to database
```

**Diagnosis:** Test environment not configured

**Fix:** Set `.env` for test database or use in-memory fixtures

## Writing new tests

Follow the **AAA pattern** (Arrange-Act-Assert) and PHPUnit conventions:

```php
<?php
declare(strict_types=1);

namespace Tests\Bitrix\Repository\ORM;

use PHPUnit\Framework\TestCase;
use Domain\User\Aggregate\User;
use Infrastructure\Bitrix\Repository\UserRepository;

class UserRepositoryTest extends TestCase
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
    public function testFindById_withValidId_returnsUser(): void
    {
        // Arrange
        $userId = 1;
        $expectedName = 'John Doe';
        // Set up test data...
        
        // Act
        $user = $this->repository->findById($userId);
        
        // Assert
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($expectedName, $user->name());
    }
    
    /**
     * @test
     */
    public function testFindById_withInvalidId_returnsNull(): void
    {
        // Arrange
        $userId = 999;  // Non-existent
        
        // Act
        $user = $this->repository->findById($userId);
        
        // Assert
        $this->assertNull($user);
    }
    
    /**
     * @test
     * @dataProvider invalidIdProvider
     */
    public function testFindById_withInvalidId_throwsException(mixed $invalidId): void
    {
        // Arrange, Act, Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->repository->findById($invalidId);
    }
    
    public function invalidIdProvider(): array
    {
        return [
            'negative' => [-1],
            'zero' => [0],
            'string' => ['abc'],
        ];
    }
}
```

## Mocking Bitrix ORM

```php
use Bitrix\Main\DB\Result;
use PHPUnit\Framework\MockObject\MockObject;

class UserRepositoryTest extends TestCase
{
    private MockObject $mockORM;
    
    protected function setUp(): void
    {
        $this->mockORM = $this->createMock(UserTable::class);
    }
    
    public function testFindById_withMockedResult(): void
    {
        // Mock the ORM to return test data
        $mockResult = $this->createStub(Result::class);
        $mockResult->method('fetch')->willReturn([
            'ID' => 1,
            'LOGIN' => 'user@example.com',
            'NAME' => 'John',
        ]);
        
        $this->mockORM->method('getList')->willReturn($mockResult);
        
        // Test with mocked ORM...
    }
}
```

## Test coverage goals

- **Domain layer:** >80% coverage (strict business logic)
- **Infrastructure adapters:** >70% coverage (data mapping)
- **Presentation layer:** >60% coverage (HTTP routing)

View coverage:
```powershell
vendor/bin/phpunit --coverage-html=coverage/
# Open coverage/index.html in browser
```

## Key assertions

```php
$this->assertTrue($condition);              // Boolean true
$this->assertFalse($condition);             // Boolean false
$this->assertEquals($expected, $actual);    // Value equality
$this->assertSame($expected, $actual);      // Strict equality
$this->assertInstanceOf(Class::class, $obj); // Type check
$this->assertThrows(Exception::class, fn() => $func());  // Exception
$this->assertContains($item, $collection);  // Array membership
$this->assertCount(3, $items);              // Collection size
$this->assertEmpty($value);                 // Empty array/string/null
$this->assertStringContains('needle', 'haystack'); // String contains
```

## Debugging test failures

Add temporary debug output:
```php
// Print variable state
$this->getDebugInfo();
echo $user->toString();  // Manual inspection

// PHPUnit verbose
vendor/bin/phpunit --verbose
```

Stop on first failure to debug interactively:
```powershell
vendor/bin/phpunit --stop-on-failure
```


---
description: 'Perform a code review focused on architecture, security, PHPStan compliance, and best practices'
agent: agent
tools:
  - read_file
  - grep_search
  - semantic_search
  - get_changed_files
  - file_search
---

# Code Review: Architecture & Quality

Use this prompt to review recent code changes and pull requests for architectural compliance, security, and coding standards.

## Review checklist

### Architecture & DDD
- [ ] Domain layer has NO imports from `Infrastructure\` or `Presentation\`
- [ ] Infrastructure layer does NOT depend on Presentation
- [ ] Presentation layer uses Domain interfaces, not direct Infrastructure
- [ ] Repositories implement domain-defined interfaces (ports)
- [ ] Proxy/DTO pattern used for Bitrix ORM conversions
- [ ] New entities follow `Domain/{Entity}/{Aggregate,Entity,ValueObject,UseCase}/` structure

### Type safety
- [ ] All files have `declare(strict_types=1)` at the top
- [ ] All method parameters have explicit type hints (no `mixed` without justification)
- [ ] All methods have explicit return types (including `void`)
- [ ] Union types used where appropriate (PHP 8.1+): `User|UserCollection`, `int|null`
- [ ] Generic array types specified: `array<int, User>`, not bare `array`
- [ ] PHPStan compliant (would pass `vendor/bin/phpstan analyse`)

### Naming & conventions
- [ ] Classes: PascalCase
- [ ] Methods/properties: camelCase
- [ ] Constants: UPPER_CASE
- [ ] Test methods: `test{Method}_should{Expected}` or `test{Scenario}`
- [ ] Namespace structure: `Domain\`, `Infrastructure\`, `Presentation\`

### Bitrix integration
- [ ] Uses Bitrix `\Main\` namespaced classes (not legacy `CB*` classes)
- [ ] ORM queries use `Bxd7` abstraction when possible
- [ ] Field type mappings are correct (INT, STRING, DATETIME, FILE, ENUM, etc.)
- [ ] Reference fields load domain entities, not raw IDs
- [ ] Catches Bitrix exceptions and wraps in domain exceptions

### Security
- [ ] User permissions checked before sensitive operations
- [ ] SQL injection prevention: parameterized queries, no string concatenation
- [ ] File operations validated (type, size, path traversal checks)
- [ ] Redis/external API credentials NOT hardcoded
- [ ] Sensitive data logged appropriately (no passwords, tokens in logs)

### Testing
- [ ] New code has corresponding tests (aim >80% coverage for Domain)
- [ ] Tests follow AAA pattern (Arrange-Act-Assert)
- [ ] Mocks only external dependencies, not domain logic
- [ ] Test names clearly describe what is being tested
- [ ] Edge cases and error paths tested

### Comments & documentation
- [ ] Complex logic documented with Russian docblocks (preserving existing style)
- [ ] Public APIs have method documentation
- [ ] Why (not what) explained in comments

## Automated review workflow

```powershell
# Get changed files
git diff --name-only HEAD~1

# Run PHPStan on changed files
vendor/bin/phpstan analyse src/Domain/... src/Infrastructure/...

# Run tests
vendor/bin/phpunit

# Check for DDD violations
grep -r "use Infrastructure" src/Domain/
grep -r "use Presentation" src/Infrastructure/
```

## Common issues to flag

### 1. Breaking layer isolation
```php
// ❌ BAD: Domain importing Infrastructure
namespace Domain\User\Service;
use Infrastructure\Bitrix\Repository\UserRepository;

// ✅ GOOD: Domain defining interface, Infrastructure implementing
namespace Domain\User\Infrastructure\Repository;
use Domain\User\Infrastructure\Repository\UserRepositoryInterface;

namespace Infrastructure\Bitrix\Repository;
use Domain\User\Infrastructure\Repository\UserRepositoryInterface;
class UserRepository implements UserRepositoryInterface { }
```

### 2. Missing type hints
```php
// ❌ BAD
public function processUser($user) {
    return $user->getName();
}

// ✅ GOOD
public function processUser(User $user): string {
    return $user->getName();
}
```

### 3. Unsafe array access
```php
// ❌ BAD: No type guard
$userId = $data['USER_ID'];

// ✅ GOOD: Explicit check
$userId = $data['USER_ID'] ?? throw new InvalidArgumentException('Missing USER_ID');
```

### 4. Bitrix legacy classes
```php
// ❌ BAD
use CUser;  // Legacy class
$user = new CUser();

// ✅ GOOD
use Bitrix\Main\UserTable;
$result = UserTable::getList(['filter' => ['ID' => $id]]);
```

## Review output

Provide a summary with:
- ✅ **Strengths** — What was done well
- ⚠️ **Suggestions** — Non-blocking improvements
- ❌ **Blockers** — Must be fixed before merge

Example:
```
## Review Summary

✅ Strengths
- Excellent PHPStan compliance
- Clean proxy/DTO pattern for Bitrix conversion
- Good test coverage for aggregate logic

⚠️ Suggestions
- Consider extracting validation into separate value object
- Add docblock for complex filter composition

❌ Blockers
- Domain layer imports from Infrastructure (lines 45-47)
- Missing type hint on $result parameter (line 12)
- User permission check missing in UpdateUserUseCase
```


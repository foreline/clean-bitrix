---
description: 'Check for Bitrix-specific integration issues, security patterns, and ORM best practices'
agent: agent
tools:
  - read_file
  - grep_search
  - semantic_search
  - file_search
---

# Bitrix Integration Quality Check

Use this prompt to audit Bitrix CMS integration code for best practices, security, performance, and correct ORM usage.

## Bitrix ORM patterns

### ✅ Correct usage
```php
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\UserTable;

// Use Main\\ ORM for queries
$result = UserTable::getList([
    'filter' => ['ID' => $id],
    'select' => ['ID', 'LOGIN', 'EMAIL'],
]);

// Check result
if ($result->getSelectedRowsCount() > 0) {
    while ($row = $result->fetch()) {
        // Process row
    }
}
```

### ❌ Problematic patterns
```php
// Legacy classes
use CUser;
$user = new CUser();
$user->Fetch($id);  // ❌ Deprecated

// String concatenation in filters
"WHERE ID = " . $id;  // ❌ Injection risk

// Unvalidated array access
$bxData['FIELD'];  // ❌ May not exist

// Missing result validation
$result = getList(/* ... */);
while ($row = $result->fetch()) { }  // ❌ Could be false
```

## Integration checklist

### Field type mapping
Verify `Bxd7` correctly maps Bitrix field types:
- [ ] INT — integer fields
- [ ] STRING — text fields (VARCHAR)
- [ ] DATETIME — datetime fields with timezone handling
- [ ] DATE — date-only fields
- [ ] FILE — Bitrix file IDs → domain File entity
- [ ] USER — User ID → domain User entity reference
- [ ] ELEMENT — Iblock element ID → referenced aggregate
- [ ] SECTION — Iblock section ID → referenced entity
- [ ] ENUM — Property enums → domain value objects
- [ ] BOOL — Boolean properties

### Reference field handling
```php
// When Bitrix has reference field:
// ❌ BAD: Store raw ID
private int $ownerId;

// ✅ GOOD: Load and store domain entity
private User $owner;  // via repository

public function getOwnerId(): int
{
    return $this->owner->id();
}
```

### Permission & security checks

```php
// ✅ GOOD: Check user permissions
use Bitrix\Main\Engine\CurrentUser;

public function updateUser(User $user): void
{
    if (!CurrentUser::get()->isAdmin() && CurrentUser::get()->getId() !== $user->id()) {
        throw new AccessDeniedException('Cannot modify other users');
    }
    
    // Update logic...
}
```

### Event handling

Bitrix events should be dispatched from Infrastructure, not Domain:

```php
// ❌ BAD: Bitrix event in Domain
namespace Domain\User\UseCase;
use Bitrix\Main\Event;

class UpdateUserUseCase {
    public function execute(): void {
        // business logic
        // ...
        $event = new Event('user', 'OnUpdate', [$user]);  // ❌ Infrastructure leak
        $event->send();
    }
}

// ✅ GOOD: Domain publishes domain event, Infrastructure handles
namespace Domain\User\UseCase;

class UpdateUserUseCase {
    public function execute(User $user): void {
        // business logic
        $user->recordEvent(new UserUpdatedEvent($user->id()));
        return $user;
    }
}

// Infrastructure wraps and publishes
namespace Infrastructure\Event\Handler;

#[AsMessageHandler]
class UserUpdatedEventHandler {
    public function __invoke(UserUpdatedEvent $event): void {
        // Publish Bitrix event if needed
        $bxEvent = new Event('user', 'OnUpdate', ['userId' => $event->userId()]);
        $bxEvent->send();
    }
}
```

### Iblock element queries

```php
// ✅ GOOD: Use Iblock 2.0 ORM
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\ElementTable;

$result = ElementTable::getList([
    'filter' => ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
    'select' => ['ID', 'NAME', 'IBLOCK_ID'],
]);

// For complex Iblock structure, leverage Bxd7 base class
```

### Cache & performance

```php
// ✅ GOOD: Cache by object ID
private static array $cache = [];  // spl_object_id-based

public function find(int $id): ?User {
    $cacheKey = "user_$id";
    if (isset($this->cache[$cacheKey])) {
        return $this->cache[$cacheKey];
    }
    
    $user = $this->queryUser($id);
    if ($user !== null) {
        $this->cache[$cacheKey] = $user;
    }
    
    return $user;
}

// ✅ GOOD: Use repository nesting depth limit
private const MAX_NESTING_DEPTH = 3;

public function find(int $id): ?Department {
    return $this->findWithNesting($id, MAX_NESTING_DEPTH);
}
```

### File handling

```php
// ✅ GOOD: Validate file before use
use Bitrix\Main\IO\File;
use Domain\File\ValueObject\FileSize;

public function uploadFile(string $filePath): void {
    $file = new File($filePath);
    
    if (!$file->isExists()) {
        throw new NotFoundException("File not found: $filePath");
    }
    
    if ($file->getSize() > FileSize::MAX_SIZE) {
        throw new InvalidArgumentException("File too large");
    }
    
    // Process file...
}
```

### Redis integration

```php
// ✅ GOOD: Use RedisClient wrapper
namespace Infrastructure\Redis;

class RedisClient {
    public function get(string $key): ?string {
        try {
            return $this->predis->get($key);
        } catch (\Throwable $e) {
            // Log, fail gracefully
            return null;
        }
    }
    
    public function publish(string $channel, array $data): void {
        $this->predis->publish($channel, json_encode($data));
    }
}
```

### User context in async workers

```php
// When event handler needs user context:
namespace Infrastructure\Event\Handler;

use Bitrix\Main\Engine\CurrentUser;

final class UserCreatedEventHandler {
    public function __invoke(UserCreatedEvent $event): void {
        // Event contains serialized context
        $userId = $event->userId();
        
        // Restore if permission checks needed
        // CurrentUser::setUser(...);
        
        // Send notification email, etc.
    }
}
```

## Common Bitrix integration pitfalls

| Issue | Risk | Fix |
|-------|------|-----|
| Using legacy `CB*` classes | Performance, deprecated API | Use `Bitrix\Main\*` |
| String concatenation in filters | SQL injection | Use parameterized filters |
| Unhandled DB result | Crashes on null fetch | Check `getSelectedRowsCount()` |
| Missing cache invalidation | Stale data, inconsistency | Clear cache on updates |
| Permissions not checked | Security breach | Always check `CurrentUser` |
| Bitrix events in Domain | Architecture violation | Publish domain events only |
| Reference fields as IDs | Hard to refactor | Load as domain entities |

## Audit command

```powershell
# Find legacy class usage
grep -r "use C" src/ --include="*.php" | grep -v "\/\/"

# Find string concatenation in filters (heuristic)
grep -r '\$.*\.' src/ --include="*.php" | grep -i filter

# Find unguarded array access to Bitrix result data
grep -r '\$.*\[' src/Infrastructure/Bitrix/ --include="*.php"
```


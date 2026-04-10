---
name: 'DDD & Hexagonal Architecture Rules'
description: 'Enforces Domain-Driven Design patterns, layer isolation, and dependency directions'
applyTo: 'src/**/*.php'
---

# DDD & Hexagonal Architecture Rules

## Layer structure
The codebase is strictly organized into three layers:

```
src/
  Domain/          ← Business logic, aggregates, value objects, interfaces (PORTS)
  Infrastructure/  ← Adapters, Bitrix integration, external systems
  Presentation/    ← HTTP controllers, form DTOs, request handlers
```

## Layer boundaries & dependencies

### Domain layer rules
- ✅ **CAN:** Define entities, aggregates, value objects, use cases, repository interfaces, domain events
- ✅ **CAN:** Depend on other Domain classes
- ❌ **CANNOT:** Import from `Infrastructure\` or `Presentation\`
- ❌ **CANNOT:** Use Bitrix classes directly (except constants/enums when unavoidable)
- ❌ **CANNOT:** Instantiate external services

### Infrastructure layer rules
- ✅ **CAN:** Import from Domain (implement interfaces)
- ✅ **CAN:** Use Bitrix D7 ORM, Symfony Messenger, Predis
- ✅ **CAN:** Implement repository interfaces defined in Domain
- ❌ **CANNOT:** Depend on Presentation
- ❌ **CANNOT:** Contain business logic (delegate to Domain)

### Presentation layer rules
- ✅ **CAN:** Import from Domain (use cases, entities)
- ✅ **CAN:** Use Infrastructure services (through Domain interfaces)
- ✅ **CAN:** Handle HTTP requests/forms
- ❌ **CANNOT:** Contain business logic (use Domain services)

## New entity pattern

When adding a new domain entity, follow this structure:

```
Domain/{EntityName}/
  Aggregate/
    {Entity}Interface.php       ← Interface
    {Entity}.php                ← Implementation
    {Entity}Collection.php      ← If collection is needed
  Entity/
    {Sub}.php                   ← Sub-entities within aggregate
  ValueObject/
    {ValueObject}.php           ← Immutable values
  UseCase/
    {Action}UseCase.php         ← Business logic operations
  Infrastructure/
    Repository/
      {Entity}RepositoryInterface.php  ← Defined in Domain
```

Infrastructure implements the repository interface:

```
Infrastructure/Bitrix/Repository/
  {Entity}/
    {Entity}Repository.php      ← Extends Repository, implements interface
    {Entity}Proxy.php           ← Proxy for Bitrix ORM conversion
```

## Repository pattern

**Domain definition (port):**
```php
namespace Domain\User\Infrastructure\Repository;

interface UserRepositoryInterface 
{
    public function findById(int $id): ?User;
    public function save(User $user): void;
}
```

**Infrastructure implementation (adapter):**
```php
namespace Infrastructure\Bitrix\Repository;

class UserRepository implements UserRepositoryInterface 
{
    // Implements with Bitrix D7 ORM
}
```

Use **composition** for filtering, sorting, and pagination:
- `FilterInterface` — build WHERE conditions
- `SortInterface` — ORDER BY
- `LimitInterface` — LIMIT, OFFSET
- `FieldsInterface` — SELECT fields
- `GroupInterface` — GROUP BY

## Reference field mapping

When mapping Bitrix reference fields to domain entities:
1. Load the referenced entity through its repository
2. Store domain entity, not Bitrix IDs
3. Use Proxy pattern for two-way conversion

```php
// Domain (clean)
private User $owner;  // Not: private int $ownerId

// Repository converts Bitrix → Domain
$ownerId = $bxData['OWNER_ID'];
$owner = $userRepository->findById($ownerId);
```

## Async event system

Events follow Domain-Driven Design:

```
Domain/{Entity}/Event/
  {AggregateChanged}Event.php     ← Immutable event
```

```
Infrastructure/Event/
  Handler/
    {AggregateChanged}Handler.php  ← Handles event
```

- Events are published from Domain aggregates
- Handlers live in Infrastructure
- Use Symfony Messenger for dispatch
- Two-table: `EventStoreTable` (immutable log) + `EventDispatchTable` (queue)

## Namespace isolation check

Before committing, verify:
```bash
# Domain should NOT contain Infrastructure/Presentation imports
grep -r "use Infrastructure" src/Domain/
grep -r "use Presentation" src/Domain/

# Infrastructure should NOT contain Presentation imports
grep -r "use Presentation" src/Infrastructure/
```

These commands should return zero results.


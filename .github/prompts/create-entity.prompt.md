---
description: 'Scaffold a new Domain entity following DDD and Hexagonal architecture patterns'
agent: agent
tools:
  - file_search
  - read_file
  - create_file
  - semantic_search
---

# Create New Domain Entity

Use this prompt to scaffold a new domain aggregate, entity, or value object following clean-bitrix patterns.

## Information needed

Before proceeding, provide:
1. **Entity name** — e.g., `Project`, `Invoice`, `NotificationTemplate`
2. **Entity type** — Aggregate, Entity, or Value Object?
3. **Bitrix integration** — Does it map to a Bitrix Iblock, UserTable, or custom table?
4. **Key properties** — Main attributes of the entity
5. **Relationships** — Does it reference other entities?

## Scaffolding pattern

### For an Aggregate

Creates the full directory structure in `Domain/{EntityName}/`:

```
Domain/Project/
  Aggregate/
    ProjectInterface.php        ← Interface
    Project.php                 ← Implementation (aggregate root)
    ProjectCollection.php       ← If collection is needed
  Entity/
    ProjectMember.php           ← Sub-entities within aggregate
  ValueObject/
    ProjectStatus.php           ← Immutable values (ACTIVE, ARCHIVED, etc.)
  UseCase/
    CreateProjectUseCase.php    ← Business operations
    UpdateProjectUseCase.php
  Infrastructure/
    Repository/
      ProjectRepositoryInterface.php
```

Corresponding Infrastructure adapter:

```
Infrastructure/Bitrix/Repository/
  Project/
    ProjectRepository.php       ← Extends Repository base class
    ProjectProxy.php            ← Converts Project ↔ Bitrix ORM
```

### For a Value Object

Much simpler — just the value object itself:

```php
namespace Domain\Project\ValueObject;

declare(strict_types=1);

use InvalidArgumentException;

final class ProjectStatus
{
    public const ACTIVE = 'ACTIVE';
    public const ARCHIVED = 'ARCHIVED';
    
    public function __construct(private readonly string $value)
    {
        if (!in_array($value, [self::ACTIVE, self::ARCHIVED])) {
            throw new InvalidArgumentException("Invalid status: $value");
        }
    }
    
    public function value(): string
    {
        return $this->value;
    }
    
    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }
}
```

## Repository implementation

The `Repository` base class provides:
- Nested fetching with depth control
- Reference field support
- Caching via `spl_object_id`
- Composition-based filtering/sorting/limiting

Typical pattern:

```php
namespace Infrastructure\Bitrix\Repository;

use Infrastructure\Bitrix\Repository\ORM\Repository;
use Domain\Project\Aggregate\ProjectInterface;

class ProjectRepository extends Repository implements ProjectRepositoryInterface
{
    protected function getBxTableClass(): string
    {
        return ProjectTable::class;  // Maps to Bitrix table
    }
    
    public function findById(int $id): ?ProjectInterface
    {
        $result = $this->queryById($id);
        return $result ? ProjectProxy::toAggregate($result) : null;
    }
    
    public function save(ProjectInterface $project): void
    {
        $bxData = ProjectProxy::toBitrix($project);
        // Save logic here
    }
}
```

## Key patterns to follow

1. **Keep Domain layer pure** — No Bitrix imports, only interfaces
2. **Proxy/DTO pattern** — Implement `Bxd7ProxyInterface` for conversion
3. **Repository composition** — Use `FilterInterface`, `SortInterface`, `LimitInterface`
4. **Type hints everywhere** — PHP 8.1 strict mode on all files
5. **Russian comments** — Preserve language in docblocks
6. **Namespace structure** — Follow PSR-4: `Domain\`, `Infrastructure\`, `Presentation\`

## Tasks performed

This prompt will:
1. ✅ Create aggregate/entity/VO files with proper structure
2. ✅ Create repository interface in Domain
3. ✅ Create repository implementation + proxy in Infrastructure
4. ✅ Generate PHPStan-compliant type hints
5. ✅ Add Russian docblock templates
6. ✅ Create ADR for significant entities (optional)

Ask for any clarifications before generation begins.


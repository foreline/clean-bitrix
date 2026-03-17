# Clean-Bitrix — Copilot Instructions

## Project Overview

**clean-bitrix** is a PHP library that provides Clean Architecture (DDD + Hexagonal) abstractions for the **1C-Bitrix CMS**. It is part of the [Foreline](https://github.com/foreline/clean) ecosystem and serves as the integration layer between domain business logic and Bitrix's native ORM/API.

- **Language:** PHP 8.1+
- **Architecture:** Domain-Driven Design with Hexagonal (Ports & Adapters) pattern
- **Framework integrations:** Bitrix D7 ORM, Symfony Messenger (async events), Predis (Redis), Symfony HTTP Foundation
- **Static analysis:** PHPStan level 0
- **Testing:** PHPUnit 9.6

## Project Layout

```
src/
  Domain/          # Business logic: aggregates, entities, value objects, use cases, repository interfaces
    File/          # File aggregate with Bitrix FileTable integration
    User/          # User aggregate (User, Group, Role, collections)
  Infrastructure/  # Adapters: external system implementations
    Bitrix/        # Bitrix D7 ORM base classes (Bxd7, BxIblockSection, Repository/)
    Event/         # Async event system (Symfony Messenger + DB transport)
    Mailer/        # Bitrix mail adapter
    Redis/         # Predis wrapper (pub/sub, user notifications, KV store)
    Repository/    # Base repository classes, ORM helpers, migrations
  Presentation/    # HTTP controllers, form DTOs, UI helpers
    Helpers/       # Design utilities
    HTTP/          # Bitrix HTTP integration, form handling
tests/             # PHPUnit tests
docs/adr/          # Architecture Decision Records organized by domain subdirectory
```

**Key configuration files:**
- `composer.json` — dependencies, PSR-4 autoloading (`Domain\`, `Infrastructure\`, `Presentation\`)
- `phpstan.neon` — PHPStan level 0, analyzes `src/`
- `.env` / environment variables — Redis config (`REDIS_SCHEME`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DATABASE`), Bitrix module ID (`BITRIX_MODULE_ID`)

## Coding Conventions

- **Always** use `declare(strict_types=1);` at the top of every PHP file.
- **Namespaces** follow PSR-4 with three root namespaces: `Domain\`, `Infrastructure\`, `Presentation\`.
- Use **full type hints** on all method signatures, including union types, nullable types, and return types (PHP 8.1+).
- Comments and documentation may be in **Russian** — this is intentional, preserve the language when editing existing comments.
- Follow the **Proxy/DTO pattern** for entity conversion between domain objects and Bitrix ORM:
  - Proxy classes implement `Bxd7ProxyInterface` / `ProxyInterface`
  - They convert domain entities ↔ Bitrix ORM objects bidirectionally
- Repository classes use **composition** with filter, sort, limit, fields, and group interfaces (e.g., `FilterInterface`, `SortInterface`, `LimitInterface`).

## Architecture Rules

- **Domain layer** (`src/Domain/`) must NOT depend on Infrastructure or Presentation. It defines interfaces (ports) that Infrastructure implements.
- **Infrastructure layer** (`src/Infrastructure/`) implements domain interfaces and integrates with external systems (Bitrix, Redis, Symfony Messenger).
- **Presentation layer** (`src/Presentation/`) handles HTTP requests, forms, and UI. It depends on Domain but not directly on Infrastructure internals.
- New entities should follow the existing pattern: `Domain/{EntityName}/Aggregate/`, `Domain/{EntityName}/Entity/`, `Domain/{EntityName}/ValueObject/`, `Domain/{EntityName}/UseCase/`, `Domain/{EntityName}/Infrastructure/Repository/`.

## Key Patterns

### Bitrix D7 ORM Integration
- `Bxd7` (abstract base, ~1400 lines) handles Iblock 2.0 queries, field type mapping (INT, STRING, DATETIME, DATE, USER, FILE, ELEMENT, SECTION, ENUM, BOOL), filters, sorting, pagination, and caching.
- `BxIblockSection` handles section queries with `Section::compileEntityByIblock()`, nested references.
- Base `Repository` class (`Infrastructure/Bitrix/Repository/ORM/Repository.php`) provides nested fetching, reference field support, nesting depth control.

### Async Event System
- Two-table architecture: `EventStoreTable` (immutable event log) + `EventDispatchTable` (dispatch queue per subscriber).
- Events cached by `spl_object_id` — written once per object.
- Fallback to sync execution if async dispatch fails (prevents event loss).
- Async workers restore user context from serialized events for permission checks.
- Retry logic with exponential backoff and per-subscriber `RetryPolicy`.

### Redis Integration
- `RedisClient` wraps Predis for pub/sub, user notifications (JSON payloads to Socket.IO), and KV store.
- Config via environment variables.

## Build & Validation

```powershell
# Install dependencies
composer install

# Run static analysis
vendor/bin/phpstan analyse

# Run tests
vendor/bin/phpunit
```

Always run `composer install` before building. PHPStan and PHPUnit must pass before committing.

The project uses internal Foreline GitLab repositories (SSH on port 2222) for `foreline/clean`, `foreline/clean-ui`, and `foreline/io-response` packages.

## Documentation

- **ADR documents** live in `docs/adr/` organized by domain subdirectory.
- ADR naming: UPPERCASE with UNDERSCORES, English only, 3-5 words.
- Use numbered format (`XX. DESCRIPTION.md`) for sequences, plain format for standalone documents.
- Always link related documents and reference source code paths.

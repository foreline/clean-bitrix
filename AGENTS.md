# clean-bitrix — Agent Guide

This file is intended for AI coding agents that work with the `clean-bitrix` project. It assumes no prior knowledge of the codebase.

## Project overview

`clean-bitrix` is a PHP library that provides Clean Architecture (DDD + Hexagonal/Ports-and-Adapters) abstractions for the **1C-Bitrix CMS**. It is part of the [Foreline](https://github.com/foreline/clean) ecosystem and acts as the integration layer between domain business logic and Bitrix's native ORM/API.

- **Language:** PHP 8.1+ (GitLab CI runs on `php:8.2`)
- **Package name:** `foreline/clean-bitrix`
- **License:** MIT
- **Primary consumers:** Projects that use `foreline/clean` inside a Bitrix environment

The library does not contain an executable application. It ships domain ports, Bitrix adapters, base repository classes, an async event dispatcher, a Redis wrapper, and Bitrix-specific HTTP controllers/UI helpers.

## Technology stack

- **PHP:** 8.1+
- **CMS / runtime:** 1C-Bitrix with D7 ORM
- **Core framework packages:**
  - `foreline/clean` (^3.3.1) — domain framework
  - `foreline/clean-ui` (^1.1.2) — UI abstractions
  - `foreline/io-response` — response objects
- **External integrations:**
  - Bitrix D7 ORM (`Bitrix\Main\*`, `Bitrix\Iblock\*`)
  - Symfony Messenger (async events)
  - Symfony HTTP Foundation
  - Predis (Redis pub/sub, KV store, user notifications)
- **Quality / testing:**
  - PHPUnit 9.6
  - PHPStan level 0
  - `jetbrains/phpstorm-attributes`

Private Foreline dependencies are resolved from internal GitLab repositories over SSH (`git@gitlab.foreline.ru:2222`).

## Project structure

```
src/
  Domain/          # Business logic: aggregates, entities, value objects, use cases, repository interfaces
    File/          # File aggregate + Bitrix FileTable integration
    User/          # User aggregate (User, Group, roles, collections)
  Infrastructure/  # Adapters: Bitrix, events, mail, Redis, repository helpers, migrations
    Bitrix/        # D7 ORM base classes (Bxd7, BxIblockSection, Repository, OrmDataManager)
    DI/Bridge/     # BitrixBridge / BitrixIBlockHelper for DI integration
    Event/         # Async event system (Symfony Messenger + DB transport)
    Mailer/Bitrix/ # Bitrix mail adapter
    Redis/         # Predis wrapper
    Repository/    # Filter/migration base classes
  Presentation/    # HTTP controllers, form DTOs, UI helpers
    Helpers/       # Design utilities
    HTTP/          # Bitrix engine controllers and form DTOs
    Lifecycle/     # Lifecycle status rendering helper
tests/             # PHPUnit tests (currently tests/Bitrix/Repository/ORM/RepositoryTest.php)
docs/adr/          # Architecture Decision Records (by domain subdirectory)
```

PSR-4 autoloading is configured in `composer.json`:

- `Domain\` → `src/Domain`
- `Infrastructure\` → `src/Infrastructure`
- `Presentation\` → `src/Presentation`

## Key configuration files

| File | Purpose |
|------|---------|
| `composer.json` | Dependencies, PSR-4 autoloading, custom GitLab repositories |
| `phpstan.neon` | PHPStan level 0, analyzes only `src/` |
| `.env` (copy from `.env.example`) | Redis, async events, Telegram/email alerts, `BITRIX_MODULE_ID` |
| `.gitlab-ci.yml` | CI stages: build, lint, check, test |

A `phpunit.xml` is present; tests are invoked with `vendor/bin/phpunit`. The configuration loads `tests/bootstrap.php`, which provides minimal Bitrix stubs so adapter tests can run outside a full Bitrix environment.

## Build and test commands

Install dependencies first:

```bash
composer install
```

Run static analysis:

```bash
php vendor/bin/phpstan analyse -c phpstan.neon --no-progress
```

> **Note:** PHPStan currently reports many `class.notFound` errors for Bitrix classes when run outside a Bitrix environment. This is expected for adapter code that depends on the Bitrix runtime. In CI (`job-phpstan`) this job is marked `allow_failure: true`.

Run tests:

```bash
php vendor/bin/phpunit
```

> **Note:** `tests/Bitrix/Repository/ORM/RepositoryTest.php` calls `prepareSelectFields()`, which is `protected` on the base repository, producing a PHP fatal error. This is a known issue to watch for when modifying repository code.

Validate the package:

```bash
composer validate
composer audit
composer check-platform-reqs
```

## Architecture and module divisions

The codebase follows strict layer isolation (DDD + Hexagonal):

### 1. Domain layer (`src/Domain/`)

- Defines business logic, aggregates, value objects, use cases, domain events, and repository interfaces (ports).
- **Must not** import from `Infrastructure\` or `Presentation\`.
- **Must not** instantiate Bitrix/external services directly.
- Example: `Domain\User\Infrastructure\Repository\UserRepositoryInterface` is declared here and implemented under `Infrastructure`.

### 2. Infrastructure layer (`src/Infrastructure/`)

- Implements domain ports and integrates with external systems.
- **May** import from `Domain`.
- **Must not** depend on `Presentation`.
- Major subsystems:
  - **Bitrix adapters:** `Bxd7` (Iblock 2.0 queries, field mapping, filters, sorting, caching), `BxIblockSection` (section queries), `Repository\ORM\Repository` (nested reference fetching).
  - **DI bridge:** `BitrixBridge` registers Bitrix globals (`$USER`, `$APPLICATION`, `$DB`) and module loader services.
  - **Events:** `SymfonyMessengerAsyncDispatcher` writes events to `EventStoreTable` and dispatch rows to `EventDispatchTable`, with sync fallback on failure.
  - **Mailer:** `Mailer\Bitrix\Mailer` wraps `Bitrix\Main\Mail\Mail`.
  - **Redis:** `RedisClient` wraps Predis for pub/sub and KV operations.
  - **Migrations:** `MigrationManager` discovers `Version*.php` classes, tracks them in `MigrationVersionTable`, and runs `up()`/`down()` inside transactions.

### 3. Presentation layer (`src/Presentation/`)

- Handles HTTP requests, forms, and UI helpers.
- **May** use Domain use cases and Infrastructure services through domain interfaces.
- Contains Bitrix engine controllers (`PublicPageController`, `PublicPageList`, etc.), `FilterFormDto`, and `LifecycleStatusViewHelper`.

### Repository pattern

Concrete repositories extend a base class and implement a domain interface:

```
Domain/{Entity}/Infrastructure/Repository/{Entity}RepositoryInterface.php   # port
Domain/{Entity}/Infrastructure/Repository/Bitrix/{Entity}Proxy.php          # DTO/proxy
Domain/{Entity}/Infrastructure/Repository/Bitrix/{Entity}Repository.php     # adapter
```

- Proxy classes convert between domain objects and Bitrix ORM objects bidirectionally.
- Repositories use composition with `FilterInterface`, `SortInterface`, `LimitInterface`, `FieldsInterface`, and `GroupInterface`.
- Reference fields should be loaded as domain entities, not stored as raw IDs.

## Code style guidelines

- Every PHP file must start with:
  ```php
  <?php
  declare(strict_types=1);
  ```
- Use the three root namespaces only: `Domain\`, `Infrastructure\`, `Presentation\`.
- Files/classes/interfaces are `PascalCase`; methods/properties are `camelCase`; constants are `UPPER_CASE`.
- All method parameters and return types must be explicitly declared (including `void`, union types, nullable types).
- Document array element types: `/** @var array<int, User> */`.
- Use fully qualified imports, grouped by Bitrix → Domain → application classes.
- Preserve **Russian** comments/docblocks in existing code; write new comments in Russian following existing patterns.
- Always declare visibility explicitly.
- Prefer `Bitrix\Main\*` namespaced classes over legacy `CB*` classes.
- No `@phpstan-ignore-next-line` without justification; code is expected to pass PHPStan level 0 where symbols are available.

Layer isolation check before committing:

```bash
# Domain must not import Infrastructure or Presentation
grep -r "use Infrastructure" src/Domain/
grep -r "use Presentation" src/Domain/

# Infrastructure must not import Presentation
grep -r "use Presentation" src/Infrastructure/
```

These commands should return no results.

## Testing instructions

- **Framework:** PHPUnit 9.6
- **Test location:** `tests/{Feature|Unit}/{Domain}/{EntityName}Test.php`
- **Class naming:** `{ClassName}Test`
- **Method naming:** `testMethod{Scenario}` or `test{Method}_should{Expected}`
- Mark test methods with `@test` annotation or prefix with `test`.
- Follow the **Arrange-Act-Assert** structure.
- Mock only external dependencies (Bitrix ORM, Redis, etc.); keep domain logic unmocked.
- Use `createMock()` / `createStub()` for Bitrix ORM or DB results.
- Coverage goals:
  - Domain layer: >80%
  - Infrastructure adapters: >70%
  - Presentation layer: >60%

Example run with coverage:

```bash
php vendor/bin/phpunit --coverage-html=coverage/
```

## Async event system

- Events are defined in `Domain/{Entity}/Event/`.
- Handlers live in `Infrastructure/Event/Handler/`.
- Transport uses a normalized two-table design:
  - `EventStoreTable` — immutable event log (serialized event body)
  - `EventDispatchTable` — per-subscriber dispatch queue
- The event body is written once per object instance (cached by `spl_object_id`).
- On async dispatch failure the dispatcher falls back to synchronous execution to prevent event loss.
- Async workers restore user context from serialized events when permission checks are required.
- Retry/backoff and purge behavior are controlled via `.env`:
  - `ASYNC_EVENTS_ENABLED`, `MESSENGER_TRANSPORT_DSN`
  - `MESSENGER_RETRY_MAX_RETRIES`, `MESSENGER_RETRY_BASE_DELAY_MS`, `MESSENGER_RETRY_MULTIPLIER`, `MESSENGER_RETRY_MAX_DELAY_MS`, `MESSENGER_RETRY_JITTER_MS`
  - `EVENT_STORE_PURGE_STRATEGY` (`immediate` | `retention` | `none`)

## Deployment and CI

The project uses GitLab CI (`.gitlab-ci.yml`):

1. **build** — `job-composer` installs dependencies, validates, audits, and checks platform requirements.
2. **lint** — `job-syntax-check` runs `php -l` over all PHP files.
3. **test** — `job-phpstan` runs PHPStan (allowed to fail) and `job-test` runs PHPUnit.

The `deploy` stage is commented out; deployment is not currently automated in this repository.

## Environment variables

Copy `.env.example` to `.env` and configure as needed:

- `APP_ENV`
- `EXCEPTION_TELEGRAM_TOKEN`, `EXCEPTION_TELEGRAM_CHAT_ID`
- `EXCEPTION_EMAIL`, `ERROR_EMAIL`, `WARNING_EMAIL`, `TESTS_EMAIL`
- Async event settings (see above)
- Redis: `REDIS_SCHEME`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`
- `BITRIX_MODULE_ID` — used by file repository and other adapters

## Security considerations

- Keep tokens, passwords, and module-specific IDs in `.env`; do not commit them.
- Use parameterized Bitrix ORM filters; avoid string concatenation in SQL/filter conditions.
- Check permissions with `Bitrix\Main\Engine\CurrentUser` before acting on users or sensitive data.
- HTML output is escaped via `LifecycleStatusViewHelper::escape()` (preferring Bitrix `htmlspecialcharsbx()` when available).
- Repository code invalidates caches after updates (e.g., `UserTable::cleanCache()`, `UserProxy::clearUserGroupsCache()`).
- Serialized async events should not contain secrets or excessive context; workers restore only what is needed for authorization.
- Redis `publishForUser()` uses a shared `user_notifications` channel; verify on the consumer side that the `target_user_id` matches the authenticated socket owner.

## Documentation

Architecture Decision Records live in `docs/adr/` and are organized by subdomain (e.g., `architecture/`, `domain/`, `event/`, `infrastructure/`, `repository/`).

ADR conventions:

- English only
- UPPERCASE_WITH_UNDERSCORE titles, 3–5 words
- Numbered if part of a sequence: `XX. TITLE.md`
- Standalone otherwise: `TITLE.md`
- Use the template from `.github/prompts/create-adr.prompt.md`

## Release and push policy

- We are the maintainers of this library. The repo has two remotes: `github` and `gitlab`. All changes AND release tags are pushed to **all** remotes (`git push github main --tags && git push gitlab main --tags`). GitLab is release-critical (composer consumers fetch from there), but github must not silently fall behind.
- Tagging conventions: see `.github/prompts/git-commit.prompt.md` (SemVer, annotated tags, conventional commits, CHANGELOG.md). A core dependency update (`foreline/clean`, `foreline/clean-ui`, `foreline/io-response`) always gets a tag.
- Dependency cascade: after a `clean-bitrix` release, update downstream consumers — e.g. the servicedesk module: `composer update foreline/clean-bitrix -W`, commit the lock.

---

## Quick reference for agents

- Run `composer install` before any static analysis or test command.
- Do not translate existing Russian comments.
- Respect layer boundaries; do not let Domain depend on Infrastructure/Presentation.
- When adding a new entity, follow the established folder layout and repository/proxy pattern.
- Update this `AGENTS.md` if you change build steps, layer rules, or environment configuration.

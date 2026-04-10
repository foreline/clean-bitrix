---
name: 'PHP & Bitrix Coding Standards'
description: 'Enforces PHP 8.1+ strict typing, PSR-4, and Bitrix D7 integration patterns'
applyTo: 'src/**/*.php'
---

# PHP & Bitrix Coding Standards

## Mandatory file header
Every PHP file must have this exact header:
```php
<?php
declare(strict_types=1);

namespace {Namespace};
```

## Naming schemes
- **Files:** PascalCase (e.g., `UserRepository.php`, `Bxd7ProxyInterface.php`)
- **Classes & Interfaces:** PascalCase
- **Methods & Properties:** camelCase
- **Constants:** UPPER_CASE
- **Namespaces:** Use three root namespaces only: `Domain\`, `Infrastructure\`, `Presentation\`

## Type hints (MANDATORY)
- **All method parameters** must have explicit type hints (including `?type` for nullable)
- **All method returns** must have explicit return types (including `void`, `:mixed`, `:self`)
- Use **union types** (e.g., `User|null`, `int|string`) for multiple possibilities
- Generic types in doc comments: `/** @var array<int, User> */`

Examples:
```php
public function getUser(int $id): ?User
{
    // ...
}

public function processItems(User|UserCollection $items): void
{
    // ...
}
```

## Comments language
- **Preserve Russian** for existing comments — do not translate
- **New code:** Use Russian for docblocks and inline comments following existing patterns
- PHPStan attributes (`JetBrains\PhpStorm\Deprecated`, etc.) stay in English

## Access modifiers
Always declare visibility explicitly (`public`, `protected`, `private`). No implicit visibility.

## Imports
- Use **fully qualified class imports** at the top
- Group imports by: Bitrix → Domain → Application classes
- One `use` per line

## Bitrix D7 specific
- Use **Bitrix\Main** namespaced classes (not legacy `CB*` classes) unless required
- When working with ORM, leverage `Bxd7` base abstraction for Iblock 2.0 queries
- For repositories, extend base `Repository` class with composition (filters, sorts, limits)
- Proxy/DTO pattern: Implement `Bxd7ProxyInterface` or `ProxyInterface` for entity conversion

## Array type hints
When using arrays, always specify element types:
```php
/** @var array<int, User> $users */
$users = [];

/**
 * @param array<string, mixed> $data
 */
public function process(array $data): array
```

## Exception handling
- Throw domain exceptions for business logic violations
- Catch Bitrix/external exceptions and wrap in domain exceptions when appropriate
- Always provide meaningful error messages

## Static analysis
Code must pass **PHPStan level 0** (strictest). No `@phpstan-ignore-next-line` or suppressions without justification.


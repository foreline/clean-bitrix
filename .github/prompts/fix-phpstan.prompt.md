---
description: 'Analyze PHPStan level 0 violations and fix them while preserving business logic'
agent: agent
tools:
  - run_in_terminal
  - read_file
  - grep_search
  - semantic_search
  - file_search
---

# Fix PHPStan Level 0 Violations

Use this prompt to identify and resolve PHPStan static analysis errors at level 0 (strictest).

## How to run analysis

```powershell
# Run full analysis
vendor/bin/phpstan analyse

# Run on specific files
vendor/bin/phpstan analyse src/Domain/User/Aggregate/User.php

# Output as JSON for parsing
vendor/bin/phpstan analyse --format=json
```

## Common level 0 violations

### 1. Missing type hints
**Error:** Local variable type not specified
```
⚠ No type specified for $value in condition
```

**Fix:** Add explicit type hint
```php
// Before
$value = getValue();
if ($value) { ... }

// After
$value = getValue(); // : ?string
if ($value !== null) { ... }
```

### 2. Unsafe array access
**Error:** Cannot access key on array
```
⚠ Cannot access property on possibly null
```

**Fix:** Check existence before access
```php
// Before
$value = $data['key'];

// After
$value = $data['key'] ?? null;
// OR
if (isset($data['key'])) {
    $value = $data['key'];
}
```

### 3. Missing method parameter types
**Error:** Parameter type not specified
```
⚠ {ClassName}::{methodName}() has no return type specified
```

**Fix:** Add return type and parameter types
```php
// Before
public function process($data)
{
    return $data;
}

// After
public function process(mixed $data): mixed
{
    return $data;
}
```

### 4. Unsafe instanceof/property access
**Error:** Accessing property on possibly-null object
```
⚠ Cannot access property on possibly null
```

**Fix:** Type narrow before access
```php
// Before
$user = getUser();
$name = $user->name;  // Unsafe if $user is null

// After
$user = getUser();
if ($user !== null) {
    $name = $user->name;
}
// OR
$name = getUser()?->name;
```

### 5. Generic type arguments
**Error:** Missing generic type
```
⚠ Type is not a generic array
```

**Fix:** Specify element types
```php
// Before
/** @var array $users */
$users = [];

// After
/** @var array<int, User> $users */
$users = [];

// In method signatures
public function getUsers(): array  // ❌ Generic type missing
public function getUsers(): array  // ✅ Use generics

// In doc comments
/** @return array<int, User> */
public function getUsers(): array
{
    // ...
}
```

## PHPStan workflow

1. **Run analysis:**
   ```powershell
   vendor/bin/phpstan analyse src/ > phpstan-report.txt
   ```

2. **Review results** — Group by file or severity

3. **Fix systematically:**
   - Start with high-impact files (`Bxd7.php`, repository base classes)
   - Fix type hints first, then unsafe access patterns
   - Do NOT suppress with `@phpstan-ignore` without justification

4. **Verify fixes:**
   ```powershell
   vendor/bin/phpstan analyse src/
   ```

5. **Commit when clean** — Zero violations

## Advanced: Creating baseline

If fixing all violations at once is not feasible, create a baseline:

```powershell
vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon
```

Then gradually fix violations and shrink the baseline.

## Domain layer priority

Focus on strict analysis for these layers in order:
1. **Domain/** — Core business logic, must be pristine
2. **Infrastructure/Repository/** — Data mapping adapter
3. **Infrastructure/Bitrix/** — ORM integration
4. **Presentation/** — HTTP handlers

## Important

- **NEVER** suppress errors without understanding why
- **ALWAYS** add explicit types rather than using `mixed` unless appropriate
- **DOCUMENT** non-obvious type casts in comments
- **ASK** if a violation seems to conflict with architecture rules


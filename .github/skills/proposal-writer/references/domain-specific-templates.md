# Domain-Specific ADR & Proposal Templates

Use these templates when the standard ADR/proposal structure needs to be extended for specific types of architectural work.

---

## Architecture Proposal

```markdown
# [Feature/Module] Architecture

## Overview
High-level description of the architectural change.

## Current Architecture
[Diagram or description]

## Proposed Architecture
[Diagram or description]

## Architectural Principles
- Clean Architecture compliance
- DDD bounded contexts
- SOLID principles
- Hexagonal (Ports & Adapters) pattern

## Component Responsibilities

### Component 1: [Name]
**Responsibility:** What it does
**Dependencies:** What it depends on

### Component 2: [Name]
**Responsibility:** What it does
**Dependencies:** What it depends on

## Data Flow
1. Step 1
2. Step 2

## Technology Stack
- PHP 8.1+
- Bitrix D7 ORM
- Symfony Messenger
- Predis (Redis)
- Symfony HTTP Foundation

## Design Patterns
- Pattern 1: Rationale
- Pattern 2: Rationale

## Security Considerations
- Consideration 1
- Consideration 2

## Performance Considerations
- Expected impact
- Optimization strategies
```

---

## Refactoring Proposal

```markdown
# Refactoring: [Component/Module Name]

## Current Problems
List specific issues with current implementation:
- Problem 1: Description
- Problem 2: Description

## Code Smells Identified
- [ ] Large Class (>1000 lines)
- [ ] Duplicated Code
- [ ] Magic Numbers/Strings
- [ ] Feature Envy
- [ ] Other: [specify]

## Refactoring Goals
1. Goal 1
2. Goal 2

## Proposed Changes

### Change 1: [Description]
**Before:**
[Current code]

**After:**
[Refactored code]

**Rationale:** Why this change improves the code.

## Risk Assessment
- **High Risk:** Areas where refactoring could break functionality
- **Medium Risk:** Areas requiring careful testing
- **Low Risk:** Safe refactorings

## Testing Strategy
- Unit tests to write
- Integration tests to update

## Backward Compatibility
How to maintain BC during transition.

## Rollback Plan
Steps to revert if issues arise.
```

---

## Feature Proposal

```markdown
# Feature: [Feature Name]

## User Story
As a [role], I want [feature], so that [benefit].

## Business Value
Why this feature matters to users and the business.

## Requirements

### Functional Requirements
- FR1: Description
- FR2: Description

### Non-Functional Requirements
- NFR1: Performance target
- NFR2: Security requirement

## Technical Design

### Entities
- Entity 1: Properties and relationships

### Use Cases
- UC1: Create [Entity]
- UC2: Update [Entity]

### Repository Interface
```php
interface [Entity]RepositoryInterface
{
    public function findById(int $id): ?[Entity];
    public function findAll(): [Entity]Collection;
    public function save([Entity] $entity): void;
    public function delete([Entity] $entity): void;
}
```

### Proxy Class
```php
class [Entity]Proxy implements ProxyInterface
{
    public function fromDomain([Entity] $entity): array;
    public function toDomain(array $data): [Entity];
}
```

## Dependencies
- External libraries
- Other domain modules

## Testing Plan
- Test scenarios
- Edge cases

## Documentation Updates
- ADR documents to create
- README updates
```

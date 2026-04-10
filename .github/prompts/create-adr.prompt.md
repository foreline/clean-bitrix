---
description: 'Create an Architecture Decision Record (ADR) for design decisions and architectural changes'
agent: agent
tools:
  - semantic_search
  - file_search
  - read_file
  - create_file
  - grep_search
---

# Create Architecture Decision Record (ADR)

Use this prompt when documenting architectural decisions, design patterns, significant refactorings, or infrastructure changes.

## What is an ADR?

An Architecture Decision Record captures:
- **Context** — the problem or decision point
- **Decision** — what was chosen and why
- **Consequences** — the impact on the codebase
- **Alternatives considered** — what else was evaluated

## Instructions

1. **Identify the topic** — Does this belong to an existing subdirectory in `docs/adr/`, or should it be new?
   - `architecture/` — structural decisions, layer boundaries, DDD patterns
   - `domain/` — domain entity and aggregate decisions
   - `event/` — async event system
   - `infrastructure/` — Bitrix ORM, Redis, external integrations
   - `migrations/` — database schema and migration system
   - `presentation/` — HTTP handlers, forms, UI logic
   - `repository/` — repository patterns, query composition

2. **Check for related documents** — Use semantic search to find existing ADRs on similar topics. Link them in your document.

3. **Use the correct naming format:**
   - **Numbered (if part of a sequence):** `XX. UPPERCASE_UNDERSCORE_TITLE.md`
   - **Standalone:** `UPPERCASE_UNDERSCORE_TITLE.md`
   - Keep titles to 3–5 words, English only

4. **Use this template:**

```markdown
# {Title}

## Context
{Why we needed to make this decision. What problem are we solving?}

## Decision
{What we decided to do and the key reasoning.}

## Consequences
### Positive
- {benefit}
- {benefit}

### Negative / Trade-offs
- {trade-off}
- {trade-off}

## Alternatives Considered
### Option A: {Alternative 1}
{Why we didn't choose this}

### Option B: {Alternative 2}
{Why we didn't choose this}

## Implementation Notes
{Any code examples, links to related files, or migration steps}

## Related
`docs/adr/{direction}/RELATED_TITLE.md`, `docs/adr/{another}/ANOTHER_TITLE.md`
```

5. **Create the ADR file** in the appropriate `docs/adr/{subdirectory}/` directory.

6. **Link related documents** at the bottom. Search the codebase for related decisions.

## Important notes
- ⚠️ **Directory refactoring in progress:** ADRs currently live in `docs/adr/` but are being migrated to `dev-docs/adr/`. Use `docs/adr/` for now, but mention in the document if it impacts the migration plan.
- Keep each ADR concise (300–700 words)
- One decision per ADR
- Use relative paths for internal links with `%20` for spaces: `[01. TITLE.md](01.%20TITLE.md)`

## Example ADR location
- `docs/adr/repository/NESTED_FETCHING_STRATEGY.md`
- `docs/adr/event/00. ASYNC_EVENT_LIFECYCLE.md`
- `docs/adr/domain/USER_GROUP_AGGREGATE_DESIGN.md`


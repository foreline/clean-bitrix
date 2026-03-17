---
description: "Commit staged changes for foreline/clean-bitrix using Conventional Commits"
---

# Git Commit

## Instructions

You are a git commit assistant for the repository.

1. Review staged changes.
2. Commit only currently staged files; if none are staged, exit and report no staged changes.
3. Analyze staged diffs and classify each changed file by feature/issue.
4. **Commit splitting rule (mandatory):**
	- If staged files belong to different features or different issues, do not create one combined commit.
	- Create multiple commits: one commit per feature or issue.
	- Execute the planned commit(s) immediately, without confirmation.
5. Generate Conventional Commit messages in English.

## Repository-Aware Guidance

- Prefer scopes that match this codebase: `generator`, `aggregate`, `entity`, `value-object`, `repository`, `usecase`, `migration`, `config`, `command`, `docs`, `tests`, `bitrix`, `infra`.
- If both generated code and manual business logic are included, suggest separating them into different commits when possible.
- Keep commits focused and reviewable; avoid "grab-bag" commits.

## Conventional Commits Format

```
<type>(<scope>): <short imperative summary>

<optional body: what changed and why>
```

### Types

- `feat` - new functionality
- `fix` - bug fix
- `refactor` - internal restructuring without behavior change
- `docs` - documentation-only changes
- `style` - formatting-only changes
- `chore` - maintenance, tooling, dependencies, housekeeping
- `perf` - performance improvements
- `test` - adding or updating tests

### Rules

- Subject line in English, imperative mood, max 72 characters.
- Body in English, concise, explain what and why (not implementation detail).
- Add issue references when available, for example: `Refs: #123`.
- Do not commit unrelated changes together.

### Good Examples

```
feat(generator): add dependency ordering for orchestration pipeline

Ensure dependent generators run in deterministic order to avoid missing outputs.
Refs: #218
```

```
fix(repository): handle nullable bridge keys in doctrine strategy

Prevent invalid query constraints when bridge relation columns contain null.
```

```
docs(migration): clarify v1 to v2 upgrade sequence
```

## User Prompt

${input:Additional context for the commit (optional)}

---
description: "Commit changes using Conventional Commits (clean-bitrix)"
model: Claude Haiku 4.5 (copilot)
tools: ['read', 'search', 'execute/runInTerminal']
---

# Git Commit

## Instructions

You are a git commit assistant for the **clean-bitrix** project — a PHP library providing Clean Architecture abstractions for 1C-Bitrix CMS, part of the [Foreline](https://github.com/foreline/clean) ecosystem.

1. Review staged changes using `git diff --cached`.
2. If no files are staged, stage all tracked and untracked changes with `git add -A`, then re-check. If still nothing to commit (clean working tree), report that and stop.
3. Analyze staged diffs and classify each changed file by feature area.
4. **Commit splitting rule (mandatory):**
   - If staged files touch different features or issues, create separate commits — one per feature/issue.
   - **Dependency updates** (`composer.lock`, `composer.json`) MUST be in their own commit, separate from code or config changes.
   - Execute the planned commit(s) immediately, without asking for confirmation.
5. Write Conventional Commit messages in English.
6. **Changelog:** After every commit, update `CHANGELOG.md` following the rules below.
7. **Tagging:** After committing, evaluate whether a new version tag is warranted. Follow the SemVer tagging rules below.

## Project-Specific Rules

### Dependency Awareness

This project critically depends on `foreline/clean` (and `foreline/clean-ui`, `foreline/io-response`). These are internal Foreline ecosystem libraries that provide the core Clean Architecture abstractions this project implements.

**When `composer.lock` changes:**
1. Always inspect the diff to identify **which packages** changed and **what versions** they moved to.
2. Classify the dependency update:
   - **Core dependency** (`foreline/clean`, `foreline/clean-ui`, `foreline/io-response`) — these are **always significant**. A version bump in `foreline/clean` likely means new interfaces, entities, or patterns that this library must align with.
   - **Other dependencies** (`symfony/*`, `predis/*`, etc.) — treat as routine maintenance.
3. Commit `composer.lock` (and `composer.json` if changed) separately from other changes using `chore(deps): ...` type.
4. In the commit body, explicitly list the package name and version transition (e.g., `foreline/clean v1.1.3 → v1.1.4`).

### Tagging After Core Dependency Updates

**A version bump in any `foreline/*` package MUST trigger a new tagged release of clean-bitrix**, even if no source code changed. Rationale: downstream consumers of clean-bitrix need to know that the underlying abstractions have been updated.

- `foreline/clean` MINOR bump (new features) → bump clean-bitrix **MINOR**.
- `foreline/clean` PATCH bump (bug fixes) → bump clean-bitrix **PATCH**.
- `foreline/clean` MAJOR bump (breaking changes) → bump clean-bitrix **MAJOR**.
- For `foreline/clean-ui` and `foreline/io-response`, use the same logic but these are less likely to require MAJOR bumps.

### Scope Detection

- Derive scopes from the project's directory structure (top-level folders under `src/`): `domain`, `infrastructure`, `presentation`.
- Sub-scopes by feature area: `event`, `redis`, `repository`, `mailer`, `user`, `file`, `http`, `form`.
- Common universal scopes: `docs`, `tests`, `config`, `ci`, `deps`.
- Keep scopes short, lowercase, and consistent within the project.
- If changes span multiple unrelated areas, split into separate commits.

## Conventional Commits Format

```
<type>(<scope>): <short imperative summary>

<optional body: what changed and why>
```

### Types

- `feat` — new functionality
- `fix` — bug fix
- `refactor` — internal restructuring without behavior change
- `docs` — documentation-only changes
- `style` — formatting-only changes
- `chore` — maintenance, tooling, dependencies, housekeeping
- `perf` — performance improvements
- `test` — adding or updating tests

### Rules

- Subject line in English, imperative mood, max 72 characters.
- Body in English, concise — explain what and why, not implementation detail.
- Add issue references when available: `Refs: #123`.
- Do not commit unrelated changes together.

### Good Examples

```
chore(deps): update foreline/clean to v1.1.4

foreline/clean v1.1.3 → v1.1.4.
```

```
feat(event): add retry policy for async event dispatching

Implement per-subscriber RetryPolicy with exponential backoff for
failed event dispatches in SymfonyMessengerAsyncDispatcher.
```

```
fix(repository): prevent duplicate field mapping in Bxd7

Skip fields already registered in the type map to avoid ORM query errors.
```

```
docs(adr): add ASYNC_EVENT_ARCHITECTURE decision record
```

## Changelog

After every commit, update (or create) a `CHANGELOG.md` file in the project root following the [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format.

### Format

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- New features.

### Changed
- Changes to existing functionality.

### Deprecated
- Features that will be removed in upcoming releases.

### Removed
- Features that were removed.

### Fixed
- Bug fixes.

### Security
- Vulnerability fixes.

## [1.0.0] - 2025-01-15

### Added
- Initial release.
```

### Rules

- Map Conventional Commit types to changelog sections:
  - `feat` → **Added**
  - `fix` → **Fixed**
  - `refactor`, `perf` → **Changed**
  - `docs` → **Changed** (only if user-facing; skip internal docs)
  - `chore(deps)` with core dependency update → **Changed** (e.g., "Updated foreline/clean to v1.1.4")
  - `chore`, `style`, `test`, `ci` → skip (not notable to end users), unless the change is significant
- New entries go under `## [Unreleased]`.
- When a version tag is created, rename `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD` and add a fresh empty `## [Unreleased]` section above it.
- Each entry is a single concise line describing the user-visible change — not the raw commit message.
- Keep entries in reverse chronological order within each section.
- If `CHANGELOG.md` does not exist, create it and back-fill entries from the git log (`git log --oneline --format="%h %s (%ai)"`) grouped by existing tags. Use commit dates for tag sections.
- Stage and include the changelog update in the same commit if it's a single-commit workflow. If committing has already happened, create a follow-up `docs(changelog): update CHANGELOG.md` commit.

## SemVer Tagging

After committing, autonomously decide whether to create a new version tag. Create an **annotated git tag** following [Semantic Versioning 2.0.0](https://semver.org/).

### When to Tag

Use your judgment. Create a tag when any of these apply:
- A meaningful `feat` commit introduces new user-facing functionality.
- A critical `fix` addresses an important bug.
- A **core dependency** (`foreline/clean`, `foreline/clean-ui`, `foreline/io-response`) has been updated — **always tag**.
- Multiple smaller changes have accumulated since the last tag forming a coherent release.
- The user explicitly requests a tag or release.

Do **not** tag for:
- Trivial or internal-only changes (`chore`, `docs`, `style`, `ci`, `test`) that don't affect core dependencies, unless the user requests it.
- Work-in-progress or partial features.

### Version Format

```
vMAJOR.MINOR.PATCH
```

- **MAJOR** — incompatible API or behavioral changes (breaking changes, removed features, changed config format).
- **MINOR** — new functionality added in a backward-compatible manner (new features, new config options, new endpoints). Also: core dependency MINOR bump.
- **PATCH** — backward-compatible bug fixes, documentation updates, internal refactors with no user-facing behavior change. Also: core dependency PATCH bump.

### Determining the Next Version

1. Find the latest tag: `git describe --tags --abbrev=0`.
2. Review commits since that tag: `git log <latest-tag>..HEAD --oneline`.
3. Classify changes using Conventional Commits types:
   - Any commit with a `BREAKING CHANGE` footer or `!` after the type → bump **MAJOR**.
   - Any `feat` commit → bump **MINOR** (unless MAJOR is already bumped).
   - Core dependency (`foreline/*`) version change → match the bump level of the dependency itself.
   - Only `fix`, `refactor`, `perf`, `docs`, `style`, `chore`, `test` → bump **PATCH**.
4. If the user specifies a version explicitly, use that instead.

### Creating the Tag

Use **annotated tags** with a summary of changes:

```powershell
git tag -a v1.2.0 -m "v1.2.0: <short summary of release>"
```

### Tag Message Format

```
v1.2.0: <one-line summary>

Changes:
- feat(scope): description
- fix(scope): description
- chore(deps): description
```

List the commit subjects since the previous tag, grouped by type. Keep it concise.

### Rules

- Always prefix versions with `v` (e.g., `v1.0.0`, not `1.0.0`).
- Never tag uncommitted or dirty state — all changes must be committed first.
- When tagging, first rename the `## [Unreleased]` section in `CHANGELOG.md` to the new version and commit that update before creating the tag.
- Do not push the tag automatically — let the user decide when to push.
- Pre-release versions use a hyphen suffix: `v1.2.0-alpha.1`, `v1.2.0-beta.2`, `v1.2.0-rc.1`.
- The first stable release of the project is `v1.0.0`. Versions below `v1.0.0` (e.g., `v0.x.y`) indicate pre-stable development where MINOR may include breaking changes.

### Good Examples

```
v1.1.8: update foreline/clean to v1.1.4

Changes:
- chore(deps): update foreline/clean v1.1.3 → v1.1.4
- chore(config): add GitHub Copilot instructions and prompts
```

```
v1.2.0: add async event retry policies

Changes:
- feat(event): add per-subscriber retry policy with exponential backoff
- fix(event): prevent duplicate event dispatch on worker restart
- docs(adr): add ASYNC_RETRY_POLICY decision record
```

## User Prompt

${input:Additional context for the commit (optional)}

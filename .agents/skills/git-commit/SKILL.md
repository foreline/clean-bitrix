---
name: git-commit
description: Commit changes for the clean-bitrix project using Conventional Commits, automatic changelog updates, and SemVer tagging. Use this skill when the user asks to commit staged or unstaged changes, create a release commit, update dependencies, or tag a new version.
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

### Core Dependency Version Tracking

**clean-bitrix version MUST track `foreline/clean` versions at both MAJOR and MINOR levels.** These two libraries are tightly coupled — clean-bitrix implements the abstractions defined in foreline/clean, so their version branches must align to signal feature parity and API stability to consumers.

> **Tracked foreline/clean version: 2.8**

**Rules:**
- If `foreline/clean` changes its **MAJOR or MINOR version** (e.g., v2.0.0 → v2.1.0 or v2.0.0 → v3.0.0), clean-bitrix MUST be bumped to match that **MAJOR.MINOR** version (e.g., v2.1.0 or v3.0.0). Commit with `chore(deps)!: ...` (note the `!` for MAJOR bumps) and **update the tracked version above** in this skill file.
- If `foreline/clean` changes its **PATCH version only** (e.g., v2.0.0 → v2.0.1), clean-bitrix can optionally bump its **PATCH** independently if there are no code changes — it's not required to match PATCH.
- The tracked version is the single source of truth for detecting MAJOR/MINOR version crossings. Always check the diff against it, not against the previous `composer.lock` alone.
- This ensures clean-bitrix version numbers transparently communicate which version branch of the core library it supports.

### Tagging After Core Dependency Updates

**A MAJOR or MINOR version bump in any `foreline/*` package MUST trigger a new tagged release of clean-bitrix**, even if no source code changed. Rationale: downstream consumers of clean-bitrix need to know that the underlying abstractions have been updated and which version branch they're aligned with.

- `foreline/clean` MAJOR or MINOR bump → set clean-bitrix to the **same MAJOR.MINOR version** as `foreline/clean` (e.g., foreline/clean v2.0.0 → clean-bitrix v2.0.0; foreline/clean v2.1.0 → clean-bitrix v2.1.0).
- `foreline/clean` PATCH bump (bug fixes only) → bump clean-bitrix **PATCH** if no code changes, or continue with next commit's natural bump.
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
chore(deps): update foreline/clean to v2.0.0

foreline/clean v1.1.3 → v2.0.0 (major version bump).
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
  - `chore(deps)` with core dependency update → **Changed** (e.g., "Updated foreline/clean to v2.0.0")
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
- **PATCH** — backward-compatible bug fixes, documentation updates, internal refactors with no user-facing behavior change.

### Determining the Next Version

1. Find the latest tag: `git describe --tags --abbrev=0`.
2. Review commits since that tag: `git log <latest-tag>..HEAD --oneline`.
3. Classify changes using Conventional Commits types:
   - Any commit with a `BREAKING CHANGE` footer or `!` after the type → bump **MAJOR**.
   - Any `feat` commit → bump **MINOR** (unless MAJOR is already bumped).
   - Core dependency (`foreline/*`) **MAJOR or MINOR** bump → match that exact MAJOR.MINOR version for clean-bitrix.
   - Core dependency **PATCH** bump only → bump clean-bitrix **PATCH** if desired, or skip if no code changes.
   - Only `fix`, `refactor`, `perf`, `docs`, `style`, `chore`, `test` with no core dependency changes → bump **PATCH**.
4. If the user specifies a version explicitly, use that instead.

### Creating the Tag

Use **annotated tags** with a summary of changes:

```powershell
git tag -a v2.0.0 -m "v2.0.0: <short summary of release>"
```

### Tag Message Format

```
v2.0.0: <one-line summary>

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
v2.0.0: align with foreline/clean v2.0.0

Changes:
- chore(deps): upgrade foreline/clean v1.1.3 → v2.0.0
- chore(deps): upgrade foreline/clean-ui v1.0.1 → v1.1.0
```

```
v2.1.0: add async event retry policies

Changes:
- feat(event): add per-subscriber retry policy with exponential backoff
- fix(event): prevent duplicate event dispatch on worker restart
- docs(adr): add ASYNC_RETRY_POLICY decision record
```

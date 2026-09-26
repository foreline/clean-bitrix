---
name: git-commit
description: Commit changes for the clean-bitrix project using Conventional Commits, a brief dated changelog, and SemVer tagging. Use this skill when the user asks to commit staged or unstaged changes, create a release commit, update dependencies, or tag a new version.
---

# Git Commit

Commit assistant for the **clean-bitrix** project — a PHP library providing Clean
Architecture abstractions for 1C-Bitrix CMS, part of the
[Foreline](https://github.com/foreline/clean) ecosystem. Three duties, in order:
conventional commit message → brief changelog entry → **one** commit containing
the staged changes **and** the changelog update.

## Speed rules (follow strictly)

1. Run all git commands in the repo root (`D:/github/clean-bitrix`).
2. Stage **everything first**, including untracked files:
   - `git ls-files --others --exclude-standard` (metadata only — list, don't open)
   - `git add -A`
   - If both the staged diff and the untracked list are empty, exit and report
     "nothing to commit".
3. Inspect staged changes with **metadata only**:
   - `git diff --cached --name-status`
   - `git diff --cached --stat`
   - Never run `git diff --cached` (full content).
4. Read content only of small source-of-truth files when needed to explain the why.
5. Do not run tests, linters, or builds.
6. **Never** create a separate commit for the changelog (no `chore: update changelog`,
   no `docs: changelog`). The changelog is always part of the main commit.

## Grouping

- One logical change = one commit.
- Multiple **unrelated** changes (different features/issues) → split into separate
  commits by area. Do not create one giant mixed commit.
- **Dependency updates** (`composer.lock`, `composer.json`) MUST be in their own
  `chore(deps): ...` commit, separate from code or config changes.
- To split, commit with pathspecs: `git commit -m "<msg>" -- <paths>` — always
  including `CHANGELOG.md` in each commit's pathspec (update it per commit).
- Execute the planned commit(s) immediately, without asking for confirmation.

## Project-Specific Rules

### Dependency Awareness

This project critically depends on `foreline/clean` (and `foreline/clean-ui`, `foreline/io-response`). These are internal Foreline ecosystem libraries that provide the core Clean Architecture abstractions this project implements.

**When `composer.lock` changes:**
1. Identify **which packages** changed and **what versions** they moved to (`git diff --cached -- composer.lock` is allowed for this file only — or read `composer.json` constraints).
2. Classify the dependency update:
   - **Core dependency** (`foreline/clean`, `foreline/clean-ui`, `foreline/io-response`) — these are **always significant**.
   - **Other dependencies** (`symfony/*`, `predis/*`, etc.) — treat as routine maintenance.
3. Commit `composer.lock` (and `composer.json` if changed) separately from other changes using `chore(deps): ...` type.
4. In the commit body, explicitly list the package name and version transition (e.g., `foreline/clean v1.1.3 → v1.1.4`).

### Core Dependency Version Tracking

**clean-bitrix version MUST track `foreline/clean` versions at both MAJOR and MINOR levels.** These two libraries are tightly coupled — clean-bitrix implements the abstractions defined in foreline/clean, so their version branches must align to signal feature parity and API stability to consumers.

> **Tracked foreline/clean version: 3.1**

**Rules:**
- If `foreline/clean` changes its **MAJOR or MINOR version** (e.g., v2.0.0 → v2.1.0 or v2.0.0 → v3.0.0), clean-bitrix MUST be bumped to match that **MAJOR.MINOR** version (e.g., v2.1.0 or v3.0.0). Commit with `chore(deps)!: ...` (note the `!` for MAJOR bumps) and **update the tracked version above** in this skill file.
- If `foreline/clean` changes its **PATCH version only** (e.g., v2.0.0 → v2.0.1), clean-bitrix can optionally bump its **PATCH** independently if there are no code changes — it's not required to match PATCH.
- The tracked version is the single source of truth for detecting MAJOR/MINOR version crossings. Always check the diff against it, not against the previous `composer.lock` alone.
- This ensures clean-bitrix version numbers transparently communicate which version branch of the core library it supports.

### Tagging After Core Dependency Updates

**A MAJOR or MINOR version bump in any `foreline/*` package MUST trigger a new tagged release of clean-bitrix**, even if no source code changed. Rationale: downstream consumers of clean-bitrix need to know that the underlying abstractions have been updated and which version branch they're aligned with.

- `foreline/clean` MAJOR or MINOR bump → set clean-bitrix to the **same MAJOR.MINOR version** as `foreline/clean` (e.g., foreline/clean v2.1.0 → clean-bitrix v2.1.0).
- `foreline/clean` PATCH bump (bug fixes only) → bump clean-bitrix **PATCH** if no code changes, or continue with next commit's natural bump.
- For `foreline/clean-ui` and `foreline/io-response`, use the same logic but these are less likely to require MAJOR bumps.

### Scope Detection

- Derive scopes from the project's directory structure (top-level folders under `src/`): `domain`, `infrastructure`, `presentation`.
- Sub-scopes by feature area: `event`, `redis`, `repository`, `mailer`, `user`, `file`, `http`, `form`.
- Common universal scopes: `docs`, `tests`, `config`, `ci`, `deps`.
- Keep scopes short, lowercase, and consistent within the project.

## Conventional Commits Format

```
<type>(<scope>): <imperative summary in English, max 72 chars>

<optional body: what and why, wrap at 100 chars>
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

## Changelog (mandatory, brief)

File: `CHANGELOG.md` in the project root. If missing, create it with a `# Changelog`
header. Simple list format, newest on top:

```markdown
# Changelog

## 2026-09-20

- feat(repository): add string filter operators to RangeFilterTranslator
- chore(deps): update foreline/clean to v2.9.0
```

Rules:
- One bullet per commit in this run, mirroring the commit subject line verbatim.
- Reuse today's `## YYYY-MM-DD` heading if it already exists; otherwise add a new
  heading directly under `# Changelog`.
- Entries describe **what changed**, briefly — no bodies, no detail sections.
- Leave pre-existing legacy sections (e.g. Keep-a-Changelog `[Unreleased]` blocks)
  untouched; new entries always go into the dated list on top.

## SemVer Tagging

After committing, autonomously decide whether to create a new version tag. Create an
**annotated git tag** following [Semantic Versioning 2.0.0](https://semver.org/).

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

```bash
git tag -a v2.0.0 -m "v2.0.0: <short summary of release>"
```

Tag message format — one-line summary plus commit subjects since the previous tag,
grouped by type:

```
v2.0.0: align with foreline/clean v2.0.0

Changes:
- chore(deps): upgrade foreline/clean v1.1.3 → v2.0.0
- chore(deps): upgrade foreline/clean-ui v1.0.1 → v1.1.0
```

### Rules

- Always prefix versions with `v` (e.g., `v1.0.0`, not `1.0.0`).
- Never tag uncommitted or dirty state — all changes must be committed first.
- Tagging does NOT rename or restructure `CHANGELOG.md`; the dated list stays as-is.
- Do not push the tag automatically — let the user decide when to push
  (release pushes go to **all** remotes: `git push github main --tags && git push gitlab main --tags`).
- Pre-release versions use a hyphen suffix: `v1.2.0-alpha.1`, `v1.2.0-beta.2`, `v1.2.0-rc.1`.

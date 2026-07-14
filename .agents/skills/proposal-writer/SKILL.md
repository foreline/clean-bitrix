---
name: proposal-writer
description: Creates architecture decision records (ADR), proposals, and design documents following project conventions in `docs/adr/` and `docs/proposals/`. Use this skill when suggesting architectural changes, proposing refactoring strategies, documenting design decisions, investigating issues, creating review responses or iterations, or discussing complex implementation approaches.
---

# ADR & Proposal Writer

## Locations

### ADRs — `docs/adr/`
Architecture Decision Records are organized by **domain subdirectory**:

```
docs/adr/
  architecture/          # Structural decisions, layer boundaries, DDD patterns
  domain/                # Domain entity decisions (User, File, new aggregates)
  event/                 # Async event system decisions
  infrastructure/        # Bitrix ORM, Redis, Mailer integration decisions
  migrations/            # Migration system decisions, schema changes
    before_up/           # Pre-migration step proposals
  presentation/          # HTTP, forms, UI helper decisions
  repository/            # Repository pattern, query composition decisions
```

Always place new ADRs in the appropriate domain subdirectory. Create a new subdirectory if the topic does not fit existing ones.

### Proposals — `docs/proposals/`
Issue investigations, bug resolution summaries, and standalone proposals that do not warrant a full ADR.

---

## Naming Convention

### Two naming formats

**1. Numbered (within a subdirectory with related documents):**
```
XX. SHORT_DESCRIPTION.md
```
Use when documents form a sequence (analysis -> response -> v2).

**2. Plain (standalone documents):**
```
SHORT_DESCRIPTION.md
```
Use for standalone analyses, summaries, and issue investigations.

### Rules
- UPPERCASE with UNDERSCORES
- English language only
- Be specific (3-5 words)

### Examples
```
GOOD:
docs/adr/migrations/before_up/00. MIGRATION_BEFORE_UP_PROPOSAL.md
docs/adr/event/ASYNC_RETRY_POLICY.md
docs/adr/repository/ORM_NESTED_FETCHING.md
docs/adr/domain/USER_GROUP_AGGREGATE.md
docs/adr/infrastructure/REDIS_PUBSUB_NOTIFICATIONS.md
docs/proposals/BITRIX_D7_FILTER_ISSUE.md

BAD:
docs/adr/status-implementation.md          (lowercase, no subdirectory)
docs/adr/architecture/new feature.md       (spaces, lowercase)
01. STATUS_IMPLEMENTATION.md               (in root, no subdirectory)
```

---

## Linking Convention

### Related Document Links
Always link to related documents:

```markdown
**Related:** `docs/adr/migrations/before_up/00. MIGRATION_BEFORE_UP_PROPOSAL.md`, `docs/adr/repository/ORM_NESTED_FETCHING.md`
```

For inline references use relative paths within the same subdirectory:
```markdown
Detailed analysis is in [01. MIGRATION_ANALYSIS.md](01.%20MIGRATION_ANALYSIS.md).
```

**Important:** URL-encode spaces as `%20` in links!

### Cross-directory references
Use paths relative to the repository root:
```markdown
**Related:** `docs/adr/architecture/HEXAGONAL_LAYER_BOUNDARIES.md`
```

---

## Document Types

### 1. ADR (Architecture Decision Record)
Records a significant architectural decision with context, alternatives, and rationale.

### 2. Proposal
Suggests a new feature, refactoring strategy, or architectural change.

### 3. Analysis
Deep investigation of a problem, codebase area, or existing pattern.

### 4. Review / Response
Feedback on an existing ADR or proposal, with reviewer comments.

### 5. Issue Investigation
Root cause analysis and resolution summary for bugs or issues.

### 6. Implementation Summary
Documents what was implemented and how (post-decision).

---

## Templates

### ADR Template

```markdown
# [Decision Title]

**Date:** YYYY-MM-DD
**Status:** Draft | In Review | Approved | Implemented | Superseded
**Priority:** LOW | MEDIUM | HIGH | CRITICAL

---

## Context
What situation or problem prompted this decision?

## Problem Statement
Clear definition of the problem. What specifically needs to change and why?

## Decision
The chosen approach. State it clearly and concisely.

## Alternatives Considered

### Alternative 1: [Name]
Description, pros, cons, and why it was rejected.

### Alternative 2: [Name]
Description, pros, cons, and why it was rejected.

## Consequences

### Positive
- Benefit 1
- Benefit 2

### Negative
- Trade-off 1
- Trade-off 2

## Implementation Plan

### Phase 1: [Description]
- Step 1
- Step 2

## Success Criteria
- [ ] Criterion 1
- [ ] Criterion 2

## References
- [Related ADR](path/to/related.md)
- Related code: `src/Infrastructure/Bitrix/Bxd7.php`
```

### Proposal Template

```markdown
# [Proposal Title]

**Date:** YYYY-MM-DD
**Status:** Draft | In Review | Approved | Rejected
**Priority:** LOW | MEDIUM | HIGH | CRITICAL

---

## Context
Brief description of the problem or opportunity.

## Current Situation
What exists today and why it is insufficient.

## Problem Statement
Clear definition of the problem to solve.

## Proposed Solution
Detailed explanation of the suggested approach.

### Architecture
High-level architecture or design.

### Implementation Details
Technical specifics of the implementation.

### Benefits
- Benefit 1
- Benefit 2

### Drawbacks
- Drawback 1
- Drawback 2

## Impact Analysis

### Breaking Changes
List any breaking changes.

### Migration Path
How to migrate from current to proposed solution.

### Backward Compatibility
Compatibility considerations.

## Implementation Plan

### Phase 1: [Description]
- Step 1
- Step 2

## Success Criteria
- [ ] Criterion 1
- [ ] Criterion 2

## Open Questions
- Question 1?
- Question 2?

## References
- [Related Document](path/to/related.md)
- Related code: `src/Domain/User/Infrastructure/Repository/Bitrix/UserRepository.php`
```

### Analysis Template

```markdown
# [Subject] Analysis

**Date:** YYYY-MM-DD
**Status:** Analysis complete | Fix proposed | Awaiting review
**Related:** `path/to/related/document.md`

---

## Summary
Brief summary of findings.

## Verified Findings

### 1. [Finding Title]
Detailed explanation with code references.

### 2. [Finding Title]
Detailed explanation with code references.

## Root Cause
What is causing the issue (if applicable).

## Recommendations
1. Recommendation 1
2. Recommendation 2

## Tasks
- [x] Completed investigation step
- [ ] Pending action item
```

### Review / Response Template

```markdown
# Response to [Original Document Title]

<!--
REVIEWER: email@example.com
REVIEW DATE: YYYY-MM-DD
-->

**Reviewer:** Name/Email
**Review Date:** YYYY-MM-DD
**Responding To:** [ORIGINAL_DOCUMENT.md](ORIGINAL_DOCUMENT.md)

---

## Summary of Concerns
The reviewer identified N concerns:

1. Concern 1
2. Concern 2

## Feedback

### Strengths
- What works well

### Concerns
- Areas that need clarification or pose risks

### Questions
1. Question about specific aspect?

## Revised Analysis / Recommendations
Updated assessment addressing reviewer feedback.

## Next Steps
- [ ] Action item 1
- [ ] Action item 2
```

Reviewer comments can also be embedded as HTML comments within any document:
```markdown
<!--
REVIEWER: dima@foreline.ru
REVIEW DATE: 2026-01-20
-->

Some proposal text here.

<!--
REVIEW_NOTICE: Important note from reviewer about this section.
-->
```

### Issue Investigation Template

```markdown
# [Issue Title]

## Problem Statement
What is broken and how to reproduce.

## Root Cause Analysis
Deep investigation of why the issue occurs.

### Call Flow
1. Step 1
2. Step 2

### The Actual Problem
Precise explanation of the bug.

## Solution

### Fix Applied
What was changed and why.

### Verification
How the fix was verified.

## Files Modified
1. **path/to/file.php** - Description of change
```

---

## Step-by-Step Process

### Creating a New ADR

1. **Identify the domain subdirectory**
   ```powershell
   # List existing ADR domains
   Get-ChildItem -Path .\docs\adr\ -Directory -Recurse
   ```

2. **Check for existing documents on the topic**
   ```powershell
   # Search within the target subdirectory
   Get-ChildItem -Path .\docs\adr\migrations\ -Filter "*.md" -Recurse | Sort-Object Name
   ```

3. **Choose naming format**
   - Use **numbered** if the document is part of a sequence within its subdirectory
   - Use **plain** for standalone documents

4. **Choose a descriptive name**
   - UPPERCASE, underscores
   - 3-5 words, be specific

5. **Select the appropriate template** (ADR, Proposal, Analysis, Review, Issue)

6. **Write the document**
   - Fill all relevant sections from the template
   - Remove sections that do not apply
   - Include code examples where relevant
   - Link to related documents and source code

### Creating a Review/Response

1. **Create the response in the same subdirectory** as the original document
2. **Link to the original** using either inline `**Related:**` or HTML comments
3. **Use reviewer HTML comments** for inline feedback:
   ```markdown
   <!--
   REVIEWER: dima@foreline.ru
   REVIEW DATE: 2026-01-20
   -->
   ```

### Creating an Iteration

When updating a document based on review feedback:

```
Original:  MIGRATION_BEFORE_UP_PROPOSAL.md
Response:  MIGRATION_BEFORE_UP_RESPONSE.md
Revised:   MIGRATION_BEFORE_UP_FINAL_RESPONSE.md
```

Or with numbered format:
```
Original:  00. MIGRATION_BEFORE_UP_PROPOSAL.md
Analysis:  01. MIGRATION_BEFORE_UP_ANALYSIS.md
```

Link all previous documents in the chain:
```markdown
**Related:** `docs/adr/migrations/before_up/00. MIGRATION_BEFORE_UP_PROPOSAL.md`
```

---

## Domain-Specific Templates

For extended proposal structures, see [`references/domain-specific-templates.md`](references/domain-specific-templates.md):

- **Architecture Proposal** — component responsibilities, data flow, design patterns, Clean Architecture compliance
- **Refactoring Proposal** — code smells checklist, before/after changes, risk assessment, rollback plan
- **Feature Proposal** — requirements, entities, use cases, testing plan

---

## Project-Specific Guidance

When writing ADRs for this project, keep in mind:

### Common Decision Domains
- **Domain layer architecture**: aggregates, entities, value objects, use case organization, repository interfaces
- **Bitrix ORM integration**: Bxd7 field mapping, proxy/DTO conversions, iblock queries, section handling
- **Repository patterns**: filter/sort/limit composition, nested fetching, reference field support, nesting depth
- **Async event system**: EventStore/EventDispatch tables, Symfony Messenger transport, retry policies, user context restoration
- **Redis integration**: pub/sub channels, user notifications, Socket.IO payloads, KV store operations
- **Migration system**: schema changes, table renaming, before_up / before_down hooks
- **Presentation layer**: Bitrix HTTP integration, form handling, design helpers

### Code References
Always reference specific source files when discussing implementation:
```markdown
Related code: `src/Infrastructure/Bitrix/Bxd7.php`, `src/Infrastructure/Event/SymfonyMessengerAsyncDispatcher.php`
```

### Task Tracking
Use checkbox lists for tracking implementation progress:
```markdown
## Tasks
- [x] Analyze the current event dispatch flow
- [x] Propose retry policy changes
- [ ] Implement the fix
- [ ] Write tests
```

### Status Markers
Mark completed decisions inline:
```markdown
## Status: COMPLETED (January 16, 2026)
```

---

## Checklist

### Before Creating
- [ ] Checked existing ADRs/proposals for similar topics
- [ ] Identified the correct domain subdirectory
- [ ] Chosen naming format (numbered or plain)
- [ ] Chosen a specific, descriptive UPPERCASE name
- [ ] Identified related documents to link

### Document Content
- [ ] Clear problem statement or context
- [ ] Solution or decision clearly stated
- [ ] Benefits and drawbacks/trade-offs listed
- [ ] Code references and examples included
- [ ] Implementation plan with phases (if applicable)
- [ ] Success criteria defined
- [ ] Links to related documents

### Review/Response
- [ ] Linked to original document
- [ ] Constructive feedback provided
- [ ] Specific questions asked
- [ ] Reviewer information included (HTML comment or inline)

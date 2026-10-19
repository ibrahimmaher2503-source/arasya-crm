# Tasks: [FEATURE NAME]

**Input**: spec.md and plan.md (including Test Design)
**Authority**: `docs/backlog/tasks.json`; this checklist is supplemental.
**Registered scope / coordinator**: [Backlog IDs, lease owner and paths]

## Test and Delegation Rules

Suitable business-logic changes and reproducible bugs require RED → GREEN → REFACTOR.
The project's risk-based test strategy overrides upstream blanket optional-test text.
For a non-behavioral change, record the focused check and why behavioral TDD is N/A.
Before test authoring, delegate safely independent test files/review using explicit
gpt-6-luna / xhigh workers. Record unavailability or actual scope/runtime conflicts.
`[P]` is a candidate only: coordinator authorization, non-overlapping file ownership,
dependency readiness and isolated fixtures/worktrees are required. No recursive work.

## Dependency-Ordered Slices

Replace the example row with real paths; do not initialize frameworks or add models
because a generic template suggests them.

| ID / story | Backlog ID | Depends on | Owner / exact files | Acceptance / test command |
| --- | --- | --- | --- | --- |
| T001 / US1 | [ID] | [IDs or none] | [owner, files] | [FR-001 outcome and command] |

Use the installed task syntax: `- [ ] T001 [P?] [US1] Description with exact path`.
Group each story's tasks together, in dependency order. Replace these placeholders
with actual files and IDs; remove non-applicable tasks with a test-design rationale.

- [ ] T001 Confirm discovery, clarification and test design in [feature plan path].
- [ ] T002 [US1] RED: add failing behavior assertions in [exact test path]; record result.
- [ ] T003 [US1] GREEN: implement the smallest change in [exact implementation path].
- [ ] T004 [US1] REFACTOR: justified cleanup in [exact path], keeping tests green.
- [ ] T005 [US1] Verify relevant negative/security/integration/UI cases in [exact test paths].
- [ ] T006 Record independent review findings and resolutions in [handoff path].
- [ ] T007 Coordinator verifies combined changes and records lifecycle evidence in [handoff path].

## Handoff Evidence

[Changed files, skills and paths, worker ownership/worktrees, RED/GREEN commands and
assertions, other PASS/FAIL/SKIPPED/NOT RUN results, independent review, integration
checks, commit or tree identity, unresolved gates and newly ready tasks. A checked box
never replaces canonical lifecycle or product acceptance.]

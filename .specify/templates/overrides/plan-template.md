# Implementation Plan: [FEATURE]

**Spec**: [spec.md] | **Backlog IDs**: [IDs] | **Owner/date**: [owner/date]
**Risk**: [low / business behavior / high, with reason]

## Constitution Check

Read `.specify/memory/constitution.md`, AGENTS.md and the execution/loop guides.
Confirm scope, decision gates, lease availability, existing caller/table ownership,
and preservation of unrelated work. Repeat before integration.

## Technical Plan

[Smallest complete change, exact files and callers, current Domain/Application/
Infrastructure/Presentation responsibilities, transaction and authorization effects.
Preserve Query Builder and Domain Records. Do not scaffold a new application.]

## Test Design

| Behavior / requirement | Risk / invariants | Test path and assertions | Fixture / runtime | Owner |
| --- | --- | --- | --- | --- |
| [FR/US] | [positive, negative, security, stale/replay cases] | [unit/feature/integration/regression as relevant] | [isolated resources] | [worker/coordinator] |

Suitable business changes use RED → GREEN → REFACTOR. Name the expected failing
behavior and command before implementation. For low-risk non-behavioral changes,
record why behavioral TDD is not applicable and select a meaningful focused check.
Never substitute a setup failure or coverage metric for a regression assertion.

## Ownership and Dependencies

[Exact worker file ownership, dependencies, fixture/database/port ownership, isolated
worktree paths, gpt-6-luna / xhigh availability and independent reviewer. Record the
concrete conflict if test delegation is unsafe; no recursive delegation.]

## Verification and Integration

[Exact targeted commands and required evidence; relevant test strategy tier;
baseline failures and blockers; reviewer; integration checks; rollback compatibility.
Use task-loop for lifecycle. No integration before applicable gates pass.]

## Additional Artifacts

[Only research/contracts/data-model changes needed by this scope, or N/A with reason.
Test Design stays in this plan; avoid duplicate policy/workflow documents.]

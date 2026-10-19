# Local task-loop bootstrap

Bootstrap recorded 2026-10-15 UTC. Runtime is Windows PowerShell 5.1.26100.9711; no Git directory is present. This repository remains documentation/planning only: no Laravel application, tools, provider sends, or secrets were created or accessed.

## Tooling and state

- Added `scripts/task-loop.ps1` for read-only status/readiness, scoped `Claim`/`Renew`/`Review`/`Complete`/`Block`/`Release`, checksummed pending intents with owner-bound `Recover`, and `RefreshBacklog`.
- Added `scripts/decision-evidence.ps1`, shared with `scripts/validate-docs.ps1`; readiness accepts only explicit OPEN/ADR status plus a complete owner/date/option/evidence/tasks/tests/review record and existing separate evidence. The checker never writes approvals.
- Added `scripts/test-task-loop.ps1` with disposable fixtures outside the backlog, deterministic intent/handoff/status/state fault injection, hash-mismatch refusal, Complete prerequisite rechecks/rollback, and [operations instructions](../../agents/LOOP_OPERATIONS.md).
- Coordinator state and leases are in [state.json](state.json); claim evidence is separate under `handoffs/`. `tasks.json` remains authoritative; [BACKLOG.md](../../backlog/BACKLOG.md) is a generated status snapshot refreshed by `& './scripts/task-loop.ps1' -Action RefreshBacklog`.

## Active claims

Both claims were created under the coordinator lock at 2026-10-15T16:37Z. They remain `in_progress`; neither task was reviewed or completed.

| Task | Owner | Scope | Lease ID | Expires UTC |
|---|---|---|---|---|
| `A0-ARCH-900` | `finance-stock-worker` | `docs/architecture/finance-stock-contracts.md`; Finance, Inventory | `fe7b40c031d84afa800df1db10ec8df8` | 2026-10-16T04:37:44.4998670Z |
| `A0-OPEN-02` | `approval-brief-worker` | `docs/governance/evidence/OPEN-02.md`; Governance | `910e87d11155450fa47d0aa38830d397` | 2026-10-16T04:37:58.8217278Z |

Claim records are [A0-ARCH-900 handoff](handoffs/A0-ARCH-900-20261015T163744865Z-a47f257a5e124ccbbf95136fda8f2848.md) and [A0-OPEN-02 handoff](handoffs/A0-OPEN-02-20261015T163759139Z-4d550520316b487b8c2d362685823450.md). Their scopes do not overlap. The worker deliverables and shared ADR files were not edited by this bootstrap.

No OPEN or ADR approval was created or changed. `OPEN-02` remains OPEN; completing its evidence brief must not resolve the decision. `A0-ARCH-900` is design-only and must retain unresolved policy placeholders.

## Verification recorded

- `& './scripts/test-task-loop.ps1'` — PASS on disposable fixtures: lock/scope/dependency/decision checks; injected intent, handoff, task-status, and finalized-state interruptions; idempotent Claim/Complete recovery; rollback on newly unresolved ADR; unknown-edit refusal; Arabic UTF-8 preservation; and event-linked handoffs (21 events).
- `& './scripts/validate-docs.ps1'` — PASS: tasks=239; PRD_IDs=124; local_links=246; JSON/enums/fields/DAG/reverse_edges/coverage/links valid.
- `& './scripts/task-loop.ps1' -Action Status` — PASS read-only report: tasks=239; blocked=87; in_progress=2; planned=131; ready=19; active_leases=2; currently_ready=19.
- `& './scripts/task-loop.ps1' -Action RefreshBacklog` — PASS; both claim rows now show `in_progress`.

No implementation work on the two claimed deliverables is represented by these coordination checks; the assigned workers and later reviewers must provide that evidence.

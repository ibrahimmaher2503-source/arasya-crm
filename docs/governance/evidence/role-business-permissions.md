# Owner-directed role permissions integration — A1-CORE-018

## Authorization and scope

2026-10-19 owner directive: grant roles customer contact visibility and the ability
to assign tasks to another eligible user. Explicit owner confirmation in thread
`01a1519e-5ead-78f2-88a7-d1981d4946c8`: **نعم، راجع الحجوزات وانقل المتوقف منها ثم ادمج**.
This authorizes coordinator review/release of stopped overlapping leases and the
bounded registration/integration procedure below. It grants no production access,
OPEN/ADR approval, automatic real-user role grant or unrelated completion.

The isolated proposal and its full evidence are retained in
`.local/role-permissions-candidate/ACCEPTANCE.md`, `source-manifest.json` and
`role-permissions.patch`. Primary baseline HEAD:
`ab392aaa4c1b0b57f5e1aef0216a56d8bed732af`. Candidate verification: MariaDB 11.4.13
300 tests/3903 assertions before final selection fixes; final focused 15/155;
desktop/tablet/mobile RTL/LTR/light/dark/keyboard 3/3 plus25 SQL/audit checks.
Pint/design checks passed; baseline PHPStan5 and Help3 failures remain disclosed.

## Stopped-owner review and handoff

At loop revision528, overlapping leases were CORE017
`authorization-performance-20261019` (blocked with recorded final-integration
blocker), TASK004 `three-page-reference-20261019`, HELP001
`tutorial-help-coordinator` and DASH003 `factory-20261019-dashboard`.
App thread inventory showed only the receiving project thread active. The
three-page owner's thread `01a1511e-47d7-7d01-885e-91a709cfb0f8` is not loaded,
its latest turn interrupted, and its last owner operations preserved existing
changes for serial handoff. No worker is restarted or messaged externally.
All11 primary proposal baseline files still exactly matched their saved hashes
after the inactive-owner review. Coordinator lock/pending transaction were absent.

The coordinator releases only these four stopped leases through task-loop using
each exact recorded lease owner, explicitly on behalf of the human owner's above
delegation. Release does not claim their deliverables complete. Their preserved
files and outstanding gaps remain separate from this functional slice.

Register A1-CORE-018 once under coordinator CreateNew lock and content hash guards,
without rewriting existing entries/status. Claim its exact integration paths with
owner `role-permission-integration-20261019`; thereafter all lifecycle transitions
use task-loop. Shared auth helpers and Tasks workspace dependencies absent HEAD
must be reviewed and committed coherently or left explicitly uncommitted; never
commit calls to missing methods or discard unrelated dirty work.

## Verification and review

Independent `gpt-6-luna` / `xhigh` agents: `role_assignment_tests` authored only the
two candidate feature tests; `role_permission_review` was read-only. Review found
and fixed current-assignee selection, off-page retention, malformed flashed input
fallback, stale Help copy and HTTP/service permission consistency. Corresponding
RED/GREEN evidence is in the candidate report. Root owns integration, DB/runtime,
fixtures, lifecycle and staging; no recursive delegation.

Integration tests, checks, findings and commit hashes will be appended after
actual execution. Skills used remain root AGENTS, installed Ponytail, Laravel best
practices/security, Laravel TDD, minimal Laravel UI guidance and independent review.
No integrated acceptance or new task DONE claim is made by this initial record.

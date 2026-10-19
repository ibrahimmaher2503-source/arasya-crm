# CR-CYCLE02-DEV-001 — Employee and supervisor management

Requester and scope owner: Ibrahim, explicit owner directive "MY ARASYA —
EXECUTE CYCLE 02: EMPLOYEE & SUPERVISOR MANAGEMENT", 2026-10-17.
Status: Approved for bounded synthetic DEVELOPMENT implementation only.
References: EMP-01, EMP-02, SEC-06, DOC-01/02, SEC-01/05, NFR-06 in the
[PRD](../../product/PRD.md), [scope](../../product/SCOPE.md),
[change control](../CHANGE_CONTROL.md) and [task ledger](../../backlog/tasks.json).

Existing A1-EMP-001 through 004 deliver domain values/read projections and
diagnostic adapters, not durable employee CRUD or a completed business cycle.
Retain these tasks and their historical handoffs. Reuse EmployeeProfile,
EmployeeStatus, ReadScopedEmployeeProfiles and AttributeWorker; do not introduce
a second employee identity or authentication system. Cycle01 A1-CORE-011 is done;
its active A1-CORE-012 correction must finish before shared integration.

Schedule A1-EMP-005 for independent Employee-owned persistence/service/tests,
then A1-EMP-006 for HTTP, shared identity contracts, UI and integrated acceptance.
The latter depends on A1-CORE-012 and A1-EMP-005. Never claim overlapping scopes
while another lease exists. Existing coordinator state remains authoritative.
Registration uses its existing exclusive CreateNew lock, pending-transaction
check, hash guard, preserved task text/statuses and validated reverse DAG edges.

Deliver employee master records with optional unique account linkage, branch and
department scope, persisted reporting lines, non-login workers, attributable
assignments/evidence, private fields/documents, profile sections and significant
change audit. Offboarding coordinates Identity revocation and owner services;
unresolved required handoffs prevent completion. Never impersonate workers,
delete history, silently reassign, invent recipient acceptance, compensation,
performance results or OPEN policy approvals. Unavailable linked modules must
be identified accurately and cannot become fabricated empty-success evidence.

Privacy/data/operations: synthetic DEVELOPMENT data only; no production,
external AI/provider, SonarQube, GitHub Actions, unrelated data or process changes.
Existing OPEN/ADR and Founder-Led gates remain unchanged. Sensitive document
storage/scan and retention activation remain subject to their established gates;
isolate any blocked operation rather than bypass it or stop independent work.
Alternative: another documentation-only batch; rejected by the owner in favor of
working Laravel/MariaDB functionality with actual UI acceptance.

Workers explicitly request gpt-6-luna / xhigh with non-overlapping file ownership.
No recursive delegation. Existing worktrees were inspected; required dirty and
untracked Cycle01 runtime is absent from HEAD, so clean linked worktrees would
omit the dependency under test. Use exclusive files in this checkout until a
faithful isolated snapshot is available. Coordinator alone runs shared database,
compiled-view and browser verification, serially, after runtime ownership clears.

Acceptance: the owner's twelve-step administrator/supervisor/other-branch
scenario with real login, reload and MariaDB read-back, attributed evidence,
authorized account linkage, revocation/history/open-work handling, negative
PHPUnit security tests, desktop/mobile RTL and English LTR, light/dark and
keyboard checks, scoped Pint, PHPStan, design-system checks and independent
technical/security review. No VERIFIED label until all mandatory criteria pass.
AI tools remain restricted/disabled; record coverage in the shared ledger only
after its current lease is released. No commit/push of unrelated dirty work.

After A1-CORE-012 completed and its owner released the lease, A1-EMP-005 was
released, extended under the coordinator lock to depend on that completed task
and own the existing Identity permission catalog plus tool coverage, then
claimed again. This permits adding the five Employee permission names to the
same RBAC catalog; it does not introduce a second authentication/authorization
system. Shared routes and shell remain outside the persistence task.

After persistence closure, A1-DASH-003 independently acquired `routes/web.php`.
Do not interfere with that lease. A1-EMP-006 is therefore the independently
claimable controller/view/browser-scenario preparation slice, excluding the
router. A later routing/integration slice must depend on both A1-EMP-006 and
A1-DASH-003 and must verify actual application URLs and browsers before any
working-UI claim. Controller/view checks alone do not satisfy that acceptance.

# Employees completion — 2026-10-18

Owner directive: complete the existing Employees module and match four approved
Employees screenshot references; preserve all existing work and policy gates.
This is bounded local synthetic DEVELOPMENT work, not production activation.
Cycle02 is PARTIAL / NOT VERIFIED until the mandatory criteria below pass.

## Ownership resolution

At inspection A1-CRM-017 was in_progress, with an unexpired lease held by
crm-polish-coordinator until 2026-10-19T01:13:13.3739966Z. Its clean isolated
crm-polish-tests and crm-polish-qa worktrees advanced to 06496e0 and 0f16919 at
16:48 and 16:53 Cairo time. No completion handoff exists. This is active work,
not an abandoned/expired task. No lease release, takeover or CRM state change
was performed. Shared shell/sidebar/catalog sources were unchanged in main.

Under the existing exclusive coordinator lock, pending-intent checks, current
backlog hash and atomic writer, the ready/unclaimed A1-EMP-006 scope was
partitioned to exclude the shared shell and AI coverage ledger. Existing CRM
lease and all other task text/statuses were preserved. Employee-owned routes,
tests and this evidence were added to that task's bounded completion scope.
Native task-loop Claim then succeeded for employees-completion-coordinator.
Shared shell, sidebar and catalog remain read-only reuse. Subsequent native
Release/Claim transitions extended only this task's exclusive scope with
TaskCycle.php's backward-compatible lightweight read flag and the previously
unleased admin.css responsive correction. No CRM-owned source was changed.
Latest observed CRM017 lease is ACTIVE, owner crm-polish-coordinator, expiring
2026-10-19T04:12:34.7273250Z, recovery=none. Its worktrees continued advancing;
no forced recovery/release was warranted or performed.

## Independent ownership

All workers explicitly request gpt-6-luna / xhigh, fork_turns=none; no fallback
or recursive delegation. Workers do not run databases or browser servers.

| Worker | Isolated worktree | Exclusive files |
| --- | --- | --- |
| employees_http_tests | .tools/worktrees/employees-tests | tests/Feature/Employees/EmployeeAcceptanceTest.php |
| employees_views | .tools/worktrees/employees-ui | resources/views/employees/ |
| employees_browser | .tools/worktrees/employees-browser | tests/Browser/employee-acceptance.spec.ts, employee-fixtures.php, playwright.employees.config.ts |
| Coordinator | main | EmployeeDirectory, EmployeeController, routes/web.php, routes/employees.php, evidence and integration |

Native final transitions: Review then Complete with independent reviewers
employees_backend_review + employee_frontend_audit. A1-EMP-006 is DONE,
lease=none, recovery=none. Complete legitimately removed only its own lease;
no forced Release was used. A1-EMP-005 completed evidence was preserved.

Delegation was assessed before test authoring: these files/fixtures are
independently authored, and runtime remains sequential with the coordinator.
Detached isolated worktrees use the complete committed source at 7933464.
No existing source/worktree or secret was overwritten.

Integration/runtime mutation ownership stayed central. Reviewers were read-only;
workers authored exact isolated files and committed them for cherry-pick.
No competing database reset was performed. TaskCycle/admin.css integration and
responsive test assertions were coordinator-owned after worker handoff.

Skills actually read: repository Ponytail and Laravel best-practices (database
performance, security.blade.php, validation, routing, Blade and architecture),
installed frontend-design at the Claude official plugin cache, and installed
dispatching-parallel-agents, using-git-worktrees, test-driven-development,
systematic-debugging and requesting-code-review under C:/Users/N/.codex/skills.
The optional verification-before-completion skill was not found. Project
evidence-before-claims verification remains mandatory.

## Reuse and delivered functionality

Reused EmployeeDirectory, EmployeeController, existing Employee tables and
migration, persisted AccessControl authorization, Organization lookup contracts,
TaskCycle authorization and all shared shell/component styling. No new employee
service, schema, UI framework or fake login account was introduced.

Registered real authenticated routes: GET /employees, /employees/workers,
/employees/create, /employees/{id}, /employees/{id}/edit; POST /employees,
/employees/{id}, /employees/{id}/evidence. Existing named-route navigation now
activates Employees without changing the Sidebar source.

Directory has SQL-backed search, branch/department/supervisor/status filters,
stable sorting and 10/25/50 pagination. Selectable options are independently
searched/paged (25) with selected values retained. Counts are authorized server
counts, not screenshot metrics. Legacy list reads are capped at 50.

Create/edit persist existing versioned service writes; field errors, CSRF,
optimistic conflicts, optional existing-account linkage and private fields are
preserved. Supervisors create/edit only their scoped non-login workers, cannot
reassign authority, and never generate dummy users. Account presentation and
link/unlink operations reject cross-branch access. Hidden linkage is not cleared
by a form that cannot display it. Historical terminated records remain editable
without allowing ordinary saves to bypass offboarding.

Profile has real information cards, scoped workers, authorized work evidence
and audit tabs. Evidence/audit reads are paged; private evidence requires both
employee and task authorization. Exact task-ID lookup reuses TaskCycle.find
with optional withDetails=false to avoid loading nested task detail tables.
Overview/workers do not load history. No guessed CRM linkage is exposed because
no approved employee-to-CRM reference projection is available.

Reused layouts.shell and its ui.page-header, ui.summary-strip, ui.table,
ui.list-controls, ui.field, ui.form-section, ui.select, ui.option-browser,
ui.status-badge, ui.list-pagination, ui.empty-state, erp.entity-header,
erp.audit-history and shared tab/activity styling. Existing
CSS tokens drive all colors and spacing. No competing design system. Existing
searchable option-browser is used rather than an unbounded select component.
Unsupported screenshot buttons/metrics were not copied.

Real visual review found and fixed a small-tablet grid/off-canvas breakpoint
conflict and an LTR off-canvas transform inherited-direction bug. admin.css now
uses the existing <=850px drawer behavior, full-width content and semantic page
background; Arabic/LTR open/close and minimum usable width are browser-asserted.
These corrections also exercise existing Access/CRM tablet pages as regressions.

## Verification evidence

Dedicated Windows-native MariaDB 11.4.13 instance initialized in ignored
.local/windows-qa/employees-runtime/data, bound only to 127.0.0.1:13418.
Separate arasya_employees_phpunit and arasya_employees_browser schemas avoid
CRM runtime/schema/fixture contention. Credentials remain in ignored storage.

Baseline command: `.local/windows-qa/run-employees.ps1 vendor/bin/phpunit
tests/Feature/Employees tests/Feature/Access --do-not-cache-result`:
PASS, 81 tests / 764 assertions, 33.038s on PHP 8.4.21 / PHPUnit 12.5.39.
This is existing regression evidence before implementation, not new acceptance.

Final PHP evidence (all centrally serialized on the isolated native database):

| Check | Actual result |
| --- | --- |
| Full PHPUnit via run-employees.ps1 vendor/bin/phpunit --do-not-cache-result | PASS 457 tests / 4,594 assertions, 82.379s |
| Employees + Access + Tasks + CRM focused regression | PASS 203 tests / 2,731 assertions |
| Full PHPStan analyse --debug --no-progress --memory-limit=512M | PASS, no errors |
| Pint --test on changed PHP files | PASS |
| npm.cmd run check:design-system | PASS checker, checker self-test, JS syntax |
| scripts/test-task-loop.ps1 | PASS 38 fixture events |
| scripts/validate-docs.ps1 | PASS tasks=272; PRD_IDs=124; links=606 after screenshot index |
| Legacy Playwright employee-cycle desktop/mobile | PASS 2 tests, 18.0s |

Independent HTTP tests cover 121-row stable pagination, scoped filters/options,
CRUD refresh/versioning, supervisor worker writes, privacy in HTML and JSON,
company/branch/department IDOR, linked-user masking, invalid reassignment,
task evidence confidentiality and bounded history queries. Multiple meaningful
RED runs preceded fixes; failures were not hidden or assertions weakened.

Final Playwright evidence: the desktop, tablet and mobile primary workflows all
PASS (1.6m / 1.3m / 1.7m), with all four canonical screens captured in each.
The desktop-only CSRF/Identity scenario PASS on its corrected rerun (29.3s test,
1.1m command). Its two duplicate tablet/mobile cases are intentionally skipped
to avoid repeating identity mutations. Legacy desktop/mobile PASS separately.

Commands: `npx.cmd playwright test --config playwright.employees.config.ts
--reporter=list` and then `npx.cmd playwright test --config
playwright.employees.config.ts --project=employee-desktop --grep 'writes require
CSRF' --output=test-results/employees-security --reporter=list`.
The first invocation reported 3 passed / 1 failed / 2 skipped: security reached
the final preserved-history assertion but compared innerText with textContent.
Independent author repaired that oracle to exact innerText equality on both
sides (69e48a9), preserving assertions. The actual security rerun passed.
An earlier 90-second timeout and genuine tablet/LTR failures remain documented
in local logs, not presented as passes. Workflow budget is now 180 seconds;
normal Playwright action/navigation waits remain bounded, no fixed sleeps.

Real browser assertions cover persisted session login; no-token POST 419 with
unchanged profile; anonymous 401; immediate deactivation revocation; old session
remaining revoked after reactivation; fresh login; unchanged task IDs, profile,
evidence and audit; private HTML/JSON; forbidden assignments; create/edit/link/
unlink/read-back; server filters/sort/pages; RTL/LTR; light/dark; keyboard skip
link; responsive width; and drawer open/close. Existing Access/CRM tablet pages
are exercised as shared-style regression, not a claim of full CRM acceptance.

Historical artifact index (original capture report; files unavailable as of the A0-ARCH-903 recovery inspection):

| Viewport | Directory | Details | Add/Edit form | Supervisors/workers |
| --- | --- | --- | --- | --- |
| 1440 desktop | `../../../test-results/employees/employee-acceptance-employ-c6e42-gh-the-real-browser-session-employee-desktop/employee-directory.png` | `../../../test-results/employees/employee-acceptance-employ-c6e42-gh-the-real-browser-session-employee-desktop/employee-detail.png` | `../../../test-results/employees/employee-acceptance-employ-c6e42-gh-the-real-browser-session-employee-desktop/employee-form.png` | `../../../test-results/employees/employee-acceptance-employ-c6e42-gh-the-real-browser-session-employee-desktop/employee-workers.png` |
| 768 tablet | `../../../test-results/employees/employee-acceptance-employ-c6e42-gh-the-real-browser-session-employee-tablet/employee-directory.png` | `../../../test-results/employees/employee-acceptance-employ-c6e42-gh-the-real-browser-session-employee-tablet/employee-detail.png` | `../../../test-results/employees/employee-acceptance-employ-c6e42-gh-the-real-browser-session-employee-tablet/employee-form.png` | `../../../test-results/employees/employee-acceptance-employ-c6e42-gh-the-real-browser-session-employee-tablet/employee-workers.png` |
| 390 mobile | `../../../test-results/employees/employee-acceptance-employ-c6e42-gh-the-real-browser-session-employee-mobile/employee-directory.png` | `../../../test-results/employees/employee-acceptance-employ-c6e42-gh-the-real-browser-session-employee-mobile/employee-detail.png` | `../../../test-results/employees/employee-acceptance-employ-c6e42-gh-the-real-browser-session-employee-mobile/employee-form.png` | `../../../test-results/employees/employee-acceptance-employ-c6e42-gh-the-real-browser-session-employee-mobile/employee-workers.png` |

The approved October18 four screenshots were inspected from the owner's local
Downloads/mark paths. Comparison is human visual review, not a fabricated
pixel-diff percentage: same shared desktop forest-green sidebar, topbar/header,
tokens, compact tables and grouped cards; responsive drawer at <=850px. No
salary/attendance/download controls were copied from screenshot-only content.
PNG/log/runtime artifacts are ignored local evidence, not committed secrets or
production data; source tests/config and this index are committed.

Post-browser direct DB verification PASS: server 11.4.13-MariaDB, 95 owned
synthetic profiles, 12 newly browser-created non-login workers, 12 attributed
evidence rows and 61 employee audit rows. These counts include safe reruns and
are test-fixture evidence, not product dashboard metrics. Verification requires
updated worker names and task evidence for each of desktop/tablet/mobile,
null worker user_id and no unattributed employee audit rows.


### Historical artifact availability correction — A0-ARCH-903

The 12 paths above are retained as historical identifiers, not available image
links or current visual acceptance evidence. Recovery inspection found no originals
in repository history, registered Employee/CRM worktrees or existing local test
artifacts. The earlier login evidence documents removal by Playwright cleanup.
The original execution report is preserved; its screenshots cannot currently be
independently verified. Employees visual acceptance is NOT CURRENTLY VERIFIED.
No replacement screenshots or fresh browser/MariaDB results were produced.

The owner explicitly authorized this bounded correction and A0-ARCH-903-only
integration after the remaining checks. This does not approve Employee product
acceptance, change its historical task state, or authorize production. A future
owned Employees browser run must produce new dated evidence, not recreate or
claim the lost historical captures. See [the recovery decision](A0-ARCH-903-hardening.md).

## Reviews and commits

Independent employees_backend_review verdict: no blocking issue in the bounded
UI/service slice after fixes for account branch scope, terminated edits and
lightweight task history. Full lifecycle dependencies remain partial.
Independent employee_frontend_audit caught the tablet screenshot defect despite
passing initial interaction tests. Final canonical desktop/tablet/mobile review:
no material visual blocker; tablet compression and mobile green strip removed;
RTL actions and mobile controls reflow correctly. Tall tablet identity cards
are a nonblocking density improvement; long synthetic names remain clipped
inside the horizontal table region rather than overflowing the page.
Reviewers did not claim independent
runtime execution. All workers explicitly used gpt-6-luna / xhigh.

Logical commits include 5866713 (HTTP tests), d2dbf09 (shared-pattern views),
5569557 (browser acceptance), d78c142 (routes/bounded services), 33d5a1c
(privacy/form integration), 81caf4b (tablet width regression), and 2be9519
(independent CSRF/session browser coverage), ddceb3d (LTR drawer controls), and
69e48a9 (exact rendered-history oracle). Further regression test commits
remain in Git history; no broad staging, reset, force push or external CI.

Mixed pre-existing/concurrent coordinator state, backlog snapshot, CRM handoffs
and Laravel skill-index fixes remain unstaged and untouched by this handoff.
Only the Employee task's isolated ledger hunk and Employee handoffs/evidence
are staged; the shared runtime state remains a mixed local coordinator file,
not a falsely clean worktree. Local worktrees and test artifacts are preserved.

## Acceptance limits

Integrated employee screens are delivered; complete SEC-06 is not. Tasks has no
authoritative reassignment API; CRM has an
acknowledged transfer contract, not an offboarding-wide resolution workflow.
Employee document storage/scan contracts and Projects handoffs are unavailable.
Those dependencies cannot be replaced with raw cross-module table mutations,
fake successful panels, silent work deletion or a generic terminated status save.
Targets, evaluations, attendance, compensation and screenshot-only unsupported
fields/actions are not added. AI tool execution remains restricted/disabled;
CRM owns the shared coverage ledger, so no competing edit is made there.

Exact remaining lifecycle backlog dependencies: A0-SEC-011 (offboarding slice)
and A0-SEC-012 (access/adapter integration), plus Tasks/Projects authoritative
handoff contracts and employee document storage/scanning/retention contracts.
Their owning modules must supply those APIs before an Employee termination
or document-download UI can be accepted. Identity deactivation/session
revocation is tested separately; it is not coordinated offboarding and does
not silently drop outstanding tasks/leads/projects.

Cycle02 full acceptance remains PARTIAL: directory/forms/workers/privacy and
existing task evidence can be accepted with actual runtime proof; coordinated
SEC06 reassignment/termination and private documents cannot be VERIFIED. No
policy/ADR decision was modified, no production gate was crossed, and no
unsupported action was given a fake successful state.

| Full acceptance criterion | Status |
| --- | --- |
| Existing persistence and authenticated Employee routes | VERIFIED |
| Bounded directory/options/search/filter/sort/page | VERIFIED |
| Profile, create/edit, scoped non-login workers | VERIFIED |
| Session/RBAC/IDOR/private HTML+JSON and task evidence | VERIFIED |
| Historical three-viewport workflows, RTL/LTR/themes/keyboard execution | HISTORICALLY REPORTED PASS; not rerun in A0-ARCH-903 |
| Screenshot artifacts and current independent visual acceptance | NOT CURRENTLY VERIFIED; 12 original captures unavailable |
| Identity deactivation and immediate persisted-session revocation | VERIFIED |
| Coordinated offboarding, outstanding work reassignment, token integration | PARTIAL / missing authoritative contracts |
| Employee private document/evidence file storage and download | PARTIAL / missing approved storage contract; note evidence is supported |
| Full Cycle02 / production acceptance | PARTIAL / NOT VERIFIED; DEVELOPMENT only |

No newly ready dependent task is declared: A1-EMP-006 has no downstream blocks
and this UI handoff cannot satisfy the missing SEC06/domain APIs. Next action
belongs to the existing SEC06/Tasks/Projects/document contract owners; do not
silently reassign work or activate another ERP module from this slice.

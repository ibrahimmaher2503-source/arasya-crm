# Cycle02 — Employee UI preparation checkpoint

Task A1-EMP-006, [authorization](cycle02-authorization.md), after the completed
[persistence slice](cycle02-persistence.md). Full Cycle02: **NOT VERIFIED**.
Coordinator checkpoint: saved status **blocked**, owner cycle02-coordinator;
own lease retained, recovery=none. Do not release another owner's lease or infer
execution readiness from the dependency-only calculated=ready value.

The active A1-DASH-003 lease owns routes/web.php. This task deliberately excludes
that file. It owns the Employee controller, view directory, bounded Employee
history contract, shared shell integration, independent controller/view tests,
browser scenario/fixture and this evidence. No temporary test-only route or fake
HTTP/browser acceptance may substitute for application routing. Later integration
requires the actual router owner's completion and release, then executed URLs.

Delegation assessed before test code. All workers explicitly use gpt-6-luna /
xhigh, fork_turns=none, accepted by dispatch. No recursive delegation or runtime
commands by workers; coordinator alone owns database/browser/lifecycle operations.

| Worker | Exact exclusive ownership |
|---|---|
| employee_ui_tests | tests/Feature/Employees/EmployeeHttpTest.php |
| employee_ui_views | resources/views/employees/ only |
| employee_browser_tests | tests/Browser/employee-cycle.spec.ts and employee-fixtures.php |
| Coordinator | app/Http/Controllers/EmployeeController.php; EmployeeDirectory history; shell; integration/lifecycle/evidence |

Same installed Ponytail, Laravel best-practices/TDD and frontend-design skills
and shared component catalog apply. Existing linked worktrees omit required
dirty/untracked runtime; exclusive current-checkout file ownership is retained.
Reuse existing layouts.shell and semantic component/token APIs. No new palette,
framework, business policy, credentials in evidence or fabricated linked results.

## Actual candidate and verification

The three UI workers failed before delivering files: employee_ui_tests and
employee_browser_tests returned usage-limit errors; employee_ui_views returned
401 missing authentication. No replacement subagent/model was used or claimed.
The coordinator reclaimed those exact files, authored the candidate and local
checks, and retains **independent Luna UI review as NOT RUN / REQUIRED**.

Actual files: app/Http/Controllers/EmployeeController.php;
resources/views/employees/{index,form,show}.blade.php;
tests/Feature/Employees/EmployeeHttpTest.php; tests/Browser/employee-cycle.spec.ts
and employee-fixtures.php; EmployeeDirectory scoped history and bulk label lookup.
The shell has permission-filtered Employee links that remain hidden until the
actual named Employee route exists. No dead application navigation was activated.

Reuse: layouts.shell, semantic CSS tokens, x-ui.form-section/field/input/select/
date-input/textarea/form-actions/button/table/detail-panel/description-list/
status-badge/alert, x-erp.entity-header and audit-history. No component extension,
new stylesheet, palette or frontend dependency. Text is actually Arabic/English;
branch/department/supervisor names are resolved in bounded bulk lookups. Private
fields remain omitted without employee.private; the history projection excludes
encrypted/raw audit metadata. Forms use CSRF and preserve locale/version.

First RED: EmployeeHttpTest failed the missing controller assertion, one test /
one assertion. The direct-render test initially lacked the normal middleware
error bag; supplying that test context fixed the harness without changing the
shared shell contract. MariaDB then passed one controller/rendered-view test /
18 assertions (5.634s), and later one / 19 assertions (4.997s) after readable
organization labels. These exercise real saved records, bilingual views,
controller create/edit, a null login link, private-note masking, write-action
hiding and rejected missing identity. **They do not prove actual HTTP routing.**

Latest fresh checks after all candidate edits:

- SQLite :memory:, private compiled-view directory: `php vendor/bin/phpunit
  tests/Feature/Employees --do-not-cache-result`, exit 0, **28 tests / 224
  assertions**, 1.852s. Local source evidence only; no MariaDB substitution claim.
- PHPStan scoped to EmployeeController and Employees, --debug --no-progress
  --memory-limit=512M: exit 0, no errors.
- Pint --test on controller, service, new rendered-view test and browser fixture:
  exit 0. Browser PHP fixture syntax: exit 0.
- `npm.cmd run check:design-system`: exit 0, checker/self-test/JavaScript syntax.
- Playwright `test employee-cycle.spec.ts --list`: exit 0, **two discovered**
  desktop/mobile tests, **zero executed**. Discovery is not browser acceptance.

## Real runtime blockers and remaining acceptance

The native MariaDB helper stopped; a focused rerun failed with connection-refused
2002 and zero assertions. The coordinator restarted the existing loopback helper
using its existing private my.ini, with no initialization/reset. A later combined
Employees+Access run reached 67 tests / 304 assertions but failed with 21 errors
and three failures when access tables disappeared during execution. Dashboard
regression logs were also updated in that shared runtime window. No currently
green combined MariaDB result is claimed after that interference. Creating a
separate database was already refused by the QA account (1044); no privileged
credentials were guessed. A coordinated exclusive runtime slot is required.

A1-DASH-003 still owns routes/web.php. No router edit, temporary test route,
standalone mock application or unsafe lease release was used to bypass it.
Documents, targets/evaluations and full SEC-06 remain explicitly unintegrated;
the profile tells the user that these are pending. The offboarding operation is
not exposed as a status write and no revocation/reassignment acceptance is claimed.
Full desktop/mobile/RTL/LTR/light/dark/keyboard browser execution, private document
security, immutable deployment audit, published outbox and production readiness
remain unverified. No new commit, push, Actions, SonarQube or provider activation.

## Exact resume checkpoint

1. Re-read only current task/lease state and this checkpoint; retain A1-EMP-005
   historical completion and all unrelated changes. Current owner: cycle02-coordinator.
2. Restore explicit gpt-6-luna/xhigh worker availability and independently review
   the controller/views/test/fixture candidate; do not count coordinator tests as
   that independent review. Apply findings and rerun the relevant checks.
   Preserve the blocked lease until its owner explicitly resumes/releases it;
   expiration alone is not permission to steal it. Use task-loop recovery,
   Release/Reassess/Claim according to the current saved state, never direct JSON
   status edits. The current lease expires 2026-10-17T10:27:49Z.
3. Arrange an exclusive MariaDB QA slot; rerun Employees + Access safely. Do not
   reset the browser database or run two PHPUnit schema resets concurrently.
4. After A1-DASH-003 completes/releases, register/claim the routing integration
   through the existing guarded coordinator/CR workflow. Intended routes:
   GET /employees, /employees/workers, /employees/create, /employees/{employee},
   /employees/{employee}/edit; POST /employees, /employees/{employee},
   /employees/{employee}/evidence. Route create/workers before the dynamic ID.
5. On the isolated browser DB apply only the new forward migration, seed the
   owned employee fixture, execute the prepared real-login desktop/mobile spec,
   verify MariaDB rows/audit and clean up only the owned synthetic company. Keep
   scenario assertions; extend account-link, private documents and SEC-06 proof
   before claiming the owner's complete twelve-step acceptance.
6. Finish the missing owner-module integrations and mandatory acceptance before
   any Cycle02 VERIFIED or move to a different business cycle. None is newly
   eligible from this partial UI checkpoint.

Repository HEAD at checkpoint: `908616916928c8a528fd02cf0d40014746f0776a`, changed
by concurrent work; this session created no commit and does not claim that SHA
as its own delivery. Preserve its dirty/untracked files and other owners' work.

The official Superpowers verification-before-completion skill became available
later in the session and was read at
`C:/Users/N/.codex/plugins/cache/openai-curated-remote/superpowers/6.4.2/skills/verification-before-completion/SKILL.md`.
Its evidence-before-claims rule was applied to the final checks. Earlier notes
that it was not discoverable describe initial discovery, not this later state.

`task-loop RefreshBacklog` was attempted after adding this task's snapshot row;
it refused because the existing index has no row for A0-VINT-001. No foreign task
state was changed to work around that unrelated snapshot defect. tasks.json
remains authoritative; the earlier six installed-skill link errors also remain.

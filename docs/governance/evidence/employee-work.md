# Employee work follow-up page evidence

## Scope and ownership

Task: A1-EMP-007, claimed by `employee-work-coordinator`. The coordinator alone owns `docs/backlog/tasks.json`, `docs/governance/loop/state.json`, and task lifecycle transitions. This worker owns only these paths:

- `app/Http/Controllers/EmployeeWorkController.php`
- `app/Modules/Employees/Application/EMP01/EmployeeWorkDirectory.php`
- `resources/views/employees/work.blade.php`
- `resources/css/employee-work.css`
- `routes/employees.php`
- `tests/Feature/Employees/EmployeeWorkTest.php`
- `tests/Browser/employee-work.spec.ts`
- `tests/Browser/employee-work-fixtures.php`
- `playwright.employee-work.config.ts`
- `docs/governance/evidence/employee-work.md`

All other working-tree changes remain untouched. No shell, sidebar, catalog, TaskCycle, EmployeeController/EmployeeDirectory, CRM, dashboard, package, migration, or environment edits are in scope.

## Discovery and constraints

- `TaskCycle::list` is the authority: readable tasks require `tasks.read` and are restricted by the shared scoped guard to the actor's entity, department, and owner/assignee identity. The HTML read model will keep those predicates in SQL, use bounded database pagination/aggregates, and re-check the shared guard for materialized task rows. It will not call `TaskCycle::list` or broaden task visibility.
- Employee identity is linked by `employee_profiles.user_id = ActorAccess::actorId`. Selection may only retain that current linked profile; requesting another profile must not reveal it or its tasks. Profile details are included only through the existing `EmployeeDirectory` read contract when `employees.read` is granted.
- TaskCycle has only title, priority, due date, status, owner/assignee, and update time; it has no project/client/progress/calendar/manager attribution. The page will not imply those unsupported fields. Existing task evidence and employee work-evidence notes may be shown only for tasks the existing scope authorizes; no quick-note write action is added. Missing profile, evidence, or note data receives an explicit unavailable/empty state.
- The shell and shared components/catalog are read-only. The shell supplies the one page header; `EmployeeWorkDirectory` supplies its title, subtitle, icon, and breadcrumbs. The page uses the shared `ui.list-controls` and `ui.summary-strip` APIs; page styles are loaded from its own CSS resource.
- Impeccable context loader reported no PRODUCT.md or DESIGN.md. The product register reference and `docs/design-system/README.md` are the applicable design sources. `GLOSSARY.md` is absent.

## Test design (recorded before feature tests)

Public seam: authenticated HTML `GET /employees/work`, with persisted AccessControl identity and the existing TaskCycle scoped permission guard.

- Authentication and permission: anonymous requests are rejected; a session without `tasks.read` is forbidden; the route is registered before `/employees/{employee}` and leaves existing employee JSON routes unchanged.
- Scope/security: only tasks matching actor entity, department, and owner/assignee are in rows, aggregates, today's due list, evidence, or notes. Include cross-owner, cross-department, and cross-entity sentinels. A different employee profile ID is indistinguishable from an unavailable record; no coworker profile/task data is returned.
- Employee mapping: a profile appears only when the current user is linked through `employee_profiles.user_id` and the existing employee read contract allows it. Without that grant, show unavailable employee details rather than querying a broad employee directory.
- Filters/counts: verify title/current-profile search, status, due-date range, branch/department constrained to the actor's own identity, four real status counts over the full authorized task scope, SQL pagination at allowed page sizes, stable tie ordering, and query-preserving pagination. The supervisor control is explicitly unavailable because task rows carry no supervisor attribution; it cannot broaden or falsely filter tasks.
- Side panels: verify due-today tasks, actual task file metadata, and actual employee work-evidence notes only for authorized tasks; verify no fabricated project/client/progress/calendar/notes and no note-write form/action.
- Browser: synthetic rows only; assert Arabic RTL and English LTR, desktop and mobile no-overflow, keyboard focus, light/dark theme behavior, and the page's explicit unavailable states. Existing task links remain the only task actions.

Delegation assessment: no recursive worker delegation is available/authorized. Independent browser/PHP suites share the local view cache/runtime and DB ownership, so suites must run serially. The implementation worker writes the feature tests and code serially; the coordinator performs the independent review. No parallel test or review worker was started.

## Runtime plan

- PHPUnit uses `phpunit.xml`'s dedicated in-memory SQLite database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`); `pdo_sqlite` is installed. This does not reset a shared database.
- Browser acceptance now uses a per-run SQLite database under `storage/framework/testing/employee-work/<run-id>`, a public synthetic `APP_KEY`, file sessions and compiled views isolated to that run directory, and loopback HTTP on port 8787. The owned Playwright setup seeds/migrates and cleans up automatically; it never resets a shared database and passes no database username/password. `reuseExistingServer` is disabled.
- An earlier attempted browser run used the existing local QA environment loader and did not print credential values; that path has been removed. The final browser config uses no `.env` file or private credentials and loads Laravel from a run-specific disabled environment-file path.

## Skills and reference files used

- `ponytail` skill.
- `impeccable` skill and `C:\Users\N\.agents\skills\impeccable\reference\product.md`.
- `laravel-best-practices` skill and rules: `db-performance.md`, `advanced-queries.md`, `security.blade.php`, `routing.md`, `blade-views.md`, `architecture.md`, `style.md`.
- `laravel-tdd`, `tdd`, `systematic-debugging`, and `laravel-verification` skills.
- `verification-before-completion` was requested from the installed catalog but is unavailable; use the available verification guidance and report the unavailable skill.
- Project references read: `AGENTS.md`, `docs/agents/AGENT_EXECUTION_GUIDE.md`, `docs/agents/LOOP_OPERATIONS.md`, `docs/agents/SPECKIT_INTEGRATION.md`, `docs/product/SCOPE.md`, `docs/governance/OPEN_DECISIONS.md`, and `docs/design-system/README.md`.

## RED / GREEN / verification log

- Initial RED attempt: `php vendor/bin/phpunit tests/Feature/Employees/EmployeeWorkTest.php` exited 1 with 4 fixture setup errors before assertions. Root cause: branch and department synthetic codes used only the first four ULID characters, colliding with their unique-per-company constraints. Fixture correction: use each full ULID in the generated codes. This was a test setup failure, not feature RED evidence.
- Valid RED: `php vendor/bin/phpunit tests/Feature/Employees/EmployeeWorkTest.php` exited 1 on PHP 8.4.21 / PHPUnit 12.5.39: 4 tests, 5 assertions, 4 failures. The anonymous JSON request correctly returned 401; the missing `/employees/work` route returned 404 for each expected page/permission path. The route/controller/read model are not implemented yet, so this is the expected missing-behavior RED.

## Coordinator integration verification

The coordinator independently read the controller, application read model, route, Blade, CSS, PHPUnit tests, browser configuration/spec/fixture and desktop/mobile screenshots after the worker attempts timed out. No worker completion report was available. Changes from concurrent CRM, Tasks, Help and employee-profile work are not attributed to this implementation.

Exact commands executed by the coordinator against the delivered state:

- `php vendor/bin/phpunit tests/Feature/Employees/EmployeeWorkTest.php`: PASS, 4 tests / 80 assertions, PHP 8.4.21 / PHPUnit 12.5.39.
- `npm.cmd run check:design-system`: PASS, checker, checker self-test and JavaScript syntax.
- `php vendor/bin/pint --test app/Http/Controllers/EmployeeWorkController.php app/Modules/Employees/Application/EMP01/EmployeeWorkDirectory.php tests/Feature/Employees/EmployeeWorkTest.php`: PASS, 3 files.
- `npx.cmd playwright test --config=playwright.employee-work.config.ts`: PASS, 2 tests in 31.6 seconds. Exercises Arabic RTL, English LTR, desktop/tablet/mobile width, keyboard focus, theme toggle, query controls, private evidence exclusion and peer-profile rejection. The owned fixture and session were removed. Screenshots are under `.local/windows-qa/employee-work-results/`.

Earlier checks failed on missing browser config, formatting, missing synthetic APP_KEY, cache-path handling and browser session handoff. These are not passing evidence; the exact commands were rerun successfully after correction. The coordinator updated this handoff directly as a small verification-record change; all feature code was delegated to the configured GPT-6 luna xhigh lane.

Review findings resolved: duplicate shell header removed, shared list-controls enabled, RTL side panel placed on the right, and browser runtime changed to isolated per-run SQLite/session/view paths. Existing task/employee authorization is retained. No shared component API was extended; catalog, shell and sidebar were not edited for this task. No commits or publication actions were performed.

## Limits and handoff

This page is a local DEVELOPMENT presentation over existing task and employee contracts. It is reachable at `/employees/work`; sidebar integration is deferred because the shared navigation was owned by other active work. It follows the reference composition, not fabricated screenshot values: no workload/progress percentages, client/project attribution, calendar meetings, document requirements or quick-note write capability are invented. Employee selection is intentionally restricted to the current linked identity under the existing task authorization contract.

Local SQLite/browser acceptance does not establish production or MariaDB acceptance. Full-project PHPUnit/PHPStan and documentation governance checks are separate from the focused checks above. Concurrent large ledger/state changes were not witnessed as implementation changes. One older interrupted browser run directory was observed and left intact rather than deleting unverified state. Canonical lifecycle is `review`, not `done`. Independent read-only Luna xhigh review was dispatched but the provider refused execution with a usage-limit error (~231 minutes); it produced no review and no changes. No fallback reviewer was substituted. `scripts/test-task-loop.ps1` passed; `scripts/validate-docs.ps1` reported 7 unrelated broken screenshot/report links in Tasks evidence. The coordinator left those unrelated files untouched. Completion awaits the independent-review and documentation gates.

Continuation verification: `php vendor/bin/phpunit tests/Feature/Employees` ran 55 tests / 590 assertions with 1 failure in the concurrently added `EmployeeProfilePresentationTest::test_terminated_employee_status_remains_read_only_in_the_edit_form` at line 168 (expected `Terminated` in the unrelated edit form). This does not establish a passing full Employees suite; the follow-up page's focused tests previously passed. The unrelated profile/form files were not changed. `powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/validate-docs.ps1` was rerun and still failed on the same 7 Tasks evidence links. The combined verification wrapper exited 1. No repeat reviewer dispatch was made while the provider's reported cooldown remained in effect. Delegation records also flag whole-tree review/check staleness due concurrent work; the focused commands above were observed passing, not a claim that all concurrent repository changes are verified.

## Independent Luna P1 review remediation — A1-EMP-007

- Finding: the coordinator supplied a confirmed independent Luna P1 review: `EmployeeWorkDirectory::authorizedTasks` lacked the canonical nonblank identifier predicates. Whitespace-only task IDs, owners or non-null assignees could enter aggregates and side panels, then fail the shared materialized-row guard and turn the whole page into 403. This records the supplied review finding, not a new review performed by this worker; the earlier unavailable-review attempt above remains historical evidence.
- Delegation assessment, recorded before test code: one surgical test/service pair uses the same runtime and fixture ownership. Run serially with the configured in-memory SQLite fixture, without recursive delegation; the coordinator owns separate independent review and integration. No shared database reset or lifecycle mutation was performed.
- Public seam: authenticated `GET /employees/work`. The regression seeds mixed ASCII whitespace including embedded NUL for each malformed `id`, `owner_id` and `assignee_id`, plus evidence sentinels. It asserts page 200, all four summary counts, paginator total/page size/last page and an empty second page, exact table/today/evidence task membership, malformed title/evidence exclusion, and retention of valid owned, assigned and null-assignee tasks.
- Minimal implementation: align only the employee SQL scope with the existing `TaskCycle::scopedTaskQuery` normalization (`HEX` plus `REPLACE` for embedded NUL). Company, department, owner/assignee predicates and materialized-row guards remain unchanged; null assignee remains allowed. No supervisor scope broadening or shared abstraction was added. Task service/tests were read-only references.
- RED: `php vendor/bin/phpunit tests/Feature/Employees/EmployeeWorkTest.php` — exit 1; PHP 8.4.21 / PHPUnit 12.5.39; 5 tests / 81 assertions / 1 failure. The new regression expected 200 but received 403; the four existing tests passed.
- GREEN: `php vendor/bin/phpunit tests/Feature/Employees/EmployeeWorkTest.php` — exit 0; 5 tests / 103 assertions, all passed.
- Formatting: `php vendor/bin/pint --test app/Modules/Employees/Application/EMP01/EmployeeWorkDirectory.php tests/Feature/Employees/EmployeeWorkTest.php` — exit 0; PASS, 2 files. No formatter rewrite was needed.
- Exact edit ownership: [EmployeeWorkDirectory.php](../../../app/Modules/Employees/Application/EMP01/EmployeeWorkDirectory.php), [EmployeeWorkTest.php](../../../tests/Feature/Employees/EmployeeWorkTest.php), and this evidence file only. These existing untracked feature files were the authorized baseline, not newly created by this remediation. All unrelated dirty work, including concurrent Tasks, CRM, Help, employee-profile, shared navigation/configuration and governance state, was left untouched.
- Skills actually used: `ponytail`, `laravel-best-practices` (security and advanced queries), `laravel-tdd`, `tdd`, `systematic-debugging`, `laravel-verification`. The separately named official Superpowers verification skill is not exposed in this worker's catalog; no substitute installation or recursive worker was started.
- Limits at worker handoff: focused SQLite acceptance only; browser, full Employees/project PHPUnit, PHPStan, MariaDB/production, design-system and documentation governance checks were not rerun by the implementation worker. Coordinator verification below supersedes the pending focused integration checks, not historical broader failures.

## Resumed final independent review and integration

- Independent GPT-6 luna / xhigh initial read-only review (`attempt-659cf9c1-a189-45ca-aae0-64bb6d7fd60d`) found the P1 identifier-scope mismatch above. Implementation remediation was delegated to the configured GPT-6.1 Sol lane (`attempt-d35b539e-6eb1-47ee-8b45-fa45623b7257`), owning only the read model, feature test and this evidence file. The coordinator independently read those changes and reran both exact success commands: PHPUnit 5/103 and Pint 2 files passed.
- Independent GPT-6 luna / xhigh follow-up (`attempt-04044dce-cc6d-4d1b-a087-c0fee6c31c59`) confirmed P1 resolved, with no blocking findings. It compared the canonical task scope and verified that counts, filtered pagination, today tasks and evidence derive from the corrected scope. Both reviews were read-only; runtime checks remained coordinator-owned. Concurrent unrelated observed changes are not attributed to either reviewer.
- Final coordinator commands, run serially after the follow-up: `php vendor/bin/phpunit tests/Feature/Employees/EmployeeWorkTest.php` PASS (5 tests / 103 assertions); `npm.cmd run check:design-system` PASS; `php vendor/bin/pint --test app/Http/Controllers/EmployeeWorkController.php app/Modules/Employees/Application/EMP01/EmployeeWorkDirectory.php tests/Feature/Employees/EmployeeWorkTest.php` PASS (3 files); `npx.cmd playwright test --config=playwright.employee-work.config.ts` PASS (2 tests, 9.7 seconds); `powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/validate-docs.ps1` PASS (287 tasks, 124 PRD IDs, 1059 local links). The combined wrapper exited 0. Prior Tasks broken-link failures are historical, not silently waived; the same validator now passes without this task editing Tasks evidence.
- Coordinator directly updated this verification artifact and canonical lifecycle; all implementation changes were delegated. No commits, branches, push or deployment. Sidebar remains deferred under another active lease as agreed; access is `/employees/work`. Full-project PHPStan, full-project PHPUnit, MariaDB and production readiness are not established by these focused results. The earlier full Employees-suite failure remains a separately reported historical result until that exact suite is rerun.
- Whole-tree delegation records flag review/check staleness when unrelated concurrent work changes; they are not a claim of frozen-repository verification. Final task-specific integration commands above were executed and observed passing on current disk state.
- Canonical closure: task-loop `Complete` with independent reviewer `independent-luna-xhigh-attempt-04044dce` committed successfully. A subsequent exact `Status -TaskId A1-EMP-007` showed `saved=done`, `lease=none`, `recovery=none`. An attempted redundant `Review` was refused because the task was already in `review`; no state was changed by that refusal, and completion proceeded from the confirmed existing review state.
- `RefreshBacklog` was attempted after closure and failed because the generated backlog has no unique row for unrelated task `A1-TASK-004` (`found 0`). No manual snapshot repair or unrelated task edits were made. This leaves the generated snapshot refresh incomplete; `tasks.json` remains the canonical source and records this task done.

## Closure 04 correction — 2026-10-19

The owner authorized functional integration of existing workflows. A1-EMP-007 was
reopened, reassessed and claimed through task-loop by `closure04-coordinator`.
The prior completion statements above are historical evidence, not proof of the
current repaired candidate being integrated into Git.

Confirmed defect: EmployeeWorkDirectory authorized the actor and read task counts,
rows, evidence and employee history outside the company lock used for permission
mutations. The repair reuses the existing working-tree
`AccessControl::withReadLock` for the entire existing read. Scope predicates, field
masking and supported controls remain unchanged. Accurate array types and a private
SQL helper restricted to the three existing literal identifier columns resolve the
owned PHPStan findings without suppression or query changes.

Independent test ownership: `closure04_lock_regression` owned only
`tests/Feature/Employees/EmployeeWorkTest.php`. It uses DatabaseMigrations and a
separate native connection to probe the company lock during the count query. The
contender must receive MariaDB 1205 while the response is being read, then acquire
the lock after the response. The coordinator alone ran database/server mutations.

Observed verification:

- RED: 1 test / 3 assertions, expected failure because the competing writer was not blocked.
- GREEN: 1 test / 4 assertions passed after wrapping the complete read.
- Native integration: isolated MariaDB 11.4.13, PHP 8.4.21, PHPUnit 12.5.39; Access, Employees, all CRM and Tasks feature directories: **290 tests / 3803 assertions PASS**, no skips, 156.003 seconds.
- Final exact owned suite after the type-only refactor: **6 tests / 107 assertions PASS**, native MariaDB, 41.563 seconds.
- Owned PHPStan: PASS. Full tree: FAIL with 6 findings in leased HelpCatalog (1) and TaskCycle (5); not suppressed or changed.
- Owned Pint: PASS for controller, read model, route, feature test and browser fixture. Full tree: FAIL on unrelated bootstrap/providers.php, config/debugbar.php and Sales/QuotationDraftTest.php.
- Design-system checker, checker self-test and JS syntax: PASS. No component API or design-system change.
- validate-docs: PASS, 288 tasks / 124 PRD IDs / 1061 links, including JSON, DAG, reverse edges and coverage.
- task-loop regression: PASS, 38 events covering locks/overlap, decision evidence, crash recovery, unknown edits and UTF-8 preservation.

Final real HTTP verification used the same application source on a fresh owned
MariaDB process/schema and isolated storage/session/view paths: **24/24 desktop,
tablet and mobile scenarios PASS** (3.6 minutes), followed by **70/70 independent
same-database persistence/audit checks PASS**. Evidence is
`.local/closure04-http-a3142b7f26704bed80910e8ad0a4043b/`; the final log is
`.local/closure04/http-isolated-final.log`. Employee work covers current-profile
scoping, search/status controls and reload, stable authorized summary counts,
foreign-profile rejection, one heading, Arabic RTL, English LTR, dark theme and
mobile document width. Real screenshots were manually inspected; no pixel parity
or performance benchmark is asserted.

A stronger, correctly shaped status/version-only request was then checked in
three separate browser sizes: **3/3 PASS**, plus **6/6** independent employee
persistence/audit checks. Rejected termination specifically reports the existing
offboarding requirement and produces no version or audit changes. Evidence:
`.local/closure04-employee-http-14a2b6e16962449e811e65af9b62acaa/` and
`.local/closure04/employee-http-final.log`. These are focused reruns, not additional
distinct business workflows.

Earlier harness failures are retained, not counted as passing acceptance. One
24/24 browser run failed its independent file check because the HTTP runtime had
not applied the temporary storage environment path. A per-run router now applies
that path before the unchanged Laravel entry point; CLI setup/verification use
the same path. Ten known synthetic attachment directories were removed only after
exact task-ID, resolved-parent, sole-file and payload ownership checks. Other
runtime cache/session files were preserved. The final fully isolated run passed
the private-file check and cleaned its own marked temporary runtime.

`closure04_security` independently reviewed the repair, native RED/GREEN evidence,
malformed whitespace/NUL regression and the final literal-column SQL-helper
extraction. No material source blocker was found. It did not run the database.
Read-only employee and CRM/Tasks workers independently mapped remaining integration
gaps. Every worker explicitly requested `gpt-6-luna` / `xhigh`; no recursive
delegation or silent model fallback. The scratch browser-test author lane was
unavailable due account usage limits, so the coordinator authored that harness.

Application-source changes are limited to EmployeeWorkDirectory; the independent test
changes are limited to EmployeeWorkTest. Existing controller, route, view, CSS and
browser fixture/config were read and exercised, not redesigned. Other source
hashes in the recorded baseline remained unchanged. Exact paths and commands,
screenshots, scenario outcomes and independent persistence/audit evidence are
retained in `.local/CLOSURE04-ACCEPTANCE.md` and `.local/closure04/`.

Skills actually read: `.agents/skills/ponytail/SKILL.md`,
`.agents/skills/laravel-best-practices/SKILL.md` (security and DB performance rules),
`.agents/skills/laravel-tdd/SKILL.md`,
`.agents/skills/systematic-debugging/SKILL.md`,
`.agents/skills/laravel-verification/SKILL.md`, and the installed Superpowers
dispatch/TDD/review instructions during the earlier execution. Frontend checks use
`C:/Users/N/.codex/skills/frontend-design/SKILL.md`,
`C:/Users/N/.codex/skills/requesting-code-review/SKILL.md` and the existing component
catalog. The earlier official Superpowers cache path later became unavailable;
no duplicate installation or invented path was used.

**Git integration remains BLOCKED.** HEAD AccessControl has no `withReadLock`;
that helper belongs to A1-CORE-017's active
`authorization-performance-20261019` lease. The task is blocked with its lease
retained. Committing EmployeeWorkDirectory alone would introduce an unresolved
dependency; a service/test-only commit also omits the existing new work
controller/route/view/CSS. The independent review explicitly flagged this.
Do not stage another owner's AccessControl changes or mark this candidate DONE.
Only this owned evidence file is safe to commit at this checkpoint; repaired
source and regression remain uncommitted for coherent integration.

Next action: A1-CORE-017's owner integrates the verified shared helper under its
coordinator workflow, and the Help/Tasks owners resolve their remaining static
findings. Then verify the complete exclusive A1-EMP-007 slice against committed
dependencies, commit it, rerun acceptance and perform independent completion.
No newly-ready task or policy approval is asserted. No branch change, push,
external CI, deployment or destructive Git operation was performed.

Canonical Closure04 handoff: task-loop Review then Block completed successfully.
Status is `saved=blocked`, lease `closure04-coordinator` retained, `recovery=none`.
Handoffs are
`docs/governance/loop/handoffs/A1-EMP-007-f9f4b55dc2a34cc482e0953a3863c153.md` and
`docs/governance/loop/handoffs/A1-EMP-007-6ea49755b390460e9edfd217e0ed90a3.md`.
The DAG's calculated readiness does not remove the real uncommitted helper
dependency. No Complete transition was made. RefreshBacklog was attempted and
again refused the existing missing A1-TASK-004 row (`found 0`); the snapshot was not
manually repaired and JSON remains canonical. This evidence-only checkpoint is
safe to commit; application source integration remains blocked as stated above.

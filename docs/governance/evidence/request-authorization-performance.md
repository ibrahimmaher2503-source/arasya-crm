# Request-scoped authorization performance — 2026-10-19

Task: A1-CORE-017, owner `authorization-performance-20261019`. Related CRM changes use the separately owned A1-CRM-020 scope. Development evidence only; no production deployment or policy approval is implied.

## Implementation and security boundary

`AccessControl` reuses verified actors and effective permissions only on the exact safe-method Request object, outside transactions, with matching session ID, user ID and session version. A weak reference rejects cloned Request attributes. No static, singleton or cross-request permission cache was introduced. A subsequent request reads authorization again, including active organization state, department roles, inherited roles and explicit denies.

Sensitive mutations clear snapshots, reacquire the existing company row lock and reauthenticate. CRM reads retain the existing company serialization lock and refresh authorization inside it. `withReadLock` removes redundant permission resolution while holding that lock through the callback. `RuntimeIdentity::fromActor` maps an already verified actor; it grants no permissions. Other-user permission lookups remain fresh. `CrmPageController` uses the permissions of its verified actor.

Independent Luna review rejected an earlier prototype that removed the CRM read lock. That prototype was repaired before acceptance; its lower query counts are not the final results below.

## Paired benchmark

Original Arabic audit and artifacts: `.local/performance-audit/PERFORMANCE_AUDIT_2026-10-19_AR.md`, `.local/performance-audit/runs-2026-10-19/`. The original frozen application is `C:/Users/N/AppData/Local/Temp/opencode/audit-freeze`. Its SQLite demo database was copied into an isolated synthetic database; the original database and server were not modified.

The historical report measured lead details at 143/128 total/authorization queries. Current paired baseline is 197/178 because current CRM code differs from the historical frozen code. These must not be presented as the same baseline. Employees and CRM overview reproduced the historical counts exactly.

Paired runs use the same copied fixture, actor, seven routes, PHP/SQLite engine, disabled Telescope/Debugbar, viewport 1440×1000, one warm-up plus five measured navigations per route. SQL execution time is the median sum of DB listener times. TTFB uses browser Navigation Timing; p95 with five samples is the maximum sample, not a production latency estimate. Authorization queries use the same local instrumentation classification in both runs.

| Route | SQL before → after | Authorization before → after | SQL reduction | SQL ms before → after | TTFB p50 ms before → after | TTFB p95 ms before → after |
| --- | --- | --- | --- | --- | --- | --- |
| `/crm/leads/312WHQ1Y15SK3VWN2T7QKZVWA5` | 197 → 69 | 178 → 50 | 65.0% | 22.96 → 6.15 | 120.3 → 52.7 | 314.7 → 61.1 |
| `/employees` | 114 → 26 | 96 → 8 | 77.2% | 16.39 → 7.08 | 72.9 → 63.5 | 222.6 → 83.4 |
| `/crm` | 100 → 36 | 88 → 24 | 64.0% | 10.09 → 5.55 | 63.5 → 52.6 | 121.9 → 72.0 |
| `/crm/leads` | 93 → 37 | 80 → 24 | 60.2% | 11.47 → 5.66 | 75.9 → 70.8 | 161.2 → 83.6 |
| `/tasks` | 25 → 9 | 24 → 8 | 64.0% | 4.01 → 3.64 | 56.6 → 53.3 | 71.3 → 62.7 |
| `/access/users` | 46 → 22 | 35 → 11 | 52.2% | 9.87 → 6.45 | 77.4 → 67.5 | 169.8 → 83.4 |
| `/access/audit` | 39 → 31 | 34 → 26 | 20.5% | 6.18 → 7.22 | 58.6 → 74.4 | 104.8 → 181.6 |

Audit latency and SQL execution time regressed in this small local sample despite fewer queries. No blanket latency improvement is claimed. Audit/write freshness remains intact.

Raw five-sample results: `.local/factory-20261019/performance-before.json` and `performance-after-final.json`. Harness: `benchmark.cjs`, `perf-router.php`, `perf-run.ps1`; local server 8791. The instrumentation is deliberately outside production code and is not a public metrics endpoint.

## Verification and independent work

New regression files: `tests/Feature/Access/RequestAuthorizationPerformanceTest.php` and `RequestAuthorizationIsolationTest.php`. Tests cover bounded repeated reads; revocation between requests and before mutations; POST freshness; transaction bypass; Request cloning; session identity/version changes; inactive company; cross-company and department isolation; inherited role and explicit deny; and other-user lookups. A two-connection MariaDB test proves the company lock is held through the read callback. CRM also asserts the retained `FOR UPDATE` read path.

Initial RED query regression observed 42 SQL reads, GREEN 7 on its role-free fixture. This is a focused service test, separate from full-route benchmark figures.

The first complete retained-lock suite passed on MariaDB 11.4.13: 538 tests, 37,454 assertions, one SQLite-only trigger skip; log `performance-full-serialized.log`. Two subsequent characterization tests were added before CRM service extraction. Final complete rerun passed: **540 tests, 37,466 assertions, one SQLite-only trigger skip**, 3:18.707; `final-full-suite.log`.

Final performance browser run: four Chromium tests passed, desktop and Pixel 7, 36.7 seconds (`performance-browser-final-corrected.log`). Routes CRM, leads, Employees, Tasks, users and audit render within measured authorization-query ceilings; Arabic lead details preserve masking and RTL; English routes preserve LTR; a new anonymous context receives 401 after an authenticated context is primed. An earlier command used the wrong environment variable and failed against unavailable port 8765; it is retained as a harness failure, not a product result.

Full configured PHPStan passed (`phpstan-full-final.log`), as did scoped analysis of modified Identity services, CRM controller/runtime services and both new authorization test files. Additional explicit analysis of existing CRM presentation tests found eight existing type errors (generic TestResponse types, iterable fixture annotations and Storage fake assertExists); these were not suppressed. Pint passes on modified PHP paths after formatting moved CRM services. `npm.cmd run check:design-system` passed, including checker self-tests.

CRM browser regression matrix passed all six desktop/mobile cases (`consistency-accepted.log`): activity creation, cancel/reopen, selected outcome retained over async search/pages, sanitized 422 response; lead selection and query retention; and identical shared-shell geometry across eight CRM pages, AR/EN, light/dark, 64 screenshots. A mobile icon/label overlap found during actual screenshot inspection was reproduced RED with bounding boxes and repaired by returning icons to normal flex flow. Independent review also caught missing board page context; the form now retains all accepted column page parameters. An intermediate desktop activity attempt timed out after navigation, while its mobile counterpart passed; standalone repeat passed both, and the complete final matrix passed all six. Those failed attempt logs remain preserved.

Independent worker `/root/authorization_security_tests`, explicitly `gpt-6-luna` / `xhigh`, authored isolation tests in a separate worktree; coordinator ran shared runtime tests serially. Independent `/root/dashboard_discovery` (Luna X High) reviewed P0 source, found the read-lock regression and anonymous-browser-test weakness, and confirmed both fixes with no remaining P0 findings. Review was source review; runtime results were executed by the coordinator. The isolation-test author did not review its own implementation.

Skills used: `.agents/skills/ponytail/SKILL.md`, `laravel-best-practices/SKILL.md`, `laravel-tdd/SKILL.md`, `laravel-verification/SKILL.md`; installed Impeccable for frontend checks; official Superpowers systematic-debugging, using-git-worktrees, test-driven-development, verification-before-completion and requesting-code-review. Shared database/compiled-view verification was not delegated because concurrent workers would share mutable fixtures; separate source/test/review scopes were delegated.

## Secondary findings and ownership

Read-only EXPLAIN inspection used MariaDB 11.4.13 and the existing synthetic design-system schema (`mariadb-query-plans.json`). Existing CRM/Employee/Task indexes were selected, with filesorts on these small fixtures. This does not establish a missing production composite index. No speculative indexes or migrations were added.

No production environment was inspected or changed. Telescope provider has local-environment gating; configuration still defaults its enable flag on, while Debugbar follows APP_DEBUG when its override is absent. These are existing settings outside this task's writable scope; production deployment configuration remains a separate check.

Related CRM task lookups still measure 1 task SELECT for 1 linked activity and 5 for 5 (`related-task-queries.json`). `TaskCycle.php` remains leased to `three-page-reference-20261019` under A1-TASK-004. The owner's promise to coordinate handoff is not an actual lease handoff. No protected Tasks file or unrestricted bulk query was introduced. This N+1 is a distinct pending CRM issue, not solved by authorization reuse.

Implementation commits on `feature/A1-CRM-020-consistency`: `00778f5` independent isolation tests, `ec23442` final retained-lock authorization implementation; associated CRM extraction commits `6161559`, `fe30877`. `337c72c` is a prerequisite snapshot of preexisting uncommitted CRM/Sales work, not new delivery credit and not suitable for blind cherry-pick into the dirty integration tree.

## Integration checkpoint

Current work is preserved in isolated worktree `C:/Users/N/.codex-acc2/worktrees/4cd4/new_arasya-crm`. Final implementation/handoff commit: `282144b`; independent Luna rechecked it with no remaining findings. Only the delta after snapshot `337c72c` was applied to the main working tree after `git apply --check` and destination-content comparison. The catalog had independent existing changes, so only the new CRM paragraph was appended. Main Git index was left unchanged; no unrelated work was included in a main commit.

Integration scoped PHPStan and design-system checks pass. Main configured PHPStan reports **14 errors in existing concurrently developed EmployeeWorkDirectory, HelpCatalog and TaskCycle** (`integration-phpstan.log`); those files were not modified. The isolated branch's configured full PHPStan passed. Main Pint initially reported formatting differences after patch application; scoped formatting was rerun under the destination environment and its final `--test` passed.

The main MariaDB suite discovered 585 tests (including concurrent modules absent from the isolated branch). The local MariaDB listener on **127.0.0.1:13317 disappeared during this run**. A read-only diagnostic then received SQLSTATE[HY000][2002], connection actively refused. The suite had reached 126 tests with connection errors and was interrupted using its own process session; no passing full integration result is claimed. No shared database process was restarted or killed.

Main integration browser run: **6 passed, 1 timed out, 3 did not run**, overall four-minute limit, plus runner timeout errors (`integration-browser.log`). Desktop authorized route traversal timed out on the final Arabic lead navigation; anonymous denial and desktop CRM picker/activity/shell checks passed. This does not supersede the isolated 4/4 performance and 6/6 CRM browser runs. The main integration remains pending.

A final benchmark cross-check preserved exactly the same SQL/auth counts on all seven routes, but timing was unstable on the shared host. `performance-release-check.json` and `performance-idle-final.json` are retained, not discarded. Latest check:

| Route | SQL median ms | TTFB p50 / p95 ms |
| --- | --- | --- |
| lead details | 13.61 | 102.7 / 158.5 |
| Employees | 10.72 | 113.6 / 335.6 |
| CRM overview | 11.90 | 78.1 / 130.5 |
| lead directory | 7.46 | 130.1 / 320.9 |
| Tasks | 23.65 | 100.6 / 280.2 |
| Access users | 10.34 | 112.3 / 150.2 |
| Access audit | 111.93 | 352.8 / 461.5 |

Thus query reduction is reproducible; a stable latency improvement is not established. The first paired timing table above remains a record of that run, not a production guarantee.

CRM findings disposition: shared shell fixed; authorized selection/filter retention fixed; activity form/search pagination unified; file/opportunity services extracted with characterization and regressions; task N+1 pending actual TaskCycle ownership handoff. LeadDirectory decreased from approximately 1,783 to 1,306 lines before final formatting. No business feature or approval policy was added by extraction.

Safe resume: restore the approved isolated MariaDB runtime on 13317 through its owner, reconcile current leases and Git changes, address/review the unrelated static-analysis errors through their owners, rerun `integration-run.ps1 vendor/bin/phpunit --configuration phpunit.xml --bootstrap C:/projects/new_arasya-crm/.local/factory-20261019/bootstrap.php --do-not-cache-result`, configured/scoped PHPStan, scoped Pint, design-system checks, and the ten selected browser tests against the isolated main server. Preserve `.local/factory-20261019` databases, session files, logs and raw benchmark samples. Do not cherry-pick prerequisite snapshot `337c72c` blindly. Canonical lifecycle changes use `scripts/task-loop.ps1`; no DONE status is implied by this checkpoint. No persistent delivery runner was configured.

The coordinator officially recorded A1-CORE-017 and A1-CRM-020 as blocked, with the above evidence and named next actions. No task was marked DONE by this performance session. Main HEAD remains `606ebb4`; implementation commits are preserved on the isolated feature branch, with final evidence checkpoint `5990f7f` followed by this formatting/status note. Founder-Led integration/production gates remain pending.

Modified implementation paths: `app/Modules/Identity/Application/SEC04/{AccessControl,RuntimeIdentity}.php`; `app/Http/Controllers/CrmPageController.php`; `app/Modules/CRM/Application/Runtime/{LeadDirectory,LeadRuntime,LeadFiles,LeadOpportunities}.php`; `resources/css/crm.css`; `resources/views/crm/{show,workspace,pipeline,_activity-form,_outcome-picker-script}.blade.php`; the two request-authorization test files, CRM files/pipeline/workspace tests, and authorization-performance/CRM-reference Playwright files. Catalog and evidence changes accompany the implementation. Existing unrelated Employee/Help/Tasks code was preserved.

Final governance verification: validate-docs passed (288 tasks, 124 PRD IDs, 1061 local links; JSON/enums/fields/DAG/reverse edges/coverage/links valid). Official RefreshBacklog was attempted but refused because the existing A1-TASK-004 Markdown row is missing; no unrelated row was invented. tasks.json remains authoritative with both owned tasks blocked. Session counts: 287 tasks/110 done before bounded performance-task registration, 288 tasks/110 done at checkpoint; no completion credit claimed. Canonical main scope Pint --test passed after destination formatting. Final implementation evidence is committed through ee5dcf4; this final governance note is a documentation-only addendum.

# Daily Tasks workspace — A1-TASK-004

## Scope and ownership

This is the bounded local DEVELOPMENT implementation of the owner-approved `/tasks` workspace. The coordinator `tasks-workspace-coordinator` owns A1-TASK-004 and all lifecycle state. I will not edit `docs/backlog/tasks.json`, loop state, approvals, routes, migrations, providers, shared components, the shell/sidebar, or any foreign CRM/Dashboard file. The exact implementation-owned files are:

- `app/Http/Controllers/TaskCycleController.php`
- `app/Modules/Tasks/Application/TASK01/TaskCycle.php`
- `resources/views/tasks/index.blade.php`
- `resources/css/tasks.css`
- `tests/Feature/Tasks/TasksWorkspaceTest.php`
- `tests/Browser/tasks-workspace.spec.ts`
- `tests/Browser/tasks-workspace-fixtures.php`
- `playwright.tasks-workspace.config.ts`
- `docs/governance/evidence/tasks-workspace.md`

The existing checkout contains unrelated user changes, including active CRM/Dashboard work and task-loop records. The owned implementation paths were clean at preflight; unrelated changes remain untouched. No commit, push, branch, approval, task-state, production-gate, provider, dependency, schema, or route change is planned.

## Requirement and acceptance map

A1-TASK-004 (registered against TASK-01, SEC-01, and NFR-06) requires a reference-led daily workspace with real scoped counts, filters, stable bounded pagination, contextual panels, and RTL/LTR/mobile/theme/keyboard verification. Acceptance is observed through these public seams:

| Acceptance | Public behavior / evidence |
|---|---|
| One shared shell/header and existing UI vocabulary | HTML `/tasks` renders the shell page header once and composes existing `ui.summary-strip`, `ui.filter-bar`, `ui.table`, `ui.list-pagination`, `ui.detail-panel`, fields, buttons, badges, and tokens. |
| Server-scoped data and counts | Feature tests seed same-entity/department records owned by or assigned to the actor plus cross-entity, cross-department, and other-owner records; only the exact authorized scope appears in results, summaries, schedule, and evidence. |
| Bounded HTML query | Search, status, priority, due range/preset, allow-listed sort/direction, and page size are validated; pagination is SQL-backed, stable, bounded, and retains query plus locale. Invalid inputs return validation errors. |
| Honest summary definitions | Total, active due-today, active overdue, audit-recorded completions this week, and completion share among non-cancelled tasks due this week are computed from the unfiltered authorized scope. Empty weekly denominator displays unavailable, not a fabricated percentage. Date boundary tests cover the start/end of day and week. |
| Contextual panels | Today and next-seven-day lists use the same scope and SQL limits. Evidence links come only from existing evidence records. No synthetic reminders, progress, project/client links, estimates, notes, export, growth comparison, or unsupported actions are presented. |
| Legacy behavior/security | `/tasks` JSON remains the original unbounded `{'data': [...]}` contract; existing lifecycle and security tests are rerun unchanged. Read-only users do not see the create action. |
| Responsive/localized/accessible view | Feature/browser checks cover Arabic RTL and English LTR, one h1, desktop/mobile no-overflow with bounded table scrolling, light/dark semantic tokens, keyboard focus/search/pagination, and captured screenshots. |

The schema has `title`, `status`, `priority`, `due_at`, `version`, and timestamps, plus task evidence and audit tables; it has no `completed_at`, progress, estimate, project/client, follow-up, or note fields. Completion counts use the existing `complete` audit event. The weekly ratio is defined as currently completed non-cancelled tasks due in the current Monday–Sunday week divided by all non-cancelled scoped tasks due in that week. Follow-ups are described only as upcoming due tasks; no separate follow-up entity exists.

## Visual context and clarification

The task is a product UI used by an employee reviewing assigned office work at a desktop in daylight and checking the same due work on a mobile device. The existing forest-green shell, shared header, reference description, design-system catalog, RTL behavior, and theme tokens govern the presentation. The attachment itself was not available to inspect. No interview, new PRODUCT/DESIGN document, or catalog API extension is needed. `PRODUCT.md` and `DESIGN.md` are absent; the design-context loader confirmed this. Its `teach` path was not run because creating additional documentation would violate the authorized file scope. The shared catalog is leased/read-only and remains unchanged; catalog update is N/A because no component API is extended.

## Technical plan

- Keep `TaskCycle::list()` as the legacy JSON projection. For HTML, add an application-service projection that builds one permission-checked SQL scope matching `ScopedPermissionGuard` (entity, department, and actor as owner or assignee), then derives unfiltered aggregates and bounded context lists before filtering and paginating the main table.
- Keep request validation and locale selection in `TaskCycleController`; allow-list all sortable columns/directions and page sizes. Keep query rules, aggregates, date boundaries, and authorized data selection in `TaskCycle`; keep Blade presentation-only.
- Use the shell's supported `title`, `pageSubtitle`, `pageIcon`, `breadcrumbs`, and locale inputs. Include only fixed local `resources/css/tasks.css` contents from the task index because the leased shell head cannot be changed. Reuse components/tokens without changing their APIs.
- Browser configuration self-started a PHP server on private loopback port `8776` with `APP_ENV=testing`, a synthetic test key, SQLite at a unique ignored `storage/framework/testing/tasks-workspace/<run-id>/` path, and file sessions kept under that same run directory. The fixture/router redirected Laravel's environment path to the isolated directory before bootstrap, preventing reads of repository `.env` files; it prepared only its private SQLite database and synthetic session state. It did not touch the shared QA database or server.
- PHPUnit uses the configured isolated SQLite `:memory:` database. No external DB resets or concurrent PHPUnit/Playwright/cache-mutating runs.

## Test design, RED/GREEN, and delegation

The accepted seams are the authenticated HTML GET `/tasks`, JSON GET `/tasks`, and unchanged existing lifecycle/security endpoints. Tests use real scoped database rows and HTTP/session behavior, not mocks of task logic.

Before initial test code, independent delegation was assessed. Implementation and query tests were serialized because they share `TaskCycle`, the Tasks fixtures, SQLite, and the Windows compiled-view cache; running them concurrently risks conflicting query/compiler/runtime state. No recursive or parallel worker was started.

The focused PHPUnit tests exercise scope exclusion, summaries/time boundaries, every filter, validation, stable bounded pagination/query persistence, empty state, RTL/LTR rendering, read-only action visibility, evidence, and the JSON contract. Existing `tests/Feature/Tasks` covers lifecycle and security regressions. The isolated Playwright test covers desktop/mobile, Arabic/English, light/dark, keyboard navigation, search/filter reset, pagination persistence, table width, and screenshots.

**Initial RED:** `php vendor/bin/phpunit tests/Feature/Tasks/TasksWorkspaceTest.php` first failed with 1 test and 2 assertions because the daily-workspace heading was not yet rendered.

**Initial GREEN:** after the HTML projection/view landed, the focused suite passed 9 tests and 78 assertions. An intermediate PHP 8.4 `DOMDocument::loadHTML()` warning for HTML5 `<aside>` was fixed with a UTF-8 document and scoped libxml error handling; the focused suite then passed.

**Initial implementation regression:** expanded coverage for date boundaries, active-status presets, owner-or-assignee access, evidence, and query/table pagination. Its Tasks regression suite passed 36 tests and 344 assertions. No shared component, route, schema, or lifecycle behavior changed.

**Browser harness history:** an earlier isolated run passed after correcting Playwright's mutually exclusive `url`/`port` config, retaining the coordinator's `vendor/autoload.php` fix, inheriting the run ID into worker processes, using project-relative Laravel cache paths, stopping the Windows server command on seed failure, and generating valid 40-character Laravel session IDs. A locator ambiguity was resolved by scoping duplicate task-title checks to the result table.

## Independent authorization review correction

The timed-out worker tooling reported a cleanup failure, so no review is recorded for that attempt. The coordinator performed final source/read checks, and an independent review confirmed a P2 authorization-scope defect: `ScopedPermissionGuard` rejects PHP-`trim`-blank record IDs, owners, and non-null assignees, while the SQL scope only rejected empty strings. Whitespace-only owner/assignee records could enter HTML results, summaries, context panels, and evidence; the legacy JSON path continued to exclude them through `canAccess`.

The bounded fix applies SQL-side blank checks for `id`, `owner_id`, and nullable `assignee_id` in the shared scope. It normalizes PHP's ASCII trim whitespace (space, tab, LF, CR, NUL, and VT) in a SQLite/MariaDB-compatible expression, before aggregates, bounded context queries, and pagination; no full-dataset or post-pagination HTML filtering was added. The regression keeps valid owned and assigned records visible, excludes malformed records from all workspace projections, and verifies the legacy JSON contract.

This follow-up worker changed only `app/Modules/Tasks/Application/TASK01/TaskCycle.php`, `tests/Feature/Tasks/TasksWorkspaceTest.php`, and this evidence file. No recursive delegation was started: this session was already delegated, recursion was prohibited, and source/tests share the Tasks fixture/runtime.

**RED:** `php vendor/bin/phpunit tests/Feature/Tasks/TasksWorkspaceTest.php --filter=test_whitespace_only_identifiers_are_excluded_from_workspace_and_legacy_json` failed with 1 test and 5 assertions; the whitespace-owner record leaked into the workspace.

**GREEN:** the same focused command passed with 1 test and 21 assertions after the SQL fix.

## Skills actually used

- `.agents/skills/ponytail/SKILL.md`
- `.agents/skills/laravel-best-practices/SKILL.md`, with `rules/db-performance.md`, `rules/advanced-queries.md`, `rules/security.blade.php`, `rules/validation.md`, `rules/routing.md`, and `rules/blade-views.md`
- `.agents/skills/laravel-specialist/SKILL.md`
- `.agents/skills/laravel-tdd/SKILL.md`
- `.agents/skills/tdd/SKILL.md`, with `tests.md` and `mocking.md`
- `.agents/skills/systematic-debugging/SKILL.md` for root-cause and SQLite query diagnostics
- `impeccable` product reference and read-only context loader (no project PRODUCT/DESIGN context available; no docs generated)
- `.agents/skills/laravel-verification/SKILL.md`

## Verification record

After the authorization correction, this worker directly ran the Tasks suite and Pint command. The coordinator reported the remaining exact integration checks below as passed; no gates were rerun in this documentation-only settlement, and the coordinator will rerun them after this evidence update.

| Exact command | Result |
|---|---|
| `php vendor/bin/phpunit tests/Feature/Tasks` | PASS: 37 tests, 365 assertions (worker-run). |
| `php vendor/bin/pint --test app/Modules/Tasks/Application/TASK01/TaskCycle.php tests/Feature/Tasks/TasksWorkspaceTest.php` | PASS: 2 files (worker-run). |
| `npm.cmd run check:design-system` | PASS (coordinator-reported). |
| `npx.cmd playwright test --config=playwright.tasks-workspace.config.ts` | PASS: 8 tests in 25 seconds, coordinator run `245e98a5-0c41-40d8-b0e0-98441ac268c4`. |
| `php vendor/bin/phpunit tests/Feature/DesignSystemComponentsTest.php tests/Feature/DesignSystemTest.php` | PASS: 63 tests, 234 assertions (coordinator-reported). |

Screenshots from coordinator browser run `245e98a5-0c41-40d8-b0e0-98441ac268c4`:

- Desktop: [Arabic RTL, light](<../../../storage/framework/testing/tasks-workspace/245e98a5-0c41-40d8-b0e0-98441ac268c4/screenshots/tasks-workspace-rtl-light-desktop.png>), [Arabic RTL, dark](<../../../storage/framework/testing/tasks-workspace/245e98a5-0c41-40d8-b0e0-98441ac268c4/screenshots/tasks-workspace-rtl-dark-desktop.png>), [English LTR, light](<../../../storage/framework/testing/tasks-workspace/245e98a5-0c41-40d8-b0e0-98441ac268c4/screenshots/tasks-workspace-ltr-light-desktop.png>).
- Mobile: [Arabic RTL, light](<../../../storage/framework/testing/tasks-workspace/245e98a5-0c41-40d8-b0e0-98441ac268c4/screenshots/tasks-workspace-rtl-light-mobile.png>), [Arabic RTL, dark](<../../../storage/framework/testing/tasks-workspace/245e98a5-0c41-40d8-b0e0-98441ac268c4/screenshots/tasks-workspace-rtl-dark-mobile.png>), [English LTR, light](<../../../storage/framework/testing/tasks-workspace/245e98a5-0c41-40d8-b0e0-98441ac268c4/screenshots/tasks-workspace-ltr-light-mobile.png>).

Coordinator source/read checks and independent review are complete; the P2 finding is fixed. Final coordinator gate rerun is pending after this documentation update. Verification is limited to synthetic SQLite; no MariaDB or production execution is claimed. No production-readiness claim, commit, or push was made.

## Owner October19 task-detail continuation

Owner explicitly requested the supplied task DETAIL reference, in addition to the existing daily list. Existing A1-TASK-004 descriptive acceptance was extended under its owned coordinator lock without changing scope/dependencies/gates. Reuse shell and existing detail, upload, audit and assistant APIs; no invented names or upload types. Match1448x1086 composition with RTL/LTR/mobile/themes. Preserve evidence-before-completion, idempotency and server authorization. Independent Luna xhigh review is delegated read-only to task_detail_review; coordinator owns exact tasks/show.blade.php, tasks.css, TaskCycleController show projection and TasksWorkspaceTest/browser assertions. Test execution is serialized because fixture ports, compiled cache and dirty baseline are shared; a separate checkout would omit the owner's current UI foundation. No parallel test edits. RED: assert summary, accepted types, current progress step and no write forms for a reader before implementation. Existing Tasks suite and isolated browser workspace are regression gates. No commit/push/production changes.

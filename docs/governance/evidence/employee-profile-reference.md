# Employee profile and edit workspace evidence

## October 19 screenshot refinement

The owner supplied the Employee Profile reference again and explicitly confirmed
all three old sessions ended, authorizing their lease release and continuation.
A1-EMP-008 is claimed by three-page-reference-20261019. Scope remains presentation
only: preserve the existing employee/profile/forms data and authorization contracts.
Plan: align the single-row hero, icon section tabs, left summary rail and two-by-two
detail cards with the supplied1672x941 image. Unsupported completion, task/project,
login/device and recent-activity metrics stay explicitly unavailable; no invented
sample values or unsupported photo/document actions. Reuse tokens/components.
Test design: existing presentation/privacy/escaping/form tests plus real Blade
browser matrix and authenticated local preview screenshots. No business TDD is
needed for markup/CSS; existing tests protect behavior. Luna X High
employee_profile_review owns independent read-only review; coordinator alone edits
and runs checks because the dirty baseline, Blade cache and fixture runtime are
shared. No concurrent edits or new worktree/branch. Final rendered review required.

Current refinement verification: `php -d opcache.enable_cli=0 vendor/bin/phpunit
tests/Feature/Employees/EmployeeProfilePresentationTest.php
tests/Feature/Employees/EmployeeAcceptanceTest.php --do-not-cache-result` PASS
24 tests/290 assertions using isolated compiled views; `npx.cmd playwright test
--config=playwright.employee-profile.config.ts` PASS4 scenarios (AR/EN, desktop/mobile,
themes, keyboard, actual Blade and unchanged edit form). Design-system check PASS.
Authenticated synthetic preview8788 also rendered the actual employee record:
`.local/three-page-reference/employee-ar.png`, viewport/document1672x941, one h1,
no page errors; hero y139.39 and detail grid y416.73 versus reference139/415.
Coordinator inspected this final screenshot. Source/form snapshots are under
`.local/three-page-reference/employee/`. No backend/controller/schema, shared shell,
commits or deployment. Independent final Luna review is pending before completion;
the task can be released for serial work on the next page without claiming done.

## Requirement mapping and ownership

- User requirement: screenshot-led employee details and shared edit/create presentation, with no server-contract changes.
- Backlog mapping: `A1-EMP-008`, registered/claimed by `employee-profile-reference-coordinator` with user approval. Lifecycle state remains coordinator-owned; this evidence file does not edit task/governance state.
- Acceptance evidence: synthetic Blade presentation assertions, existing employee acceptance suite, real-Blade browser checks, design-system checker, and the screenshots listed below.
- Exact owned paths: `resources/views/employees/show.blade.php`, `resources/views/employees/form.blade.php`, `resources/css/employee-profile.css`, `tests/Feature/Employees/EmployeeProfilePresentationTest.php`, `tests/Browser/employee-profile.spec.ts`, `playwright.employee-profile.config.ts`, and this evidence file. No optional summary partial was needed.
- Other pre-existing working-tree changes remain untouched, including routes/controller/service, shared sidebar/catalog, CRM/task/help/dashboard, and employee-work paths. No commit, branch, DB change, or lifecycle transition was made.

## Design and data constraints

- Reference image inspected: `C:\Users\N\AppData\Roaming\ZadLoop\harness\attachments\v1\objects\3e\3ee78d420c62325525f495c5185d682656a61f85d3b289c6c40a6488d6fcdf78`.
- Reused `layouts.shell`, forest-green sidebar, shared avatar/status/description/form components, and semantic tokens. Employee styles are loaded by the owned employee views and scoped by employee-profile/form class names; no shared component or shell changes.
- Overview shows the actual branch, department, supervisor, hire date, status, login account visibility, authorized private contact and direct-report count. Paginator totals use `total()`; array fixtures use `count()`; missing report data is labeled unavailable rather than shown as zero. The report rows/pagination remain the controller-provided scoped value.
- Existing controller fetches evidence/audit history only for those tabs. Overview does not load their data. Existing tabs, evidence form, permissions, privacy gates, routes, account options, optimistic version, CSRF, old input, errors, worker defaults and terminated-status handling remain.
- No unsupported photo action, tasks/projects, activity, devices, 2FA, completion metric, or other fabricated data/action was introduced. Missing detail values display “Unavailable”/localized equivalent.
- Impeccable context loader reported no root `PRODUCT.md` or `DESIGN.md`. These files were not created; the inspected product-register reference, task screenshot and design-system catalog supplied the design context.

## Test design and delegation

The seams were recorded before implementation/test coding in this file:

1. Synthetic `employees.show` render: identity/actions, summary, employment/account/private panels, existing tabs, scoped report count, and no evidence/audit sentinels on Overview.
2. Privacy/escaping: without the private/account read permissions, private contact and account identifiers remain absent; hostile public values render escaped.
3. Synthetic `employees.form` renders: grouped controls, existing POST URL, CSRF/version, old input/errors/selected options, private/account field gates, hidden worker defaults, active worker status, and terminated status treatment.
4. Chromium against the actual `employees.show` and `employees.form` Blade render output and actual shell/CSS: RTL/LTR, light/dark theme, desktop/mobile layout, no horizontal overflow, keyboard focus, and truthful links/form controls. The renderer writes each synthetic HTML artifact into Playwright's own output directory; it does not use a cloned DOM fixture or submit the form.

No independent worker was started. The view assertions and markup evolve together, while PHPUnit and browser rendering share the Windows Laravel view-cache/runtime; the task grants no separate fixture/database or worktree ownership. The implementation and checks ran serially. The coordinator retains independent review, integration verification and lifecycle control.

## Skills and references used

- Skills: `ponytail`, `impeccable` product register, `laravel-best-practices` with `rules/blade-views.md`, `laravel-tdd`, and `tdd`.
- Skill paths: `.agents/skills/ponytail/SKILL.md`, `C:\Users\N\.agents\skills\impeccable\reference\product.md`, `.agents/skills/laravel-best-practices/rules/blade-views.md`, `.agents/skills/laravel-tdd/SKILL.md`, `.agents/skills/tdd/SKILL.md`.
- Project guides read: `AGENTS.md`, `docs/agents/AGENT_EXECUTION_GUIDE.md`, `docs/agents/LOOP_OPERATIONS.md`, and `docs/design-system/README.md`.

## RED/GREEN and verification log

- Initial focused RED: `php vendor/bin/phpunit tests/Feature/Employees/EmployeeProfilePresentationTest.php` exited 1 with 1 test/9 assertions, missing the new direct-report overview summary on the existing template.
- While expanding coverage, synthetic partial employee overrides initially replaced required fixture IDs; corrected the fixture merge. The terminated form now visibly badges the immutable terminated status while preserving its hidden submitted value. These were test/presentation corrections; server logic was not changed.
- Final focused presentation suite: `php vendor/bin/phpunit tests/Feature/Employees/EmployeeProfilePresentationTest.php` — PASS, 8 tests/65 assertions.
- Existing acceptance suite: `php vendor/bin/phpunit tests/Feature/Employees/EmployeeAcceptanceTest.php` — PASS, 16 tests/225 assertions.
- Design system: `npm.cmd run check:design-system` — PASS (`Design-system check passed`; checker self-test passed).
- Real Blade browser suite: `npx.cmd playwright test --config=playwright.employee-profile.config.ts` — PASS, 4 tests across desktop and mobile projects. It checked both RTL/LTR output, theme switching, keyboard focus, horizontal overflow, detail hierarchy/links, and edit form destination/CSRF/version/fields. The browser emitted only Node `NO_COLOR`/`FORCE_COLOR` warnings.
- Reviewed screenshot artifacts (Arabic; synthetic data):
  - `.local/windows-qa/employee-profile-results/employee-profile-renders-t-c012e-ive-layout-and-honest-links-employee-profile-desktop/employee-profile-ar-desktop.png`
  - `.local/windows-qa/employee-profile-results/employee-profile-renders-t-c012e-ive-layout-and-honest-links-employee-profile-mobile/employee-profile-ar-mobile.png`
  - `.local/windows-qa/employee-profile-results/employee-profile-renders-t-263cf-t-submitting-synthetic-data-employee-profile-mobile/employee-profile-form-mobile.png`

## Runtime limits and handoff

- Browser tests render real Blade/shell via a PHP CLI bootstrap with synthetic in-memory view data and an output file under Playwright's test result directory. They do not start an HTTP server, access/reset/mutate a database, or claim persistent save behavior. No separate authenticated browser DB fixture was available/needed.
- No command explicitly printed, inspected, or modified `.env` or secret values. No migrations/schema, controller/service, route, package, shared component, or lifecycle ledger changes.
- Final coordinator review and any broader authorized HTTP/persistence integration remain outside this worker's completed presentation evidence.

# User profile reference, bounded DEVELOPMENT

## October 19 owner-directed refinement

Owner confirmed all previous sessions ended and authorized their leases to be
released; coordinator three-page-reference-20261019 claimed A1-CORE-016. Scope:
existing user view/style, existing tests and this scoped documentation only.
Use the supplied1536x1024 reference: left avatar/identity and right unavailable
date metadata, joined selected section navigation, stacked basic/organization
fields in paired cards, password in Basic, and security plus an honest additional
information panel. Move the existing bounded option browser below the main form;
preserve both POST forms, version/CSRF, selection retention and incomplete-scope
read-only warning. No invented phone/notes/title persistence, delete/reset action,
created/last-login values or photo upload. Native hash navigation marks its current
location; a focused RED/GREEN assertion and browser hash/keyboard checks cover it.
Pure layout CSS is verified by existing feature/browser tests and viewport-matched
screenshots. Luna X High user_edit_review owns independent read-only review; root
owns all edits and serial runtime checks (shared dirty baseline and compiled views).

## Authorization and scope

The owner requested the user detail/edit page to match the supplied 1536×1024 reference and reuse shared UI. In this session the owner explicitly approved a separately registered task restricted to the user page, its own stylesheet and isolated tests, without editing the shell/sidebar or other agents' files. Coordinator: user-profile-reference-coordinator. Task: A1-CORE-016. This is not production authorization and changes no OPEN/ADR approvals.

Owned implementation: resources/views/access/user.blade.php, resources/css/user-profile.css. Shared shell, components, JavaScript, controllers, services, migrations, routes and catalog README are read-only. Test ownership: tests/Feature/Access/UserProfilePresentationTest.php, tests/Browser/user-profile.spec.ts, tests/Browser/user-profile-fixtures.php, playwright.user-profile.config.ts. Documentation: this file and docs/design-system/user-profile.md.

## Discovery, specification and clarification

The current page already has authorized versioned account and access forms, paginated option sources, retained selections, and server-owned authorization. The reference adds a clear account summary, section navigation, two-column basic/organization fields and security surface. Only existing supported account fields/actions are in scope. No fake phone/notes/job-title persistence, deletion, photo upload, last-login or reset endpoint may be invented. The shared shell already supplies one header and sidebar. Its existing page-header props can be supplied by the child view.

No further business policy clarification is needed. Unsupported reference fields/actions are omitted rather than made falsely interactive.

## Technical plan and Test Design

Presentation-only change: preserve all field names, CSRF, optimistic version, self-access/incomplete-assignment guards, paginated option browsing and retained role values. Include the page stylesheet from the child view without editing shell asset registration. Reuse existing UI/ERP components and semantic tokens; do not extend shared APIs or add dependencies. Match the supplied light RTL desktop composition and adapt logically to English LTR, dark theme and mobile.

No new business logic is authorized; behavioral TDD is N/A for layout CSS. Focused regression assertions cover editable/read-only rendering, escaping, assignment guards, posted names/version and retained selections. A second Luna xhigh attempt owns independent tests/review after implementation settles: shared Blade compilation/runtime means parallel checks are unsafe, and the currently dirty checkout is required. No simultaneous test processes or separate worktree that omits dirty user changes.

Success commands (coordinator reruns after review):

- npm.cmd run check:design-system
- php vendor/bin/phpunit tests/Feature/Access/UserProfilePresentationTest.php
- php vendor/bin/pint --test tests/Feature/Access/UserProfilePresentationTest.php
- npx.cmd playwright test --config=playwright.user-profile.config.ts
- powershell.exe -NoProfile -File scripts/validate-docs.ps1

Browser tests must use isolated synthetic presentation fixtures and explicitly distinguish rendered-page evidence from authenticated persistence/security integration. Existing services/controllers remain unchanged; no database reset, real data or shared browser runtime. Capture actual screenshots for visual review.

## Skills and handoff

Coordinator read ponytail (installed .agents/skills/ponytail/SKILL.md), impeccable frontend instructions and product reference, root policy, lifecycle guide, loop operations, Spec Kit integration and component catalog. PRODUCT.md/DESIGN.md are absent; existing project scope/catalog and the explicit owner reference supply the task context. No new global design-context documents or competing design system are authorized.

Implementation, independent review, exact command outputs, residual limits, lifecycle and next-ready evidence will be appended after execution. No commits, pushes, branches or external publication authorized.

## October 19 existing-work closure continuation

The newer owner directive authorizes small safe commits for owned verified work;
it supersedes the earlier no-commit limit above. Coordinator
`ui-closure-20261019` claimed the existing ready A1-CORE-016 through task-loop.
No other lease, OPEN/ADR policy, branch, provider or deployed system was changed.
The prior uncommitted profile implementation was preserved and reviewed as part
of the bounded candidate; unrelated dirty work remains outside this handoff.

### Scope, test design and observed repairs

Requirement: finish existing UI and controls using the shared design system.
Task A1-CORE-016 owns only the profile view/style, dedicated tests/config and these
two profile documents. Reuse the existing account/access routes and components.
No controller, shell, sidebar, shared component or business service changes.

Regression design: render real Blade using an isolated compiled-view directory,
then inspect submitted/selected controls and visible text. Assert literal dotted
permission keys; retained off-page choices without restoring removed roles;
empty role selection after failed validation; malformed permission fallback;
unavailable location labels without visible internal IDs; read-only title; and
matching visual/keyboard action order. Preserve the account/access CSRF/version
boundaries, permission guards, self-access protection and escaping checks.

Each behavioral defect was observed RED before its minimal GREEN fix:

- `.local/ui-closure-red.log`: two failures, dotted `organization.read` deny became
  inherit and the removed off-page role was restored instead of the submitted role.
- `.local/ui-closure-ids-red.log`: visible internal branch/department identifiers.
- `.local/ui-closure-review-red.log`: two failures for all-unchecked roles and an
  invalid permission value leaving no selected option.
- `.local/ui-closure-title-red.log`: read-only h1 incorrectly promised editing.
- `.local/ui-closure-actions-red.log`: DOM Cancel/Save order differed from visual
  Save/Cancel. The browser regression also checks actual Tab movement.

### Ownership, skills and independent review

Root owns integration, view/style/config, runtime checks, docs, lifecycle and Git.
Luna worker `closure_review` requested `gpt-6-luna` / `xhigh`, first read root policy,
and independently reviewed the connected view/controller/permission paths. It
owned edits only to `tests/Feature/Access/UserProfilePresentationTest.php` and,
for the final accessibility regression, `tests/Browser/user-profile.spec.ts`.
It added the empty-role/malformed-permission and heading/action-order assertions.
No worker changed DB, source, fixtures, lifecycle or Git; no recursive delegation.
Its final source review found no remaining major issue in the owned repairs and
considered the bounded slice ready to commit, while retaining the locale blocker.

The first independent test dispatch failed with an account usage-limit error;
the initial inventory workers returned no usable artifacts. No model fallback
was used. Root authored the first regressions after that unavailability, and the
later Luna review independently caught and tested additional defects.

Separate editing worktrees could not reproduce the carried uncommitted view,
styles and fixtures from HEAD. Exact file ownership was therefore serialized in
the current checkout. Runtime isolation was separate: unique presentation output
directories, disposable native MariaDB processes/datadirs/schema/accounts, and
run-owned HTTP sessions/storage. Existing MariaDB listeners, demo credentials,
shared fixtures, browser sessions and other agents' runtime state were untouched.

Skills actually read: `.agents/skills/ponytail/SKILL.md`,
`C:/Users/N/.codex/skills/frontend-design/SKILL.md`,
`.agents/skills/laravel-best-practices/SKILL.md` and its Blade rules,
`.agents/skills/laravel-tdd/SKILL.md`,
`C:/Users/N/.codex/skills/dispatching-parallel-agents/SKILL.md`,
`C:/Users/N/.codex/skills/requesting-code-review/SKILL.md`, and the installed
official Superpowers 6.4.2 `systematic-debugging`, `test-driven-development`
(including writing-good-tests), `receiving-code-review`, and
`verification-before-completion` SKILL.md files under
`C:/Users/N/.codex/plugins/cache/openai-curated-remote/superpowers/6.4.2/skills/`.

### Executed evidence

- PASS: `php vendor/bin/phpunit tests/Feature/Access/UserProfilePresentationTest.php
  --do-not-cache-result`: **10 tests / 101 assertions**, 71.395s. Testing environment,
  uncached config, in-memory SQLite configuration; presentation only, no DB writes.
  `.local/ui-closure-profile-final.log`.
- PASS: `node node_modules/@playwright/test/cli.js test --config
  playwright.user-profile.config.ts --global-timeout 300000`: **6 tests**, 40.7s.
  Desktop 1536x1024, tablet 768x1024 and mobile 390x844; Arabic RTL/English LTR,
  light/dark, keyboard navigation, section targets, real Blade forms and screenshots.
  `.local/ui-closure-browser-final.log`. These route-fulfilled presentation fixtures
  do not establish authenticated persistence.
- PASS: disposable native **MariaDB 11.4.13** via the application's mysql driver:
  **224 tests / 2500 assertions**, 238.629s, no skips.
  `.local/ui-closure-mariadb-final.log`; reproducible local runner:
  `powershell.exe -NoProfile -ExecutionPolicy Bypass -File
  .local/ui-closure-mysql-native.ps1`. It checks testing environment, uncached config,
  server version/port/schema/datadir before PHPUnit, then stops only its owned DB.
  Suites: Access, Employees, CRM/Presentation and Tasks. The earlier native run
  using Laravel's mariadb driver skipped two tests that explicitly require the
  mysql driver name; the repeat above executed those tests rather than waiving them.
- PASS: actual HTTP login, failed access submission, empty-role retention, direct
  deny persistence, access save and account save on a disposable DB/storage. The
  initial full inventory captured **81 HTTP screenshots / 27 pages / 3 viewports**,
  all HTTP 200, one h1/sidebar/topbar and no page-width overflow. Desktop was
  1672x941 for comparison with the available administration references.
  `.local/ui-closure-http-final.log` and
  `.local/ui-closure-http-e8f1f28345b542539f35f90873f0bd19/screens.json`.
  This is screen/layout evidence, not proof that every control was exercised.
- PASS: `npm.cmd run check:design-system`; owned PHP Pint checks; scoped
  `git diff --check`; docs validator (288 tasks / 1061 links); task-loop fixtures
  (38 events). Logs use `.local/ui-closure-*` names.
- FAIL: full PHPStan level 8, **14 errors** in EmployeeWorkDirectory (8), HelpCatalog
  (1), TaskCycle (5), outside profile scope. `.local/ui-closure-phpstan.log`.
- FAIL: full Pint snapshot listed bootstrap/providers.php, config/debugbar.php,
  QuotationDraftTest.php and the carried profile fixture. The owned fixture was
  formatted and its scoped Pint recheck passed; unrelated files were not edited.
  `.local/ui-closure-pint-full.log`, `.local/ui-closure-pint-owned.log`.

### Acceptance limits and next work

**PARTIAL**, not lifecycle Complete. Independent review found that successful saves
from English return to Arabic: both profile form action URLs omit locale and the
shared AccessController::saved redirect omits it too. Real account save reproduced
the Arabic return. The controller is outside A1-CORE-016's isolated scope. Its
broader A1-CORE-015 task overlaps active CRM/shared-shell and dashboard leases;
do not extend ownership or change those files here. Next safe action: the existing
coordinator integrates the shared locale fix under a valid exclusive task, then
retests both account and access saves from English.

Only access.png, users.png and roles.png remain as local approved references.
The removed Organization image was not substituted; other reference images were
not fabricated. The account's earlier supplied reference is not an available
local file in this continuation. No pixel-parity claim, global PHPStan/Pint green,
all-page functional acceptance, new business module, production signoff, push or
deployment. No downstream task is declared newly ready.

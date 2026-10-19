# A1-HELP-001 tutorial/help foundation

## Scope and acceptance

Standalone foundation only. The server-owned catalog will describe pages/workflows from the routes, controllers, views, and effective access grants currently present. Arabic is the default; English is supported. Help progress belongs to the authenticated persisted access user and company, contains no business record identifiers or PII, and is re-authorized on every read and update. Route/provider/shell/sidebar integration and business-page tour anchors remain pending A1-HELP-002; no contextual coverage is claimed until those anchors are integrated. `/employees/work` remains pending while its route is absent. No business action is invoked by a tour.

## Technical plan

- `app/Modules/Help/`: central fixed-ID page/article/workflow/FAQ/glossary/tour registry, filtering pages and action explanations against `RuntimeIdentity`'s freshly resolved effective permissions, plus a small progress persistence service that requires a verified `access_user_id` session.
- `app/Http/Controllers/HelpController.php`, `routes/help.php`, `app/Providers/HelpServiceProvider.php`: server-rendered `/help` and allowlisted article pages, plus authenticated `GET`/`PATCH` progress endpoints inside `web` middleware. The provider loads this route file but is not registered globally. Focused tests register it manually.
- `resources/views/help/`, `resources/views/components/ui/product-tour.blade.php`, `resources/css/help.css`, `resources/js/product-tour.js`, `lang/{ar,en}/help.php`: accessible localized index/article content, token-based styling, and native deterministic tour controls/spotlight/positioning. Assets are included by the Help view/component because `layouts.shell` is out of scope.
- `database/migrations/2026_10_19_000010_create_tutorial_progress_table.php`: one row per company/user/allowlisted tour, with tour version, stable step key, status, timestamps, composite scope key, and foreign keys to the existing access tables.
- `tests/Feature/Help/FoundationTest.php`: HTTP contract tests using persisted access-session fixtures, manually registered Help provider, and `RefreshDatabase` against a private disposable MariaDB instance. `scripts/test-tutorial-help.ps1` owns that loopback server/schema, guards its identity before PHPUnit migrations, isolates process environment/config, and restores prior process settings without reading production `.env` secrets.
- `docs/design-system/tutorial-help.md`: separate Help catalog entry; do not edit the shared README.

## Test Design (public seams)

- `GET /help`: authenticated HTML, Arabic-first/English rendering, bounded search, and omission of pages/actions whose actual permission is absent.
- `GET /help/articles/{article}`: fixed article ID lookup, current-permission filtering, and no client-selected HTML/URL/selector.
- `GET /help/api/progress/{tour}`: current persisted user's own company-scoped progress, default state, version-reset response, fresh permission checks, `private, no-store`, and fail-closed identity.
- `PATCH /help/api/progress/{tour}`: CSRF-protected exact bounded `{version, step_key, status}` payload; known tour/version/step/status validation; no client-supplied user/company/business identifiers; persisted own-scope update; permission revocation and storage failure behavior.
- Migration-backed checks verify the unique company/user/tour scope and progress values. JavaScript syntax is checked with the requested Node command; browser coverage remains with the later worker and is not authored here.

Expected response contract: `GET /help/api/progress/{tour}` and successful `PATCH` return `{"data":{"tour_id":"…","version":1,"step_key":null,"status":null}}` (GET uses null state when absent or obsolete; PATCH returns the stored stable step/status). Invalid/unknown input is 404 or 422, unauthenticated/unverified persisted identity is 401, permission revocation is 403, stale tour version is 409, and persistence failure is a safe 503 message. All responses are `Cache-Control: private, no-store`; PATCH uses the existing web session/CSRF middleware. The route names are `help.progress.show` and `help.progress.update`.

## RED/GREEN evidence

- RED: `powershell.exe -NoProfile -File scripts/test-tutorial-help.ps1` ran on PHP 8.4.21 / PHPUnit 12.5.39. The pre-migration identity guard passed; `FoundationTest` ran 1 test / 1 assertion and failed as intended because the registered route returned HTTP 200 instead of the required 401. The runner's owned temporary run directory was removed (`remaining_run_directories=0`).
- GREEN (identity slice): `powershell.exe -NoProfile -File scripts/test-tutorial-help.ps1` passed on PHP 8.4.21 / PHPUnit 12.5.39: 1 test, 1 assertion. `RuntimeIdentity` now rejects missing `access_user_id` before its testing-only fallback and the route returns the required 401.
- Full foundation GREEN: pending implementation of the remaining catalog and persistence behaviors.
- Required final checks: `node --check resources/js/product-tour.js`; `npm.cmd run check:design-system`; `php vendor/bin/pint --test app/Modules/Help app/Http/Controllers/HelpController.php app/Providers/HelpServiceProvider.php routes/help.php database/migrations/2026_10_19_000010_create_tutorial_progress_table.php tests/Feature/Help/FoundationTest.php`; `php vendor/bin/phpstan analyse --no-progress --memory-limit=512M`; `powershell.exe -NoProfile -File scripts/test-tutorial-help.ps1`.

## A1-HELP-002 integration (CLOSURE 06)

- Provider registration uses `AppServiceProvider::register()` because
  `bootstrap/providers.php` is owned by concurrent work. Registration is idempotent
  (`getProvider` guard) and leaves `routes/help.php` inside `web` middleware.
- `layouts.shell` now exposes one shared Topbar Help link (`route('help.index')`)
  beside the theme toggle; no second shell or navigation was added.
- `HelpCatalog::tourConfig()` returns the server-authored tour payload and localized
  messages. `ui.page-tour` resolves the actor array to `ActorAccess`, renders nothing
  when the tour is not integrated or has no steps, and is mounted with explicit
  `actor`/`permissions` props because anonymous Blade components do not inherit view
  variables.
- Integrated tours with `data-tour` anchors: Help Center, Dashboard (autostart),
  Access overview/users/organization/roles/audit, Employees directory/workers,
  CRM overview/lead list.
- First-login onboarding: Dashboard passes `:autostart="true"`; the engine starts only
  when the server reports no existing progress, so returning users are not interrupted.
- Pending anchors (not integrated, not claimed): Tasks list, CRM pipeline, quotations.
  Their views are owned by other active leases and remain labelled `integrated => false`,
  so no tour is rendered and no coverage is claimed.
- Engine corrections found while integrating: `findTarget` must not reject anchors that
  the spotlight made `inert` (otherwise every out-of-root step was skipped); the
  launcher controls sit above the scrim so Restart/Pause remain operable while a tour
  is active; the synthetic fixture forces its own root URL so generated article links
  stay on the mocked origin.

### Executed verification

- `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-tutorial-help.ps1`
  → `OK (20 tests, 157 assertions)` on the owned disposable MariaDB.
- `npx playwright test --config=playwright.tutorial-help.config.ts`
  → `24 passed` (desktop/tablet/mobile synthetic fixtures).
- `npm.cmd run check:design-system` → checker and self-test passed.
- `php vendor/bin/pint --test app/Modules/Help app/Http/Controllers/HelpController.php app/Providers/HelpServiceProvider.php app/Providers/AppServiceProvider.php tests/Feature/Help tests/Browser/tutorial-help-fixtures.php`
  → passed.
- `php vendor/bin/phpstan analyse app/Modules/Help app/Providers/HelpServiceProvider.php app/Providers/AppServiceProvider.php --no-progress`
  → no errors.
- Limits: browser coverage is synthetic and does not prove real HTTP session/CSRF
  behavior; Tasks/pipeline/quotation tours and `/employees/work` remain pending; the
  shared `docs/governance/loop/state.json` was not edited because the coordinator owns it.

## Ownership, safety, and handoff limits

- This worker owns only the exact A1-HELP-001 paths listed by the request. Existing concurrent edits in dashboard, Tasks, CRM/Sales, EmployeeWork, `bootstrap/providers.php`, `routes/web.php` and other shared files are context only and will remain untouched.
- No recursive delegation. Independent browser/contextual-anchor tests are reserved for the later worker by the request; the parent owns independent review and final integration. The shared application DB/test state is not used: focused tests use the runner's separately initialized random loopback MariaDB datadir, port, database, and credentials.
- No task/backlog/ADR/lease/provider-registration state changes, commits, production readiness claims, AI integration, business mutation, or customer-screen claims.

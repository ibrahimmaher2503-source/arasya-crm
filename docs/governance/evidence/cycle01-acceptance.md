# Cycle01 — Windows DEVELOPMENT acceptance

Authorization: [CR-CYCLE01-DEV-001](cycle01-authorization.md).
Task: A1-CORE-011. Owner directive: 2026-10-17.
Result: VERIFIED for the bounded, synthetic Windows organization/access cycle.
This does not complete A0-VINT-101, approve OPEN-02, activate AI, or authorize production.

## Executable application

- `http://127.0.0.1:8765/login`: real password login, CSRF, account/IP throttling,
  session invalidation/rotation and fresh persisted identity on every request.
- `/access`: current principal; no grants means 403.
- `/access/organization`: company name/configuration, branch and department
  create/edit/activation, company/branch-linked foreign keys and scoped lists.
- `/access/users` and `/access/users/{id}`: create/edit/activate/deactivate,
  password and assignment changes, version checks and session revocation.
- `/access/roles` and `/access/roles/{id}`: granular catalog and explicit roles;
  user detail assigns/revokes roles and allow/deny overrides.
- `/access/audit`: authorized actor/time/action/subject review; redacted persisted
  metadata includes secure changes, correlation, origin and outcome.

The company ID is the existing entity scope. One branch/department per user is
supported. Existing OrganizationReference linkage and record-level guards are
reused. Persisted identities have an explicit branch, avoiding the former
department-as-branch mapping. Legacy synthetic session snapshots are accepted
only in APP_ENV=testing; new access endpoints always require persisted identity.
All new HTTP surfaces are unavailable outside local/testing.

No public bootstrap or registration exists. Bootstrap administrator is solely a
private synthetic fixture. No business role matrix, approval chain, threshold,
delegation, real-user provisioning or operational retention policy is invented.

## Actual verification

Environment reused: PHP 8.4.21, Laravel 13.36.0, MariaDB 11.4.13 for Win64,
Node 24.15.0, npm 11.12.1, existing Playwright 1.64.1/Chromium. No WSL used.
MariaDB: loopback 127.0.0.1:13316; dedicated `arasya_windows_phpunit` and
`arasya_windows_browser`. Secrets remain in ignored local files, never evidence.
Only the coordinator runs database/migration/browser commands, serially.

| Executed check | Result |
|---|---|
| `php vendor/bin/phpunit` with injected dedicated MariaDB QA environment | PASS, 318 tests / 2339 assertions, 48.308 seconds, exit 0 |
| `php vendor/bin/phpunit tests/Feature/Access` | PASS, 21 tests / 173 assertions, 5.813 seconds, exit 0 |
| `node node_modules/@playwright/test/cli.js test access-cycle.spec.ts --reporter=json --timeout=90000 --global-timeout=180000` | PASS, 2/2 desktop Chromium and Pixel 7 emulation, no skips/retries, 51.421 seconds, exit 0 |
| `php artisan migrate --path=database/migrations/2026_10_17_000003_create_access_directory_tables.php --force --no-interaction` on browser DB | PASS, actual forward migration, exit 0 |
| `php tests/Browser/access-fixtures.php verify` | PASS, scoped durable rows, hashed passwords, revoked roles and attributed audit |
| `php tests/Browser/access-fixtures.php cleanup` | PASS, removed only the owned synthetic company and children; credential file removed |
| PHPStan level 8, app/Modules, app/Providers and affected controllers, `--debug --no-progress --memory-limit=512M` | PASS, no errors, exit 0; no suppressions/baseline changes |
| Scoped `php vendor/bin/pint --test` | PASS, 12 PHP files including routes and fixture |
| `npm run check:design-system` | PASS, reuse checker, checker self-test and JavaScript syntax |
| `php artisan view:cache` | PASS, Blade compilation; real routes also rendered by browser |
| `scripts/test-task-loop.ps1` | PASS, isolated fixtures, 38 lifecycle events |
| `scripts/validate-docs.ps1` | FAIL, six pre-existing skill-index links; no new DAG/acceptance link failure |

There is no npm `build` script/bundler in the existing plain CSS/JavaScript and
Blade architecture. Frontend verification is the existing asset checks, compiled
Blade views and real browser rendering; an npm build is not claimed.

Initial RED: missing `/access` returned 404 instead of 401. Additional regression
RED checks reproduced legacy local session acceptance, privileged detail leakage,
HTML version coercion and missing locked service-level roles.write enforcement.
All are covered by the final passing tests. One assertion was corrected to compare
audit count against the post-login baseline, preserving the legitimate login row.

Bounded failures preserved in ignored logs: the first full suite exceeded 60s;
the final full suite completed within its monitored 50s window. One PHPStan
parallel-worker attempt exceeded 60s; sequential `--debug` succeeded. No unrelated
service/process was stopped. Initial browser assertions incorrectly expected a
role name as text rather than an edit input; selectors were corrected against
actual markup. No acceptance assertion was removed or simulated.

## Browser and durable database proof

Both projects perform administrator login, branch/department creation, restricted
role/user creation, branch/role assignment, a separate real restricted-user login,
allowed scoped reads, users/roles/audit 403s, same-session immediate revocation,
administrator audit review and logout. They verify Arabic RTL, keyboard order and
no viewport overflow. Mobile is browser emulation, not physical-device evidence.

- [Desktop organization screenshot](cycle01-organization-desktop.png)
- [Mobile audit screenshot](cycle01-audit-mobile.png)
- [MariaDB read-back counts](cycle01-database.json)

Read-back across the bounded browser attempts: one synthetic company, seven
branches, seven departments, five users, six roles, 44 audit rows, zero remaining
role assignments; every audit row has actor and timestamp. These include partial
first-run fixtures, not six independent successful business cycles. The final
two browser projects both passed. Cleanup then removed that owned company.
A fresh disposable demonstration fixture was seeded afterward so local login is
usable; its random credentials are only in ignored
`storage/framework/testing/access-fixture.json`. It is not production bootstrap.

Detailed ignored logs: `.local/windows-qa/access-full-pass.log`,
`access-security-final.log`, `access-browser-current.json`,
`access-phpstan-last.log`, `access-docs.log`, `access-loop-tests.log`.

## Independent workers and integration

All nine dispatches explicitly requested `gpt-6-luna` and `xhigh`, with
`fork_turns=none`; the dispatch tool accepted them. This is the owner's sufficient
operational evidence. Independent runtime model identity was not exposed/claimed.
No recursive delegation or parallel database resets occurred.

| Worker | Exclusive ownership / actual output |
|---|---|
| cycle01_governance | Read-only DAG/CR boundaries and completed foundation assessment |
| cycle01_contract | Read-only service/schema/auth risks and reuse contracts |
| cycle01_http_tests | tests/Feature/Access/OrganizationAccessCycleTest.php; CRUD, persistence, stale/duplicate and HTML regressions |
| cycle01_security_tests | tests/Feature/Access/AccessSecurityTest.php; real login, revocation, denial precedence, company/branch isolation, self-grant/escalation and locked-service negatives |
| cycle01_browser | tests/Browser/access-cycle.spec.ts and access-fixtures.php; real login/forms, desktop/mobile, scoped proof and cleanup |
| cycle01_identity | app/Modules/Identity/Application/SEC04/AccessControl.php |
| cycle01_org | app/Modules/Organization/Application/CORE01/OrganizationDirectory.php |
| cycle01_ui | resources/views/access/ only, shared shell/components and Arabic forms |
| cycle01_review | Read-only independent security/technical review |

Coordinator owned registration/lease, migration, controllers/routes, existing
runtime principal binding, integration fixes, all runtime checks and lifecycle.
Test delegation was explicitly assessed and dispatched before production code.
Existing worktrees were inspected; essential unrelated dirty runtime files are
absent from HEAD, so a new linked worktree would not represent this application.
Exclusive file ownership in the current checkout prevented overlapping edits.

Review found no P1 and three P2 issues: grant detail leakage/empty-role UI loss,
HTML scalar coercion, and supplemental roles.write reauthorization outside the
company lock. Fixes are integrated and tested, including a direct service-level
403 regression. Reviewer independently confirmed the first two source fixes and
identified the final lock issue; its last callback later hit account usage limit.
No substitute model or independently attested final approval is claimed.
Low known limit: a deactivated/revoked session cannot use the current logout
operation because it reauthorizes first; protected routes deny it and a new login
rotates it. SQL-admin tamper resistance and genuine two-process contention/crash
tests were not established by this local slice.

## Skills actually applied

- `.agents/skills/ponytail/SKILL.md` — minimal reuse-oriented implementation.
- `.agents/skills/laravel-best-practices/SKILL.md` — architecture, validation,
  routing, Blade/style, error/database rules and installed security/migrations
  `.blade.php` references; Laravel TDD used by test workers.
- `C:/Users/N/.codex/skills/frontend-design/SKILL.md` — established green design
  system, RTL, responsive forms, keyboard semantics and visual inspection.
- Same installed skills root: `dispatching-parallel-agents/SKILL.md`,
  `using-git-worktrees/SKILL.md`, `test-driven-development/SKILL.md`,
  `systematic-debugging/SKILL.md`, `requesting-code-review/SKILL.md`.
- `verification-before-completion` was not found in installed discovery and is
  not claimed. Completion relies on the actual executed evidence above.

Unrelated dirty files, previous completed tasks and historical handoffs remain
preserved. Global dirty task/loop/backlog ledgers are retained locally; this
change commits owned implementation, evidence and new lifecycle handoffs only.
OPEN-02 workflows, broader VINT/AI cycles and production remain separately gated.

Coordinator result: Review and Complete committed through task-loop for
A1-CORE-011; saved status done, DEVELOPMENT gate, no remaining lease or recovery.
Final Ready recalculation: 259 tasks, 91 done, 83 blocked, 85 planned,
currently_ready=0. The separate A0-DS-003 lease is preserved. No task is marked
ready by bypassing a decision. Further Cycle01 enhancements need their own
bounded scope entry; approval workflows still require the owner’s OPEN-02 policy.

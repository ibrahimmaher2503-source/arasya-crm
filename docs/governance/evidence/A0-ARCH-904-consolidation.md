# A0-ARCH-904 / A0-ARCH-905 — reviewed local consolidation

Owner request: consolidate existing work, preserve recoverability, publish the canonical project and verify the Egyptian synthetic dataset. This closes the bounded maintenance work, not the unfinished ERP backlog or production gates.

## Ownership and preservation

Coordinator: consolidation-coordinator (904); formatting-coordinator (905). All workers used gpt-6-luna / xhigh, isolated worktrees, exact file ownership and no recursive delegation. Existing A0-ARCH-903 remains done; its screenshot limitation remains unchanged.

- docs_fixture_repair: Demo feature/safety tests and independent seeder review.
- quality_audit: bounded DemoSeeder follow-up/transfer/employee corrections; independent 905 review.
- architecture_audit: sidebar and navigation regressions; independent frontend review.
- pint_baseline: branch/worktree inventory and 128-file mechanical formatting candidate.
- Coordinator: source fixes, lifecycle, shared runtime, integration and final verification. Shared database/browser runs were serialized; tests used a separate MariaDB schema and compiled-view directory.

Skills actually used: `.agents/skills/ponytail/SKILL.md`, `.agents/skills/laravel-best-practices/SKILL.md`, `.agents/skills/laravel-tdd/SKILL.md`, `.agents/skills/laravel-verification/SKILL.md`; installed systematic-debugging, test-driven-development, using-git-worktrees, dispatching-parallel-agents, requesting-code-review and frontend-design guidance. Skills do not grant production approval.

Initial verified recovery checkpoint: `C:\projects\new_arasya-crm-checkpoints\consolidation-20261018-225451`. It contains the all-refs Git bundle and 50 initial dirty/untracked source files with hashes. Subsequent archives and final verification are recorded below. No force push, production deployment or real-data migration is authorized by this task.

Owner explicitly included the new ERP status report and deletion of the organization reference image. Its obsolete catalog link was converted to an explicit historical limitation. Owner explicitly excluded the concurrent Debugbar/Telescope work (Composer files, provider registration/provider, package configs and migration); those local changes must remain outside the consolidation commits.

## Confirmed defects and tests first

- Seed/clean production guard and late-delete rollback: focused RED failures, then GREEN. Purge with force remains forbidden in production.
- Seeded task evidence now consists of real, clearly synthetic text files; purge rollback restores file lists and bytes as well as rows.
- Lead/customer identity, activity follow-up tasks, transfer ownership/version history and employee status chronology now agree with their stored audit history.
- Tests isolate storage and credentials. Credential-path collision fails before seeding. A late credential write failure explicitly reports that rows were already seeded; no false cross-filesystem atomicity claim. Cleanup removes only the owned credential file. File mode is tightened before writing plaintext; Windows access remains subject to local filesystem ACLs.
- English login locale and employee-create navigation permissions: two failing independent regressions, then GREEN. Sidebar labels and initial theme button state are accessible; navigation tests reset their page per theme.
- 905 is owner-requested historical Pint cleanup, separate from 904. The 128 files have identical normalized PHP ASTs after name resolution. Review caught and corrected Pint placing a Kernel import after its first executable use in the queue validation script.

## Executed verification

| Command / environment | Actual result |
|---|---|
| `php vendor/bin/phpunit --do-not-cache-result tests/Unit/Demo tests/Feature/Demo` with isolated SQLite/storage/views | PASS: 22 tests, 32161 assertions; small, medium and large presets |
| `powershell.exe -NoProfile -File .local/consolidation/run-tests.ps1 vendor/bin/phpunit --do-not-cache-result` on separate MariaDB schema | PASS: 486 tests, 7314 assertions, one SQLite-only trigger test skipped; this run preceded the final additional medium/large test |
| Focused Access navigation/dashboard/scope PHPUnit files | PASS: 20 tests, 104 assertions |
| `php vendor/bin/phpstan analyse --no-progress --memory-limit=1G` | PASS configured level-8 scope |
| `php vendor/bin/phpstan analyse app/Demo app/Console app/Http/Controllers/AccessSessionController.php config/demo.php --no-progress --memory-limit=1G` | PASS additional changed PHP scope |
| `npx.cmd --no-install playwright test --config=playwright.consolidation.config.ts` | PASS: four real-demo admin/sales tests, desktop/mobile, Arabic/English, light/dark, keyboard and permission navigation |
| `npx.cmd --no-install playwright test --config=playwright.login.config.ts` | PASS: three responsive login tests (final rerun recorded below) |
| `npm.cmd run check:design-system` | PASS checker, checker self-test and JS syntax |
| `powershell.exe -NoProfile -File scripts/test-task-loop.ps1` | PASS, 38 fixture events |
| `powershell.exe -NoProfile -File scripts/test-speckit-workflow.ps1` | PASS, 19 assertions including Windows PowerShell and pwsh |
| `powershell.exe -NoProfile -File scripts/test-validate-docs.ps1` | PASS all valid/missing/restored link cases |
| `powershell.exe -NoProfile -File scripts/validate-docs.ps1` | PASS: 275 tasks, 124 PRD IDs, 813 links at this checkpoint |

Invalidated runs are retained under ignored `.local/consolidation`: an early general run was interrupted by another task changing installed development dependencies; another run collided with shared compiled-view deletion. Neither is claimed as passing. The subsequent MariaDB run used an isolated compiled-view directory and passed.

## Local demo and acceptance limits

The canonical local preview is `http://127.0.0.1:8773/dashboard`. Credentials are in ignored `storage/app/private/demo-credentials.json`; never commit or publish them.

Medium demo seeded successfully: 3 branches, 6 departments, 30 users, 180 leads, 65 customers, 333 activities, 22 transfers, 45 employees and 348 tasks (110 base tasks plus activity follow-ups), with 37 synthetic evidence files. Hashes of non-demo company-scoped rows matched before and after. No outbound messages/calls were made.

Authenticated screenshots were visually inspected for desktop Arabic and mobile Arabic; browser tests cover both languages and themes. No pixel-perfect reference parity is claimed. Dashboard financial cards remain explicitly labelled sample presentation data, not actual Egyptian sales/stock/accounting aggregates. Disabled future modules and the ERP backlog remain unfinished; see the dated ERP status report for its historical scope snapshot.

Browser/local MariaDB results do not establish hosted CI, production readiness or recovered historical Employee Acceptance screenshots.

## Final integration and cleanup

Archived 36 obsolete worktree directories in full under the external checkpoint `retired-worktrees` directory; hidden/untracked/ignored data were preserved and Git links retained as text. The complete before-retirement Git bundle verified successfully. Five current review worktrees remain temporarily active until integration.

### Final verified candidate

- Historical formatting integrated as `7f454af7ca527d28fdbde7ed096cbcaad7492cd0`, followed by whitespace convergence commit `a1c7420`. Independent normalized-AST review passed; no behavior change found.
- Full candidate MariaDB initially exposed an invalid Cairo DST-gap timestamp in the large preset. An independent regression first failed by 7200 seconds; the seeder now uses the database session timezone and elapsed timestamps. Focused MariaDB: 23 tests / 32125 assertions / one SQLite-only skip. Focused SQLite: 23 tests / 32161 assertions / one MariaDB-only skip.
- Final full staged-source candidate: `powershell.exe -NoProfile -File .local/consolidation/run-tests.ps1 vendor/bin/phpunit --configuration <candidate>/phpunit.xml --bootstrap .local/consolidation/candidate-bootstrap.php --do-not-cache-result`: PASS, 488 tests / 36772 assertions / one SQLite-only trigger skip, 51.208 seconds. Shared installed dependencies were reused, with development tools disabled; no dependency installation was performed.
- Full raw Git-blob candidate `php <main>/vendor/bin/pint --test --config=pint.json`: PASS. Both configured and additional changed-scope PHPStan checks: PASS. Latest design-system check and documentation validator: PASS (275 tasks / 124 PRD IDs / 813 links).
- Medium preview reseeded after the timezone fix. Non-demo row hashes match exactly. Final authenticated Playwright navigation: four PASS, 20.0 seconds. SQLite covers the rollback trigger skipped by MariaDB.
- Independent final reviews: architecture_audit found no frontend engineering blocker; docs_fixture_repair approved credential ordering; quality_audit approved formatting and the tested timezone correction. Residual portability limitation: a different database exposing SYSTEM timezone only as an abbreviation may lack historical DST rules in PHP. This local server reports Africa/Cairo; exact historical timezone fidelity on other installations is not established.
- All 41 obsolete/review worktrees are retired; only the canonical main worktree remains. Nineteen obsolete local branches were removed after bundle SHA verification. Full source/data recovery archives remain outside the repository. No user data was discarded.
- Seven excluded Debugbar/Telescope paths remain local and their hashes match the preservation manifest. They are not part of the candidate; consequently the working directory intentionally retains those changes.
- Source-only `git diff --cached --check` passes. A check including generated historical handoffs reports their pre-existing trailing spaces; canonical handoff bytes were preserved.
- Remote main was fetched and remains an ancestor (0 remote-only commits); publication uses a normal fast-forward push. Lifecycle completion and publication are recorded by the subsequent canonical commands/commits, not fabricated in this evidence.

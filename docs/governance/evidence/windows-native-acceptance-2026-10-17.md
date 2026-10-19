# Native Windows acceptance — 2026-10-17

Scope: synthetic DEVELOPMENT only. No WSL, production, external AI, SonarQube or GitHub Actions.

## Executed evidence
- PHP 8.4.21; Laravel 13.36.0; Composer 2.10.3; Node 24.15.0; npm 11.12.1; MariaDB 11.4.13; Playwright 1.64.1; Chromium 156.0.8078.4.
- Dedicated loopback MariaDB port 13316, isolated arasya_windows_phpunit and arasya_windows_browser databases. Private credentials/runtime under ignored .local/windows-qa.
- Native full PHPUnit: 209 tests, 1670 assertions, exit 0, 50.069s. Focused vertical suite: 17 tests, 54 assertions, exit 0.
- Playwright: 10 passed, 20.0s, desktop and Pixel 7. Real encrypted synthetic Laravel sessions; visible Arabic RTL form; HTTP save; anonymous and permission denial; replay; cross-entity isolation. No production auth bypass.
- Independent DB verifier: 8 durable rows (form 2, replay 2, isolation 4), correct payload/branch/entity, denied rows 0. Deleted only captured 8 IDs and verified 0 remaining. Nine assertions passed.
- PHPStan configured application scope: no errors. Scoped Pint including browser PHP helpers: passed.
- Coordinator fixture suite including Reopen and all four crash boundaries: Windows PowerShell 5.1 exit 0.
- Independent technical review: no critical issue after repair. Raw historical synthetic keys do not replay under scoped hash format.

Local artifacts: .local/windows-qa/full-phpunit.log, phpunit.log, phpstan.log, browser-db.log; playwright-report/index.html; test-results RTL screenshots. These artifacts contain synthetic data and remain local.

## Lifecycle and delivery
Owner-authorized Reopen changed A5-VINT-002 done to blocked via coordinator journal, event ff188862db0948cb833391f1555ccb84. Historical handoffs preserved. Browser/database acceptance is now evidenced; governed AI findings/actions remain unimplemented and NOT PASS.
READY=0; no pending transaction or active lease. A0-SEC-002 and A0-ARCH-902 are already done. A4-FIN-006 depends on blocked A4-FIN-005 and OPEN-03; A3-INV-004 depends on blocked A3-INV-003 and OPEN-04. These block A5-AI-001 then A5-VINT-001. Finance must approve chart/tax/fiscal/partner posting policy with accounting examples; Warehouse+Finance must approve FIFO versus weighted average, units/batches and negative stock rules with movement examples. No approval inferred.

## Limitations
Docs validator fails on six missing referenced Laravel skill rule files. RefreshBacklog fails because existing BACKLOG.md lacks A0-VINT-001; tasks.json remains authoritative. Composer install verified lock dependencies present, but stalled after autoload generation and was interrupted; subsequent invalid no-audit retry failed. Composer validate passes and all runtime dependencies executed successfully. No repeated infrastructure retries.

## Skills and workers
Read/applied repository ponytail and Laravel best practices, installed frontend-design, dispatching-parallel-agents, using-git-worktrees assessment, TDD, systematic-debugging, requesting-code-review, Laravel verification. verification-before-completion was not found. Missing Laravel rule references reported above.
- windows_browser: package/Playwright config and tests/Browser exclusively; browser/session/DB verification.
- idempotency_repair: controller, PersistCycleRecord and HttpIdempotencyTest exclusively; RED/GREEN scope/replay/conflict/header/race regressions.
- lifecycle_correction: task-loop, fixture suite and correction operation docs exclusively; no real task mutation.
- windows_review: independent read-only integration/security/lifecycle review.
Coordinator owned database/runtime, final checks and actual lifecycle operation. Worktrees were assessed unsafe for this runnable baseline because required application files are untracked; exclusive file ownership used. No concurrent database resets.

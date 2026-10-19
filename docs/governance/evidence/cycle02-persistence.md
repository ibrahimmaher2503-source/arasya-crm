# Cycle02 — Employee persistence execution checkpoint

Authorization: [CR-CYCLE02-DEV-001](cycle02-authorization.md).
Task: A1-EMP-005, coordinator `cycle02-coordinator`.
State: A1-EMP-005 completed through coordinator Review/Complete after independent
repair review; full Cycle02 is **NOT VERIFIED**.

## Sources actually inspected

- Root AGENTS.md; docs/agents/AGENT_EXECUTION_GUIDE.md and LOOP_OPERATIONS.md.
- README navigation; product PRD EMP-01/02, SEC-01/05/06, DOC-01/02, TASK-01,
  acceptance AT-12; SCOPE and REQUIREMENTS_TRACEABILITY.
- SOLUTION_ARCHITECTURE, MODULE_CATALOG, DATA_MODEL, ADR-INDEX and ADR-001/002/003/004.
- PERMISSION_MATRIX, THREAT_MODEL, WORKFLOW_CATALOG, TEST_STRATEGY,
  DEFINITION_OF_DONE, CHANGE_CONTROL, OPEN_DECISIONS and Founder-Led evidence.
- Current tasks.json and current leases; historical A1-EMP handoffs;
  Cycle01 authorization, acceptance and scope-correction authorization.
- Design-system catalog and existing shell/access screens; existing domain,
  Tasks service and Identity principal/permission contracts.

## Reconciliation and dependencies

Historical A1-EMP-001 through 004 stay unchanged: they delivered in-memory domain
and diagnostic slices, not persistent employee CRUD, worker assignment checks or
the requested twelve-step UI acceptance. EMP-01 evidence exists; the referenced
EMP-02 evidence file was absent at inspection. A1-CORE-011 is recorded done with
bounded acceptance evidence. At initial inspection A1-CORE-012 was actively leased
by its owner, including AccessControl, Organization/Identity, routes, shell, the
department-role migration, browser fixtures and tool coverage. Its owner then
completed and released it. No takeover or release of another owner's lease was
performed by this session. A1-EMP-005 was explicitly released/rescoped/reclaimed
to add the completed dependency and own the Employee catalog/coverage extension.

The initial Identity catalog lacked all five Employee grants. After the owner's
release, the five grants were added to that same catalog under the rescoped task;
no permission bypass or second authentication system was introduced.
Register/claim integration only after its actual dependencies and scope are ready;
A1-EMP-006 is the proposed integration follow-up, not a registered/READY task yet.

Documents has no available durable application service. Targets/evaluations are
domain/diagnostic values, not verified Employee360 integrations. Tasks currently
allows self-assignment only; CRM/Projects lack a complete durable offboarding
handoff contract. SEC-06 completion must fail closed until authoritative owner
services establish pending-work resolution. Generic status save must not bypass
offboarding by writing `terminated`.

## Skills and independent file ownership

Actually loaded: `.agents/skills/ponytail/SKILL.md`,
`.agents/skills/laravel-best-practices/SKILL.md` (architecture, validation,
security.blade.php and migrations.blade.php rules), and installed
`C:/Users/N/.codex/skills/{dispatching-parallel-agents,using-git-worktrees,
test-driven-development,systematic-debugging,requesting-code-review,frontend-design}/SKILL.md`.
Workers also read the installed Laravel TDD skill. The separate
verification-before-completion skill and official Superpowers plugin were not
found in installed discovery; neither is claimed installed or newly installed.
Actual execution evidence and project verification requirements remain mandatory.

All dispatches explicitly requested `gpt-6-luna` / `xhigh`, `fork_turns=none`;
the dispatch tool accepted them. No independent runtime identity is asserted.

| Worker | Exclusive ownership / scope |
|---|---|
| employee_contract_audit | Read-only documented/source contract reconciliation |
| cycle02_governance_review | Read-only dependency, lease and registration review |
| employee_persistence_tests | tests/Feature/Employees/EmployeePersistenceTest.php |
| employee_security_tests | tests/Feature/Employees/EmployeePersistenceSecurityTest.php |
| employee_backend | EmployeeDirectory.php and migration 2026_10_17_000005 only |
| employee_frontend_review | Read-only component/browser integration review |

Delegation was assessed before writing test code: separate test files and local
fixtures are independent; runtime is coordinator-only. Existing linked worktrees
were inspected. The needed dirty/untracked Cycle01 runtime is missing from HEAD,
so workers use non-overlapping files in the current checkout, with no recursive
delegation, staging or unrelated worktree changes.

## Executed checks so far

- `php -l tests/Feature/Employees/EmployeePersistenceTest.php`: exit 0.
- `php -l tests/Feature/Employees/EmployeePersistenceSecurityTest.php`: exit 0.
- First isolated PHPUnit attempt: 1 error, 0 assertions, missing EmployeeDirectory.
  Added an explicit availability assertion to express the missing capability.
- `php vendor/bin/phpunit tests/Feature/Employees/EmployeePersistenceTest.php
  --filter test_directory_persists_and_reads_back_versioned_employee_changes_with_audit
  --do-not-cache-result`: **RED**, exit 1; 1 test, 1 assertion, 1 expected failure
  (missing employee persistence service), 0.477 seconds. PHP 8.4.21, PHPUnit
  12.5.39, SQLite `:memory:` and a private Cycle02 compiled-view directory.
  This is TDD evidence only, not MariaDB persistence acceptance.
- Creating a separate synthetic schema on loopback MariaDB failed with SQLSTATE
  42000 / 1044: the existing `arasya_qa` account lacks CREATE DATABASE authority.
  No database was created; no shared schema was reset; no privileged credential
  was guessed. Wait for the existing owner's shared QA runtime slot.
- Guarded registration: task count, reverse edges, unchanged existing statuses,
  current leases/non-overlap and ledger hash checked under exclusive coordinator
  lock; Claim committed through task-loop. Existing history was preserved.
- `scripts/validate-docs.ps1`: exit 1, six pre-existing broken links in installed
  Laravel skill index. A misplaced Markdown backup caused two extra links during
  the first run; its extension was corrected, and the rerun returned to six.

## Integrated checks after dependency release

- Focused Employee PHPUnit on `arasya_windows_phpunit`, injected from the existing
  ignored Windows QA environment: exit 0, **17 tests / 128 assertions**, 5.857s.
  Three test-development defects were corrected: a helper collided with Laravel's
  public `json()` method, two expected fixture values/counts were wrong, and the
  rejection helper needed to register successful exception checks as assertions.
  No application acceptance assertion was removed.
- Combined `php vendor/bin/phpunit tests/Feature/Employees tests/Feature/Access
  --do-not-cache-result`: exit 0, **57 tests / 376 assertions**, 19.964s, after
  the final static-analysis repair. Same MariaDB and separate compiled-view path.
- Direct PDO read-back: `11.4.13-MariaDB`, database `arasya_windows_phpunit`, all
  three Employee tables exist. RefreshDatabase rolls test records back; this is
  service/schema evidence, not browser-reload acceptance or production data.
- PHPStan, scoped to Employees and AccessControl with `--debug --no-progress
  --memory-limit=512M`: exit 0, no errors. Initial 20 errors were repaired by
  accurate iterable documentation/list normalization and deriving create/update
  from the existing nullable profile rather than a redundant boolean.
- Scoped Pint `--test`: exit 0 after formatting only the three new PHP files.
- `scripts/test-task-loop.ps1`: exit 0, 38 isolated fixture events.
- `git diff --check` reported one pre-existing task-ledger trailing space; the
  pre-registration backup confirms the same line was already present. Preserved.

Pre-review source SHA256 (superseded by the fixes below): EmployeeDirectory
`C9297A0053DD4A078BAED197B2120AE3F9188B73BC8F0BD1E2FA3C01CE3AAC3D`;
migration `0F3D511C04D92B679F0BD6A268427BE6F56BEE548F73E68DBD0AD451F58FF08C`.
No commit created: shared dirty files include unrelated owner changes.

## Independent review and repairs

`employee_backend_review` found no authorization bypass. It identified dropped
domain status intent, insufficient audit context/diffs, and inactive placement
checks. Independent test workers added regressions before repairs. Executed RED:
three inactive-scope/account/terminated-evidence failures; separate status/audit
regressions failed on the two missing metadata contracts. The ordinary edit of an
already revoked account link was already passing and remains permitted.

Repairs retain the domain event intent atomically in Employee audit with the
persisted aggregate version. This is a durable pending intent, **not** a published
event or shared outbox integration. Non-private before/after values are retained;
private diffs are encrypted with Laravel Crypt and never copied in plaintext to
the canonical Identity audit. Both trails share a server-generated correlation;
the existing AccessControl append service supplies canonical effective-role and
actor context in the same transaction. New placement into inactive units and new
inactive-account links are denied; historical links survive ordinary edits.
Suspended/terminated workers cannot receive new evidence.

Final integrated repair checks: `php vendor/bin/phpunit tests/Feature/Employees
tests/Feature/Access --do-not-cache-result` on the same MariaDB: exit 0,
**63 tests / 445 assertions**, 16.079s. Scoped PHPStan: exit 0, no errors.
Scoped Pint `--test`: exit 0. Source SHA256 after formatting:
`42C6D4C6468D09D816A4F45C989ED203F1F1A498DB6351817269D438E38653CD`.
The independent reviewer confirmed no blocking finding remains in the bounded
repairs. A1-EMP-005 was closed through task-loop with that reviewer and exact
evidence, preserving prior handoffs. No full-cycle approval is inferred.

Actual routes/UI, RTL/LTR/theme/mobile/browser proof, private documents, session
offboarding, independent technical/security review closure and commits remain
pending. No completed business workflow, new ready business cycle, INTEGRATION
acceptance or production result is claimed.

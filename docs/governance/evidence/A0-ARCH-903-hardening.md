# A0-ARCH-903 — Architecture and AI workflow hardening

## Authorization and boundaries

Owner request: “Arasya Architecture & AI Development Workflow Hardening”, 2026-10-18.
The owner explicitly approved the bounded registration exception proposed in this chat:
“اعتمد التسجيل المحدود المقترح”. This permits coordinator registration under an exclusive
lock and file-hash checks, then the existing Claim/Review/Complete lifecycle. It does not
add a general registration API or approve an ADR, business policy or production change.

Scope: documentation, Spec Kit constitution/project overrides, development-tool regression
tests, EditorConfig and gradual PHPStan coverage. Preserve Query Builder, domain records,
all unrelated dirty/untracked work, existing decision gates and authoritative task state.

## Verified findings and capability matrix

| Capability | Existing | Gap / bounded response |
| --- | --- | --- |
| Modular monolith | Domain/Application/Infrastructure/Presentation directories, central HTTP/Blade, Query Builder services | Runtime architecture and historical proposals need explicit separation; no rewrite |
| Authorization | Custom access services, scope guards, negative tests | Preserve existing service boundaries; no new model/repository layer |
| Reliability | Domain outbox intents, in-memory reliability store, SQL schema and tests | No proof of durable application publisher/worker delivery; retain as unresolved |
| Spec Kit | 11 skills, PowerShell helpers, templates and integration rules | Missing active constitution; generic tasks template makes tests optional |
| TDD / traceability | Test strategy, requirements map, PHPUnit/Playwright | Explicit test-design phase, requirement-to-test mapping and RED/GREEN evidence needed |
| Orchestration | task-loop lock/lease/journal/review, dependency gates | No registration command; this task uses only the owner-approved bounded bootstrap exception |
| Static analysis | PHPStan level 8 on Modules and Providers; baseline passes | Middleware and Shared probe also passes; add these paths without suppressions |
| Spec Kit verification | Existing install checker passes in pwsh | Windows PowerShell 5.1 default-root and non-ASCII source compatibility fail reproducibly |
| Documentation validation | Validator and fixtures exist | Baseline has 12 missing employee screenshot links; do not fabricate historical evidence |
| CI | GitHub origin identified | Supported hosted runner not established; defer CI configuration |
| Demo data | Untracked custom generator and commands alongside Query Builder services | Inspect/document only; no seed, cleanup, factory conversion or real-data changes |

## Prioritized implementation proposal

1. Register/claim this maintenance slice using the approved exception and existing lifecycle.
2. Establish a subordinate constitution and resolved project templates with test design,
   acceptance traceability, risk-based TDD, ownership and independent review.
3. Repair the Windows checker after a failing regression; verify template precedence and
   read-only helper behavior using disposable fixtures.
4. Expand PHPStan only to verified-clean Middleware/Shared; update current-state documentation.
5. Run focused safe checks, independent Luna review and integration checks; report blockers
   separately from this slice's evidence. No completion claim for missing product acceptance.

## File-by-file scope

| Paths | Proposed change |
| --- | --- |
| `.specify/memory/constitution.md` | Active engineering principles subordinate to AGENTS/governance |
| `.specify/templates/overrides/{spec,plan,tasks}-template.md` | Existing resolver's project override mechanism; real Arasya layout and mandatory risk-based tests |
| `docs/agents/SPECKIT_INTEGRATION.md` | Full/light workflow, precedence and evidence mapping |
| `docs/agents/AGENT_EXECUTION_GUIDE.md`, `AGENTS.md` | Link authoritative workflow; explicit TDD/delegation/review handoff |
| `docs/engineering/TEST_STRATEGY.md` | Executable commands, risk/test matrix and honest evidence classes |
| `docs/architecture/SOLUTION_ARCHITECTURE.md` | Current implementation and demo/outbox limits above historical proposal |
| `README.md` | Current functionality/setup and quality entry points; preserve existing demo section |
| `.editorconfig` | Encoding/format conventions for future edits, no bulk reformat |
| `phpstan.neon` | Add Middleware and Shared to level 8 paths |
| `scripts/test-speckit-install.ps1` | Windows-compatible root resolution and source text; hardening checks |
| `scripts/test-speckit-workflow.ps1` | Independent cross-host and template resolution regression fixtures |
| `docs/backlog/tasks.json`, `docs/backlog/BACKLOG.md`, loop state/handoffs | Coordinator registration and supported lifecycle only |
| This evidence file | Audit, ownership, exact verification and limitations |

## Risk and compatibility assessment

No application behavior, schema, dependency or frontend change is planned. The main risks
are governance precedence drift, Windows PowerShell encoding/default-parameter behavior,
and overwriting concurrent documentation edits. Mitigations: subordinate constitution,
project overrides instead of upstream skill edits, child-process regression tests,
exclusive coordinator ownership and hash-guarded integration from isolated worktrees.
Changes to missing historical screenshot evidence, broad formatting/static-analysis debt,
production outbox, database selection and hosted CI remain outside this bounded slice.

## Execution evidence

### Candidate and ownership

Base commit: `489c1a0be548580e1382b6d4cdb35b800a82f5f6`.
Candidate: `C:/Users/N/.codex/worktrees/arasya-workflow-hardening/new_arasya-crm`.
Independent test worktree: `C:/Users/N/.codex/worktrees/arasya-workflow-tests/new_arasya-crm`.
The main working tree remains authoritative for task-loop and contains unrelated
dirty/untracked UI/demo/RBAC work. Candidate README starts with the user's existing
demo section preserved; no demo source/data was copied or changed.

All dispatched agents used `gpt-6-luna` / `xhigh`, no recursive delegation:

- `architecture_audit`: read-only architecture/demo audit, then independent docs/config review.
- `workflow_audit`: read-only Spec Kit/governance gap analysis.
- `quality_audit`: read-only quality/safety audit, then independent helper/harness review.
- `workflow_tests`: sole owner of new `scripts/test-speckit-workflow.ps1` in its isolated
  worktree; no shared files or databases. Coordinator integrated that file into the candidate.
- Coordinator: all other candidate files, canonical lifecycle, integration and verification.

No test writing was delegated into shared fixtures or shared database state. The single
test-file worker did not recursively delegate its one-file/one-fixture scope.

### Changes and RED/GREEN evidence

The candidate adds a subordinate constitution, spec/plan/tasks overrides, EditorConfig,
current-state architecture/README documentation, the full/light development workflow,
test-design/TDD/ownership evidence requirements and Middleware/Shared PHPStan paths.
Existing upstream skill files, application code, schemas and dependencies are unchanged.

The checker now resolves its default root in the script body and uses UTF-8 BOM for
Windows PowerShell 5.1. `.specify/scripts/powershell/common.ps1` replaces four template
reads with explicit UTF-8 `ReadAllText`, preserving plain-string JSON and Unicode.
This discovered helper dependency was added to the bounded task scope under the
coordinator lock after Release, then reclaimed through task-loop; no lifecycle token
or approval was edited manually.

RED: the independent harness initially failed 3 of 17 assertions on Windows PowerShell:
empty default root, object-valued TEMPLATE_CONTENT and corrupt Unicode. pwsh resolver
checks passed. Timeout regression was then observed failing before implementing the
bounded child wait. GREEN results below use the combined candidate, not the old test
worker checkout. The fixture checks exact UTF-8 text, override precedence, missing
template nonzero exit, unchanged file hashes and owned-process timeout/termination.

### Verification — local, 2026-10-18

| Command / scope | Result |
| --- | --- |
| `php .tools/composer.phar validate --no-check-publish` in main | PASS, exit 0 |
| `php vendor/bin/phpstan analyse --no-progress --memory-limit=512M` in main | PASS, original paths |
| `php vendor/bin/phpstan analyse app/Http/Middleware app/Shared --no-progress --memory-limit=512M` in main | PASS, proposed added paths |
| PHPStan with candidate configuration copied to ignored `.local/hardening/phpstan-validation.neon`, paths/autoload rebased to main installed dependencies | PASS, exit 0, all four candidate path groups at level 8; no suppressions |
| `powershell.exe -NoProfile -File <candidate>/scripts/test-speckit-workflow.ps1` | PASS, 19 assertions, exit 0 |
| `pwsh -NoProfile -File <candidate>/scripts/test-speckit-workflow.ps1` | PASS, 19 assertions, exit 0 |
| `npm.cmd run check:design-system` in main | PASS, checker/self-test/JS syntax |
| `powershell.exe -NoProfile -File scripts/test-task-loop.ps1` in main | PASS, 38 fixture events covering locks/overlap/decision gates/recovery/UTF-8/handoffs |
| `php vendor/bin/phpunit --configuration phpunit.xml --do-not-cache-result tests/Unit tests/Feature/Outbox tests/Feature/Platform` in main | PASS, 126 tests / 1265 assertions, exit 0; testing/SQLite-memory/empty DB_URL and unique nonexistent APP_CONFIG_CACHE set and restored |
| Changed candidate PowerShell parser check and changed-document local-link check | PASS |
| `git diff --check` in candidate | PASS |
| `php vendor/bin/pint --test` in main | FAIL, existing formatting across untouched application/test files; no reformat applied |
| `powershell.exe -NoProfile -File scripts/validate-docs.ps1` in main and candidate | FAIL, same 12 missing employee acceptance screenshot links; no new candidate link failures |
| `powershell.exe -NoProfile -File scripts/test-validate-docs.ps1` in main | FAIL, fixture copies docs/scripts/root Markdown but omits linked source/resources/.agents and historical screenshot targets; final observed run 62 errors including concurrent untracked RBAC document |
| Browser, MariaDB qualification, hosted CI, deployment, production, demo seed/clean | NOT RUN; no application/data changes; fixture/server/runner acceptance not established by this slice |

The initial registration attempt failed before replacement because the runtime treated
the null backup path as empty. Retry used a concrete ignored backup, verified all prior
task objects unchanged and registered the sole new task. No previous task state was lost.

### Independent review and remaining gates

Docs/config review found no blocking defect; its precision suggestions about original
main demo files, ignored dependency prerequisites and adjacent cleanup warning were applied.
The coordinator also restored installed T001/[US1]/path checklist syntax in the tasks
override. Technical review raised P2 unbounded child waits; the independent worker added
bounded waits and timeout regression checks. `quality_audit` independently re-read the
final timeout path and confirmed P2 resolved with no further findings. Reviewers inspected
code/documents only; test executions above belong to the coordinator/independent test worker.

Whole-repository failures are not waived. The candidate stays isolated: it is not merged,
copied over the main source files, pushed, deployed or marked done. The missing employee
evidence needs its owning recovery task; broad Pint/validator-fixture debt needs an owned
follow-up rather than a hidden expansion of this maintenance task. The untracked
`demo:clean` environment/transaction risk is documented, not silently fixed or executed.

No duplicate root architecture/contribution/security policy was created: existing
authoritative docs were extended instead. CI is deferred until runner/services and
commands are established. DEVELOPMENT evidence grants no later gate acceptance.

### Skills actually used

- `.agents/skills/ponytail/SKILL.md`
- `.agents/skills/laravel-best-practices/SKILL.md` and `rules/architecture.md`
- `.agents/skills/laravel-verification/SKILL.md`
- `.agents/skills/systematic-debugging/SKILL.md`
- `.agents/skills/speckit-constitution/SKILL.md`
- `.agents/skills/tdd/SKILL.md` and `tests.md` (test worker)
- `C:/Users/N/.codex/skills/dispatching-parallel-agents/SKILL.md`
- `C:/Users/N/.codex/skills/using-git-worktrees/SKILL.md`
- `C:/Users/N/.codex/skills/test-driven-development/SKILL.md`
- `C:/Users/N/.codex/skills/requesting-code-review/SKILL.md`

No frontend implementation skill was needed because no UI source was changed. Available
session frontend skills remain discoverable; the root policy still requires reading the
selected skill and component catalog before later UI work.

## Final handoff

Reviewed candidate commit: `bc895a09f2c1a8ef4283a4cb25b60b0047dc95e9`, branch
`codex/arasya-workflow-hardening`; candidate working tree clean after commit.
Both final harness invocations passed 19 assertions. Intended main source target hashes
still match the pre-edit snapshot, including the user's original README changes.
Only canonical registration/lifecycle/backlog snapshot and this evidence changed in main.

Canonical task was moved through Review then Block using task-loop. It is **blocked**,
not done; the existing lease is retained for the documented blocker. No new tasks
became ready through completion because none was recorded. Integration, push and release
acceptance remain withheld. Next action belongs to the coordinator and the owners of
Employees evidence and quality-maintenance scopes: recover actual evidence, repair
fixture/formatting debt, or establish an explicitly authorized scoped baseline policy,
then rerun applicable gates. Do not fabricate images or silently waive failures.

## Resume decision and blocker recovery — 2026-10-18

This section supersedes the earlier blocked handoff only after the remaining
verification, independent review and canonical lifecycle transitions are recorded.
Original candidate: bc895a09f2c1a8ef4283a4cb25b60b0047dc95e9.
Reconciled main: 489c1a0be548580e1382b6d4cdb35b800a82f5f6.

### Explicit owner decision

The owner replied: "أعتمد القرار المحدود والدمج بعد بقية الفحوص" (approve the
limited decision and integration after the remaining checks). The approved scope:

- Preserve all 12 missing Employee image paths as historical text and explicitly
  mark their visual evidence NOT CURRENTLY VERIFIED. No fabricated replacements,
  new browser acceptance, Employee lifecycle change or production permission.
- For A0-ARCH-903 only, Pint applies to changed PHP files. There are zero such
  files. Repository-wide Pint remains FAIL with 131 untouched failing paths;
  this is recorded debt, not a passing gate or exemption for later tasks.
- Integration still requires the remaining applicable checks and independent review.

This records the human's scoped engineering decision; no approval token, ADR,
Founder-Led policy or historical Employee task status was edited.

### Genuine artifact recovery

Luna xhigh worker recover_employee_evidence inspected each exact path with
`git log --all --full-history`, tracked paths in all 84 refs, project test-results
and playwright-report, ignored local test artifact directories, three
.tools/worktrees/employees-{browser,tests,ui}, b3-emp-002, and linked CRM polish
worktrees. No original was found. The registered b2-emp-001 checkout was absent.
Existing PNGs were older design references/Cycle01 or CRM evidence and were not
substituted. External backups and personal download locations were not inspected.
No hash can be supplied for absent originals. The earlier login-design evidence
records the cleanup incident. No browser or database run was performed here.

### Pint debt inventory

Read-only worker pint_baseline ran `php vendor/bin/pint --test --format=json`
on canonical main: exit 1, 131 failing files, zero candidate PHP changes.
130 failing paths were clean; app/Providers/AppServiceProvider.php already had
unrelated user changes. No formatting was applied. The ignored sanitized report
is `.local/workflow-hardening/pint-baseline.json`, SHA-256
08733B7E7DB393FE7E926A3BA6DC0B4FB0937159F2718CA6077BDBFDAA61FE6E.
The complete failing path inventory follows. Future cleanup needs its own owned
scope and review; this task neither registers nor claims that cleanup.
- `app/Http/Controllers/AccessController.php`
- `app/Http/Controllers/CrmPageController.php`
- `app/Modules/Communications/Application/COM01/ProjectWorkspace.php`
- `app/Modules/Communications/Application/COM01/WorkspaceMutationResult.php`
- `app/Modules/Communications/Application/COM02/ManageConversationMembership.php`
- `app/Modules/Communications/Domain/COM01/WorkspaceItem.php`
- `app/Modules/Communications/Domain/COM01/WorkspaceProjection.php`
- `app/Modules/Communications/Domain/COM02/ConversationMembership.php`
- `app/Modules/Communications/Infrastructure/COM01/WorkspaceAccessDiagnosticAdapter.php`
- `app/Modules/Communications/Infrastructure/COM02/ConversationMembershipDiagnosticAdapter.php`
- `app/Modules/CRM/Application/CRM01/LeadImportItem.php`
- `app/Modules/CRM/Application/CRM01/LeadImportPreview.php`
- `app/Modules/CRM/Application/CRM01/PreviewLeadImport.php`
- `app/Modules/CRM/Application/CRM02/QueryAssignedLeads.php`
- `app/Modules/CRM/Application/CRM03/RecordLeadTaxonomy.php`
- `app/Modules/CRM/Application/Runtime/LeadDirectory.php`
- `app/Modules/CRM/Domain/CRM01/LeadHistoryEntry.php`
- `app/Modules/CRM/Domain/CRM01/LeadLifecycle.php`
- `app/Modules/CRM/Domain/CRM01/LeadRecord.php`
- `app/Modules/CRM/Domain/CRM01/LeadTransition.php`
- `app/Modules/CRM/Domain/CRM02/AssignedLeadProjection.php`
- `app/Modules/CRM/Domain/CRM03/LeadTaxonomyHistory.php`
- `app/Modules/CRM/Domain/CRM05/LeadHandover.php`
- `app/Modules/CRM/Infrastructure/CRM01/LeadAccessDiagnosticAdapter.php`
- `app/Modules/CRM/Infrastructure/CRM02/AssignedLeadAccessDiagnosticAdapter.php`
- `app/Modules/CRM/Infrastructure/CRM04/LeadActivityAccessDiagnosticAdapter.php`
- `app/Modules/CRM/Infrastructure/CRM05/LeadHandoverAccessDiagnosticAdapter.php`
- `app/Modules/Employees/Application/EMP01/ReadScopedEmployeeProfiles.php`
- `app/Modules/Employees/Application/EMP02/AttributeWorker.php`
- `app/Modules/Employees/Domain/EMP01/EmployeeProfile.php`
- `app/Modules/Employees/Domain/EMP01/EmployeeStatus.php`
- `app/Modules/Employees/Domain/EMP01/EmployeeTransitionResult.php`
- `app/Modules/Employees/Domain/EMP02/WorkerAttribution.php`
- `app/Modules/Employees/Infrastructure/EMP01/EmployeeAccessDiagnosticAdapter.php`
- `app/Modules/Employees/Infrastructure/EMP02/WorkerAttributionDiagnosticAdapter.php`
- `app/Modules/Identity/Application/SEC03/ProposeApprovalTransition.php`
- `app/Modules/Identity/Application/SEC04/BrowseAccessDirectory.php`
- `app/Modules/Identity/Domain/SEC03/ApprovalHistoryEntry.php`
- `app/Modules/Identity/Domain/SEC03/ApprovalRequestState.php`
- `app/Modules/Identity/Domain/SEC03/ApprovalStatus.php`
- `app/Modules/Identity/Domain/SEC03/ApprovalTransition.php`
- `app/Modules/Identity/Domain/SEC03/ApprovalTransitionGuard.php`
- `app/Modules/Organization/Application/CORE04/SearchScopedRecords.php`
- `app/Modules/Organization/Domain/CORE04/SearchRecord.php`
- `app/Modules/Organization/Domain/CORE04/SearchResult.php`
- `app/Modules/Performance/Application/KPI01/DefineTarget.php`
- `app/Modules/Performance/Application/KPI02/ProjectApprovedResults.php`
- `app/Modules/Performance/Application/KPI03/DetectTrajectoryAlert.php`
- `app/Modules/Performance/Domain/KPI01/TargetDefinition.php`
- `app/Modules/Performance/Domain/KPI01/TargetOwnerType.php`
- `app/Modules/Performance/Domain/KPI01/TargetStatus.php`
- `app/Modules/Performance/Domain/KPI01/TargetTransition.php`
- `app/Modules/Performance/Domain/KPI01/TargetTransitionGuard.php`
- `app/Modules/Performance/Domain/KPI02/ApprovedResultProjection.php`
- `app/Modules/Performance/Domain/KPI03/TrajectorySnapshot.php`
- `app/Modules/Performance/Infrastructure/KPI01/TargetAccessDiagnosticAdapter.php`
- `app/Modules/Performance/Infrastructure/KPI02/ApprovedResultAccessDiagnosticAdapter.php`
- `app/Modules/Performance/Infrastructure/KPI04/AchievementHistoryAccessDiagnosticAdapter.php`
- `app/Modules/Platform/Application/NFR02/RunConcurrentMutation.php`
- `app/Modules/Platform/Domain/ARCH02/OutboxRecoveryContract.php`
- `app/Modules/Platform/Domain/NFR01/Nfr01AccessContext.php`
- `app/Modules/Platform/Domain/NFR01/Nfr01Decision.php`
- `app/Modules/Platform/Domain/NFR01/Nfr01Guard.php`
- `app/Modules/Platform/Domain/NFR01/Nfr01Resource.php`
- `app/Modules/Platform/Domain/NFR02/ConcurrentMutationHarness.php`
- `app/Modules/Platform/Infrastructure/NFR01/Nfr01AccessDiagnosticAdapter.php`
- `app/Modules/Platform/Infrastructure/NFR02/ConcurrentMutationDiagnosticAdapter.php`
- `app/Modules/Reporting/Application/DASH01/ProjectExecutiveMetrics.php`
- `app/Modules/Reporting/Application/DASH02/SelectRoleDashboard.php`
- `app/Modules/Reporting/Application/DASH03/BuildAuthorizedDrillDown.php`
- `app/Modules/Reporting/Domain/DASH01/ExecutiveMetric.php`
- `app/Modules/Reporting/Domain/DASH01/ExecutiveMetricProjection.php`
- `app/Modules/Reporting/Domain/DASH01/MetricSource.php`
- `app/Modules/Reporting/Domain/DASH02/RoleDashboardSelection.php`
- `app/Modules/Reporting/Domain/DASH03/AuthorizedDrillDown.php`
- `app/Modules/Reporting/Domain/DASH04/OperationalAlert.php`
- `app/Modules/Reporting/Infrastructure/DASH01/ExecutiveMetricAccessDiagnosticAdapter.php`
- `app/Modules/Reporting/Infrastructure/DASH02/RoleDashboardDiagnosticAdapter.php`
- `app/Modules/Reporting/Infrastructure/DASH04/OperationalAlertAccessDiagnosticAdapter.php`
- `app/Modules/Tasks/Application/TASK01/ManageTasks.php`
- `app/Modules/Tasks/Application/TASK01/TaskMutationResult.php`
- `app/Modules/Tasks/Domain/TASK01/TaskAuditIntent.php`
- `app/Modules/Tasks/Domain/TASK01/TaskEvidence.php`
- `app/Modules/Tasks/Domain/TASK01/TaskRecord.php`
- `app/Modules/Tasks/Domain/TASK01/TaskStatus.php`
- `app/Modules/Tasks/Infrastructure/TASK01/TaskAccessDiagnosticAdapter.php`
- `app/Providers/AppServiceProvider.php`
- `app/Shared/Outbox/InMemoryOutboxStore.php`
- `app/Shared/Outbox/OutboxEvent.php`
- `app/Shared/Outbox/OutboxException.php`
- `database/migrations/2026_10_18_000001_add_crm_lead_priority.php`
- `routes/crm.php`
- `scripts/adr-validation/concurrent-race.php`
- `scripts/adr-validation/queue-lifecycle.php`
- `tests/Browser/crm-fixtures.php`
- `tests/Feature/Access/AccessDirectoryBrowsingTest.php`
- `tests/Feature/Communications/COM01AccessTest.php`
- `tests/Feature/Communications/COM02AccessTest.php`
- `tests/Feature/CRM/CRM01AccessTest.php`
- `tests/Feature/CRM/CRM02AccessTest.php`
- `tests/Feature/CRM/Presentation/CrmPagesTest.php`
- `tests/Feature/CRM/Presentation/CrmPolishQaTest.php`
- `tests/Feature/Employees/EMP01AccessTest.php`
- `tests/Feature/Employees/EMP02AccessTest.php`
- `tests/Feature/Outbox/OutboxReliabilityTest.php`
- `tests/Feature/Performance/KPI01AccessTest.php`
- `tests/Feature/Performance/KPI02AccessTest.php`
- `tests/Feature/Platform/NFR01AccessTest.php`
- `tests/Feature/Platform/NFR02AccessTest.php`
- `tests/Feature/Reporting/DASH01AccessTest.php`
- `tests/Feature/Reporting/DASH02AccessTest.php`
- `tests/Feature/Tasks/TASK01AccessTest.php`
- `tests/Unit/Communications/COM01DomainTest.php`
- `tests/Unit/Communications/COM02DomainTest.php`
- `tests/Unit/CRM/CRM01DomainTest.php`
- `tests/Unit/CRM/CRM02DomainTest.php`
- `tests/Unit/CRM/CRM03DomainTest.php`
- `tests/Unit/Employees/EMP01DomainTest.php`
- `tests/Unit/Employees/EMP02DomainTest.php`
- `tests/Unit/Identity/SEC03DomainTest.php`
- `tests/Unit/Organization/CORE04DomainTest.php`
- `tests/Unit/Performance/KPI01DomainTest.php`
- `tests/Unit/Performance/KPI02DomainTest.php`
- `tests/Unit/Performance/KPI03DomainTest.php`
- `tests/Unit/Platform/ARCH02DomainTest.php`
- `tests/Unit/Platform/NFR01DomainTest.php`
- `tests/Unit/Platform/NFR02DomainTest.php`
- `tests/Unit/Reporting/DASH01DomainTest.php`
- `tests/Unit/Reporting/DASH02DomainTest.php`
- `tests/Unit/Reporting/DASH03DomainTest.php`
- `tests/Unit/Tasks/TASK01DomainTest.php`

### Resume implementation and independent ownership

Only docs_fixture_repair wrote implementation: an isolated native worktree at
C:/Users/N/.codex/worktrees/arasya-doc-fixture-repair/new_arasya-crm, owning
scripts/test-validate-docs.ps1 and scripts/test-validate-docs-fixture.ps1.
It read root AGENTS, installed Ponytail, systematic-debugging and TDD guidance.
The worker wrote a failing fixture regression before correction (24 omitted-link
errors in the candidate snapshot), then replaced the live-repository copy with
minimal synthetic governance/docs/source/resources/hidden-skill/image targets.
Synthetic empty image targets are confined to the disposable fixture; they are
not historical screenshot evidence. Production validate-docs.ps1 is unchanged.
Independent existing Luna xhigh reviewers quality_audit and architecture_audit
review the fix and the scoped evidence correction respectively. All workers
used explicit gpt-6-luna/xhigh; no fallback or recursive delegation occurred.
Read-only recovery and Pint workers shared no mutation ownership. The coordinator
alone owns canonical scope/lifecycle, evidence, integration and final checks.

The canonical scope was extended only with the two fixture scripts and Employee
evidence correction, under exclusive coordinator lock and ledger hash comparison,
using existing Release/Claim operations around the scope change. No task status
or approval token was manually changed. Unrelated dirty/untracked work is guarded
by the ignored resume-preservation.json snapshot. A coordinator-created backup
BACKLOG-before-registration.md was renamed to .md.bak with identical SHA-256 to
avoid treating a backup as live documentation; no validator exclusion was added.

### Resume development verification (before integration)

Commands execute from canonical main unless a candidate path is specified.

| Exact command / environment | Observed result |
| --- | --- |
| `php vendor/bin/phpunit --configuration phpunit.xml --do-not-cache-result tests/Unit tests/Feature/Outbox tests/Feature/Platform` with APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, empty DB_URL and unique nonexistent APP_CONFIG_CACHE; prior environment restored | PASS exit 0, 126 tests / 1265 assertions, 8.113 seconds |
| `php vendor/bin/phpstan analyse --configuration=C:/Users/N/.codex/worktrees/arasya-workflow-hardening/new_arasya-crm/.local/hardening/phpstan-validation.neon --no-progress --memory-limit=1G` | PASS exit 0; candidate paths rebased to canonical installed dependencies |
| `php vendor/bin/pint --test --format=json` | FAIL exit 1, 131 unrelated PHP files; changed-PHP gate N/A under explicit owner decision |
| `npm.cmd run check:design-system` | PASS exit 0, checker + self-test + JS syntax |
| `powershell.exe -NoProfile -File scripts/test-task-loop.ps1` | PASS exit 0, 38 fixture events |
| `powershell.exe -NoProfile -File scripts/test-speckit-workflow.ps1` in candidate | PASS exit 0, 19 assertions |
| `pwsh -NoProfile -File scripts/test-speckit-workflow.ps1` in candidate | PASS exit 0, 19 assertions |
| `powershell.exe -NoProfile -File scripts/test-speckit-install.ps1` in candidate | PASS exit 0, 11 skills / 6 scripts / 5 templates |
| `powershell.exe -NoProfile -File scripts/validate-docs.ps1 -Root <candidate>` after approved historical correction | PASS exit 0, 272 tasks / 124 PRD IDs / 612 local links; canonical ledger separately has 273 tasks |

Browser, MariaDB, hosted CI, deployment and production: NOT RUN by this task.
No install, migration, provider call, push or production mutation occurred.
### Independent review resolution

quality_audit ran the final fixture directly under Windows PowerShell and pwsh:
PASS exit 0 in both. It confirmed production validator unchanged, guarded temp
cleanup, valid/missing/repaired link assertions and no remaining findings. RED
was worker-reported, not independently replayed. The coordinator's recovery
assertion finding was fixed in 1978c23: keep the broken link when adding its target.
architecture_audit found one stale VERIFIED screenshot acceptance row. It was
split into historical execution and NOT CURRENTLY VERIFIED screenshot acceptance;
the reviewer reread the correction and found no remaining documentation blocker.
The coordinator independently reran `powershell.exe -NoProfile -File
scripts/test-validate-docs.ps1` and `pwsh -NoProfile -File
scripts/test-validate-docs.ps1` in the candidate: PASS exit 0, three assertions per
host. Candidate documentation validation and `git diff --check` also passed.

Integration will preserve the pre-existing README demo section as an unstaged
user overlay; the main source commit will exclude that section instead of
committing unrelated demo work. The complete working README retains the reviewed
candidate wording, including the warning on demo cleanup. Existing mixed ledger,
state, backlog and unrelated user changes remain outside the source commit.
### Verified integration checkpoint

Main HEAD was rechecked at 489c1a0 before integration; candidate 59e048128a618cd34fc21967072968007cbd78eb
was its descendant. All 19 target paths matched their recorded main baseline
SHA-256 (or expected absence), were copied with a second per-file precheck, and
matched candidate SHA-256 afterward. Every unrelated file in the resume snapshot
retained its bytes. No merge conflict or user-source overwrite occurred.

Post-integration commands in C:/projects/new_arasya-crm:

| Exact command | Result |
| --- | --- |
| `powershell.exe -NoProfile -File scripts/validate-docs.ps1` | PASS exit 0, 273 tasks / 124 PRD IDs / 677 links |
| `powershell.exe -NoProfile -File scripts/test-validate-docs.ps1` | PASS exit 0, valid / missing / repaired target assertions |
| `pwsh -NoProfile -File scripts/test-validate-docs.ps1` | PASS exit 0, same three assertions |
| `powershell.exe -NoProfile -File scripts/test-speckit-install.ps1` | PASS exit 0, 11 skills / 6 scripts / 5 templates |
| `powershell.exe -NoProfile -File scripts/test-speckit-workflow.ps1` | PASS exit 0, 19 assertions |
| `pwsh -NoProfile -File scripts/test-speckit-workflow.ps1` | PASS exit 0, 19 assertions |
| `php vendor/bin/phpstan analyse --no-progress --memory-limit=1G` | PASS exit 0, canonical expanded configuration |
| `php vendor/bin/phpunit --configuration phpunit.xml --do-not-cache-result tests/Unit tests/Feature/Outbox tests/Feature/Platform` | PASS exit 0, 126 tests / 1265 assertions, 3.552 seconds; same isolated SQLite/in-memory environment guards as above |
| `npm.cmd run check:design-system` | PASS exit 0, checker / self-test / JS syntax |
| `powershell.exe -NoProfile -File scripts/test-task-loop.ps1` | PASS exit 0, 38 fixture events |
| `php vendor/bin/pint --test --format=json` | FAIL exit 1, same 131 failing files; changed-PHP gate N/A under owner's scoped decision |
| `git diff --check -- <19 integrated paths>` | PASS exit 0 |

No PHP application or test file changed in this task. Browser, MariaDB, hosted CI
and production remain NOT RUN. Final source commit IDs and canonical Review /
Complete outcome belong to the task-loop handoff generated after this checkpoint;
this evidence does not predeclare lifecycle completion. Complete must release
only this task's lease. No push or deployment is authorized or performed.
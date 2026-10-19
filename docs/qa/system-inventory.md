# ERP QA system inventory — batch 1 (PARTIAL / PROVISIONAL)

> This is the first bounded discovery artifact under `A0-ARCH-906`, not the comprehensive ERP knowledge base and not full module discovery. It records a source-backed snapshot and safe next steps; it makes no acceptance, coverage, production-readiness, or business-policy claim.

## 1. Scope, ownership, and snapshot

| Field | Record |
|---|---|
| Artifact version | `0.1` — initial partial/provisional inventory |
| Registered task | [`A0-ARCH-906`](../backlog/tasks.json), status `in_progress`; scope `docs/qa/`, module `QADiscovery` |
| Canonical owner | `erp-qa-coordinator` owns this QA discovery queue and every module row below. No domain specialists were dispatched or assigned by this artifact. |
| Registration authorization | The registered task's `agent_notes` explicitly say: “Owner directly authorized coordinator registration in this session.” It also says no separate signed authorization artifact or source/commit reference was supplied. The existing coordinator, task, and lease remain canonical; no task, backlog, or governance state was changed here. |
| Current lease snapshot | [`state.json`](../governance/loop/state.json), revision `490`, updated `2026-10-18T22:42:26.6946704Z`: lease `ef277c92030046c0b9f3e31f41a5fcf5`, owner `erp-qa-coordinator`, scope `docs\\qa` / `QADiscovery`, claimed `2026-10-18T22:30:43.1979745Z`, expires `2026-10-19T10:30:43.1862148Z`. At the final state check there was no coordinator lock or pending transaction. |
| Review | Independent review **pending**. No review or acceptance is implied. |
| Source snapshot time | Filesystem/module enumeration at `2026-10-18T22:48:34.874Z`; test paths/status/hashes and task/lease hashes at `2026-10-18T22:49:09.031Z` UTC. Counts below were stable across that snapshot window; the tree can change afterward. |
| Git checkpoint | `HEAD` `606ebb4b440cbc6314a374656a8a94c09cc0af7b`; branch `main`, two commits ahead of `origin/main`. The tree was already dirty, with concurrent tracked and untracked changes in CRM, Sales, dashboard, Tasks, Employees, Help, routes/configuration, tests, backlog, and loop state. This is not a clean-HEAD snapshot; the source inventory includes worktree-only code. The new artifact itself is also a worktree-only file. |
| Change boundary | This task writes only `docs/qa/system-inventory.md`. No application, test, migration, database, lifecycle, provider, dependency, branch, or commit changes are made. |

The current loop snapshot shows five other leases alongside this one: `factory-20261019-dashboard` (`A1-DASH-003`), `lead-overview-reference-coordinator` (`A1-CRM-020`, CRM/Sales), `tasks-workspace-coordinator` (`A1-TASK-004`), `employee-work-coordinator` (`A1-EMP-007`), and `tutorial-help-coordinator` (`A1-HELP-001`). Their source, tests, migrations, and browser outputs are not edited or executed here. The [`A0-ARCH-906` claim handoff](../governance/loop/handoffs/A0-ARCH-906-0a8c534066984e5f986297c08db200e5.md) is supporting lease evidence, not a business approval.

Checkpoint hashes (SHA-256, same source snapshot):

| Input | SHA-256 |
|---|---|
| [`docs/backlog/tasks.json`](../backlog/tasks.json) | `fc63093f820180da5ac17aae415a73a163fe89f329ae09c08cbd6d0151d10bca` |
| [`docs/governance/loop/state.json`](../governance/loop/state.json) | `1dac207e9417d947d2f3d0e9f4b75c03229f6b0b0ff45a606db746dec7c6209d` |
| [`ModuleCatalog.php`](../../app/Modules/Shared/Application/ModuleCatalog.php) | `924e92274e93f6b07300727414c1da1108dcd37fb9cd1b693dd1653cdf8d84db` |
| Test-file manifest: sorted repository-relative path plus each file's SHA-256, 125 included files | `3e5d2456d751a7fe0f9ba3b3c18d4a54f1b9fa46b2b2240cc7483a47fd825186` |

## 2. Current architecture observed in source

The inspected source and the dated implemented snapshot support one Laravel application organized as a modular monolith, not proof that every catalog module is implemented. `bootstrap/app.php` loads the central web route file and adds `routes/sales.php`; [`routes/web.php`](../../routes/web.php) includes CRM and Employees route files and defines access, dashboard, and Tasks routes. [`ModuleServiceProvider.php`](../../app/Providers/ModuleServiceProvider.php) registers only the static `ModuleCatalog` singleton; [`ModuleCatalog.php`](../../app/Modules/Shared/Application/ModuleCatalog.php) returns a fixed 20-name list, not filesystem auto-discovery. [`AppServiceProvider.php`](../../app/Providers/AppServiceProvider.php) also connects the persisted Identity access service to the shared sidebar when a session exists.

Selected code confirms real cross-module seams: CRM's [`LeadDirectory.php`](../../app/Modules/CRM/Application/Runtime/LeadDirectory.php) uses Identity's `AccessControl`/`RuntimeIdentity`, CRM domain types, and Tasks' `TaskCycle`; [`TaskCycle.php`](../../app/Modules/Tasks/Application/TASK01/TaskCycle.php) uses the pure Identity scoped-record guard; Employees' [`EmployeeDirectory.php`](../../app/Modules/Employees/Application/EMP01/EmployeeDirectory.php) uses Identity and Tasks. This establishes selected current code paths only, not a universal module boundary or complete ERP architecture. Current architecture documentation explicitly distinguishes implemented snapshot from proposed target contracts; the proposed logical ERD and payload schemas are not evidence of implemented tables, events, workers, or provider delivery.

### ModuleCatalog and filesystem roots

The source catalog has 20 business-module names. Adding the `Shared` and `Platform` infrastructure roots gives the expected 22-root baseline. At the snapshot, the filesystem had **23** directories under `app/Modules`: those 22 plus `Help`, which is present in the worktree under a separate active Help lease and is absent from `ModuleCatalog`. The discrepancy is recorded, not normalized away or treated as an approved catalog addition.

The count in the source-observation column is PHP files physically found below each root at snapshot time. A zero means **no PHP file was observed under that `app/Modules/<root>` directory**; it does not prove implementation is absent elsewhere. A nonzero count is not a completeness, runtime-wiring, or acceptance measure. Full discovery remains pending for every one of the 22 canonical roots; `Help` remains a separate, non-catalog concurrent root.

| Root / catalog status | PHP files observed | Conservative source observation | QA discovery owner |
|---|---:|---|---|
| `Identity` — ModuleCatalog | 20 | SEC01/SEC02 pure guards and synthetic diagnostic adapters plus SEC04 persisted access/session services are present. System-wide requirement coverage remains unverified. | `erp-qa-coordinator` |
| `Organization` — ModuleCatalog | 25 | CORE01–CORE04 domain/application code and an `OrganizationDirectory` source path are present; no full module/runtime acceptance inferred. | `erp-qa-coordinator` |
| `Workflow` — ModuleCatalog | 0 | No PHP observed under `app/Modules/Workflow`; absence elsewhere is unverified. | `erp-qa-coordinator` |
| `Audit` — ModuleCatalog | 0 | No PHP observed under `app/Modules/Audit`; absence elsewhere is unverified. | `erp-qa-coordinator` |
| `Documents` — ModuleCatalog | 0 | No PHP observed under `app/Modules/Documents`; absence elsewhere is unverified. | `erp-qa-coordinator` |
| `CRM` — ModuleCatalog | 24 | Domain/application code and `Application/Runtime/LeadDirectory.php` are present and route seams exist. Several CRM files are concurrent, untracked work; no full discovery or fresh test claim. | `erp-qa-coordinator` |
| `Tasks` — ModuleCatalog | 9 | TASK01 domain/application code and runtime `TaskCycle` route path are present; current workspace files/tests are under an active lease. | `erp-qa-coordinator` |
| `Performance` — ModuleCatalog | 16 | KPI01–KPI04 domain/application and diagnostic-adapter code is present; system integration and coverage are not established. | `erp-qa-coordinator` |
| `Sales` — ModuleCatalog | 1 | A worktree-only `QuotationDrafts.php` source is present; related controller/routes are concurrent untracked work. Partial and unverified. | `erp-qa-coordinator` |
| `Projects` — ModuleCatalog | 0 | No PHP observed under `app/Modules/Projects`; absence elsewhere is unverified. | `erp-qa-coordinator` |
| `Inventory` — ModuleCatalog | 0 | No PHP observed under `app/Modules/Inventory`; absence elsewhere is unverified. | `erp-qa-coordinator` |
| `Delivery` — ModuleCatalog | 0 | No PHP observed under `app/Modules/Delivery`; absence elsewhere is unverified. | `erp-qa-coordinator` |
| `Quality` — ModuleCatalog | 0 | No PHP observed under `app/Modules/Quality`; absence elsewhere is unverified. | `erp-qa-coordinator` |
| `Production` — ModuleCatalog | 0 | No PHP observed under `app/Modules/Production`; absence elsewhere is unverified. | `erp-qa-coordinator` |
| `Finance` — ModuleCatalog | 0 | No PHP observed under `app/Modules/Finance`; absence elsewhere is unverified. | `erp-qa-coordinator` |
| `Employees` — ModuleCatalog | 10 | EMP01/EMP02 domain/application and `EmployeeDirectory` source are present; concurrent Employee Work source is separately leased and untracked. | `erp-qa-coordinator` |
| `Compensation` — ModuleCatalog | 0 | No PHP observed under `app/Modules/Compensation`; absence elsewhere is unverified. | `erp-qa-coordinator` |
| `Communications` — ModuleCatalog | 8 | COM01/COM02 domain/application and diagnostic-adapter code is present; runtime/provider integration is not established here. | `erp-qa-coordinator` |
| `Reporting` — ModuleCatalog | 16 | DASH01–DASH05 application/domain and diagnostic-adapter code is present; this does not establish live analytics or export endpoints. | `erp-qa-coordinator` |
| `AI` — ModuleCatalog | 0 | No PHP observed under `app/Modules/AI`; absence elsewhere is unverified. | `erp-qa-coordinator` |
| `Shared` — infrastructure root | 2 | Static `ModuleCatalog` and vertical-integration source are present; the provider registers the catalog singleton. | `erp-qa-coordinator` |
| `Platform` — infrastructure root | 17 | Architecture-boundary and NFR guard/harness source plus an outbox recovery contract are present; no durable production infrastructure is established. | `erp-qa-coordinator` |
| `Help` — **extra, not in ModuleCatalog** | 2 | Concurrent worktree-only Help sources exist under `A1-HELP-001`; not counted in the 22-root catalog/infrastructure baseline and not reclassified here. | `erp-qa-coordinator` (QA queue only; Help lease remains with its owner) |

The queue owner column assigns QA coordination only; it does not invent business-domain ownership or imply that the coordinator implements every module. Domain ownership boundaries in the module catalog are explicitly proposed.

### Package versions read without application boot

| Source | Declared / installed fact |
|---|---|
| [`composer.json`](../../composer.json) | PHP constraint `^8.4`; Laravel constraint `^13.17`; PHPUnit constraint `^12.5.12`. The installed PHP interpreter version was not queried. |
| [`composer.lock`](../../composer.lock) and [`vendor/composer/installed.json`](../../vendor/composer/installed.json) | Laravel `v13.36.0`; PHPUnit `12.5.39`. |
| [`package.json`](../../package.json) and [`node_modules/@playwright/test/package.json`](../../node_modules/@playwright/test/package.json) | Playwright manifest constraint `^1.64.1`; installed metadata `1.64.1`. |

These are current working-tree manifests/installed metadata, not an immutable committed baseline. No dependency installation, package script, PHP runtime query, or application boot was performed.

## 3. First-party documentation read and authority

Titles below are from documents actually read. “Authority” describes the source's stated role; it does not elevate a proposal or historical report into approval.

| Path | Read title / sections | Authority and limitation |
|---|---|---|
| [`AGENTS.md`](../../AGENTS.md) | “My Arasya agent skill routing” | Project-level agent workflow, lifecycle precedence, skills-first rules, and no recursive delegation. |
| [Ponytail skill](../../.agents/skills/ponytail/SKILL.md) | “Ponytail” | Minimal-change coding guidance; read before this artifact. |
| [`AGENT_EXECUTION_GUIDE.md`](../agents/AGENT_EXECUTION_GUIDE.md) | “بروتوكول Loop” | Canonical discovery, scope, lease, verification, review, and lifecycle protocol. |
| [`LOOP_OPERATIONS.md`](../agents/LOOP_OPERATIONS.md) | “تشغيل local task loop” | Canonical task-loop and lease operations; `tasks.json` is the lifecycle source. |
| [`SPECKIT_INTEGRATION.md`](../agents/SPECKIT_INTEGRATION.md) | “تكامل Spec Kit للمشروع” | Project phase contract; new tasks need explicit coordinator authorization and task-loop has no Register command. |
| [`SOLUTION_ARCHITECTURE.md`](../architecture/SOLUTION_ARCHITECTURE.md) | “تصميم الحل”; implemented snapshot and proposal boundaries | Dated implemented snapshot is descriptive; later target contracts and proposals are not implementation evidence. |
| [`MODULE_CATALOG.md`](../architecture/MODULE_CATALOG.md) | “Module ownership — Proposed” | Proposed domain responsibility and write-ownership boundaries, not named QA specialists or confirmed runtime coverage. |
| [`DATA_MODEL.md`](../architecture/DATA_MODEL.md) | “Logical ERD — Proposed وليس schema نهائياً” | Logical proposal, not a final schema or migration inventory. |
| [`EVENT_CATALOG.md`](../architecture/EVENT_CATALOG.md) | “Event contracts” | PRD event names distinguished from proposed payloads; not evidence of a wired durable event system. |
| [`TEST_STRATEGY.md`](../engineering/TEST_STRATEGY.md) | “Repository check commands and test design”; environment, DB, and browser sections | Test-selection and isolation policy; original acceptance requirements are not evidence that checks ran. |
| [`PRD.md`](../product/PRD.md) | Master Product Requirements Document; SEC-01/02 and approval baseline | Product requirement authority; SEC-01 and SEC-02 are mandatory requirements, not proof of implementation. |
| [`REQUIREMENTS_TRACEABILITY.md`](../product/REQUIREMENTS_TRACEABILITY.md) | “خريطة تتبع المتطلبات” | Planning map only; it maps SEC-01 to A0-SEC-001/002 and SEC-02 to A0-SEC-003/004, not completion/coverage. |
| [`OPEN_DECISIONS.md`](../governance/OPEN_DECISIONS.md) | “القرارات المفتوحة” | Open decision register; proposals are not final decisions and statuses remain `OPEN`. |
| [`gate-policy.json`](../governance/gate-policy.json) | Founder-Led policy fields and task-scoped authorization list | Deterministic gate contract; it neither closes decisions nor authorizes production. A0-ARCH-906 has no gate override/decision dependency. |
| [`CHANGE_CONTROL.md`](../governance/CHANGE_CONTROL.md) | “ضبط التغيير”; staged amendment and gate model sections | Change/approval boundaries; ADR-002/003 amendment is limited and does not grant this task application execution or production acceptance. |
| [`RBAC_OPERATIONS_MATRIX.md`](../security/RBAC_OPERATIONS_MATRIX.md) | Header/status, source index, Identity/Employees rows, and gaps | Implementation reference for owner review, explicitly **not an approved permission policy**; no current-tree runtime verification was performed for that report. |
| [`SEC-01.md`](../evidence/SEC-01.md) | “SEC-01 adapter evidence” | Historical evidence for a local synthetic diagnostic only; it expressly disclaims authentication/real-record coverage. |
| [`SEC-02.md`](../evidence/SEC-02.md) | “SEC-02 implementation evidence — A0-SEC-004” | Historical evidence for a local synthetic masking diagnostic only; it expressly disclaims real-session and system-wide masking coverage. |
| [`tasks.json`](../backlog/tasks.json) | Registered A0-ARCH-906 record | Canonical task/scope/status and recorded registration authorization; current file is dirty and must remain coordinator-managed. |
| [`state.json`](../governance/loop/state.json) | Current lease and claim event | Canonical lease/event state at the timestamp above; it is not a business approval. |
| [`A0-ARCH-906` claim handoff](../governance/loop/handoffs/A0-ARCH-906-0a8c534066984e5f986297c08db200e5.md) | “Claim: A0-ARCH-906” | Supporting evidence for the matching claim event; explicitly not a business approval. |

Relevant first-party sources identified for a later phase but **UNREAD** in this batch (no title, policy, or requirements have been inferred from the filenames): [`docs/product/SCOPE.md`](../product/SCOPE.md), [`docs/security/PERMISSION_MATRIX.md`](../security/PERMISSION_MATRIX.md), [`docs/workflows/WORKFLOW_CATALOG.md`](../workflows/WORKFLOW_CATALOG.md), [`docs/architecture/ADR-INDEX.md`](../architecture/ADR-INDEX.md), and [`docs/architecture/finance-stock-contracts.md`](../architecture/finance-stock-contracts.md). Other module-specific requirements and evidence remain outside this first batch.

## 4. Existing test-file catalog (snapshot only)

At `2026-10-18T22:41:24.329Z`, recursive path enumeration counted **125 conventional test files**: 32 Unit + 77 Feature + 16 Browser. The `Unit` and `Feature` directory suite names are declared in [`phpunit.xml`](../../phpunit.xml); Browser entries below are Playwright `*.spec.ts` files under `tests/Browser`.

**Counting definition:** include every file matching `tests/Unit/**/*.php`, `tests/Feature/**/*.php`, and `tests/Browser/**/*.spec.ts`. Exclude `tests/Browser/*.php` helpers/fixtures, `tests/Browser/README.md`, non-`*.spec.ts` files, `scripts/*test*.ps1`, ignored `.local/qa-discovery` registration/fixture scripts, and artifacts under `test-results`/`playwright-report`. The three included patterns are file-counting patterns only. They do not count test methods, scenarios, assertions, executed tests, coverage, or requirement mappings.

**Git status at that timestamp:** no modified tracked test files were listed; 13 of the 125 counted files were untracked in the changing worktree. Each such entry is marked **[untracked]** below. Two additional untracked Browser fixture helpers—[`employee-work-fixtures.php`](../../tests/Browser/employee-work-fixtures.php) and [`tasks-workspace-fixtures.php`](../../tests/Browser/tasks-workspace-fixtures.php)—are excluded by the stated patterns. These files belong to active concurrent work and were not run or edited.

### PHPUnit `Unit` (32 files)

- [tests/Unit/Identity/SEC01DomainTest.php](../../tests/Unit/Identity/SEC01DomainTest.php)
- [tests/Unit/Organization/CORE01DomainTest.php](../../tests/Unit/Organization/CORE01DomainTest.php)
- [tests/Unit/Platform/ARCH01DomainTest.php](../../tests/Unit/Platform/ARCH01DomainTest.php)
- [tests/Unit/Identity/SEC02DomainTest.php](../../tests/Unit/Identity/SEC02DomainTest.php)
- [tests/Unit/Organization/CORE02DomainTest.php](../../tests/Unit/Organization/CORE02DomainTest.php)
- [tests/Unit/Organization/CORE03DomainTest.php](../../tests/Unit/Organization/CORE03DomainTest.php)
- [tests/Unit/CRM/CRM04DomainTest.php](../../tests/Unit/CRM/CRM04DomainTest.php)
- [tests/Unit/Performance/KPI04DomainTest.php](../../tests/Unit/Performance/KPI04DomainTest.php)
- [tests/Unit/Reporting/DASH04DomainTest.php](../../tests/Unit/Reporting/DASH04DomainTest.php)
- [tests/Unit/CRM/CRM05DomainTest.php](../../tests/Unit/CRM/CRM05DomainTest.php)
- [tests/Unit/Reporting/DASH05DomainTest.php](../../tests/Unit/Reporting/DASH05DomainTest.php)
- [tests/Unit/CRM/CRM06DomainTest.php](../../tests/Unit/CRM/CRM06DomainTest.php)
- [tests/Unit/Demo/EgyptianDataTest.php](../../tests/Unit/Demo/EgyptianDataTest.php)
- [tests/Unit/Communications/COM01DomainTest.php](../../tests/Unit/Communications/COM01DomainTest.php)
- [tests/Unit/Communications/COM02DomainTest.php](../../tests/Unit/Communications/COM02DomainTest.php)
- [tests/Unit/CRM/CRM01DomainTest.php](../../tests/Unit/CRM/CRM01DomainTest.php)
- [tests/Unit/CRM/CRM02DomainTest.php](../../tests/Unit/CRM/CRM02DomainTest.php)
- [tests/Unit/CRM/CRM03DomainTest.php](../../tests/Unit/CRM/CRM03DomainTest.php)
- [tests/Unit/Employees/EMP01DomainTest.php](../../tests/Unit/Employees/EMP01DomainTest.php)
- [tests/Unit/Employees/EMP02DomainTest.php](../../tests/Unit/Employees/EMP02DomainTest.php)
- [tests/Unit/Identity/SEC03DomainTest.php](../../tests/Unit/Identity/SEC03DomainTest.php)
- [tests/Unit/Organization/CORE04DomainTest.php](../../tests/Unit/Organization/CORE04DomainTest.php)
- [tests/Unit/Performance/KPI01DomainTest.php](../../tests/Unit/Performance/KPI01DomainTest.php)
- [tests/Unit/Performance/KPI02DomainTest.php](../../tests/Unit/Performance/KPI02DomainTest.php)
- [tests/Unit/Performance/KPI03DomainTest.php](../../tests/Unit/Performance/KPI03DomainTest.php)
- [tests/Unit/Platform/ARCH02DomainTest.php](../../tests/Unit/Platform/ARCH02DomainTest.php)
- [tests/Unit/Platform/NFR01DomainTest.php](../../tests/Unit/Platform/NFR01DomainTest.php)
- [tests/Unit/Platform/NFR02DomainTest.php](../../tests/Unit/Platform/NFR02DomainTest.php)
- [tests/Unit/Reporting/DASH01DomainTest.php](../../tests/Unit/Reporting/DASH01DomainTest.php)
- [tests/Unit/Reporting/DASH02DomainTest.php](../../tests/Unit/Reporting/DASH02DomainTest.php)
- [tests/Unit/Reporting/DASH03DomainTest.php](../../tests/Unit/Reporting/DASH03DomainTest.php)
- [tests/Unit/Tasks/TASK01DomainTest.php](../../tests/Unit/Tasks/TASK01DomainTest.php)

### PHPUnit `Feature` (77 files)

- [tests/Feature/FoundationSmokeTest.php](../../tests/Feature/FoundationSmokeTest.php)
- [tests/Feature/RtlShellTest.php](../../tests/Feature/RtlShellTest.php)
- [tests/Feature/Identity/SEC01AccessTest.php](../../tests/Feature/Identity/SEC01AccessTest.php)
- [tests/Feature/Platform/ARCH01AccessTest.php](../../tests/Feature/Platform/ARCH01AccessTest.php)
- [tests/Feature/Organization/CORE01AccessTest.php](../../tests/Feature/Organization/CORE01AccessTest.php)
- [tests/Feature/Identity/SEC02AccessTest.php](../../tests/Feature/Identity/SEC02AccessTest.php)
- [tests/Feature/Organization/CORE02AccessTest.php](../../tests/Feature/Organization/CORE02AccessTest.php)
- [tests/Feature/Organization/CORE03AccessTest.php](../../tests/Feature/Organization/CORE03AccessTest.php)
- [tests/Feature/CRM/CRM03AccessTest.php](../../tests/Feature/CRM/CRM03AccessTest.php)
- [tests/Feature/Performance/KPI03AccessTest.php](../../tests/Feature/Performance/KPI03AccessTest.php)
- [tests/Feature/Reporting/DASH03AccessTest.php](../../tests/Feature/Reporting/DASH03AccessTest.php)
- [tests/Feature/CRM/CRM04AccessTest.php](../../tests/Feature/CRM/CRM04AccessTest.php)
- [tests/Feature/Performance/KPI04AccessTest.php](../../tests/Feature/Performance/KPI04AccessTest.php)
- [tests/Feature/Reporting/DASH04AccessTest.php](../../tests/Feature/Reporting/DASH04AccessTest.php)
- [tests/Feature/CRM/CRM05AccessTest.php](../../tests/Feature/CRM/CRM05AccessTest.php)
- [tests/Feature/Reporting/DASH05AccessTest.php](../../tests/Feature/Reporting/DASH05AccessTest.php)
- [tests/Feature/CRM/CRM06AccessTest.php](../../tests/Feature/CRM/CRM06AccessTest.php)
- [tests/Feature/VerticalIntegration/HttpContractTest.php](../../tests/Feature/VerticalIntegration/HttpContractTest.php)
- [tests/Feature/VerticalIntegration/RtlContractTest.php](../../tests/Feature/VerticalIntegration/RtlContractTest.php)
- [tests/Feature/VerticalIntegration/PersistenceContractTest.php](../../tests/Feature/VerticalIntegration/PersistenceContractTest.php)
- [tests/Feature/VerticalIntegration/AuthorizationContractTest.php](../../tests/Feature/VerticalIntegration/AuthorizationContractTest.php)
- [tests/Feature/VerticalIntegration/A5VINT002VerticalIntegrationTest.php](../../tests/Feature/VerticalIntegration/A5VINT002VerticalIntegrationTest.php)
- [tests/Feature/VerticalIntegration/AuthorizationNegativePathTest.php](../../tests/Feature/VerticalIntegration/AuthorizationNegativePathTest.php)
- [tests/Feature/VerticalIntegration/PersistenceAcceptanceTest.php](../../tests/Feature/VerticalIntegration/PersistenceAcceptanceTest.php)
- [tests/Feature/VerticalIntegration/HttpIdempotencyTest.php](../../tests/Feature/VerticalIntegration/HttpIdempotencyTest.php)
- [tests/Feature/DesignSystemComponentsTest.php](../../tests/Feature/DesignSystemComponentsTest.php)
- [tests/Feature/DesignSystemTest.php](../../tests/Feature/DesignSystemTest.php)
- [tests/Feature/Tasks/TasksCycleAssistantTest.php](../../tests/Feature/Tasks/TasksCycleAssistantTest.php)
- [tests/Feature/Tasks/TasksCycleHttpTest.php](../../tests/Feature/Tasks/TasksCycleHttpTest.php)
- [tests/Feature/Tasks/TasksCycleSecurityTest.php](../../tests/Feature/Tasks/TasksCycleSecurityTest.php)
- [tests/Feature/Access/OrganizationAccessCycleTest.php](../../tests/Feature/Access/OrganizationAccessCycleTest.php)
- [tests/Feature/Access/AccessSecurityTest.php](../../tests/Feature/Access/AccessSecurityTest.php)
- [tests/Feature/Employees/EmployeePersistenceSecurityTest.php](../../tests/Feature/Employees/EmployeePersistenceSecurityTest.php)
- [tests/Feature/Employees/EmployeePersistenceTest.php](../../tests/Feature/Employees/EmployeePersistenceTest.php)
- [tests/Feature/Employees/EmployeeHttpTest.php](../../tests/Feature/Employees/EmployeeHttpTest.php)
- [tests/Feature/CRM/Runtime/LeadSecurityTest.php](../../tests/Feature/CRM/Runtime/LeadSecurityTest.php)
- [tests/Feature/Access/AccessPresentationTest.php](../../tests/Feature/Access/AccessPresentationTest.php)
- [tests/Feature/CRM/Runtime/LeadWorkflowTest.php](../../tests/Feature/CRM/Runtime/LeadWorkflowTest.php)
- [tests/Feature/CRM/Http/LeadDirectoryHttpTest.php](../../tests/Feature/CRM/Http/LeadDirectoryHttpTest.php)
- [tests/Feature/CRM/Http/LeadWorkflowHttpTest.php](../../tests/Feature/CRM/Http/LeadWorkflowHttpTest.php)
- [tests/Feature/CRM/Http/LeadSecurityHttpTest.php](../../tests/Feature/CRM/Http/LeadSecurityHttpTest.php)
- [tests/Feature/CRM/Presentation/CrmOverviewTest.php](../../tests/Feature/CRM/Presentation/CrmOverviewTest.php)
- [tests/Feature/CRM/Presentation/CrmRecipientScaleTest.php](../../tests/Feature/CRM/Presentation/CrmRecipientScaleTest.php)
- [tests/Feature/Employees/EmployeeAcceptanceTest.php](../../tests/Feature/Employees/EmployeeAcceptanceTest.php)
- [tests/Feature/Demo/DemoSafetyTest.php](../../tests/Feature/Demo/DemoSafetyTest.php)
- [tests/Feature/Access/AccessScopeCompletenessTest.php](../../tests/Feature/Access/AccessScopeCompletenessTest.php)
- [tests/Feature/Access/DashboardHomeTest.php](../../tests/Feature/Access/DashboardHomeTest.php)
- [tests/Feature/Access/AccessDirectoryBrowsingTest.php](../../tests/Feature/Access/AccessDirectoryBrowsingTest.php)
- [tests/Feature/Access/ConsolidatedNavigationTest.php](../../tests/Feature/Access/ConsolidatedNavigationTest.php)
- [tests/Feature/Communications/COM01AccessTest.php](../../tests/Feature/Communications/COM01AccessTest.php)
- [tests/Feature/Communications/COM02AccessTest.php](../../tests/Feature/Communications/COM02AccessTest.php)
- [tests/Feature/CRM/CRM01AccessTest.php](../../tests/Feature/CRM/CRM01AccessTest.php)
- [tests/Feature/CRM/CRM02AccessTest.php](../../tests/Feature/CRM/CRM02AccessTest.php)
- [tests/Feature/CRM/Presentation/CrmPagesTest.php](../../tests/Feature/CRM/Presentation/CrmPagesTest.php)
- [tests/Feature/CRM/Presentation/CrmPolishQaTest.php](../../tests/Feature/CRM/Presentation/CrmPolishQaTest.php)
- [tests/Feature/Employees/EMP01AccessTest.php](../../tests/Feature/Employees/EMP01AccessTest.php)
- [tests/Feature/Employees/EMP02AccessTest.php](../../tests/Feature/Employees/EMP02AccessTest.php)
- [tests/Feature/Outbox/OutboxReliabilityTest.php](../../tests/Feature/Outbox/OutboxReliabilityTest.php)
- [tests/Feature/Performance/KPI01AccessTest.php](../../tests/Feature/Performance/KPI01AccessTest.php)
- [tests/Feature/Performance/KPI02AccessTest.php](../../tests/Feature/Performance/KPI02AccessTest.php)
- [tests/Feature/Platform/NFR01AccessTest.php](../../tests/Feature/Platform/NFR01AccessTest.php)
- [tests/Feature/Platform/NFR02AccessTest.php](../../tests/Feature/Platform/NFR02AccessTest.php)
- [tests/Feature/Reporting/DASH01AccessTest.php](../../tests/Feature/Reporting/DASH01AccessTest.php)
- [tests/Feature/Reporting/DASH02AccessTest.php](../../tests/Feature/Reporting/DASH02AccessTest.php)
- [tests/Feature/Tasks/TASK01AccessTest.php](../../tests/Feature/Tasks/TASK01AccessTest.php)
- [tests/Feature/Demo/DemoSeedTest.php](../../tests/Feature/Demo/DemoSeedTest.php)
- [tests/Feature/Demo/DemoTimezoneTest.php](../../tests/Feature/Demo/DemoTimezoneTest.php)
- [tests/Feature/CRM/Presentation/CrmFileBoundaryTest.php](../../tests/Feature/CRM/Presentation/CrmFileBoundaryTest.php) — **[untracked]**
- [tests/Feature/CRM/Presentation/CrmFilesTest.php](../../tests/Feature/CRM/Presentation/CrmFilesTest.php) — **[untracked]**
- [tests/Feature/Sales/QuotationBoundaryTest.php](../../tests/Feature/Sales/QuotationBoundaryTest.php) — **[untracked]**
- [tests/Feature/Sales/QuotationDraftTest.php](../../tests/Feature/Sales/QuotationDraftTest.php) — **[untracked]**
- [tests/Feature/CRM/Presentation/CrmPipelineBoundaryTest.php](../../tests/Feature/CRM/Presentation/CrmPipelineBoundaryTest.php) — **[untracked]**
- [tests/Feature/CRM/Presentation/CrmPipelineTest.php](../../tests/Feature/CRM/Presentation/CrmPipelineTest.php) — **[untracked]**
- [tests/Feature/CRM/Presentation/CrmReferenceWorkspaceTest.php](../../tests/Feature/CRM/Presentation/CrmReferenceWorkspaceTest.php) — **[untracked]**
- [tests/Feature/Tasks/TasksWorkspaceTest.php](../../tests/Feature/Tasks/TasksWorkspaceTest.php) — **[untracked]**
- [tests/Feature/Employees/EmployeeWorkTest.php](../../tests/Feature/Employees/EmployeeWorkTest.php) — **[untracked]**
- [tests/Feature/Help/FoundationTest.php](../../tests/Feature/Help/FoundationTest.php) — **[untracked]**

### Playwright Browser specs (16 files)

- [tests/Browser/cycle-records.spec.ts](../../tests/Browser/cycle-records.spec.ts)
- [tests/Browser/tasks-cycle.spec.ts](../../tests/Browser/tasks-cycle.spec.ts)
- [tests/Browser/dashboard-home.spec.ts](../../tests/Browser/dashboard-home.spec.ts)
- [tests/Browser/access-redesign.spec.ts](../../tests/Browser/access-redesign.spec.ts)
- [tests/Browser/employee-cycle.spec.ts](../../tests/Browser/employee-cycle.spec.ts)
- [tests/Browser/employee-acceptance.spec.ts](../../tests/Browser/employee-acceptance.spec.ts)
- [tests/Browser/crm-cycle.spec.ts](../../tests/Browser/crm-cycle.spec.ts)
- [tests/Browser/crm-polish.spec.ts](../../tests/Browser/crm-polish.spec.ts)
- [tests/Browser/login-design.spec.ts](../../tests/Browser/login-design.spec.ts)
- [tests/Browser/access-cycle.spec.ts](../../tests/Browser/access-cycle.spec.ts)
- [tests/Browser/access-scope.spec.ts](../../tests/Browser/access-scope.spec.ts)
- [tests/Browser/shared-sidebar.spec.ts](../../tests/Browser/shared-sidebar.spec.ts)
- [tests/Browser/design-system.spec.ts](../../tests/Browser/design-system.spec.ts)
- [tests/Browser/crm-reference.spec.ts](../../tests/Browser/crm-reference.spec.ts) — **[untracked]**
- [tests/Browser/employee-work.spec.ts](../../tests/Browser/employee-work.spec.ts) — **[untracked]**
- [tests/Browser/tasks-workspace.spec.ts](../../tests/Browser/tasks-workspace.spec.ts) — **[untracked]**

The ignored `.local/qa-discovery/register-qa-batch.ps1` and `.local/qa-discovery/test-register-qa-batch.ps1` are temporary registration/fixture preparation artifacts, not ERP test suites. Preparation context reports their fixture tests passed; this batch did not rerun or independently verify that report. They are not evidence that any ERP test ran or passed.

## 5. Identity SEC-01 / SEC-02: later discovery priority, not a pass claim

The [`PRD.md`](../product/PRD.md#L113-L119) defines SEC-01 as configurable inherited grants, employee overrides, deny-by-default, and record-level access; SEC-02 requires contact masking across UI, APIs, search, exports, logs, notification previews, and downloads, with explicit audited exceptions. The [`REQUIREMENTS_TRACEABILITY.md`](../product/REQUIREMENTS_TRACEABILITY.md#L124-L125) maps those requirements to A0-SEC-001/002 and A0-SEC-003/004 respectively; current task records show those four earlier slices as `done`. That mapping and historical status do not prove current end-to-end coverage.

| Concern | Exact inspected sources and test/evidence references | What the evidence means / does not mean |
|---|---|---|
| SEC-01 pure record guard | [`ScopedPermissionGuard.php`](../../app/Modules/Identity/Domain/SEC01/ScopedPermissionGuard.php), [`AuthorizeScopedRecord.php`](../../app/Modules/Identity/Application/SEC01/AuthorizeScopedRecord.php), [`SEC01DomainTest.php`](../../tests/Unit/Identity/SEC01DomainTest.php) | Tests source asserts exact grants/overrides, entity/department scope, owner/assignee, unassigned denial, malformed data, and no role-label bypass. This is pure domain behavior, not authentication or persisted permission loading. |
| SEC-01 synthetic diagnostic | [`PolicyDiagnosticAdapter.php`](../../app/Modules/Identity/Infrastructure/SEC01/PolicyDiagnosticAdapter.php), [`policy-diagnostic.php`](../../app/Modules/Identity/Infrastructure/SEC01/policy-diagnostic.php), [`SEC01AccessTest.php`](../../tests/Feature/Identity/SEC01AccessTest.php), [`SEC-01 evidence`](../evidence/SEC-01.md) | The CLI accepts explicit caller-provided synthetic actor/record/grant fixtures and evaluates the pure guard. Evidence states it does not authenticate, query a real record, persist, or exercise a route/session. Historical commands/results in that document were not rerun here. |
| SEC-02 hidden projection | [`ContactMaskingProjector.php`](../../app/Modules/Identity/Domain/SEC02/ContactMaskingProjector.php), [`ProjectContactReadModel.php`](../../app/Modules/Identity/Application/SEC02/ProjectContactReadModel.php), [`ContactDiagnosticAdapter.php`](../../app/Modules/Identity/Infrastructure/SEC02/ContactDiagnosticAdapter.php), [`SEC02DomainTest.php`](../../tests/Unit/Identity/SEC02DomainTest.php), [`SEC02AccessTest.php`](../../tests/Feature/Identity/SEC02AccessTest.php), [`SEC-02 evidence`](../evidence/SEC-02.md) | Unit and feature sources check a fixed hidden DTO, declared channel labels, malformed inputs, serialization, and synthetic contact non-leakage. The feature test evaluates a synthetic guard context separately from a CLI call that receives only channel/contact. No actor, record, authenticated session, or current database grant reaches that CLI. The evidence document explicitly limits this to a diagnostic, not system-wide masking. |
| Persisted session/runtime path | [`AccessControl.php`](../../app/Modules/Identity/Application/SEC04/AccessControl.php#L49-L105), [`RuntimeIdentity.php`](../../app/Modules/Identity/Application/SEC04/RuntimeIdentity.php#L11-L28), [`AccessSecurityTest.php`](../../tests/Feature/Access/AccessSecurityTest.php), [`LeadSecurityTest.php`](../../tests/Feature/CRM/Runtime/LeadSecurityTest.php), [`LeadSecurityHttpTest.php`](../../tests/Feature/CRM/Http/LeadSecurityHttpTest.php) | These source/test references show separate runtime seams using persisted users, session version, current grant reload, scoped CRM calls, and HTTP denial/masking checks. They were read but not executed in this batch; test-specific setup and full coverage remain to be reviewed. |

Inspection found the SEC-02 `ProjectContactReadModel` usage in application source only through its SEC-02 diagnostic adapter, not a demonstrated shared runtime masking service across all PRD surfaces. That is a discovery lead, not a defect verdict: full caller search and per-surface inspection belong in the separately registered Identity QA task. Do not equate synthetic CLI proofs with actual `RuntimeIdentity`/`AccessControl` session behavior, API/search/export behavior, or global masking.

**Next Identity work:** separately register and authorize a focused documentation/discovery task for SEC-01/SEC-02 before work outside this `A0-ARCH-906` scope. No task ID is assigned here. Its evidence should trace PRD requirement → registered task → actual runtime/source caller → exact test assertion → dated execution result, keeping synthetic CLI diagnostics separate from persisted-session/API behavior. Do not inflate scenario or test-case counts from these files.

## 6. Safe execution plan and hazards

**This batch executed no app, test, migration, database, provider, browser, or external-service command.** It did not read `.env`, secrets, or process environment values; it did not install dependencies or inspect live data. Next work in this batch is limited to non-mutating documentation validation.

- `phpunit.xml` declares `APP_ENV=testing`, SQLite `:memory:`, array cache/session, and sync queue defaults; [`config/database.php`](../../config/database.php#L20-L45) defaults the connection to SQLite if no override applies. PHPUnit `<env>` defaults are not forced against inherited process values, and a stale Laravel `APP_CONFIG_CACHE` can retain a different connection/configuration. Therefore these defaults are not proof of the effective runtime DB. No environment was inspected. The [test strategy](../engineering/TEST_STRATEGY.md#L45-L67) calls for an isolated child process, explicitly controlled/restored environment, SQLite memory settings, and a unique config-cache path before safe SQLite suites. Do not migrate or seed unless the disposable target is verified.
- MariaDB helpers are not safe to invoke merely because a command exists. [`adr-mariadb-validation.ps1`](../../scripts/adr-mariadb-validation.ps1#L16-L20) creates a unique `.tools/adr-validation/<run-id>` working/data directory and temporary local MariaDB instance/database, but also writes the shared `.tools/adr-queue-last.json` diagnostic file and output artifacts. Require an owner-confirmed disposable target, no concurrent writer, and reviewed cleanup before any later run. [`vertical-integration.ps1`](../../scripts/vertical-integration.ps1#L7-L14) requires `DB_CONNECTION=mysql` and otherwise skips; it does not provide a safe fallback. Neither helper was executed.
- Playwright runs write and may clear `test-results`/`playwright-report` by default; dedicated configs also use fixed paths such as `test-results/employees` and `.local/windows-qa/*`, while the Tasks workspace config creates a run directory and starts a fixture-seeding web server when loaded. Concurrent runs can collide with or destroy browser evidence. Require an isolated synthetic server/database and unique output directory, after ownership confirmation. No browser config was loaded or run.
- Current concurrent CRM/Sales/dashboard/Tasks/Employees/Help source and test files are dirty/untracked. They were not executed, modified, or used as acceptance evidence.
- The coordinator reports a Windows PowerShell 5.1 default `$PSScriptRoot`-derived `Root` parameter issue for task-loop/registration scripts; explicit `-Root` is required when those scripts are used. This unrelated issue was not reproduced or fixed here. The documentation validator's `Root` parameter was inspected and is supported.

## 7. Durable checkpoint and next eligible work

| Measure | Checkpoint at snapshot |
|---|---|
| Canonical catalog/infrastructure roots | 22 total: 20 `ModuleCatalog` names plus `Shared` and `Platform`; **0/22 fully discovered**. All remain pending full module discovery. |
| Actual filesystem roots | 23; the extra `Help` root is concurrent, untracked, and not in `ModuleCatalog`. |
| Requirements extracted/mapped in this batch | `0`; denominator unknown. SEC-01/02 are pointers for a later task, not decomposed here. |
| Scenarios extracted | `0`; denominator unknown. |
| Test cases designed/authored | `0`; denominator unknown. No scenario/test-case inflation. |
| Automated requirement coverage mapped | `0`; denominator unknown. The 125 file count is not coverage. |
| ERP tests executed in this batch | `0`; applicable execution denominator unknown. |
| Defects created/triaged in this batch | `0`; repository defect baseline and denominator unknown. |
| Independent reviews completed | `0`; review pending, denominator unknown. |
| Acceptance / integration / production claims | None. |

**Next actions, under canonical lifecycle only:**

1. The existing coordinator retains `A0-ARCH-906`; after independent review, use the documented task-loop lifecycle to record only evidence actually completed. This artifact does not transition task state or release/renew the lease.
2. Separately register/authorize Identity SEC-01/SEC-02 discovery before investigating them as a work slice outside this batch. A new task insertion requires the coordinator's explicit authorized procedure; `SPECKIT_INTEGRATION.md` states task-loop has no Register command. Do not invent an ID, specialist owner, or ready status.
3. Queue full module discovery only through separately scoped, lifecycle-valid tasks. Do not execute currently leased modules' tests or change their code as part of this artifact.
4. Preserve Founder-Led gates: documentation discovery closes no `OPEN` or ADR, grants no `DEVELOPMENT` exception, and does not qualify `INTEGRATION` or `PRODUCTION`. Do not alter the gate policy, backlog, task status, or approvals here.

## 8. Verification and handoff record

- Skills actually used during preparation: the installed [Ponytail skill](../../.agents/skills/ponytail/SKILL.md). Laravel source inspection also requires `laravel-best-practices`; it was not loaded during preparation, and this omission is not retroactively claimed as compliance. The independent reviewer loaded `ponytail` and `laravel-best-practices`; see the [review and finding](handoffs/batch-001-independent-review.md). No recursive delegation occurred, and all module queue ownership remains with `erp-qa-coordinator`.
- Files produced in this scope: this `docs/qa/system-inventory.md` only. No JSON index was useful for this first batch.
- Assertions/tests run: none. The `.local/qa-discovery` fixture preparation report is not an ERP test result and was not independently rerun.
- Documentation checks: pending at artifact drafting time; record exact commands and outputs in the coordinator handoff after they are run.
- Review: independent review pending. No integration acceptance, commit, newly registered task, or newly ready task is claimed.

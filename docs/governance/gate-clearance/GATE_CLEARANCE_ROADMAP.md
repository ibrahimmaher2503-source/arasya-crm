# Gate-clearance roadmap — recommendations only

> Snapshot from fresh read-only `Status`/`Ready`: 239 tasks (`24 done`, `86 blocked`, `129 planned`), zero active leases, no pending transaction, and only `A0-ARCH-001` `currently_ready`. This is not a durable status guarantee. This document does not mutate the backlog, DAG, gates, or decision statuses and does not authorize task execution.

## Priority 0 — review design without circular runtime proof

1. **ADR-001 owner review (design scope).** Engineering + Product can review modular-monolith boundaries, Laravel 13/PHP 8.4 as documented direction, and alternatives. A pure architecture decision need not wait for a production database/runtime proof. The local `A0-ARCH-901` authorization remains narrowly local and does not accept ADR-001/002/008. Direct task `A0-CORE-900` has no unmet task prerequisite and no additional decision dependency; ADR-001 itself is its sole direct gate. Approval could make it dependency-eligible in theory, but it remains stored `blocked` until an authorized coordinator separately reassesses. No task becomes `ready` from this document.
2. **ADR-002 + ADR-003 joint design gate.** The current `A0-ARCH-902` edge requires both decisions; approving ADR-002 alone yields no immediate eligibility. ADR-003 has five direct roots: `A0-ARCH-902` has no unfinished task prerequisite but additionally requires ADR-002; the other four (`A0-ARCH-003/004`, `A0-CORE-007/008`) each have unfinished task prerequisites. Neither decision alone yields an immediately eligible task. Resolve the model/queue choices at design level with Engineering/Finance/IT, and state a bounded compatibility evidence plan without demanding production evidence to approve a pure design choice.
3. **Bounded compatibility evidence, only after a separate written authorization.** Request from IT/Engineering the exact candidate MariaDB version, PDO driver, transactional engine and isolation settings; explicit permission to install a missing local tool if needed; and an isolated disposable database/data location with no production network, real credentials, or business data. Synthetic setup only, no business-implementation code and no live provider call. The eight existing ADR-002 acceptance IDs remain `NOT RUN`: `ADR002-RACE-RESERVE` (two concurrent qty-4 reserves against available 4: one succeeds, final available 0, replay idempotent); `ADR002-RACE-ISSUE` (two qty-4 issues against reserved 5: one movement/effect); `ADR002-RACE-PERIOD-CLOSE` (post-before-close visible; close-before-post rejects without journal/outbox); `ADR002-MONEY-DECIMAL` (exact round-trip/overflow/scale against owner-signed precision; no invented rounding); `ADR002-UNIQUE-SCOPE-FK` (one idempotent row; cross-entity FK rejected); `ADR002-IDENTIFIER-COMPAT` (UUIDv7/ULID Laravel binding/serialization/FK round-trip, names within the ADR limit); `ADR002-MIGRATION-FORWARD` (fresh and prior-state forward migration/recovery with no data loss); `ADR002-BACKUP-RESTORE` (restore only into isolated target and reconcile keys/constraints/ledger/stock/outbox against owner-defined RPO/RTO). Queue/cache follow-up is separately bounded to ADR-003 scenarios: enqueue/publish races and duplicate/out-of-order events; crash before/after commit and publish-before-inbox; worker death/outage/drain; bounded retry/DLQ/replay with the same `event_id` and one consumer effect; reauthorization after revoke; scope/masking on search/count/export; zero external side effects. Pass criteria, target, allowed setup and location must be agreed before running; no deletion/cleanup action without separate explicit permission. These are design acceptance criteria, not evidence obtained here.

## Available local validation tools — inventory only, not fresh test results

- Read-only availability check at `2026-10-16T05:31:04.8067074+03:00`: `Get-Command php` found `C:\Users\N\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe`; `Get-Command composer` found no command. `.tools/composer.phar`, `vendor/bin/phpunit`, `vendor/bin/pint`, `vendor/bin/phpstan`, and `phpunit.xml` exist. This proves availability only. The local smoke/test setup uses SQLite `:memory:`; it cannot prove MariaDB locks, concurrency, production backups or provider behavior.
- Existing repository-safe commands are `& './scripts/validate-docs.ps1'`, `& './scripts/test-task-loop.ps1'`, `& './scripts/test-validate-docs.ps1'`, `& './scripts/task-loop.ps1' -Action Status`, and `& './scripts/task-loop.ps1' -Action Ready`. Status/Ready are read-only. The older results recorded in local authorization/evidence documents are historical reports, not fresh results from this correction. No application tests, Pint, PHPStan, Composer validation, DB checks or migrations were run here.
- Local application commands available for later authorized use include `php artisan test`, `php vendor/bin/phpstan analyse --no-progress`, and `php vendor/bin/pint --test`; their presence does not authorize running them in this docs-only scope.

## Action classification — current authority boundary

| Category | Current state | Permission/next authority |
|---|---|---|
| Documentation | This six-file correction is documentation-only. | Checks for docs/read-only status are within scope; no task/approval lifecycle write. |
| Disposable MariaDB/queue validation | Proposed criteria only; none run. | Written IT/Engineering authorization must specify target version/driver/engine, install/connect permission, isolated disposable location/setup and explicit pass criteria first. |
| `A0-ARCH-001` | Read-only Ready report says ready; no implementation claimed. | Separate app-write/test authorization and task-lifecycle authority required before any implementation or claim. |
| Decision-gated tasks | Source statuses/edges remain as recorded; design eligibility is not readiness. | Owner decision and required source/approval evidence, then authorized coordinator re-evaluation; never infer an unblock. |
| Business/Legal/Operations policy | Rates, thresholds, accounting, inventory, retention, channels and acceptance inputs remain open as shown in OPEN matrix. | Corresponding named role owners provide and review evidence; no default or agent recommendation substitutes for sign-off. |

## Priority 1 — identity/security before any real-user workflow

- The initial A0 shell and security chain remains as recorded: ADR-001 is a direct dependency for `A0-CORE-900`; then `A0-SEC-002/003/004`; approval/SoD work `A0-SEC-005..008` depends on OPEN-02 and on unfinished predecessors. The direct-dependency matrix shows no immediate OPEN-02 task eligibility because every direct task has unmet task predecessors (and PAY tasks also need OPEN-03).
- Do not expose real-user routes before scoped identity/authentication, authorization, masking, audit and SoD are implemented and accepted. Current `SEC-01` in-memory guard is not auth, query scoping, field masking, or business service. Never infer production security from the config's MariaDB/database queue/cache entries.
- `A0-ARCH-001` is independently ready in the captured report, but it is an implementation task and outside this docs-only authorization. It is not claimed done or started.

## Priority 2 — evidence briefs and design ambiguity

- All eight ADR packets and twelve OPEN evidence briefs are documented in [ADR packets](ADR_APPROVAL_PACKETS.md) and [OPEN gaps](OPEN_GAP_MATRIX.md). No task state or approval record is changed by completing documentation. The OPEN briefs already exist; the new matrix consolidates evidence/readiness, not task completion.
- Finance/inventory/project/production chain remains in the exact source DAG: OPEN-03 is prerequisite evidence for Finance paths; OPEN-04 is relevant to inventory and manufacturing; OPEN-05/12 gate project acceptance; no rates, tax/account mappings, valuation, recognition, units, legal retention terms, or warranty duration should be invented. Use source-linked reversal/adjustment and preserve posted journals/movements.
- OPEN-10 design/owner review can clarify record categories and legal inputs without a production database. `A0-DOC-001` is the one direct task with no unmet dependency/other decision edge in the current graph; only a coordinator with lifecycle authority could separately reconsider its saved status. Do not treat the theoretical count as current readiness.

## Priority 3 — channels, AI, mobile and operations

- ADR-006 + OPEN-07 remain joint gates. Keep email/SMS PRD MUST requirements explicit; current proposals for fake-only/deferment are unapproved change requests. `AT-11` tests failure semantics and does not alone require paid sends. Fake connector work, if separately approved and authorized, cannot establish provider compatibility or justify live egress.
- ADR-007 + OPEN-09/10 do not authorize provider use. AI audit advisory, action assistant, and proactive alerts are distinct; synthetic foundation is not PRD practical-AI completion, `AT-10`, or `A5-GATE-001`. Any future action/proactive task split requires owner change control and coordinator DAG update first; none is proposed as a hidden edge here.
- ADR-005/OPEN-08 need an owner-confirmed device matrix and tests; ADR-008/OPEN-11 need capacity, numeric SLO/RPO/RTO and recovery evidence before production deployment—not before design-only review. No live device/provider/VPS check in this sprint.

## Owner action queue — choices, authority and missing evidence

| Priority | Decision | Decision owner(s) | Next owner input/evidence | Scope boundary |
|---:|---|---|---|---|
| 1 | ADR-001 | Engineering + Product | Confirm modular ownership boundaries, rationale/alternatives, and design-only acceptance wording. | Design review; runtime/DB proof is not a prerequisite for pure architecture acceptance. |
| 2 | ADR-002 | Engineering + Finance; IT/Operations for target/recovery | Select/reject/defer MariaDB and identifier direction; supply exact target and fixtures for compatibility; Finance owns signed decimal expectations. | Keep pure engine choice distinct from authorized disposable compatibility testing; no SQLite-to-production inference. |
| 3 | ADR-003 | Engineering + IT | Decide database queue/cache and scoped SQL search direction vs alternatives; provide workload and security/recovery expectations. | Design choice need not await production proof; any operational claim needs bounded tests. |
| 4 | ADR-004 | IT + Security | Resolve `LOCAL` vs PRD encrypted private object-storage requirement; define encryption/key ownership/access/restore boundary. | No production/file-store access until separately authorized. |
| 5 | ADR-006 / OPEN-07 | Management + IT; Security/Legal/Finance as applicable | Specify internal conversation/push boundary, corporate email/SMS operational delivery, WhatsApp evaluation/live decision, Telegram scope, account ownership, consent, costs and retention. | Any fake-only or live deferral that changes PRD MUST requires explicit Product change control; egress is separately authorized. |
| 6 | ADR-007 / OPEN-09 | Executive + Security; Legal as required | Choose provider/data-region/data classes/DPA/budget/evaluation thresholds or defer, with privacy/security review. | Evaluation direction is not live integration or data-egress permission. |
| 7 | ADR-005 / OPEN-08 | Engineering | Name supported devices/browser matrix and acceptance evidence for RTL, camera/upload/signature, reconnect and revoke. | No offline financial/stock/approval writes; no deployment in this review. |
| 8 | ADR-008 / OPEN-11 | IT + Engineering | Provide workload/resources, supported runtime/DB target, numeric SLO/RPO/RTO, backup/restore and recovery/runbook evidence. | No VPS/production access or deployment authorization in this sprint. |
| 9 | OPEN-01 | Executive + Finance | Sign eligibility trigger, actual rates, categories/splits, refund/reversal/rounding fixtures and authority; accountant/legal review as required. | No guessed rates, auto-post or payroll deduction. |
| 10 | OPEN-02 | Executive | Sign exact process/amount basis, role/delegate/SoD limits, calendar, material-change list, break-glass and 24-business-hour action. | No automatic approval, unauthorized fallback or invariant bypass. |
| 11 | OPEN-03 | Finance + accountant | Provide COA, tax/fiscal/partner mappings, opening balances and signed journal/reconciliation fixtures. | No invented account/tax/FX/rounding rules. |
| 12 | OPEN-04 | Warehouse + Finance | Decide costing, units/conversions, batches/negative stock and signed movement/count fixtures. | No invented valuation; preserve movement ledger. |
| 13 | OPEN-05 | Finance + Operations | Sign recognition milestones, direct/overhead/WIP allocation and example calculations. | No cash-as-revenue or silent rewrite of posted entries. |
| 14 | OPEN-06 | Operations | Choose output-only vs bounded consumption/output trace with units, transformations and evidence. | No MRP/BOM/routing or unapproved task split. |
| 15 | OPEN-10 | Legal + HR/Finance | Supply record-category legal basis, retention schedule, consent/access grants, hold/deletion/backup rules and authority. | No guessed statute/period or destructive deletion. |
| 16 | OPEN-12 | Operations + Quality | Sign work-type checklists, partial/rework rules, warranty terms and sign-off authority. | No assumed warranty duration or fabricated acceptance. |

## Future formal approval record fields — template only, not a record

LOOP verifier requires the source status and separate record/evidence. Future OPEN record shape uses `Decision: Approved`; future ADR record uses `Decision: Accepted` and must also set the ADR file and index to `Accepted`. The following are field labels only; no values are filled and no `decision-approvals/` file is created:

```text
Future OPEN record (only after owner approval):
Decision: Approved
Owner: <authorized owner/role>
Approved on: yyyy-MM-dd
Approved option: <exact selected option>
Evidence: docs/.../evidence-file.md
Affected tasks: <exact IDs>
Tests: <exact commands and observed results>
Review: <reviewer and review evidence>

Future ADR record (only after owner acceptance):
Decision: Accepted
Owner: <authorized owner/role>
Approved on: yyyy-MM-dd
Approved option: <exact selected option>
Evidence: docs/.../evidence-file.md
Affected tasks: <exact IDs>
Tests: <exact commands and observed results>
Review: <reviewer and review evidence>
```

Each record needs an existing evidence file in-repository. `APPROVE/REJECT/DEFER` in a review packet is not this record; approvals remain owner actions and do not grant implementation/lifecycle/provider/production permission.

## Decision and implementation authorization stay separate

Formal ADR acceptance requires the ADR index and ADR file both `Accepted` plus the verifier's separate approval record and evidence. OPEN approval requires `Approved` in the source row plus required approval record/evidence. Change Control separately governs PRD scope changes. Even those approvals do not grant implementation/test, lifecycle, provider-egress, credentials, infrastructure, migration, or production permission; obtain that authority separately. No approvals exist in this roadmap and no status/gate was changed.

## What was completed here / not authorized next

- Completed here: documentation snapshots, evidence gaps, exact dependency map, and proposed sequencing only.
- Not done/authorized: any `Claim`, `Review`, `Complete`, `Release`, `RefreshBacklog`, task/DAG edit, application/test/config/migration/dependency change, external call, credentials/VPS/production access, or install/run of the disposable validation plan.
- Next smallest step: owners review the unsigned ADR-001 design packet and answer its open rationale/ownership questions; separately, obtain a written scoped sandbox-validation authorization if MariaDB compatibility evidence is wanted.

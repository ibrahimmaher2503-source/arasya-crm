# Sales S1 foundations — bounded development checkpoint

Date: 2026-10-19, Africa/Cairo. Coordinator: `sales-s1-20261019`.

## Authorization and ownership

The owner explicitly instructed this chat to take over the stopped CRM/Sales work: “اسحبها منه وكمل انت هنا”. The receiving coordinator inspected the previous idle/failed chat, preserved hashes plus working/index patches in the primary checkout's ignored `.local/sales-s1-20261019/takeover-baseline`, then used task-loop Release on behalf of the human owner and Claim as the receiving owner. This is not a former worker approval. A1-CRM-020 remains a partial development task; no DONE or integration/production approval is asserted. Existing unrelated leases/source/index remain intact.

Task-specific OPEN-02 development authorization was already recorded for A1-CRM-020 and A2-SALE-011. OPEN-02 stays OPEN. Approval, sending, fulfillment, stock release, revenue recognition, production and real data remain disabled/outside this increment.

## S1.1 delivered behavior

- Existing `POST /crm/pipeline` accepts a UUID submission key; same actor/company/key and equivalent validated data return the existing current opportunity/version without duplicate audit or history. Changed payload returns409; malformed keys422. Existing callers without keys remain compatible, and therefore have no retry deduplication guarantee. The new create form always supplies a key.
- Existing `PATCH /crm/pipeline/{id}` retains optimistic versions. Create/edit append safe opportunity snapshots atomically with the existing audit inside the existing company lock. Current permission, assignment scope and archived-lead state are checked again on replay.
- `GET /crm/pipeline?selected={id}&history_page={n}` renders authorized history newest first,25 rows per page, using existing detail-panel/table/pagination components. Keys/hashes and contact data are excluded from all pipeline projections and snapshots. Existing history is not fabricated/backfilled.
- A forward migration adds scoped unique submission keys and company-scoped history foreign keys/version uniqueness. Its development rollback deletes new history only and preserves existing opportunity rows. No shared preview database was migrated.
- The pipeline's direct sections can shrink inside the shell; tablet/mobile tables scroll within their own region. No new component API, palette, spacing system or dependency.

Reuse: `LeadRuntime::withActor/withReadActor/visibleLeads/recordAudit`, SEC04 AccessControl, existing LeadOpportunities/LeadDirectory controller contract, installed Brick decimal arithmetic, `layouts.shell`, semantic tokens and shared UI components. Customer identity remains the canonical lead.customer_id→crm_customers path; no duplicate Customer service was introduced.

## TDD and verification

- Native Windows MariaDB binary verified11.4.13. Every run created its own random loopback port, data directory/schema/account, verified source/database identity before migrations, and stopped/removed only its owned runtime. Database-mutating runs were serialized.
- Existing baseline:12 tests/114 assertions PASS.
- New feature RED:7 behavioral failures plus2 missing-history-table errors before implementation. After implementation:21 tests/232 assertions PASS.
- Full current-source backend checkpoint:619 tests/38,280 assertions,1 SQLite-only skip, no failures. This run preceded the additive history presentation; final relevant presentation/Sales run after that change:66 tests/994 assertions PASS.
- History presentation RED:1 test/31 assertions, fails because the history heading is absent. GREEN includes rendered original snapshot and latest-version replay assertions.
- Configured full PHPStan, scoped Pint and `npm.cmd run check:design-system` PASS. Clean delivery branch repeats focused/native and static/design verification; final output is retained in its ignored `.local/sales-s1`.
- Playwright PASS1 scenario across1672×941,768×1024,390×844; Arabic RTL/English LTR; light/dark; keyed duplicate create, edit/reload/history, Escape dismissal and contained horizontal scrolling. Twelve actual screenshots retained in source worktree `.local/ui-closure-http-e13a021da00746d2802eadcd2aa0c346`. Coordinator viewed desktop Arabic dark and mobile English light. This establishes the exercised behavior, not pixel parity or full Sales UI acceptance.
- Browser setup failures were diagnosed rather than counted as passes: unrelated suite discovery guards, missing isolated view/session directories, then actual tablet overflow. Only the final successful run is acceptance evidence. No external sending, hostedCI, SonarQube or production test.

## Workers and review

All workers explicitly requested `gpt-6-luna` / `xhigh`, without fallback or recursive delegation:

- `sales_s1_requirements`: read-only discovery, then exclusive ownership of `tests/Feature/CRM/Presentation/OpportunitySubmissionHistoryTest.php`; no runtime/DB execution.
- `sales_s1_browser`: exclusive append to `tests/Browser/crm-reference.spec.ts`; parent later added screenshot scroll positioning; no runtime/DB execution.
- `sales_s1_security_review`: read-only independent source/security review; found missing rendered history and missing replay-after-edit coverage. Both resolved; re-review found no blocking issue. Coordinator owns implementation, migration, integration and all test execution.

Skills actually used: `.agents/skills/{ponytail,laravel-best-practices,laravel-tdd}/SKILL.md`, relevant Laravel security/migrations/validation/query rules, and installed `C:/Users/N/.codex/skills/{frontend-design,dispatching-parallel-agents,using-git-worktrees,test-driven-development,systematic-debugging,requesting-code-review}/SKILL.md`. `verification-before-completion` was not found in the installed skill discovery; no duplicate was installed. Source/runtime checks above provide the verification evidence.

## S1.2 proposed transition foundations

The owner explicitly supplied the sequence as a PROPOSED development workflow,
not an approved commercial policy. `OpportunityTransitions` centralizes stage
validation: creation starts at Lead; open transitions move one adjacent stage;
backward movement requires a reason. Generic edits cannot change stage or edit
closed records. Existing records are not restaged by migration.

`POST /crm/pipeline/{id}/transition` enforces existing crm.read/crm.write and
record visibility, an active linked lead, an optimistic version and a required
UUID retry key. Replaying an identical request returns the current authorized
record, including later edits, without extra history/audit. A changed request
under the same key or a stale fresh request returns409. Transition snapshots,
version changes and audit append atomically. Reasons are encrypted in the audit;
public history exposes only whether a reason was provided. Retry keys/hashes
remain excluded from presentation.

The separate reopen endpoint defaults to403 for every role. Both endpoints are
available only in the existing testing + synthetic Sales development boundary.
Closing Won/Lost also returns403; the nullable Won/Lost schema field is a
foundation, not an enabled close operation. No quotation, contract, stock or
financial side effect is attached.

Tests were authored independently before implementation:9 transition cases,
8 RED failures, then9/115 assertions GREEN. The combined CRM presentation/Sales
checkpoint passed75 tests/1,109 assertions before the final additive replay test.
Configured PHPStan, scoped Pint and design-system verification passed. Final
delivery-branch backend verification, including replay after a later edit, passed
558 tests/37,715 assertions with1 SQLite-only skip and no failures. This is the
isolated committed baseline plus S1 changes, distinct from the earlier619-test
current-source checkpoint containing unrelated concurrent development. Final
Playwright passed both scenarios, including real form transitions, history after
reload, reasoned backwards movement, jump refusal and closure/reopen refusal.
Screenshots/runtime evidence are retained at
`.local/ui-closure-http-77eabe98331d4f4191866b843b2f9869` in the delivery worktree.
This does not establish screenshot parity or full S1 acceptance.

Exclusive worker ownership: `sales_transition_guard` owned only the pure Sales
transition service; `sales_s1_requirements` only the transition test file;
`sales_s1_browser` only the existing CRM browser test file. All explicitly used
gpt-6-luna/xhigh. No worker ran database resets. The independent security reviewer
found no blocking behavior/security issue and requested the decisive
replay-after-later-edit assertion, which was added. The coordinator owns routes,
runtime, migration, views, final verification and integration.

Before enabling closure/reopening the owner must approve who may Close Won,
Close Lost and Reopen, their record/company scope, required prerequisites and
evidence/reasons, and any approval/separation-of-duty requirements. No title or
existing administrator role implies these powers. OPEN-02 remains unresolved.

## Remaining S1 scope and next increment

S1 is NOT complete. Direct Customer-source opportunities, approved owner/site/work-type context and complete S1 integration still require implementation/contract verification. S2 has not started; A2-SALE-001's existing A0-SEC-006 dependency is unchanged. No newly ready tasks or policy approvals are claimed. Delivery is isolated on `codex/sales-s1-development`; the primary dirty checkout and shared preview database have not received these source/migration changes. S1.1 is commit `06728fef84d03c6fb867638160a1b4224d7ee544`.

AI tools: no tool registry entry or execution capability was exposed. The new opportunity mutations/history remain unavailable to AI execution; the shared AI coverage ledger is separately leased and was preserved.

## Owner-requested finalization — test design

Continue `codex/sales-s1-development` from06728fe/cd510cf. The authoritative
coordinator remains the primary checkout; this delivery branch's older backlog
is not the live lease ledger. Read-only independent security and functional
review use gpt-6-luna/xhigh. Independent authors exclusively own the existing
OpportunitySubmissionHistoryTest.php (atomic rollback/retry) and CrmPipelineTest.php
(remaining acceptance). The parent serializes all disposable MariaDB/browser runs.

Policy-independent acceptance gaps: optional PRD work type and bounded free-text
requirements; Customer-source creation reusing canonical converted CRM identity;
server-paginated opportunity directory on the existing pipeline route. Existing
lead assignee/branch, handover and activity links remain the ownership source.
No site contract or new customer/owner permission is inferred. Customer-only
creation requires exactly one currently visible active linked lead; ambiguous
links conflict and invisible/unlinked customers remain unavailable. Requirements
are encrypted at rest and excluded from public card/history projections.

Implementation reaches LeadOpportunities, its existing controller, create/edit
form, pipeline view, existing browser tests/catalog and one additive nullable
work-context migration140000, registered under the primary coordinator lock.
No new route/service/framework is needed. Legacy fields and retry hashes remain
compatible when optional context is omitted. Test RED before implementation;
verify fresh MariaDB migrations, positive/negative scoped sources, retry payload
conflicts, bounded pagination, rollback on final audit failure and actual UI.
Close Won/Lost/Reopen remain fail-closed for all roles throughout.

## Resume verification and completion — 2026-10-19

This section supersedes the earlier remaining-implementation paragraph for the
bounded finalization scope. The primary checkout remains the authoritative
coordinator. Its A1-CRM-020 lease was renewed using task-loop as
`sales-s1-20261019`; unrelated source, leases and shared preview data were preserved.

### Verified previous work and later unfinished work

- Both reported commits exist on `codex/sales-s1-development`: `06728fe`
  (submission deduplication/history) and `cd510cf` (proposed guarded transitions).
  Neither is in local main or any existing remote-tracking branch.
- The stored full-run evidence actually reports **558 total tests, 37,715
  assertions, one skipped**, rather than 558 executed passes plus a skip.
  The two stored browser scenarios match the implemented paths. This historical
  evidence is separate from the fresh reruns below.
- A later session had added uncommitted tests in CrmPipelineTest,
  OpportunitySubmissionHistoryTest and OpportunityTransitionTest plus a test
  design, but no finalization implementation or newer delivery commit existed.
  Those tests were preserved and resumed, not rewritten or duplicated.
- Fresh RED on those tests: 38 tests / 413 assertions / 6 failures. Five exposed
  the recorded feature gaps; the sixth exposed loss of encrypted long Unicode
  transition reasons due to a 4,096-byte audit metadata limit.

### Completed here

- Canonical customer-source creation through exactly one visible, active lead;
  no customer duplication, new authority or invented site contract. Source
  picker counts and options exclude unavailable/ambiguous customers, and the
  write rechecks the same restriction under the existing company lock.
- Nullable work type and encrypted requirements; compatibility for omitted
  fields on older callers and submission hashes. Context-bearing retry payloads
  use a keyed HMAC so a leaked database cannot test private text guesses without
  the application key. Requirements never enter public rows, API or history.
- Bounded opportunity directory, stable order and preserved filters; existing
  fields/select/table/pagination/dialog components and semantic tokens reused.
- Audit encryption capacity covers 1,000 four-byte Unicode characters without
  dropping the reason in either audit ledger. Existing CRM error middleware
  prevents validation input flashing; an exact requirements regression asserts
  sanitized HTML 422 and no `_old_input` session value.
- Adopted independent MariaDB failure-injection tests prove create/edit/transition
  rollback and retry after failure of the final audit insert. These install
  triggers only in disposable MariaDB; never run them on shared data.
- The forward migration adds nullable context only. Its development rollback
  drops those new columns and therefore discards their contents; it does not
  delete opportunities. Production migration/rollback is not authorized here.

### Review and file ownership

Both workers were explicitly dispatched with `gpt-6-luna` / `xhigh`, no fallback
and no recursive delegation. `s1_audit` owned read-only requirements/security
review; `s1_ui_review` owned read-only UI/test review. They owned no write paths
or runtime. The coordinator exclusively owned the delivery-worktree source,
migration, Blade, tests, catalog, evidence, runtime execution and lifecycle.
Concurrent test writes were not delegated because the inherited test files and
browser fixture/runtime were already shared by the resumed increment. Independent
review was delegated instead; all database-mutating runs were serialized.

Review resolutions: keyed private-context hash, exclude ambiguous picker entries,
customer-specific labels, current-state navigation, actual page-two browser
coverage and both private-text sentinels. The reviewer withdrew a proposed
session-flash issue after tracing the existing global CrmHttpBoundary; regression
coverage was added. Final backend review found no remaining scoped privacy or
authorization blocker. No broader production/security acceptance is implied.

Skills used: `.agents/skills/ponytail/SKILL.md`,
`.agents/skills/laravel-best-practices/SKILL.md` and its validation/security/migration
rules; installed `C:/Users/N/.codex/skills/frontend-design/SKILL.md`,
`dispatching-parallel-agents/SKILL.md`, `test-driven-development/SKILL.md`,
`systematic-debugging/SKILL.md`, `using-git-worktrees/SKILL.md` and
`requesting-code-review/SKILL.md`. Existing sales-s1-verified worktree was reused.
No installed verification-before-completion file was found; no skill installed.

### Gap and acceptance classification

| Class | Result |
| --- | --- |
| DONE, verified locally | scoped opportunity create/edit; UUID deduplication; immutable safe history; optimistic version; adjacent proposed transitions/backward reasons; atomic audit rollback; canonical customer source; work context; paginated directory; fail-closed close/reopen |
| Implemented but unverified at acceptance boundary | hosted CI, combined main integration, production, full six-screen reference pixel parity; browser checks establish exercised behavior only |
| Missing implementation/contract | standalone site record and dedicated opportunity owner/site assignment contract; no policy or scope was invented to implement these |
| Owner-policy blocked | Close Won, Close Lost, Reopen authority/prerequisites/reasons/SoD; OPEN-02 remains OPEN |
| Integration pending | isolated delivery branch remains unmerged/unpushed; broader A1-CRM-020 six-screen acceptance is not complete |

S1 remains **PARTIAL for full acceptance**. Its safe bounded development increment
is complete subject to the final checks recorded below. S2 Quotation Draft Engine
cannot start under A2-SALE-001 because A0-SEC-006 remains blocked by OPEN-02.
A2-SALE-011 already delivered a synthetic draft/PDF foundation; do not rebuild it
or treat its bounded authorization as S2 authority. No newly ready tasks are claimed.
AI execution remains restricted/disabled, with no new tool exposed. The shared AI
coverage ledger is separately leased and was preserved; no execution readiness is
inferred from these browser/API capabilities.

## Owner-directed local source integration — 2026-10-19

Owner explicitly requested integration into the final copy. Delivery commit
`6eb48ac` contains the bounded S1 finalization after full disposable MariaDB
568 tests/37,821 assertions/one documented SQLite-only skip, three browser
scenarios and configured PHPStan. The SQLite-specific test separately passed.

The coordinator integrated18 owned paths into C:/projects/new_arasya-crm on main,
with byte backups in ignored .local/sales-s1-20261019/integration-4210e8f5e11a478d96b821ce5aebd637.
The concurrent design-system catalog append was preserved. Git index and unrelated
working files were preserved; no blanket merge/reset or push was used. This is
source integration, not a main merge commit or completion of the full task.

Combined primary-source verification:85 tests/1,222 assertions PASS in a fresh
owned disposable MariaDB, PHP syntax PASS, design-system checks PASS, full docs
validator PASS (289 tasks/124 PRD IDs/1,063 links). Source checks did not migrate
shared data. The primary environment remains local; Sales endpoints require the
existing testing plus synthetic flag boundary. No production/shared-preview
activation, policy approval or environment bypass occurred. Close Won/Lost/Reopen
remain fail-closed. Full A1-CRM-020 acceptance remains partial/in_progress.

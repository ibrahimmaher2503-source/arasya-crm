# Cycle03 checkpoint — CRM013 completed; CRM014 candidate blocked

[Owner authorization](cycle03-authorization.md). Implementation in progress;
no Cycle03 VERIFIED and no consolidated run yet. Coordinator cycle03-coordinator.
Current lifecycle: CRM013 done; CRM014 saved blocked with retained own lease
141081602c3943529378d86bbb6d6eb6, expires2026-10-17T12:05:08Z; recovery=none.

## Worker contract (before implementation)

Application class `App\Modules\CRM\Application\Runtime\LeadDirectory` uses real
AccessControl Request sessions. Methods (all return arrays):

- listing(Request, array filters=[]): list of masked leads; filters q/stage/archived.
- find(Request, string id): masked detail with activity/history/pending transfer.
- save(Request, array data, ?string id=null): create or versioned manager edit.
- assign(Request, string id, array data): manager initial assignment only;
  data assignee_id/version. Existing owner changes use acknowledged handover.
- archive(Request, string id, array data): manager, data version.
- reveal(Request, string id, array data): data reason; scoped contact.reveal,
  audited explicit detail only; never list contacts even with reveal permission.
- taxonomy(Request): company versioned stage/outcome list; saveTaxonomy(Request,
  array data): kind stage/outcome, label, optional replaces_id; immutable values.
- activity(Request, string id, array data): kind call/message/meeting/visit/note/
  quotation, outcome_id, next_step, note, occurred_at, next_follow_up nullable,
  version. Real TaskCycle create for current actor follow-up, safe generic title;
  no manager impersonation or Tasks-table writes. Reject unsupported assignment.
- requestTransfer(Request, string id, array data): recipient_id/context/version.
- respondTransfer(Request, string transferId, array data): decision accept/reject,
  version (lead version); recipient only. inbox(Request): scoped pending requests,
  minimum masked lead identity; recipient cannot read full lead before accept.
- convert(Request, string id, array data): version, returns scoped lead with
  customer_id; repeat conversion returns same customer; no fabricated downstream.

Permissions: crm.read, crm.manage, crm.write, crm.assign, crm.transfer,
crm.convert, crm.taxonomy, contact.reveal. manage allows own branch/dept library,
ordinary sales only current assignee; company isolation always, no admin bypass
of contact grant. Manager creates in own branch/department. assignee/recipient
must be active persisted same company/branch/department, crm.read+crm.write.
Persisted grants/session reloaded under company write lock. Unknown fields denied.
Mutations expected version except create/taxonomy; double accept conflicts.

Tables: crm_leads (id/company_id/branch_id/department_id/name/organization/source,
encrypted contacts JSON, duplicate_key, consent boolean, preferences JSON, notes,
stage_id nullable, assignee_id nullable, customer_id nullable, archived boolean,
version, timestamps); crm_taxonomy(id/company_id/kind/label/replaces_id nullable,
created_at); crm_activities(id/company_id/lead_id/actor_id/kind/outcome_id,
outcome_label snapshot/note/next_step/occurred_at/next_follow_up/task_id nullable,
created_at); crm_transfers(id/company_id/lead_id/sender_id/recipient_id/context,
state pending/accepted/rejected, requested_version, created_at/responded_at);
crm_customers(id/company_id/branch_id/department_id/name/organization,
duplicate_key, created_at); crm_audit(id/company_id/lead_id nullable/actor_id/
action/metadata JSON/created_at). ULIDs, scoped FKs and unique duplicate keys.
Contacts never in generic history, list, Tasks title, audit or errors. User free
text is retained as content; do not claim semantic contact redaction.

Existing LeadRecord/LeadLifecycle, LeadActivity/RecordLeadActivity and
LeadHandover/HandoverLead reused where compatible. ConversionIdentity requires
unimplemented downstream IDs: do not invent them to satisfy its constructor.
Document attachments/retention remain blocked owner-module integrations.

## Ownership and delegation

Before test authoring, independent feature/security/transfer test files are
delegable; coordinator alone executes schema-mutating checks sequentially.
Workers receive exact ownership, root AGENTS and only relevant skills, no recursion.
EMP006's same coordinator released its lease at the preserved Cycle02 checkpoint;
CRM013 now legitimately owns shared tool coverage, with CRM and Identity modules.
No other owner's lease was released. CRM013 was narrowed to core library,
assignment/masking and schema foundation; registered CRM014 depends on it for
activity/handover/conversion. UI integration is a later separately claimed task.

Workers explicitly dispatched gpt-6-luna / xhigh, fork_turns=none:
crm_contract_audit and crm_governance_audit read-only; crm_application owns only
LeadDirectory; crm_persistence owns only migration000006; crm_security_tests owns
LeadSecurityTest; crm_handover_tests owns LeadWorkflowTest; crm_ui_plan read-only;
crm_core_review independent read-only. Coordinator owns catalog/coverage,
lifecycle, runtime and integration. All dispatch configurations were accepted.

## Executed evidence so far

- Initial missing-service RED: SQLite, LeadWorkflowTest, one test/one assertion,
  expected failure missing LeadDirectory. No behavior or MariaDB acceptance.
- Admin reveal-grant RED: one test/two assertions failed because catalog ignored
  explicit contact.reveal. Catalog added CRM grants; implicit admin defaults now
  omit reveal, then existing explicit roles/user overrides run with deny last.
- Fresh isolated MariaDB 11.4.13 created under ignored `.local/windows-qa/cycle03-db`,
  loopback port13317, dedicated `arasya_cycle03_acceptance`. Generated credentials
  only in ignored local environment. No shared13316 resources changed or reset.
- Focused admin explicit-grant regression on this MariaDB: exit0, one test/four
  assertions, 2.345s. Access regression after catalog change: exit0, 39 tests/268
  assertions, 17.463s. These are focused development checks, not consolidated
  acceptance. Migration schema foundation is exercised on actual MariaDB.
- No full suite, browser acceptance, completion, source commit or production run.

## CRM013 core review and closure evidence

Core service now implements library save/read/edit/archive, initial assignment,
explicit contact reveal, immutable taxonomy configuration and minimum eligible
recipient projection. Existing LeadRecord/LeadLifecycle and OutboxEvent are
reused. Active users are referenced through Identity's persisted permissions;
no second employee identity or cross-module writes. Manager library scope is
company/branch/department; sales read only current assignment. SQL filters
precede projection and lists never disclose contact values even with reveal grant.

Independent crm_core_review found department leakage, default archived-list
behavior, discarded reveal reason and id-only user FKs. All were repaired and
reviewer reread confirms no blocking core finding. Regression tests prove exact
archive outbox payload/version/correlation, same-branch other-department denial,
encrypted contact-bearing reveal reason with no plaintext in audit/history, and
direct cross-company user FK rejection. The latter first failed on actual MariaDB
(one test/two assertions), then passed with composite user/company FKs. Migration
adds only its owned `crm_users_company_uq` candidate index on Identity; rollback
drops CRM tables then that index, preserving existing Identity data/schema.

Fresh core-focused final command: `php vendor/bin/phpunit tests/Feature/CRM/Runtime
--filter 'LeadSecurityTest|test_manager_can' --do-not-cache-result`, isolated
MariaDB11.4.13 port13317, exit0, **13 tests/143 assertions**, 4.096s.
Scoped PHPStan: exit0 no errors after correcting eight shape/PHPDoc errors;
no suppressions/baseline changes. Scoped Pint formatted only new service/workflow
test; final --test is recorded at closure. Forward/rollback/up of migration000006
on this disposable DB: exit0, rollback84.84ms/up733.68ms. No shared DB touched.
task-loop fixture tests: exit0, 38 events. Documentation validator still fails
only the six pre-existing missing Laravel skill links; no new DAG/link failure.

The first broad security run exposed a fixture mismatch: ordinary sales attempted
master name edit, although the reviewed contract allows manager-only master edits;
independent author corrected it to assert 403 and unchanged name/version. Sales
follow-up remains the separately tested CRM014 operation, not an expanded grant.

Duplicate detection currently reuses the existing normalized-email identity rule
with HMAC fingerprint. It does not merge/fuzzily match organizations/phones or
settle a production customer identity/merge policy. This limit and missing private
handover attachments prevent full CRM VERIFIED. Contacts and reasons encrypted;
free-text notes can contain user-authored contacts, a documented residual risk.
Archive intent is durably retained in audit, not published to shared outbox.
CRM014 workflows, actual HTTP/UI/browser and consolidated acceptance remain open.

CRM013 completed through Review/Complete with independent crm_core_review;
CRM014 subsequently claimed by cycle03-coordinator. Latest own-slice scoped
Pint --test: exit0. CRM014 missing-method RED: five workflow tests, zero assertions,
five expected missing application methods (requestTransfer/activity/convert),
3.481s on the same isolated MariaDB. No full suite was run.

At 2026-10-17T08:05:44Z, sidebar-coordinator legitimately claimed A1-DASH-006,
including `resources/views/layouts/shell.blade.php` and the shared application
sidebar component, until10:05:44Z. UI work must preserve that new ownership;
no navigation edit or consolidated integrated acceptance before explicit handoff.
Independent CRM services/controller/views/routes remain possible after their
own legitimate task claim; no hidden route registry or lease stealing.

## CRM014 candidate — blocked independent review checkpoint

The required explicit gpt-6-luna/xhigh follow-up for crm_application and new
crm_workflow_security and crm_browser_plan dispatches returned usage-limit errors
before delivering this phase's files. No substitute model was dispatched. Before
writing further tests, delegation was assessed: the new independent test file was
safe, dispatch failed; coordinator reclaimed unproduced scope and extended the
existing workflow test. Coordinator implemented the remaining candidate methods
in LeadDirectory. These coordinator edits have **no independent CRM014 review**.
Prior CRM013 source review does not approve them.

Candidate behavior: real LeadActivity UTC record + immutable outcome snapshot;
safe generic self-follow-up through TaskCycle, with CRM/task/audit rollback on
denial; real LeadHandover recipient acknowledgement, old owner retained pending,
single accept/reject, current persisted grants and scoped eligible recipient;
version-bound request context (changed lead must be rejected/re-requested rather
than overwrite); customer conversion with stable CRM identity/retry and no
fabricated downstream records. Scoped find returns actual activities/pending
transfer. Audit retains actor, version and safe typed activity/task/transfer/
customer references in both CRM and canonical Identity trails.

Recipient inbox/response require crm.read + crm.write and exact named recipient;
crm.transfer authorizes initiating a transfer. This matches the independent
positive fixtures and eligibility contract (recipients need read/write, not a
global initiation grant). A first candidate run over-required crm.transfer on
recipient response: 19 tests/183 assertions, two errors. Corrected the boundary;
19 tests/204 assertions then passed. No recipient ID or session authority is
trusted from caller data.

Additional coordinator regressions cover duplicate pending transfers, stale
proposal after lead edit, same-session recipient revocation, denied Tasks write
rolling back all activity/task/audit changes, archived workflow denial, and
converted-lead notes edits preserving customer identity. That last test exposed
self-collision against the linked Customer fingerprint; it failed before repair.
Fixed uniqueness exclusion for the same linked customer. Converted contact
identity changes remain explicitly rejected until a customer-owned change/merge
workflow exists; no silent contact identity drift or merge policy was introduced.

Latest fresh `php vendor/bin/phpunit tests/Feature/CRM/Runtime
--do-not-cache-result`: **exit0, 23 tests/225 assertions, 4.815s**, PHP8.4.21,
MariaDB11.4.13, disposable13317/arasya_cycle03_acceptance. Scoped PHPStan runtime:
exit0/no errors. Scoped Pint --test service/Identity/migration/Runtime tests:
exit0. `npm.cmd run check:design-system`: exit0 checker/self-test/JS syntax.
No full PHPUnit or Playwright run; no CRM URLs/screens have been registered.
No frontend build script exists in package.json; no build claim. The latest
`RefreshBacklog` attempt refuses pre-existing missing A0-VINT-001 snapshot row;
tasks.json remains authoritative. `git diff --check` reports the pre-existing
ledger whitespace line1056, confirmed in pre-Cycle03 backup; no foreign cleanup.

Source checkpoint SHA256 LeadDirectory:
`FC444933474F1D82D81C7E95831C8192C163C9ADB4566C9144DF50DEF792D656`;
migration000006:
`2F6EA0DEF733ABAC134A6DCF3398E18364A867CA4928390034CB0E41170B3320`.
Repository HEAD `0ea1b6b2ebc9bef210dc705bd9a9455cfb1fd15f` belongs to concurrent
work; this session created **no commit**, push, PR, Actions or Sonar run. Existing
dirty/untracked runtime remains preserved; do not sweep it into a CRM commit.

### Consolidated acceptance matrix — NOT RUN

| Scope | Current cycle status | Missing final acceptance |
|---|---|---|
| Cycle01 Organization/Access | PARTIAL against current integration tree | One coordinated real-login/HTTP/MariaDB/browser run; earlier39/268 is focused catalog regression only |
| Tasks slice | PARTIAL against current integration tree | Full lifecycle, private evidence/RBAC/idempotency and browser in final window; CRM self-task link tested locally |
| Cycle02 Employees | PARTIAL, NOT VERIFIED | Actual routing/UI, private documents, offboarding/session revocation and real work handoffs; old checkpoint retained |
| Cycle03 CRM | PARTIAL, NOT VERIFIED | CRM014 independent review, routes/screens/import/customer-change/private transfer attachments and 15-step real-login acceptance |

Authoritative references actually used: PRD CRM01..06; SCOPE; REQUIREMENTS_TRACEABILITY;
MODULE_CATALOG/DATA_MODEL/SOLUTION_ARCHITECTURE; ADR001..004/index; permissions/
threat model; workflow catalog; change control/OPEN/gate policy; task schema/
execution/loop guides; test strategy/DoD; Cycle02 evidence and design-system catalog.
Skills actually applied: Ponytail, Laravel best-practices (architecture/security/
migrations/validation/routing/Blade/performance/errors), LaravelTDD, installed
frontend-design (planning only), dispatching-parallel-agents, using-git-worktrees,
test-driven-development, systematic-debugging, requesting-code-review. Official
Superpowers verification skill not discoverable now; no duplicate installed.
No new UI components: intended existing layouts.shell/UI/ERP components from the
read-only plan, **not a claim of CRM screen implementation or visual acceptance**.

### Exact resume sequence

1. Recheck current hashes/leases and task-loop Status; retain CRM013 completed
   bounded evidence and all foreign work. CRM014 remains blocked with own lease.
2. When explicit Luna xhigh dispatch is available, independent review CRM014
   candidate and missing negative paths. Do not relabel the coordinator as reviewer.
   Fix findings with narrow regression checks using the dedicated ignored
   `.local/windows-qa/cycle03-environment.json`, not shared13316.
3. After review blocker clears, Release own blocked014 with evidence, recheck
   readiness/Claim, then Review/Complete via coordinator. No direct status edit.
4. Register/claim separate UI/routes task dependent on completed014; recheck
   DASH006 owner handoff for shell/sidebar before navigation. Reuse approved
   components; delegate HTTP/UI/browser tests and independent frontend review to
   explicit Luna xhigh. Do not invent alternate route registries or bypass review.
5. Finish CRM import/customer contact-change identity and private handover-document
   contract through legitimate tasks/gates. Do not fabricate Documents approval.
6. After all ready integration and ownership release, reserve **one** fresh
   consolidated MariaDB/Playwright window, run suites sequentially and record each
   cycle criterion PASS/FAIL/BLOCKED. Current focused DB can be retained as a
   reproducible checkpoint, never use a shared destructive reset in parallel.
7. No next operational ERP business cycle is declared verified/eligible by this
   partial run. Planned Sales work must retain its real prerequisites and gates.

Final database read-back: zero rows in all six CRM tables, access_users and
tasks_cycle after transactional tests; MariaDB11.4.13 confirmed. No real data or
browser fixture was seeded. The owned disposable server13317 was shut down via
its own authenticated connection **after matching @@datadir to the exact newly
created cycle03-db directory**. Shared13316 was untouched. Schema/ignored local
environment remain for resume; no recursive deletion/drop of unrelated resources.
Restart only the owned bundled mariadbd with defaults-file
`.local/windows-qa/cycle03-db/my.ini`, bind127.0.0.1, port13317 in a hidden process;
load the ignored Cycle03 environment only in the check process. Credentials must
never appear in reports, commands expanded into logs, browser traces or commits.


## Phase 1/6 resume acceptance - 2026-10-17

This section supersedes the earlier CRM014 independent-review blocker only;
CRM013 done evidence above is preserved, not restarted or re-approved.
No frontend work, external CI, SonarQube, production, real data or policy change.

Independent workers explicitly dispatched with model `gpt-6-luna`,
reasoning_effort `xhigh`, fork_turns=none; both dispatches accepted:
- `crm014_review`: read-only service, called TaskCycle/Identity contracts,
  schema and workflow security review; no file or runtime mutation.
- `crm014_test_review`: read-only test/acceptance review; no file/runtime mutation.
Neither recursively delegated. Both rereviewed repairs and returned no blocking
finding in bounded CRM014. Independent source review covered activity, Tasks
transaction boundary, recipient binding and persisted grants, stale versions,
duplicate transfer, conversion retry, contact encryption/masking and both audits.

Confirmed P2 defects and smallest repairs:
1. Activity next_step accepted 2000 characters against existing VARCHAR(200).
   Validation now caps at 200 before TaskCycle or CRM writes; schema unchanged.
2. Archive could hide and permanently strand a pending transfer. Archival now
   rejects pending transfers under the same company/lead transaction lock;
   recipient rejection resolves the request and then archival succeeds.

Delegation assessment before test authoring: independent source and test review
were delegated. Coordinator alone edited LeadWorkflowTest using its existing
private fixtures and then LeadDirectory sequentially; no concurrent edits,
no worker schema tests. Existing worktrees were inspected: historical HEAD
omits the untracked runtime/bootstrap/config required by this candidate, so
read-only workers inspected the current candidate in place without new checkout,
branch, dependency installation or foreign edits. Only coordinator owns lifecycle.
Owned edits: LeadDirectory.php, LeadWorkflowTest.php, this checkpoint and CRM
coverage summary. Existing LeadSecurityTest is included in focused verification.

Exact checks, PHP8.4.21 / PHPUnit12.5.39 / isolated MariaDB11.4.13:
- Restarted only owned mariadbd with cycle03-db/my.ini, bind127.0.0.1 port13317,
  hidden process. PDO read-back confirmed VERSION(), DATABASE() and @@datadir
  exactly arasya_cycle03_acceptance / .local/windows-qa/cycle03-db.
  Credentials loaded only in check process from ignored cycle03-environment.json.
- Baseline: `php vendor/bin/phpunit tests/Feature/CRM/Runtime
  --do-not-cache-result`: exit0, 23 tests / 225 assertions, 19.813s.
- RED: `php vendor/bin/phpunit tests/Feature/CRM/Runtime/LeadWorkflowTest.php
  --filter 'test_activity_rejects_next_step|test_pending_transfer_prevents_archival'
  --do-not-cache-result`: exit1, 2 tests / 1 assertion, 2.241s;
  expected SQL1406 for 201 characters and missing archive409 reproduced.
- GREEN same focused command: exit0, 2 tests / 14 assertions, 2.286s.
  Checks validation field, no activity/task/version/CRM+canonical audit mutation;
  failed archival leaves lead available/inbox visible, then reject/archive works.
- Final after Pint: `php vendor/bin/phpunit tests/Feature/CRM/Runtime
  --do-not-cache-result`: exit0, 25 tests / 239 assertions, 4.583s.
- `php vendor/bin/phpstan analyse app/Modules/CRM/Application/Runtime
  --no-progress`: exit0, no errors; no baseline/suppression changes.
- `php vendor/bin/pint --test app/Modules/CRM/Application/Runtime
  tests/Feature/CRM/Runtime`: exit0. Initial post-edit style failure was corrected
  using Pint on exactly LeadDirectory and LeadWorkflowTest, then passed.
- `./scripts/test-task-loop.ps1`: exit0, fixture coverage 38 events.
- `./scripts/validate-docs.ps1`: exit1, same six pre-existing Laravel skill links
  (security/migrations/events-notifications/mail/scheduling/config); not repaired
  outside scope, no new validation failure accepted as passing.

Both reviewers accept bounded CRM014 after repairs. Optional extra negative
coverage (not confirmed defects) remains: recipient moved/deactivated after
request, denied/stale first conversion, invalid/superseded outcome/time values.
Full CRM remains PARTIAL, NOT VERIFIED: HTTP/routes/UI, import, customer-owned
contact-change identity, private attachments and consolidated acceptance remain
separate tasks/gates. Assistant contracts remain Restricted/disabled.
No descendant is newly ready from CRM014 (registered blocks=[]); the next phase
needs its own legitimate task registration/claim and fresh shared-file leases.


### Phase 1 final coordinator read-back

Supported Release -> ready -> Claim -> Review -> Complete all committed;
final Status: A1-CRM-013 done/no lease/recovery=none;
A1-CRM-014 done/no lease/recovery=none. Independent reviewer crm014_review.
Source commit: b7edfbd49861ca1b65e786557f5c242a1b4fa89c, exactly three CRM files:
LeadDirectory, LeadWorkflowTest, LeadSecurityTest. Those files were previously
untracked and include preserved CRM013 foundations; no unrelated runtime,
migration, shared coordinator ledger, or foreign dirty work was swept into it.
This checkpoint and shared AI_TOOL_COVERAGE remain local untracked documents;
this is not a clean-checkout runtime or deployment acceptance claim.

RefreshBacklog exit1 retains pre-existing missing A0-VINT-001 row; no snapshot
repair or direct ledger edits. tasks.json remains authoritative. Scoped source
staged diff --check passed. Final direct PDO read-back: all six CRM tables,
access_users and tasks_cycle contain zero rows after transactional tests.
SHUTDOWN issued only after exact normalized @@datadir matched cycle03-db;
owned port13317 server stopped, shared13316 untouched.

Skills actually read/applied (no installation):
- C:/projects/new_arasya-crm/.agents/skills/ponytail/SKILL.md
- C:/projects/new_arasya-crm/.agents/skills/laravel-best-practices/SKILL.md
  with rules/security.md, architecture.md, validation.md, error-handling.md.
- C:/Users/N/.codex/plugins/cache/openai-curated-remote/superpowers/6.4.2/skills/dispatching-parallel-agents/SKILL.md
- C:/Users/N/.codex/plugins/cache/openai-curated-remote/superpowers/6.4.2/skills/using-git-worktrees/SKILL.md
- C:/Users/N/.codex/plugins/cache/openai-curated-remote/superpowers/6.4.2/skills/test-driven-development/SKILL.md
- C:/Users/N/.codex/plugins/cache/openai-curated-remote/superpowers/6.4.2/skills/systematic-debugging/SKILL.md
- C:/Users/N/.codex/plugins/cache/openai-curated-remote/superpowers/6.4.2/skills/requesting-code-review/SKILL.md
  plus code-reviewer.md template.
- C:/Users/N/.codex/plugins/cache/openai-curated-remote/superpowers/6.4.2/skills/verification-before-completion/SKILL.md

STOP at Phase1: next phase eligible for planning/legitimate registration only;
no CRM frontend implemented and no full Cycle03 VERIFIED claim.

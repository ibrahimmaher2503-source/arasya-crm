# Cycle03 Phase 3 — CRM HTML integration

Owner directive: pasted Phase 3 recovery/resume request and attached October 17
CRM overview reference, received October 17; verification continued October 18,
2026 (Africa/Cairo). Bounded local synthetic DEVELOPMENT only. No Phase 4,
consolidated ERP acceptance, production, providers, real customer data, hosted
CI, or SonarQube work is authorized here.

## Recovered baseline and coordinator

Actual initial HEAD `696e060a8579e87d56d684ed64991d7b9e269d90`; 471 porcelain
entries (existing modified/untracked work preserved). CRM013/014/015 remain
done. Initial task-loop Status: 270 tasks, 98 done, 82 blocked, 85 planned,
5 ready, zero leases, no pending transaction. The latest independently
verifiable HTTP checkpoint is [cycle03-http](cycle03-http.md). No standalone
Phase 3 recovery-audit file was discoverable in the repository; the owner's
pasted audit reference was checked against source rather than assumed current.

Registered `A1-CRM-016` through the existing task-loop helper registration
precedent: exclusive coordinator lock, pending-intent guard, scope conflict
check, DAG/reverse-edge validation, preservation of historical fields and
expected-hash atomic replacement. Supported Claim committed for
`crm-phase3-coordinator`. Shared shell/sidebar/routes/CSS had no active lease;
their existing dirty contents were read and retained. No lifecycle JSON was
edited outside that guarded coordinator procedure.

## Routes, reuse and privacy

HTML: GET `/crm`, `/crm/leads`, `/crm/leads/create`,
`/crm/leads/{lead}/edit`, `/crm/leads/{lead}`. Four screen types live in
`resources/views/crm/{overview,index,form,show}.blade.php`; `_nav` is a body
section link list, not another application sidebar. `error` renders sanitized
HTML failures through the existing shell.

All 15 existing JSON method/path pairs and route names are preserved. Explicit
JSON and legacy wildcard/default callers retain JSON. HTML opt-in is explicit
`Accept: text/html` or real browser `Sec-Fetch-Mode: navigate` with HTML accepted.
The boundary records that choice before forcing internal JSON error rendering;
this prevents Laravel's validation redirect from flashing private submitted
contacts into the session. HTML errors retain safe status/messages/field names,
not submitted values; responses are private/no-store. Page-only routes fail
closed for JSON clients. API-only taxonomy/recipient/inbox paths stay JSON.

Native CSRF-protected forms reuse the reviewed LeadDirectory operations and
optimistic versions. Revealed contacts appear only in the immediate audited,
no-store response; subsequent reads mask them. Edit forms never round-trip
masked contact values. Effective persisted permissions control visible actions;
application services reauthorize every operation.

Reuse: `layouts.shell`, `erp.application-sidebar`, `ui.page-header`,
`ui.summary-strip/stat-card`, `ui.table`, `ui.list-pagination`, form sections,
fields/inputs/selects/checkboxes/textareas/actions/buttons, badges, `ui.tabs`,
`erp.entity-header`, `erp.audit-history`, alerts/empty states and existing
semantic CSS tokens. No new shared component or framework. The shell gains
optional breadcrumb/title/subtitle/icon extension values with old defaults.
Its existing propagation guard covers theme toggling as well as navigation,
preventing dashboard and design-system handlers from toggling twice.

Pagination adapts bounded service data/meta to LengthAwarePaginator without
loading full datasets. Option pages have separate query/page names and local
translated searches because the existing option-browser labels explicitly
refer to branches/departments/roles. Selected stages absent from an option
page remain explicit options. Ordering remains existing created_at/id stable
ordering. Activity and audit history retain service bounds.

Overview uses scoped SQL aggregates, top-ten source/stage groups and ten recent
activities/upcoming follow-ups; incoming transfers use the existing recipient
inbox contract. No revenue, opportunities, percentage growth, conversion-rate
or fake screenshot totals are inferred from unsupported records. The visual
reference guides the green shared sidebar, aligned header, metric strip,
three-panel overview and follow-up table. Exact screenshot metric/content
replication is deliberately excluded by the owner's real-data requirement.

## Skills and worker ownership

Actually read/applied: `.agents/skills/ponytail/SKILL.md`,
`.agents/skills/laravel-best-practices/SKILL.md` (routing, Blade, validation,
database-performance and error-handling rules), installed
`C:/Users/N/.codex/skills/{frontend-design,dispatching-parallel-agents,using-git-worktrees,test-driven-development,systematic-debugging,requesting-code-review}/SKILL.md`.
Workers also read applicable installed Laravel UI guidance. The Laravel
security rule referenced by its index was absent. No installed official
Superpowers bundle or verification-before-completion skill was discoverable;
no duplicate installation or invented path was used. Completion evidence is
verified directly with the repository workflow.

All five workers were explicitly dispatched with `model=gpt-6-luna`,
`reasoning_effort=xhigh`, `fork_turns=none`; no substitution or recursion:

- `crm_seam_review`: read-only integration/security review, no edits/runtime.
- `crm_html_tests`: only `tests/Feature/CRM/Presentation/CrmPagesTest.php`.
- `crm_overview`: only LeadDirectory and overview/recipient-scale test files.
- `crm_views`: only five CRM body views and `resources/css/crm.css`.
- `crm_browser_tests`: only `tests/Browser/crm-cycle.spec.ts` and fixture PHP.

Delegation was assessed before test code. File scopes are independent; all
database/runtime execution remains centralized. Native artifacts list was
empty; Git worktrees were inspected. As in the preceding cycle checkpoint,
HEAD lacks the untracked runnable foundation/shared components. A clean
worktree would not contain the actual integration checkpoint. Workers therefore
authored disjoint files in the current checkout without runtime execution;
coordinator owns integration/shared files, database, state and final checks.

## Verification log

Runtime guard verified MariaDB `11.4.13-MariaDB`, exact owned
`.local/windows-qa/cycle03-db` datadir and `arasya_cycle03_acceptance` on
loopback port 13317. Shared port 13316 untouched. Browser HTTP uses owned
loopback 8773, testing environment and file sessions with the same key.

RED before implementation: overview 2 tests/2 assertions failed for missing
overview contract; HTML 5 tests/5 assertions failed for absent routes/forced
JSON rejecting locale. First SQL run exposed ambiguous company_id in the
stage join; qualifying the shared visibleLeads scope fixed the root cause.
Overview GREEN: 2 tests/19 assertions. Initial HTML GREEN: 5 tests/76 assertions.
HTTP security regression GREEN: 9 tests/473 assertions. PHPStan initial final
candidate: no errors. Design-system checker and its self-test: PASS.

Browser first run: both salesperson desktop/mobile scope/privacy scenarios
passed; manager path found a locale-specific selector issue, then the actual
double-theme-handler defect, then an overlong synthetic email fixture. These
were investigated individually; no timeout or security assertion was relaxed.
Browser fixture ownership verification reported 300 synthetic leads/3 users;
cleanup succeeded before returning to schema-mutating PHPUnit. Test execution
and browser fixtures never overlap.

Final central command (through the environment-only runner):
`php vendor/bin/phpunit tests/Feature/CRM/Http tests/Feature/CRM/Runtime tests/Feature/CRM/Presentation tests/Feature/Tasks tests/Feature/Access --do-not-cache-result`
passed **147 tests / 2253 assertions**, PHP 8.4.21, MariaDB 11.4.13, 36.706s.
All schema-mutating execution was serialized on the owned disposable database.
This is focused local regression, not full-project/consolidated acceptance.

Recipient scale RED: exact effective recipient IDs/count matched but 395 SQL
queries exceeded the <30-query assertion. GREEN: one test/four assertions
passed after using SQL EXISTS for same-company direct/department roles,
administrator baseline, explicit grants and deny precedence. The page now
counts/selects in SQL; no per-candidate permissions call. This is only the
two explicitly tested CRM permissions, not a replacement Identity engine.

`npx.cmd playwright test crm-cycle.spec.ts --reporter=list` against loopback8773
passed **4/4**, 14.8s final run: desktop Chromium and Pixel7/mobile Chromium,
real login, Arabic/RTL and English/LTR, actual light/dark toggle, keyboard focus,
one sidebar/header and no horizontal overflow; create/edit/reasoned reveal and
both assigned/unrelated sales scopes. Screenshots were inspected at
`.local/windows-qa/crm-phase3-{desktop,mobile}.png`. The source/stage/activity
panels align in one desktop row and collapse on mobile. Empty states show the
actual synthetic dataset has no recent activities/follow-ups; they do not fill
those panels with screenshot fiction. Four supported metrics replace the
reference's unsupported opportunity/revenue/growth metrics. This is visual
consistency with the reference and existing design system, not a pixel-exact
replica or evidence for business data absent from fixtures.

Independent post-browser PDO verification read 304 synthetic leads/3 users
(300 seeded plus two successful create/edit flows in each of two final runs),
confirmed browser-created versions/updated organizations, encrypted contacts,
distinct salesperson assignments and at least two reasoned-reveal audits.
Ownership-checked fixture cleanup passed. Runtime proof then read zero rows
from all six CRM tables, access_users and tasks_cycle before shutting down only
the owned13317 server. Owned8773 HTTP server stopped; shared13316 untouched.

Scoped PHPStan: **PASS, no errors** for both CRM controllers, boundary and
LeadDirectory. Scoped Pint `--test`: **PASS** including all new PHP tests and
fixture. `npm.cmd run check:design-system`: **PASS**, checker self-tests and JS
syntax included. `scripts/test-task-loop.ps1`: **PASS**, 38 fixture events.
`scripts/validate-docs.ps1`: **FAIL, six pre-existing missing rule links** in
the installed Laravel skill (security, migrations, events-notifications, mail,
scheduling, config). No new document-link failure; unrelated skills preserved.

RefreshBacklog initially correctly rejected the newly registered task's absent
projection row. A one-row source-derived addition used the existing guarded
projection helper; supported RefreshBacklog then passed. No status invented.

Independent reviewer `crm_seam_review` (gpt-6-luna/xhigh) re-read the current
controllers, boundary, SQL grants, views, shell, sidebar and registration:
**no blocking source issues**. It explicitly confirmed deny precedence and
company/department role semantics, no contact/authorization bypass, and the
single theme handler fix. Review was read-only; all executed checks above are
coordinator evidence. Nonblocking findings: unused detail inbox queries removed;
stale ignored registration descriptions aligned to registered Phase3 metadata;
off-page stage labels can fall back to the stage ID (selection is retained).

Exact changed source paths: `app/Http/Controllers/{CrmPageController,CrmLeadController}.php`,
`app/Http/Middleware/CrmHttpBoundary.php`,
`app/Modules/CRM/Application/Runtime/LeadDirectory.php`, `routes/crm.php`,
`resources/views/crm/{overview,index,form,show,_nav,error}.blade.php`,
`resources/css/crm.css`, shared `resources/views/layouts/shell.blade.php` and
`resources/views/components/erp/application-sidebar.blade.php`; three
`tests/Feature/CRM/Presentation/*Test.php`, browser spec/fixture, this evidence,
component catalog and tool-coverage note. No new migration/shared component.

Commit boundary: only exclusively owned CRM source, new views/CSS/tests and
this evidence are committed. Existing shared dirty shell/sidebar/catalog/tool
coverage/coordinator foundation remains in the working checkout with the
bounded integration additions; it is neither overwritten nor swept into the
source commit. Consequently this is acceptance of the actual working checkpoint,
not a claim of runnable clean-checkout delivery. A later owner-scoped foundation
publication must preserve those shared files. No Phase4 task or descendant was
registered by this task; eligibility still belongs to the actual DAG/gates.

## Final coordinator checkpoint

Source commit: `11115817ea4b40b66ea74ad49c254f3588c805d8`.
Supported Review and Complete both COMMITTED with independent reviewer
`crm_seam_review`. Final CRM016 state: **done**, DEVELOPMENT, lease none,
recovery none. Supported RefreshBacklog passed. Final Ready report: 271 tasks,
99 done, 82 blocked, 85 planned, five ready, zero active leases, no pending
transaction. CRM013/014/015 were not reopened or restarted.

No newly ready descendant: CRM016.blocks remains empty. Existing ready tasks
are DS003, DASH003, EMP006, DASH006 and CORE015; this checkpoint neither claims
their unrelated dirty implementations nor starts them. Phase4 remains outside
this task and requires its own scoped coordinator registration/readiness.

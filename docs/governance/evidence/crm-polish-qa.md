# CRM component alignment — owner-stopped checkpoint

Status: PARTIAL / STOPPED AT OWNER REQUEST, 2026-10-18 (Africa/Cairo). This document supersedes informal progress claims for this candidate. No pixel-perfect, production, final browser, or lifecycle DONE acceptance is claimed.

## Location and ownership

- Canonical project: `C:\projects\new_arasya-crm`. Its HEAD was `3335a3a57be5f43f6f83727330302251e0f2e7c0` when this checkpoint was captured; another coordinator independently advanced Employees work. Do not overwrite that work or its ledger updates.
- Preserved CRM candidate: `C:\Users\N\.codex\worktrees\crm-polish-qa\new_arasya-crm`.
- Candidate HEAD: `5ee78d29afab1859048523f696061078ce79892f`. Latest component/backend edits listed below are UNCOMMITTED above that HEAD and have NOT been integrated into the canonical checkout.
- Task `A1-CRM-017`, owner `crm-polish-coordinator`. The owner explicitly requested documentation and stopping; release the CRM lease using the coordinator, without claiming completion. Other leases must remain untouched.
- Latest directive explicitly prohibited subagents. All work in this component-alignment continuation was performed by the coordinator alone. Earlier Luna reviews apply only to earlier commits, not this new diff.

## Actual changes preserved

1. Replaced the generic six-column lead listing composition with shared `ui.directory-workspace`, `ui.filter-bar`, and `ui.directory-toolbar` components, reusing summary-strip, table, inputs, badges, avatar, pagination, and the existing drawer.
2. Added four real scope-filtered metrics: active lead total, active leads created this month, overdue follow-up lead count, and converted percentage of active leads. No fabricated growth comparisons or reference sample totals.
3. Added HTML-only `LeadDirectory::workspace()` projection with stable SQL pagination, source/status/priority/assignee filtering, bounded owner-name lookup and activity/follow-up metadata. Contacts remain absent from the projection and require audited reveal on detail.
4. Added nullable-free persisted priority with default `normal`, allowlisted `low/normal/high` save validation, and a new additive migration. No shared database migration/reset was run.
5. Added directory quick-create using real POST/CSRF and existing save service. It includes name/company, optional phone/email, source, stage, priority and notes. It does NOT yet include owner/branch/tags, bulk operations or export.
6. Extended the shared drawer with opt-in desktop docking; smaller screens keep native modal behavior. Added Escape close and focus restoration through the existing shared interactions. Full responsive/focus acceptance remains unverified.
7. Added actual CRM search to the shared topbar. Sidebar still reflects real permissions and routes; no fake notification system or unsupported links were added.
8. Density changes live in shared UI styles. No new CRM-specific CSS was added in this continuation, but the earlier candidate still contains approximately 797 lines of CRM CSS. Removing/consolidating that earlier CSS remains unfinished.

## Exact uncommitted source paths

- `app/Http/Controllers/CrmPageController.php`
- `app/Modules/CRM/Application/Runtime/LeadDirectory.php`
- `database/migrations/2026_10_18_000001_add_crm_lead_priority.php` (new)
- `resources/css/ui-data.css`
- `resources/css/ui-feedback.css`
- `resources/js/design-system.js`
- `resources/views/components/ui/directory-workspace.blade.php` (new)
- `resources/views/components/ui/filter-bar.blade.php` (new)
- `resources/views/components/ui/directory-toolbar.blade.php` (new)
- `resources/views/components/ui/drawer.blade.php`
- `resources/views/components/ui/icon.blade.php`
- `resources/views/crm/index.blade.php`
- `resources/views/crm/_quick-create.blade.php` (new)
- `resources/views/layouts/shell.blade.php`
- `tests/Feature/CRM/Presentation/CrmPolishQaTest.php`
- `tests/Feature/CRM/Presentation/CrmPagesTest.php`

The main checkout also contains coordinator task/state/handoff edits for this task. Preserve these through the supported task-loop workflow; never copy the old worktree ledger over the current main ledger.

## Executed evidence and limits

- Historical pre-component candidate: PASS 152 PHPUnit tests / 2,283 assertions; PASS four basic browser tests. These do not validate the new component/backend diff.
- New regression RED: unsupported priority rejected by baseline, `workspace-red.log`.
- New focused GREEN: PASS six presentation tests / 44 assertions, `workspace-green.log`. This run preceded some subsequent UI/priority projection adjustments and is not final-head acceptance.
- Combined new candidate: 153 tests / 2,295 assertions, ONE FAILURE in `CrmPagesTest::test_overview_count_uses_only_the_actors_authorized_lead_scope`. The earlier visual pass changed the overview label from `Total leads` to `Active leads`. The PHP assertion was subsequently aligned with that label, but NOT rerun. Log `workspace-regression.log`.
- Latest browser run: ONE FAILED, THREE NOT RUN, with suite/teardown timeout errors. The first desktop scenario still expects the old overview `Total leads` label and fails before the rest of that workflow. Do not report this run as a browser pass. Log `component-cycle.log`.
- Design-system checker plus self-test and JS syntax check: PASS at an intermediate point. Later shared drawer/CSS edits were NOT rechecked.
- Scoped Pint: FAIL on formatting in LeadDirectory and CrmPolishQaTest; NOT repaired before the stop. Log is in terminal/session output, not a final acceptance artifact.
- PHPStan was green only for the previous candidate. New workspace service, migration and docking behavior have NOT received final PHPStan or independent review.
- No final twelve-scenario browser matrix, final clean-checkout reproduction, pixel measurements, final scope-leak/query-count regression, production, push, deployment or external CI.

## Visual evidence

All paths below are inside the preserved worktree `.local/windows-qa/`:

- True historical before: `before-verified-{overview,directory,create,edit,detail}.png`.
- New component composition: `component-pass-{overview,directory,create,edit,detail}.png`.
- Reference-size actual app capture with drawer open: `reference-size-drawer.png`, 1672px viewport width, 941px viewport height, full-page capture. Route `/crm/leads?locale=ar&per_page=10`.
- The last capture preceded the final row-height override and contact-anchor correction; it is not a screenshot of every latest edit.
- Reference: `C:\Users\N\Downloads\mark\ChatGPT Image Oct 17, 2026, 12_25_43 PM-2.png`.

Largest remaining differences: the supplied reference is denser; typography/proportions still need viewport-matched measurement; the synthetic fixture creates long English names and sparse owner/activity data; owner/branch/tag controls, export and bulk actions are not implemented; private contacts intentionally remain protected. Other existing CRM screen types were not fully realigned in this continuation. No pixel-parity claim is justified.

## Runtime and recovery

- Owned QA HTTP server PID 44108 on 127.0.0.1:8773 was stopped at the owner's request.
- Browser fixture cleanup uses verified synthetic company ownership. Record its final command result; do not reset shared MariaDB.
- Isolated MariaDB is 127.0.0.1:13317, `arasya_cycle03_acceptance`, canonical `.local/windows-qa/cycle03-db`. Shared 13316 was not touched. The isolated MariaDB process was left available; do not stop it without confirming current ownership.
- Credentials remain only in ignored local files and must not be committed or printed.
- No ongoing implementation or test rerun is authorized after this stop.

## Exact continuation action, only after the owner resumes

Inspect current main HEAD, all leases, worktree status and the preserved diff first. Reclaim/renew only this task through the coordinator. Reconcile the overview label contract, finish focused tests and formatting/static checks, review the new metrics/latest-follow-up semantics and bounded projection, test docked/modal focus and responsive behavior, then compare actual screenshots at the reference viewport. Complete supported missing behavior deliberately; no fake controls or data. Do not integrate or mark DONE until current-source acceptance is established. AI Assistant and interactive tutorial remain broader mandatory module closure gaps; neither was implemented or claimed by this stopped UI candidate.

## Confirmed shutdown / coordinator result

Verified synthetic browser-fixture cleanup PASSED. CRM task release PASSED through `task-loop`: `A1-CRM-017` is `ready`, with no lease and no pending recovery; ready is the coordinator's release state, not a DONE or permission to resume after the owner's stop. Ledger snapshot at release: 272 tasks — 100 done, 82 blocked, 85 planned, 5 ready. CRM candidate changes remain uncommitted and isolated. No source integration or push occurred.

## Reference alignment continuation — 2026-10-19

Owner requests both existing-screen alignment and six supplied reference screens. Current source/browser inspection confirms missing CRM Tasks, Notes, Activities, Files, Quotations and Pipeline workspaces. Existing activity notes, paginated activity data and linked follow-up Tasks must be reused. Sales approval policy OPEN-02 remains unresolved; owner separately authorized synthetic local draft development, with live approvals/sending prohibited. New scope must be registered separately from this four-screen task.

This checkpoint only removes redundant CRM subnavigation on lead detail and gives the existing entity header a compact token-based surface. Files: resources/views/crm/show.blade.php and resources/css/crm.css. No route, data, authorization or component API changes. TDD N/A for this non-behavioral presentation adjustment; no new test code. Independent reviewer reference_gap_review (gpt-6-luna/xhigh) found no blocking defects in those two files. Review owned no files and mutated no runtime.

Executed: npm.cmd run check:design-system PASS (checker, self-test, JS syntax); git diff --check PASS. Authenticated existing local browser on port 8773 confirms compact header, retained edit action, masked contact fields and single h1; requested viewport 1672x941 reported 1672x942, so exact-height parity NOT VERIFIED. Full RTL/LTR/mobile/theme workflow, PHPUnit and full visual parity remain NOT TESTED for this checkpoint. No commits, deployment, external sends or lifecycle completion.

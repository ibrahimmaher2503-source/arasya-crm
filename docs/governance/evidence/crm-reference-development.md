# CRM reference workspace — bounded development authorization

Date: 2026-10-19 (Africa/Cairo). Requester: project owner in this chat.
Status: owner-authorized DEVELOPMENT scope; implementation and acceptance pending.

## October 19 lead overview refinement — active slice

Owner requested the lead at localhost:8788 to match the supplied 1672x941 overview.
Owner explicitly confirmed the previous session ended and authorized its lease
release; A1-CRM-020 is now claimed by lead-overview-ui-20261019. Preserve the dirty
baseline, synthetic runtime, existing mutations and all gates. No commit requested.

Lightweight plan/test design: reuse the entity header, detail panels, summary cards,
tables, pagination and dialogs. Compose existing scoped overview/workspace reads in
CrmPageController; render a compact information/contact/timeline board, genuine
activity summaries, recent leads and scoped source/stage charts. No fabricated
company fields, lead score, revenue or sales performance. Contacts keep audited reveal.
RED/GREEN checks extend CrmReferenceWorkspaceTest: six preview entries with all
activities counted, real scoped summaries/recent leads, no foreign records/private
contacts, and RTL/LTR rendering. Existing CRM presentation tests remain regressions.
Pure spacing/layout has no business TDD requirement; browser geometry, responsive
RTL/LTR, themes, keyboard dialogs and viewport-matched screenshots verify it.

Delegation assessment before test code: Luna X High lead_reference_review owns
read-only source/image review, no files or runtime mutations. Test execution and
integration stay serialized because migrations, Blade cache and the dirty baseline
are shared. Coordinator owns show/header/CSS/controller and the existing regression
test; reviewer will inspect the final combined changes independently. No fallback.

### Delivered overview checkpoint

Superseding owner change: the owner then explicitly requested removing the recent
leads table, source chart and stage distribution from this detail page. Removed
the three rendered widgets, their CSS and the two now-unused scoped aggregate/
directory reads in CrmPageController::show; CRM overview/directory remain unchanged.
RED regression failed on the existing widgets; GREEN CrmReferenceWorkspaceTest
PASS8 tests/246 assertions, asserting all three widgets and their view projections
are absent while private contacts and the six-entry/full activity contract remain.
Updated screenshot artifacts supersede the earlier chart-bearing screenshots below.

- Coordinator changed `app/Http/Controllers/CrmPageController.php`,
  `resources/views/crm/{show,_entity-header,_workspace-nav,_overview-board}.blade.php`,
  `resources/css/crm.css`, `tests/Feature/CRM/Presentation/CrmReferenceWorkspaceTest.php`,
  `tests/Browser/crm-reference.spec.ts` and the design-system catalog. Previously dirty
  versions were snapshotted in ignored `.local/lead-overview-ui-20261019/` before edits.
  Later discovered presentation improvements were retained; the original complete
  activity content/pagination remains available in a dialog. No domain/schema change.
- RED: the new overview test failed because `data-lead-overview` was absent. GREEN:
  one test / 20 assertions. Final command with isolated compiled views:
  `php -d opcache.enable_cli=0 vendor/bin/phpunit tests/Feature/CRM/Presentation --do-not-cache-result`
  PASS 44 tests / 771 assertions. Existing layout/privacy/full-activity regressions
  passed unchanged. A stale compiled/opcode result during edits was not counted as
  a valid pass; final runs disable CLI opcode caching and isolate compiled views.
- `php vendor/bin/pint --test app/Http/Controllers/CrmPageController.php tests/Feature/CRM/Presentation/CrmReferenceWorkspaceTest.php`
  PASS. `npm.cmd run check:design-system` PASS including checker self-tests.
- Playwright on the existing synthetic preview8788, scoped fixture credentials read
  without intentional credential logging:
  `npx.cmd playwright test tests/Browser/crm-reference.spec.ts --project=desktop-chromium --grep 'lead overview stays compact|reference workspace navigation' --reporter=list`
  PASS 2 scenarios. Covers AR/EN, widths1672/390, light/dark, no horizontal overflow,
  all native dialogs, keyboard opening/Escape/focus restoration and existing section
  navigation/file fields. No test submitted a business mutation.
- Screenshot/browser proof for the requested lead: actual viewport1672x941;
  document1672x941, six preview events, zero page errors. Main board begins at
  y311.375; information868.8px wide and timeline579.2px wide; timeline440px high.
  Artifacts: `.local/lead-overview-ui-20261019/{desktop-ar.png,desktop-ar-dark.png,mobile-en.png}`.
  Fresh final images replace earlier captures after the independent review fixes.
- Luna X High `lead_reference_review` found source-chart text alternatives missing,
  arbitrary stages incorrectly green, and general notes mislabeled as interests.
  Fixed with described source/count text, neutral stage badges, and truthful panel
  copy. Also fixed history divider/dark text. Independent re-review found no remaining
  blocking source issue. Coordinator inspected the final images and browser evidence.
- Local source/tests and browser proof only. No commit, push, deployment, hosted CI,
  provider action or production approval. Full A1-CRM-020 six-screen integration is
  still pending; this checkpoint closes only the requested overview slice. Remaining
  reference differences: real records/labels, initials avatars, grouped shared menu,
  no unsupported notifications/contact shortcuts, no fabricated score/sales numbers.
  Exact pixel parity is not claimed. No new tasks were unblocked by this slice.
- Skills actually used: `.agents/skills/{ponytail,laravel-best-practices,laravel-tdd,systematic-debugging,laravel-verification}/SKILL.md`
  and `C:/Users/N/.codex/skills/{laravel-ui-ux-minimal,test-driven-development,dispatching-parallel-agents,requesting-code-review}/SKILL.md`.
  Installed `verification-before-completion` was not found; no duplicate installed.

The owner explicitly requested both existing-screen alignment and the six supplied
Tasks, Notes, Activities, Pipeline, Files and Quotations references. The owner then
authorized bounded local Sales/Quotations development using synthetic test data only:
creation, editing, line items, calculations, discounts, taxes, draft management,
PDF previews and internal workflows. This is not a signed OPEN-02 policy.

## Restrictions and expiration

- OPEN-02 stays unresolved. Existing approval gates and their task dependencies stay intact.
- No real quotation approval, external sending, threshold, self-approval exception or emergency override.
- Draft work must be restricted server-side to local/testing environments and existing authorized identity scopes. UI hiding alone is insufficient.
- Use isolated synthetic databases; preserve the current preview and unrelated local changes.
- No production activation, deployment, external provider or real financial commitment.
- This exception expires on owner revocation, completion of this bounded development work, or adoption of the formal approval policy, whichever happens first. Re-evaluate before any integration/production activation; it never transfers automatically to those gates.

## Registered delivery slices

| Task | Scope | Dependencies |
| --- | --- | --- |
| A1-CRM-018 | Reference lead workspace, activity/notes views and linked Tasks using delivered contracts | A1-CRM-016 |
| A1-CRM-019 | Scoped private CRM files, upload/download and reference file workspace | A1-CRM-018 |
| A2-SALE-011 | Synthetic local quotation drafts, exact calculations and PDF preview; approval/sending denied | A1-CRM-019, A0-ARCH-902, A0-SEC-002 |
| A1-CRM-020 | Synthetic local opportunity pipeline and integrated six-screen reference acceptance | A2-SALE-011 |

SALE-011 is a draft-development slice, not completion or replacement of SALE-001–010,
their approval-dependent contracts or A1-VINT-101. No dependency or decision gate is removed.

## Acceptance and test design

Reuse the shared shell, tokens, controls, table/pagination, dialog/drawer and existing
CRM/Tasks services. Compare actual screenshots with the six originals at 1672x941;
record browser-reported dimensions, remaining differences, and data differences.
Check Arabic RTL, English LTR, light/dark, mobile and keyboard behavior.

New behavior requires RED/GREEN tests for authorization, cross-scope denial, stale
edits, bounded pagination, file safety and exact monetary arithmetic. Draft approval
and sending must fail server-side. Runtime/database changes remain serialized by
the coordinator. Independent tests/reviews use gpt-6-luna/xhigh, exact file ownership
and isolated worktrees. No silent model fallback. Passing functional tests is not
pixel-parity evidence. Each slice requires independent review before completion.

Source registration uses the existing coordinator lock, pending-intent check,
hash-guarded atomic writes and DAG/reverse-edge validation. Historical task state,
OPEN-02 and unrelated approvals remain unchanged. Initial audit/review found the
six surfaces absent or partial; no all-screen completion claim is justified.

## CRM-018 local implementation checkpoint

- Added separate scoped Activities, Notes and linked Tasks pages, shared record tabs,
  search/type filters, bounded stable pagination, note detail selection and existing
  activity/follow-up creation dialogs. No new task status or activity edit contract.
- Skills used: `.agents/skills/ponytail/SKILL.md`,
  `.agents/skills/laravel-best-practices/SKILL.md`,
  `.agents/skills/laravel-tdd/SKILL.md`,
  `.agents/skills/systematic-debugging/SKILL.md`, and installed
  `C:/Users/N/.codex/skills/{laravel-ui-ux-minimal,test-driven-development,dispatching-parallel-agents,using-git-worktrees}/SKILL.md`.
- Luna X High `reference_gap_review` owned only
  `tests/Feature/CRM/Presentation/CrmReferenceWorkspaceTest.php` in the isolated
  `crm-reference-workspace` worktree. Coordinator integrated and ran it serially.
  RED: missing routes/service; GREEN: 5 tests, 211 assertions. Existing
  `CrmPagesTest`: 11 tests, 155 assertions. Independent source review: no findings.
- Browser/UI: Arabic desktop Activities/Tasks, English Notes at 390px, light/dark,
  one h1 and no document overflow; activity dialog opens and Escape closes it.
  Requested 1672x941; browser reported 1672x942. Screenshot:
  `.local/reference-proof/tasks-workspace.png` (local ignored artifact).
- Design-system check passed. Pint applied only the four owned PHP files.
- This is local source/test and browser evidence, not hosted CI or production proof.
  Sidebar/header proportions, full task metadata and six-screen visual parity remain
  unfinished and belong to integrated acceptance. No commits or deployment made.

## CRM-019 local implementation checkpoint

- Private CRM files table, scoped HTML list/search/category filters, bounded25/100
  pagination, multipart upload and authorized streamed attachment downloads.
  Existing lead version/audit advances on upload; archived/stale changes fail409.
  Uploads validate extension and MIME, maximum50MB, description/category/name bounds.
  No public links or storage paths in returned metadata; downloads use nosniff.
- Coordinator-owned `CrmFileBoundaryTest`: RED missing route404, GREEN401.
  Luna X High owned only `CrmFilesTest.php` in the same isolated worktree; its first
  integrated run found two expectation mismatches (999 rejected vs capped; forced
  attachment MIME). Corrected to existing bounded paging and safe download contract.
  Final files suite5 tests192 assertions; all CRM presentation31 tests627 assertions.
  `LeadDirectory.php` PHPStan level8 PASS; design-system PASS; scoped Pint PASS.
- Independent Luna source review: no concrete authorization/storage/validation defect.
  Separate SQLite database `.local/reference-proof/runtime.sqlite` migrated and seeded
  with synthetic small demo data; isolated server8788. Existing8773 database untouched.
  Files empty page rendered in Arabic desktop without document overflow; screenshot
  `.local/reference-proof/files-empty.png`. Upload/download behavior tested via HTTP
  feature tests with fake private storage; browser upload interaction not yet tested.
- No commits, external sending, approval activation or deployment. File quotas are not
  invented; UI reports actual scoped stored bytes. Full visual acceptance remains020.

## SALE-011 bounded draft checkpoint

- Synthetic quotation create/edit/list/detail and printable HTML preview reuse CRM
  identity/lead scope, shared shell and components. Default-disabled
  `ARASYA_SYNTHETIC_SALES` switch plus testing environment required. Production is
  denied; explicit approve/send routes always deny403 after authorized lookup.
- Exact BigDecimal arithmetic uses explicit quantity, unit price, discount amount
  and tax rate. Six-place input bounds and discount-before-tax are synthetic draft
  assumptions only; no production rounding, thresholds or financial postings.
  Preview is browser Print/Save-as-PDF, not a server-generated PDF attachment.
- Luna X High sales_contract_review owned only
  `tests/Feature/Sales/QuotationDraftTest.php` in the isolated worktree. Coordinator
  money boundary test RED missing service then GREEN; delegated fixtures corrected
  to encrypted CRM contacts. Independent reference_gap_review found sparse line-key
  collision; regression RED keys0,2, fix normalizes saved/presented lines, GREEN.
  Independent re-review confirmed fix; no remaining source findings.
- Sales8 tests59 assertions; integrated CRM+Sales39 tests686 assertions; Sales
  PHPStan level8 PASS. Browser isolated8788 created a synthetic quotation, exercised
  add/remove line and save, and rendered preview subtotal20.50 discount0.50 tax3
  total23.00SAR. English mobile390px has no document overflow; screenshot
  `.local/reference-proof/quotation-preview.png`. Browser connection recovered by
  opening a dedicated check tab. Full six-screen comparison remains020.
- OPEN-02 unresolved; no approval policy or external sending enabled. No commits,
  deployment, hosted CI or production acceptance. Installed decimal dependency
  reused; unrelated Composer/Telescope changes preserved.

## CRM consistency and authorization performance checkpoint (2026-10-19)

Reviewed implementation on feature/A1-CRM-020-consistency: 1690db2 pagination,
c43b50b shared shell, ec23442 request-local authorization, 6161559/fe30877 file and
opportunity extraction, 282144b shared activity/lead selectors and browser regressions.
Only these deltas were applied locally; existing dirty work and the index were preserved.
Isolated MariaDB: 540 tests, 37466 assertions, one SQLite-only skip. Isolated browser:
4 performance and 6 CRM cases passed; query-retention request/href assertions passed
an additional two-case review rerun. Full branch PHPStan, scoped Pint and design checks
passed. Independent Luna X High review has no remaining findings.
Main integration remains pending: unrelated active-module PHPStan errors (14),
MariaDB13317 connection refused during the full integration suite, and main browser
6 passed/1 timeout/3 not run at its bounded global timeout. TaskCycle N+1 remains
blocked by the actual Tasks004 lease; the promise to coordinate handoff is not a handoff.
See [full performance evidence](request-authorization-performance.md) for raw artifact
paths, security invariants, measured timings, failures, ownership and resume steps.

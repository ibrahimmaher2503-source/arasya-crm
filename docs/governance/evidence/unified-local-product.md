# Unified local product — 2026-10-18

Owner request: consolidate existing project versions and all implemented pages.
This supersedes the stopped CRM candidate as the local integration handoff; it
does not grant production acceptance or declare unfinished modules complete.

## Integration and preservation

Audited all **37 registered Git worktrees** and local branches. The existing
Foundation, Access, Tasks, Employees, reporting and historical domain work is
already represented in main by original commits, equivalent patches or later
implementations. The three exceptional old patch IDs are already represented:
CRM lead event intent is present, the Employees patch adds only a trailing blank
line, and KPI01 source/tests are identical. The only missing source file in old
dirty worktrees is the obsolete `a0-core-900/resources/css/rtl-shell.css`: it
defines an alternative token palette, superseded by the canonical design system.
It remains preserved at its original path and is deliberately not activated.
Old dirty documentation, dependencies, secrets, local databases and generated
files were not copied over current source. No worktree was deleted or reset.

The latest CRM candidate is preserved in `2ee1d67`, then integrated as `9d94e51`
on `codex/unified-local-product` after merging main into the existing managed
QA worktree. CRM UI/browser/PHP worker commits are already incorporated in that
candidate; replaying their obsolete versions would lose newer repairs.
Original candidate HEAD: `5ee78d29afab1859048523f696061078ce79892f`.
Starting canonical HEAD: `1c4497c140303c522983cbc0ed8cc69c6ef4363d`.
The independently added `c89fbb4` documentation checkpoint is also preserved.
Resolve final integration SHA with `git log -1 --format=%H --merges main`.

Inventory: ignored canonical `.local/windows-qa/unified-worktree-inventory.json`.
No new workers were dispatched: the owner explicitly required solo execution.
Older workers did not review this integration. No independent review or lifecycle
DONE approval is fabricated; A1-CRM-017 remains partial after lease release.

## Pages in the consolidated application

- Dashboard: `/dashboard`.
- Access overview, organization, users, user detail, roles, role detail and audit.
- Employees directory, create/edit/detail and my workers.
- Tasks directory, create/detail and existing workflow/evidence/assistant preview.
- CRM overview, lead directory, quick create, full create/edit/detail, assignment,
  activities, transfer decisions, conversion, archival and audited contact reveal.
- Existing local design-system showcase, with no business execution authority.

All pages retain the same shell, permission filtering, tokens and real server
contracts. CRM shared components, persisted priority migration and bounded scoped
HTML projection are included. No framework, invented metrics, unsupported page,
provider, deployment or external CI was added.

## Executed verification

- Isolated MariaDB `arasya_cycle03_acceptance`, loopback port **13317**, existing
  owned Cycle03 datadir. Shared MariaDB was not reset or migrated.
- `php vendor/bin/phpunit tests/Feature/CRM tests/Feature/Employees tests/Unit
  --do-not-cache-result`: **235 tests, 2438 assertions PASS** after composition.
- `npx playwright test --config=playwright.crm-polish.config.ts`: **12 PASS**,
  desktop/tablet/mobile, Arabic/English, light/dark; validation, stale edits,
  filtering/pagination, role privacy, assignment/activity/transfers/conversion
  and archival. Runtime serialized, one worker.
- Authenticated page smoke: **30 PASS** (15 routes in both locales), including
  Dashboard, all Access top-level pages, Employees/workers/create, Tasks/create,
  CRM/lead directory/create and development showcase.
- `npm run check:design-system`: checker, self-test and JS syntax **PASS**.
- Focused PHPStan controller/service: **PASS** after removing a redundant
  nullsafe operator. Focused Pint applied normal formatting. The old browser
  overview count assertion now uses the actual `Active leads` label.
- Logs and screenshots remain ignored in QA `.local/windows-qa/unified-*`.

The PHPUnit run preceded formatting and the semantically equivalent nullsafe
cleanup; the browser suite ran after formatting. The 30-page smoke used the
owned preview manager promoted to administrator only in the isolated database,
after limited-role tests finished. Existing credential files remain ignored.

## Remaining acceptance boundaries

This is a consolidated **local preview**, not a claim that the full ERP scope is
finished. No viewport-matched pixel parity or independent final review is claimed.
The advanced deals board, customer/account directory and independent activities
screen from references are not implemented routes. Employees coordinated
offboarding and private file contracts remain incomplete as recorded separately.
The complete governed AI Assistant and interactive tutorial/help center remain
mandatory closure requirements and are not delivered by merging existing code.

CRM overdue aggregation still includes historical activity follow-ups; a current
follow-up/supersession contract is needed before using it as production evidence.
Priority is editable in quick create, but a dedicated full-form priority editor
is still absent. Docked drawer resizing while open requires further interaction
review. No completion, policy or gate approval was inferred from a merge.

Next: review this consolidated local application with the owner, then address
these bounded gaps through the existing coordinator. Do not restore old worktree
files over the current main or deploy this preview as a production release.

## Canonical merge verification

Main was fast-forwarded to integration commit
`230a4875c34a13cc50640e69cfd67a44a3c6c4f0`. The local HTTP service on port 8773
now serves **C:/projects/new_arasya-crm/public**, not an older worktree. The
30 authenticated page/locale checks passed again against that canonical server.
Full documentation validation passed (272 tasks, 124 PRD IDs, 607 links), and
the coordinator fixture suite passed (38 events). A1-CRM-017 was released through
the supported workflow: ready, no lease, no recovery; unfinished acceptance is
still explicitly partial. Git status was clean after the source merge.

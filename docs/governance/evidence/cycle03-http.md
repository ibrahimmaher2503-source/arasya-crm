# Cycle03 Phase 2 — CRM HTTP integration

Owner directive: CRM Phase 2/6, 2026-10-17. Bounded local synthetic DEVELOPMENT
only, following [Phase 1](cycle03-persistence.md), source commit
`b7edfbd49861ca1b65e786557f5c242a1b4fa89c`. CRM013/014 remain done; their
implementation and completed independent reviews are preserved.

Task `A1-CRM-015` registered using the existing Cycle03 lock/hash/DAG registration
precedent, reviewed independently by `phase2_registration_review`. The one-off
registration uses task-loop helpers, pending intent and scope conflict checks,
semantic preservation of all old task fields except CRM014's required reverse
edge, and atomic expected-hash replacement. Supported Claim committed under
`cycle03-coordinator`; no state/history/decision/approval rewrite.

BACKLOG defect: existing primary index omitted 25 ledger tasks and had 42 stale
status cells. Adding CRM015 made 26 missing rows. Source-derived missing rows
were inserted under the coordinator lock with pending/hash guards; supported
RefreshBacklog then passed. tasks.json remains authoritative. Existing appendix
tables and unrelated content were preserved.

## Ownership and delegation

Root AGENTS read first. Independent test authoring assessed before test edits:
three disjoint self-contained test files, separate native managed worktrees;
only coordinator executes database-mutating checks, sequentially. Explicit
`model=gpt-6-luna`, `reasoning_effort=xhigh`, `fork_turns=none` accepted for each
worker; no silent substitution or recursive delegation.

- `phase2_registration_review`: read-only registration/projection review.
- `phase2_http_tests`: `tests/Feature/CRM/Http/LeadDirectoryHttpTest.php`.
- `phase2_security_tests`: `tests/Feature/CRM/Http/LeadSecurityHttpTest.php`.
- `phase2_workflow_tests`: `tests/Feature/CRM/Http/LeadWorkflowHttpTest.php`.
- `phase2_http_implementation`: `app/Http/Controllers/CrmLeadController.php`,
  `app/Http/Middleware/CrmHttpBoundary.php`, `routes/crm.php`.
- `phase2_technical_review`: independent read-only final technical/security review.
- Coordinator: CRM service pagination, routes/web.php/bootstrap integration, lifecycle,
  central runtime, documentation, final verification and integration.

Worktrees are based at Phase1 SHA. Untracked runtime/bootstrap/config absent from
HEAD stays in the main checkout; workers read it as reference and author only
owned files in isolation. No dependencies or foreign changes copied into Git.
Dashboard, Design System, Identity and shared shell files are outside this task.

## Verification checkpoint

Baseline `php vendor/bin/phpunit tests/Feature/CRM/Runtime --do-not-cache-result`:
exit0, 25 tests / 239 assertions, 18.455s, dedicated Windows-native MariaDB
11.4.13 on port13317 using ignored Cycle03 credentials. Shared13316 untouched.

## Implemented HTTP contract

15 web-session JSON routes, all reusing LeadDirectory:
GET/POST `/crm/leads`, GET/PATCH `/crm/leads/{lead}`;
POST `/crm/leads/{lead}/archive`, `/assignment`, `/reveal`, `/activities`,
`/transfers`, `/convert`; GET/POST `/crm/taxonomy`; GET `/crm/recipients`;
GET `/crm/transfers/inbox`; POST `/crm/transfers/{transfer}/response`.
Create/taxonomy/activity/transfer return201; other successful operations200.
Authentication401, authorization403, scoped missing404, stale/duplicate409,
CSRF419, invalid input422, unexpected failure500 are verified as HTTP responses.

Lead/taxonomy/recipient/inbox pages use `data` plus `meta` with page/per_page/
total/last_page; defaults25, maximum100. Detail activities use the same limits
and `activities_meta`. Scope precedes count/projection; stable sort uses ID as
tie-breaker. Recipient effective grants filter before eligible count/page.
Search is restricted to allowed non-contact fields. Unknown fields reject;
only transport `_token`/`_method` are removed from mutation bodies. Query data
does not become mutation payload. Existing runtime APIs remain compatible.

CRM-only middleware is prepended globally before route matching and web CSRF; bootstrap ownership was
added through supported Release, guarded scope amendment and Claim. Non-CRM
requests pass through unchanged. No CSRF exclusion. Error responses are JSON,
sanitized even when debug enabled and carry private/no-store, as do successes.
Persisted session/grants reloaded under the original company transaction lock;
no caller-supplied identity or new domain layer.

## TDD and verification

- Full initial HTTP RED:24 tests/24 assertions,24 expected missing-route404
  failures,5.020s. Before that directory7/7 failures and security5/5 failures;
  workflow8/8 failures separately reproduced before integration.
- Initial integrated24 run:943 assertions,one archived query string422 failure.
  Root cause: service's boolean validator accepts bool/0/1, while URL sent
  true/false. Paginate normalizes exactly those strings; focused GREEN1 test /
  17 assertions,2.446s. Invalid alternatives still reject.
- Independent review found unbounded nested detail activities. Added test first:
  RED1 test/3 assertions missing metadata,2.081s; then bounded query+metadata:
  GREEN1 test/26 assertions,2.277s. No repeat of CRM014 implementation/review.
- Final `php vendor/bin/phpunit tests/Feature/CRM/Http
  tests/Feature/CRM/Runtime --do-not-cache-result`: exit0,52 tests /
  1242 assertions,11.518s. Includes27 HTTP and25 preserved runtime tests.
  Late QueryExecuted failure after actual activity insert verifies rollback of
  TaskCycle row/task audit/activity/lead version/CRM and canonical audit.
  Other coverage: recipient stale scope/grants, single acceptance, duplicate
  transfer, conversion retry/audit once, reveal encrypted reason, no contact
  values in other responses, forged/stale/inactive sessions, explicit deny,
  admin reveal denial, no Accept header JSON, CSRF419 no writes and valid token201.
- `php vendor/bin/phpstan analyse app/Modules/CRM/Application/Runtime
  app/Http/Controllers/CrmLeadController.php app/Http/Middleware/CrmHttpBoundary.php
  --no-progress`: exit0,no errors. Initial cursor argument count fixed using
  installed framework signature (`select()->cursor()`); query input now uses
  typed query bag `all()`. No suppressions/baseline changes.
- `php vendor/bin/pint --test app/Modules/CRM/Application/Runtime
  app/Http/Controllers/CrmLeadController.php app/Http/Middleware/CrmHttpBoundary.php
  routes/crm.php routes/web.php bootstrap/app.php tests/Feature/CRM/Http
  tests/Feature/CRM/Runtime`: exit0,passed.
- `php artisan route:list --path=crm --except-vendor`: exit0,15 routes.
- Adjacent `php vendor/bin/phpunit tests/Feature/Tasks tests/Feature/Access
  --do-not-cache-result`: exit0,80 tests/806 assertions,33.099s before final
  middleware placement repair; final combined rerun follows below.
- `./scripts/test-task-loop.ps1`: exit0,38 events.
- `./scripts/validate-docs.ps1`: exit1,same six existing skill index broken
  links security/migrations/events-notifications/mail/scheduling/config.
  Actual installed security.blade.php read; no unrelated skill repairs.

Initial extra no-Accept test used assertJson without required argument; this was
a test API error, corrected to assertJsonStructure and focused GREEN1/10.
No production defect or waived failing assertion is hidden by that correction.

Independent technical review identified two confirmed HTTP defects, both fixed
through regression-first changes: nested detail activities were unbounded; and
web-group middleware omitted route-matching404/405 sanitization. Routing
regression RED1 test/2 assertions,1.967s (no-store absent), then global CRM-only
prepend GREEN routing+CSRF2 tests/42 assertions,2.107s. Non-CRM passthrough remains.
No confirmed designated-contact/reason plaintext logging leak was found; reviewer
confirmed encryption occurs before SQL binding. Framework diagnostics remain.

Final combined verification after both review repairs:
`php vendor/bin/phpunit tests/Feature/CRM/Http tests/Feature/CRM/Runtime
tests/Feature/Tasks tests/Feature/Access --do-not-cache-result`: exit0,
**133 tests / 2075 assertions**,16.736s. This includes28 HTTP,25 runtime,
80 adjacent Tasks/Access tests, run serially on the verified disposable runtime.
Final scoped PHPStan and exact Pint command above both exit0. This is focused
feature integration evidence, not Browser E2E or consolidated ERP acceptance.

## Skills actually used

- `C:/projects/new_arasya-crm/.agents/skills/ponytail/SKILL.md`.
- `C:/projects/new_arasya-crm/.agents/skills/laravel-best-practices/SKILL.md`,
  rules/routing.md,validation.md,architecture.md,error-handling.md,
  db-performance.md,security.blade.php.
- Official installed `C:/Users/N/.codex/plugins/cache/openai-curated-remote/superpowers/6.4.2/skills/`:
  dispatching-parallel-agents, using-git-worktrees, test-driven-development
  (and writing-good-tests), systematic-debugging, requesting-code-review
  (and code-reviewer template), verification-before-completion, each SKILL.md.
  No duplicate skill installation. User's focused checks take precedence over
  generic full-suite guidance; consolidated acceptance remains a later phase.

## Boundaries and risks

No frontend redesign, browser E2E, production, real business data, SonarQube,
GitHub Actions or live AI provider. No Cycle03 VERIFIED or consolidated claim.
Assistant tools stay Restricted/disabled, detector activation N/A for this HTTP
adapter; existing human contracts unchanged except bounded read projections.
Recipient exact counts reuse effective Identity permissions per active scoped
user, so grant-query work scales with department size; no invented grant SQL.
Free-text notes/context remain user content, without semantic contact redaction.
Designated contacts/reveal reason are encrypted; standard framework exception
reporting remains, with sanitized HTTP bodies and no raw manual reporting added.
Shared Dashboard/Design System/Identity files preserved; no ownership blocker
was found at claim. UI/browser work needs a separate legitimate Phase3 task.

Commit scope is exactly the CRM service, controller, boundary, CRM route file,
two integration seams (`routes/web.php`, `bootstrap/app.php`), three HTTP tests
and this checkpoint. Bootstrap was previously untracked, so its existing local
foundation is included with the one CRM registration change; this is not a
clean-checkout runtime delivery. Shared dirty tasks/state/BACKLOG/coverage and
all other local foundation files are preserved without sweeping them into the
source commit. Lifecycle history and generated projection remain authoritative
local artifacts; future work must use this working checkpoint.

Independent final verdict `phase2_technical_review`: **accept CRM015 HTTP
candidate**, no remaining blocking technical findings after both repairs.
Reviewer read current working/staged candidate based on Phase1 SHA (not a claim
that Phase1 commit itself contains Phase2). Read-only review; all runtime checks
were coordinator-run. Nonblocking scaling/free-text logging limitations above
retained. Four managed worker worktrees requested archived after integration,
preserving recoverable snapshots; no unrelated checkout removed.

## Final coordinator handoff

Source commit: `ec66d208f170e40baea96e516d4b6844910b1caf`.
Supported Review and Complete both COMMITTED with independent reviewer
`phase2_technical_review`. Final Status CRM015: DEVELOPMENT, saved=done,
lease=none, recovery=none. CRM013/014 preserved done/no lease. RefreshBacklog
passed after completion. No new descendant registered (`CRM015.blocks=[]`).
Phase3 is ready for its own owner-scoped UI task registration/claim and fresh
shared-file checks; no frontend implementation started. Consolidated acceptance,
imports/private attachments and remaining full-cycle boundaries are not approved
or claimed by this phase. The six pre-existing documentation skill links remain
the only documentation validator failures. Source and acceptance checks pass;
no shared-file ownership blocker remains for this completed HTTP slice.

Final authenticated PDO runtime guard matched MariaDB11.4.13, exact cycle03-db
datadir and arasya_cycle03_acceptance database; six CRM tables plus access_users
and tasks_cycle each read back zero rows after transactional tests. SHUTDOWN
requested only on this owned port13317 server. Shared13316 untouched.

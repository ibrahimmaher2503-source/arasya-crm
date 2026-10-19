# ADR-002/003 — Environment and evidence check

## Approved direction and current status

Ibrahim approved the design direction: MariaDB, with database-backed queue/cache and indexed, scoped SQL search. No technology re-selection is requested or needed. This approval does not itself satisfy formal acceptance: the ADR-002 source remains `OPEN`, ADR-003 remains `Proposed`, and both approval records remain `Pending (NOT Accepted)`. No source status or task status is changed here.

## Safe environment discovery

Read-only local probes found no discovered explicitly safe disposable MariaDB target. The probes checked `Get-Command mariadb,mysql,docker,podman`, matching `Get-Service` entries, names only for environment variables matching `MARIADB|MYSQL|^DB_|DISPOSABLE|TEST_DATABASE`, recursive `*compose*.y*ml` files, and the selected settings in `.env.example`. No matching executable, service, variable name, or compose file was returned. `.env.example` specifies `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `QUEUE_CONNECTION=sync`, and `CACHE_STORE=array`. Top-level `.tools` entries are tooling/staging/worktrees/manifests, not a designated database target.

This establishes only that no safe target was discovered locally; it does not prove that no remote server exists. No database connection was attempted, no secret environment values were read, and no service or tool was installed or started. No database/schema was created or deleted; no migration or database test was run. Technical DB execution stops here until an existing target is explicitly designated.

Before DB execution, provide/authorize out of band: endpoint; exact MariaDB version; dedicated synthetic test database(s); least-privilege test credentials; and authorization for fixture/schema writes, concurrent sessions, isolated backup/restore, and cleanup. Do not use production or unspecified databases.

## Tests and remaining evidence

**ADR-002 — all NOT RUN:** `ADR002-RACE-RESERVE`, `ADR002-RACE-ISSUE`, `ADR002-RACE-PERIOD-CLOSE`, `ADR002-MONEY-DECIMAL`, `ADR002-UNIQUE-SCOPE-FK`, `ADR002-IDENTIFIER-COMPAT`, `ADR002-MIGRATION-FORWARD`, `ADR002-BACKUP-RESTORE`. Local/documentary inspection is not a test pass.

**ADR-003 — NOT RUN:** race/concurrency; crash/failure and retry/DLQ/replay; worker drain/recovery; provider failure; authorization recheck and search/count/export scope/masking; and workload/performance tests. No operational, security, or capacity evidence is established.

**OPEN-02 remains open:** obtain authorized operation/role matrix; value basis and discount denominator; business calendar; delegation scope/duration and nondelegable actions; material-change and break-glass allowlists/reviewer; signed policy version; and boundary, SoD, revocation, and escalation tests. Existing directions are not signed financial authorities.

**OPEN-10 remains open:** obtain legally reviewed per-category basis/schedule and triggers; consent/access grants; legal-hold, deletion, and backup/restore semantics; Legal, HR, and Finance signoffs; and synthetic access/revoke/hold/delete/restore tests. No retention period is invented.

No formal ADR acceptance can be recorded on this evidence. Do not run Reassess/Ready or close OPEN-02/OPEN-10 on its basis. The ADRs and approval records remain unchanged.

## 2026-10-17 — superseding local runtime probe

The earlier safe-target discovery above records the state at that time. For this run, Laravel 13's [official database documentation](https://laravel.com/docs/13.x/database) lists MariaDB 10.3+; installed MariaDB 10.4.32 meets that published minimum. This is compatibility eligibility only, not an operational or production selection.

Required probes (exact commands and observed results):

- `php --ri pdo_mysql` — PASS; PDO MySQL enabled, mysqlnd 8.4.21.
- `& 'C:\xampp\mysql\bin\mysqld.exe' --version` — PASS; 10.4.32-MariaDB Win64.
- `& './scripts/validate-docs.ps1'` — PASS; `tasks=239`, `PRD_IDs=124`, `local_links=512`.
- `& './scripts/adr-mariadb-validation.ps1'` — ran in 7.2 seconds on its final attempt. Output confirmed connection to 10.4.32-MariaDB, ULID/decimal-string round-trip, and entity-scoped SQL query. **INCOMPLETE, not a full pass:** expected composite-FK rejection, transaction-rollback, and final `RESULT: PASS` markers were absent even though the PowerShell process returned normally; those assertions are not claimed as passed. Two earlier attempts failed before the fixture and cleaned their owned directories. No further repair was made.

The runner created only a fresh randomly named `.tools/adr-validation/<runId>` datadir and a synthetic database in it, bound MariaDB to `127.0.0.1`; it installed no service and changed no `.env` or application configuration. It stopped its owned server and removed only its marker-verified run directory. Post-run check found zero `mysqld` processes and zero entries under `.tools/adr-validation`. No existing MariaDB data/recovery directory was accessed.

**NOT RUN / not established at that time:** project migrations, production/business schema compatibility, reservation/issue/period-close races, Finance-approved rounding/fixtures, full backup/restore, queue/cache behavior, and operational/security signoffs. The runner output lacked required success markers; it was not a pass.

## 2026-10-17 — corrected authenticated harness run

`& './scripts/adr-mariadb-validation.ps1'` — **PASS, 19 explicit assertions, 11.3 seconds.** Eighteen Laravel fixture assertions plus a separate restored-data assertion each emitted `PASS`, and the launcher required both exact `RESULT` markers/counts and zero exit status. Outcomes: authenticated MariaDB version/port/datadir identity; Laravel `Schema` create/alter/index plus idempotent backfill; synthetic `DECIMAL(20,6)` exact-string round-trip/sum and overflow; scoped unique and composite FK rejection; transaction rollback; ULID PK/FK round-trip; indexed scoped SQL query; separate active PDO sessions demonstrating row-lock contention for synthetic reserve/issue/period-close cases; Laravel database queue job handler failure/release/retry and unique transactional consumer effect; database cache scope separation/invalidation; and dump restore into a newly created owned database with row-count verification.

This corrected run initialized root with a random password, created a random credential and a database-scoped test account, enforced grants (no `--skip-grant-tables`), and confirmed the serving instance's actual loopback port and datadir matched the owned random run. Credential files were ACL-restricted within the run directory; no credentials were printed or retained. The test account was granted only its run-owned database and restore database. The runner removed the marker-verified run directory and stopped its own server. Post-run check found zero `mysqld` processes and zero run entries. It did not touch existing MariaDB data/recovery directories, install a service, or alter application configuration.

The earlier unauthenticated/incomplete harness output remains historical and is superseded by this authenticated run; an intervening dump attempt failed with client error 2002 because it omitted the random port, then was corrected and rerun successfully.

**Still NOT RUN / not established:** the eight ADR-002 business acceptance cases as written; real project migrations (none are present); actual concurrent business reserve/issue/period-close semantics (the lock tests are synthetic contention only); Finance-approved rounding, fixtures, and business precision; business-level outbox/inbox integration; queue DLQ/replay, crash recovery, worker drain, provider-failure behavior; authorization recheck or search/count/export masking; production workload/performance/security/retention; and operational RPO/RTO. Dump/restore proves only a local synthetic logical restore, not an operational recovery drill. The fixture creates no business policy or production implementation. Governance approvals and Finance/Operations/IT/Security/Legal signoffs remain pending; ADR status, task dependencies, and task state remain unchanged.

## Stop-and-preserve review — no full-suite rerun

The coordinator stopped the final correction at the user's request and recovered the files. The 19-assertion/11.3-second result above is the preceding worker's recorded result, not an independently reproduced result for the final edited harness. Raw run-local logs/dumps were removed by cleanup; the evidence summary and runner remain. The final correction published no completion report.

Source review confirms random root/test credentials, a test account granted only the run-owned databases, loopback binding, restricted credential files, no authentication bypass, server `--no-defaults`, and client `--defaults-file` isolation. False assertions throw; unexpected native database errors are rethrown, expected negative cases require codes 1062/1452/1264/1205, and the launcher requires exact assertion counts/result markers plus zero exits. These final database error-code checks have not been independently executed. A database-free coordinator probe extracted the actual `$check` helper, called it with `false`, and observed `EXPECTED_ASSERTION_FAILURE` and exit 1 (probe wrapper exit 0): failure propagation confirmed for that helper only.

Read-only `Get-Process -Name mysqld,mariadbd -ErrorAction SilentlyContinue` and directory listing of `.tools/adr-validation` found no matching server process and no run entries. No database connection, setup, cleanup mutation, or full validation rerun was performed during this review. Tools responded successfully during recovery; the reported earlier `fetch failed` condition was not reproduced. Overall validation remains PARTIAL; ADR-002/003 are not accepted on this evidence.

## 2026-10-17 - bounded synthetic validation failure

FAIL: INIT_EXIT_NONZERO. Raw output and credentials are not included. The failed run remains NOT ACCEPTED.
Completed sanitized results:

Coordinator review: the final implementation worker hit its 20-minute limit without a completion report. Two correction dispatches were used; no further repair or runtime retry was attempted. The exact command `& './scripts/adr-mariadb-validation.ps1'` exited 1 during fresh authenticated datadir initialization, before server startup or any fixture assertions. This batch has one database infrastructure failure, not two repeated failures; the initializer's underlying native diagnostic was not preserved, so its root cause is not established. A suspected PowerShell syntax blocker did not prevent execution and is not the observed failure.

| Criterion | Result | Evidence / remaining blocker |
|---|---|---|
| Disposable harness initialization | FAIL | `INIT_EXIT_NONZERO`; no fixture ran. |
| ADR002-RACE-RESERVE | NOT TESTED | Independent-process fixture added but not reached; same-key replay currently checks existence rather than invoking the operation again. |
| ADR002-RACE-ISSUE | NOT TESTED | Independent-process fixture added but not reached. |
| ADR002-RACE-PERIOD-CLOSE | NOT TESTED | Only sequential examples delivered; both concurrent interleavings still missing. |
| ADR002-MONEY-DECIMAL | NOT TESTED | Candidate fixtures not reached; approved Finance precision/scale, excess-scale and rounding fixtures/signoff still missing. |
| ADR002-UNIQUE-SCOPE-FK | NOT TESTED | Fixture not reached; concurrent tuple race and approved cross-entity business-key policy still missing. |
| ADR002-IDENTIFIER-COMPAT | NOT TESTED | UUIDv7/ULID database fixtures not reached; model/route-binding/serialization/relationship coverage missing. |
| ADR002-MIGRATION-FORWARD | NOT TESTED | Synthetic schema/backfill fixtures not reached; comparison covers names rather than complete schema equivalence and lacks operation lock/downtime evidence. |
| ADR002-BACKUP-RESTORE | NOT TESTED | Restore not reached; operational encryption/permissions, repeatable reconciliation and owner-defined RPO/RTO remain required. |
| Transaction rollback/isolation | NOT TESTED | New isolation block references adrv_journals/adrv_outbox before their creation; fixture ordering requires correction before future execution. |
| ADR003-34 source of truth and nonauthoritative cache/search | NOT TESTED | No fixture reached; application acceptance remains separate. |
| ADR003-35 atomic domain/outbox and inbox/effect, dedup and out-of-order reconciliation | NOT TESTED | Stronger synthetic rollback/version fixtures added but not reached; publisher/consumer race coverage still absent. |
| ADR003-36 current authorization/entity/assignment/purpose/version at execution | NOT TESTED | Direct synthetic revoked read added, not an actual queued authorization test; no fixture reached. |
| ADR003-37 search/suggestions/count/export scope and masking, cache revocation | NOT TESTED | Synthetic scoped/masked read added but not reached; endpoint/suggestion/deletion and queued boundaries incomplete. |
| ADR003-38 bounded retries, sanitized DLQ, authorized/audited fixed-ID replay, stale/out-of-order reconciliation, provider ambiguity | NOT TESTED | Queue fixture not reached; provider simulator, audited replay and complete reconciliation still missing. |
| ADR003-39 stop intake, in-flight completion or safe termination, timeout/lease and deployment drain | NOT TESTED | Queue fixture not reached; stop-when-empty alone does not prove deployment drain with in-flight work. |
| ADR003-47 enqueue/publish races and concurrent projection versions | NOT TESTED | No fixture reached; inventory races do not substitute for publisher/projection contention. |
| ADR003-47 duplicate/out-of-order, changed/revoked authorization | NOT TESTED | Partial synthetic probes not reached; queued authorization boundary remains absent. |
| ADR003-47 crashes before/after commit, publish-before-inbox, worker kill, DB/search outage, drain | NOT TESTED | Queue helper added but not reached; commit/publish crash and outage probes incomplete. Redis is outside selected baseline. |
| ADR003-47 retry/DLQ/replay, index rebuild and ambiguous reconciliation | NOT TESTED | No fixture reached; full recovery paths incomplete. |
| ADR003-47 provider rejection/timeout/success with lost response | NOT TESTED | No local provider simulator implemented; no external API used. |
| ADR003-47 scope/masking/revocation/hiding/deletion leakage | NOT TESTED | Partial direct-read fixtures not reached; complete application boundaries absent. |
| ADR003-47/53 workload and capacity | NOT TESTED | Approved workload, concurrency, backlog/lag, availability and RPO/RTO targets missing; no invented benchmark targets. |
| ADR003-54 security review | NOT TESTED | Formal threat/configuration/TLS/encryption/logging review and Security signoff missing; source isolation review is not production acceptance. |
| ADR003-55 retention/recovery | NOT TESTED | OPEN-10 schedules, deletion/hold/restore semantics, replay authority and Legal/Operations signoffs missing. |

Checks independently run: `git diff --check -- scripts/adr-mariadb-validation.ps1 scripts/adr-validation docs/governance/evidence/ADR-002-003-environment-check.md` exited 0 (untracked content is not covered by git diff); `php -l scripts/adr-validation/concurrent-race.php` and `php -l scripts/adr-validation/queue-lifecycle.php` each reported no syntax errors. Read-only process/directory inspection found `MARIADB_PROCESS_COUNT=0` and `VALIDATION_RUN_ENTRY_COUNT=0`. No raw credentials/logs/dumps were retained. Source also retains unbounded initializer/client and main PHP waits; these require bounded handling before another authorized run. Source PASS strings are conditional future output, not observed evidence.

Neither ADR is ready for formal acceptance. Missing formal approvals: ADR-002 Engineering + Finance acceptance (Engineering design-direction approval already exists), Finance OPEN-03 policy/fixtures and Operations restore/version/RPO/RTO approval; ADR-003 Engineering + IT acceptance (Engineering design direction already exists), Security review/signoff, Legal/Operations retention/replay/recovery signoffs, and OPEN-11 owner-approved workload/capacity targets. No ADR/task/dependency state changed and no tasks became newly eligible. Smallest executable next action is a separately authorized, bounded diagnostic of the initializer's supported isolated options with sanitized native error capture, followed by the identified fixture/safety repairs; no retry is authorized by this record.


## 2026-10-17 - bounded synthetic validation failure

FAIL: VALIDATION_FAILED. Raw output and credentials are not included. The failed run remains NOT ACCEPTED.
Completed sanitized results:
PASS: authenticated owned server identity/version/port/datadir
PASS: forward-compatible schema alter and initial backfill
PASS: backfill replay is idempotent
PASS: forward schema and unique index visible through Laravel Schema
PASS: synthetic prior-upgraded and empty-built schemas match with rows preserved
PASS: interrupted forward backfill resumes and replay remains idempotent
PASS: schema_match ddl=CREATE TABLE `adrv_forward` ( `id` char(26) NOT NULL, `legacy_value` varchar(255) NOT NULL, `normalized_value` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `adrv_forward_norm_uq` (`normalized_value`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci build_and_compare_seconds=0.804464 recovery_seconds=0.010224
PASS: ULID PK/FK round-trip and synthetic exact decimal string
PASS: Laravel UUIDv7 PK/FK round-trip
PASS: indexed entity-scoped SQL query returns no cross-scope row
PASS: synthetic decimal sum remains exact string (precision 20, scale 6)
PASS: scoped idempotency unique rejects duplicate with MariaDB 1062
PASS: composite foreign key rejects parent from other entity with MariaDB 1452
PASS: synthetic decimal precision overflow rejects with MariaDB 1264 under strict mode
PASS: Laravel transaction rollback
PASS: two independent connections serialize synthetic reserve lock
PASS: two independent connections serialize synthetic issue lock
PASS: two independent connections serialize synthetic period-close lock


## 2026-10-17 - bounded synthetic validation failure

FAIL: VALIDATION_FAILED. Raw output and credentials are not included. The failed run remains NOT ACCEPTED.
Completed sanitized results:
PASS: authenticated owned server identity/version/port/datadir
PASS: forward-compatible schema alter and initial backfill
PASS: backfill replay is idempotent
PASS: forward schema and unique index visible through Laravel Schema
PASS: synthetic prior-upgraded and empty-built schemas match with rows preserved
PASS: interrupted forward backfill resumes and replay remains idempotent
PASS: schema_match ddl=CREATE TABLE `adrv_forward` ( `id` char(26) NOT NULL, `legacy_value` varchar(255) NOT NULL, `normalized_value` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `adrv_forward_norm_uq` (`normalized_value`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci build_and_compare_seconds=0.142698 recovery_seconds=0.008803
PASS: ULID PK/FK round-trip and synthetic exact decimal string
PASS: Laravel UUIDv7 PK/FK round-trip
PASS: indexed entity-scoped SQL query returns no cross-scope row
PASS: synthetic decimal sum remains exact string (precision 20, scale 6)
PASS: scoped idempotency unique rejects duplicate with MariaDB 1062
PASS: composite foreign key rejects parent from other entity with MariaDB 1452
PASS: synthetic decimal precision overflow rejects with MariaDB 1264 under strict mode
PASS: Laravel transaction rollback
PASS: two independent connections serialize synthetic reserve lock
PASS: two independent connections serialize synthetic issue lock
PASS: two independent connections serialize synthetic period-close lock
PASS: independent connection cannot see uncommitted domain/outbox rows and sees both after commit
PASS: synthetic reserve transition, same-key replay, and rejected request have one persisted effect
PASS: synthetic issue transition emits one movement/outbox and decrements stock once
PASS: synthetic period close ordering posts before close and rejects afterward without journal/outbox
PASS: domain journal and outbox writes both roll back atomically
PASS: inbox/effect commit atomically and fixed event id replay deduplicates
PASS: failed consumer effect rolls back its newly inserted inbox row
PASS: version 3 then version 2 delivery keeps projection current and does not repeat effect
PASS: Laravel database queue job handler retries and commits unique consumer effect
PASS: transactional unique consumer effect deduplicates replay with MariaDB 1062
PASS: database cache stores isolated scoped keys
PASS: database cache forget preserves unrelated scope
PASS: synthetic scoped search/count/export excludes other entity and hidden rows and masks contact fields
PASS: revoked queued access is denied at execution despite stale allow cache
PASS: all generated synthetic index/FK/constraint names are ASCII and at most 60 bytes


## 2026-10-17 - bounded synthetic validation failure

FAIL: QUEUE_FIXTURE_ASSERTION_OR_CHILD_FAILURE. Raw output and credentials are not included. The failed run remains NOT ACCEPTED.
Completed sanitized results:
PASS: authenticated owned server identity/version/port/datadir
PASS: forward-compatible schema alter and initial backfill
PASS: backfill replay is idempotent
PASS: forward schema and unique index visible through Laravel Schema
PASS: synthetic prior-upgraded and empty-built schemas match with rows preserved
PASS: interrupted forward backfill resumes and replay remains idempotent
PASS: ULID PK/FK round-trip and synthetic exact decimal string
PASS: Laravel UUIDv7 PK/FK round-trip
PASS: indexed entity-scoped SQL query returns no cross-scope row
PASS: synthetic decimal sum remains exact string (precision 20, scale 6)
PASS: scoped idempotency unique rejects duplicate with MariaDB 1062
PASS: composite foreign key rejects parent from other entity with MariaDB 1452
PASS: synthetic decimal precision overflow rejects with MariaDB 1264 under strict mode
PASS: Laravel transaction rollback
PASS: two independent connections serialize synthetic reserve lock
PASS: two independent connections serialize synthetic issue lock
PASS: two independent connections serialize synthetic period-close lock
PASS: independent connection cannot see uncommitted domain/outbox rows and sees both after commit
PASS: synthetic reserve transition, same-key replay, and rejected request have one persisted effect
PASS: synthetic issue transition emits one movement/outbox and decrements stock once
PASS: synthetic period close ordering posts before close and rejects afterward without journal/outbox
PASS: domain journal and outbox writes both roll back atomically
PASS: inbox/effect commit atomically and fixed event id replay deduplicates
PASS: failed consumer effect rolls back its newly inserted inbox row
PASS: version 3 then version 2 delivery keeps projection current and does not repeat effect
PASS: Laravel database queue job handler retries and commits unique consumer effect
PASS: transactional unique consumer effect deduplicates replay with MariaDB 1062
PASS: database cache stores isolated scoped keys
PASS: database cache forget preserves unrelated scope
PASS: synthetic scoped search/count/export excludes other entity and hidden rows and masks contact fields
PASS: revoked queued access is denied at execution despite stale allow cache
PASS: all generated synthetic index/FK/constraint names are ASCII and at most 60 bytes
PASS: concurrent reserve4 race has one reservation/outbox and replay has no extra effect
PASS: concurrent issue4 race has one movement/outbox and remaining/on_hand each one
PASS: owned Laravel worker killed during leased job; expired lease redelivered once and effect committed
PASS: bounded failing job moved to database failed_jobs

## 2026-10-17 - bounded synthetic validation batch  Runner assertions: 32 Laravel fixture assertions, two independent-process races, three Laravel queue worker assertions, and three isolated restore checks. Synthetic fixture results do not establish application or ADR acceptance.  Per-criterion status:
ADR002-RACE-RESERVE: PASS synthetic only — two PHP processes synchronized at bounded barrier race reserve4 against available4; one reservation/outbox, loser no effect, winner key replay has no extra effect. Application/business acceptance NOT TESTED.
ADR002-RACE-ISSUE: PASS synthetic only — two synchronized PHP processes race issue4 against reserved5; one movement/outbox and remaining/on_hand each one. Application/business acceptance NOT TESTED.
ADR002-RACE-PERIOD-CLOSE: NOT TESTED — sequential order examples and independent-connection uncommitted visibility PASS; competing post/close lock interleavings and exact FIN_PERIOD_CLOSED concurrency remain untested.
ADR002-MONEY-DECIMAL: PASS candidate DECIMAL(20,6) storage/sum/overflow only; Finance precision/scale and OPEN-03 rounding policy NOT TESTED.
ADR002-UNIQUE-SCOPE-FK: PASS synthetic scoped unique and cross-entity FK negatives; concurrent source/idempotency tuple and owner business-key policy NOT TESTED.
ADR002-IDENTIFIER-COMPAT: PASS synthetic ULID and UUIDv7 PK/FK database round trips plus ASCII <=60 synthetic index/constraint names; Laravel model/route/serialization/relation and target-driver compatibility NOT TESTED.
ADR002-MIGRATION-FORWARD: PASS synthetic schema/backfill only — empty-built and prior-upgraded column/index metadata compared; interrupted resumable backfill checked; generated DDL and recovery timing captured. Actual project migration acceptance NOT TESTED.
ADR002-BACKUP-RESTORE: PASS synthetic restore only — all listed fixture table counts, FK/unique metadata, and stock/movement/outbox/inbox/projection reconciliation checked. Encryption, operational permissions, RPO/RTO and operational restore NOT TESTED.
ADR003-34 source-of-truth/cache/search: NOT TESTED — no application behavior tested; only local synthetic scopes and cache primitives.
ADR003-35 atomic outbox/inbox/reconciliation: PASS synthetic only — domain journal and outbox rollback together; failed consumer effect rolls back inbox; fixed event replay deduplicates; v3 then v2 keeps projection at v3 without re-effect. Application event integration NOT TESTED.
ADR003-36 execution-time authorization: PASS synthetic only — execution query denies revoked permission despite stale positive cache; real Laravel job authorization NOT TESTED.
ADR003-37 search/count/export scope and masking: PASS synthetic SQL fixture — scope, hidden-row exclusion, count, export masking checked; application endpoints and revoked access at execution NOT TESTED.
ADR003-38 bounded retry/DLQ/authorized replay: PASS synthetic Laravel database worker failed_jobs and authorized same-event-id replay; provider rejection/timeout/ambiguous success and network reconciliation NOT TESTED.
ADR003-39 worker lifecycle/drain: PASS synthetic real queue:work child killed during leased job, expired lease redelivered, and stop-when-empty drain; production shutdown/deployment semantics NOT TESTED.
ADR003-47 race: inventory synthetic concurrent independent-process races PASS; enqueue/publish contention and concurrent projection copies NOT TESTED.
ADR003-47 duplicate/out-of-order events: PASS synthetic fixed-id dedup and v3-before-v2 projection reconciliation; concurrent application consumer contract NOT TESTED.
ADR003-47 authorization changed/revoked before job execution: NOT TESTED — no queued job authorization boundary exercised.
ADR003-47 crash before/after commit and publish-before-inbox: NOT TESTED — no kill/crash injection or durable publish lifecycle.
ADR003-47 worker kill during job and stop-when-empty synthetic drain PASS; DB/Redis/search outage and graceful deployment shutdown NOT TESTED.
ADR003-47 bounded retry, failed_jobs/DLQ, authorized same-event-id replay, and v3-then-v2 projection version handling PASS synthetically; projection rebuild and ambiguous-state reconciliation NOT TESTED.
ADR003-47 provider rejection, timeout, and success with lost response: NOT TESTED — no network/provider call or local provider simulator.
ADR003-47 synthetic scoped search/count/export mask and hidden-row/revoked-scope checks PASS; deletion leakage and application endpoint authorization NOT TESTED.
ADR003-47 agreed real-data workload/performance: NOT TESTED — no owner-approved workload or performance targets.
OPEN-11 capacity/workload targets: NOT TESTED — no owner-approved workload, throughput, availability, or RPO/RTO targets.
APPROVALS: ADR-002 Finance review and operations restore input missing; ADR-003 IT approval, independent Security signoff, and Legal/Operations signoffs missing; OPEN-10/11 policy decisions remain open.
OPEN-10 retention/replay/restore policy: NOT TESTED — no retention schedule, legal deletion, or replay governance approval.

Synthetic DDL/timing record (no credentials): 
schema_match ddl=CREATE TABLE `adrv_forward` ( `id` char(26) NOT NULL, `legacy_value` varchar(255) NOT NULL, `normalized_value` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `adrv_forward_norm_uq` (`normalized_value`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci build_and_compare_seconds=0.104462 recovery_seconds=0.005909

Synthetic state-transition cases include reserve/replay/rejection, issue/movement/outbox, atomic rollback, fixed-event-id inbox/effect dedup, version-3 then version-2 delivery, scope masking/revocation, failed-job replay, and queue lease redelivery. Period-close competing interleavings remain NOT TESTED.

Policy blockers remain: Finance OPEN-03 precision/scale/rounding; business cross-entity uniqueness owner policy; OPEN-10 retention/replay/restore and Legal/Operations signoff; OPEN-11 workload and RPO/RTO targets; Engineering/IT/Security approvals and independent Security signoff. ADR-002 approval record still requires Finance review and operational restore input; ADR-003 still requires IT, Security, Legal/Operations approvals as recorded in its decision-approval document.

Synthetic fixture success is not implementation acceptance. ADR statuses and task states are unchanged.


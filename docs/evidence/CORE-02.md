# CORE-02 registered adapter slice evidence — A0-CORE-004

## Boundary and behavior

`app/Modules/Organization/Infrastructure/CORE02/record-linkage-diagnostic.php` is a standalone, read-only CLI for explicitly supplied synthetic identity snapshots. It accepts one JSON object containing exactly `source`, `links`, and `catalog`; each identity contains exactly `kind`, `id`, and `entityId`. The kind is restricted to the ten existing CORE02 `RecordKind` values. The transport caps stdin at 65,536 bytes, JSON nesting at depth 8, each identity field at 128 bytes, and each list at 256 entries. Caller-supplied actor, role, grant, or other fields are rejected.

The adapter performs only transport shape/type/size checks and creates typed domain values. All identity membership, duplicate/conflicting identity, exact kind/ID/entity, dangling-link, and same-entity linkage decisions are delegated to the existing `ProjectRecordLinkage` and `RecordLinkageGuard`. A valid projection emits only `{"decision":"allow","code":"core02_linkage_projected"}`; a well-formed but denied projection emits only `{"decision":"deny","code":"core02_linkage_denied"}`. Malformed JSON, schema, types, IDs, unsupported kinds, excessive size/depth/count, and unknown fields emit the generic Arabic message `تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.` and exit 2. No raw supplied identities, inputs, exceptions, or traces are echoed or logged. Every process invocation evaluates its supplied snapshot anew; changing the catalog to remove a linked identity produces the fixed denial result.

Subprocess acceptance covers both successful projections and wrong-kind denials across all ten allowlisted kinds, plus duplicate/conflicting catalog identities, dangling links, wrong-kind and cross-entity identities, replay and changed-catalog denial. Malformed JSON/schema, wrong types, malformed and overlong IDs, unknown actor/role/grant fields, count/byte/depth caps, and sanitized output/errors are also exercised.

The explicit catalog is only a per-request consistency snapshot. This diagnostic does not authenticate callers, authorize records, establish ownership/assignment, consult live sessions, persist state, provide global uniqueness, or modify records. The acceptance tests build synthetic SEC01 guard snapshots separately and assert own/assigned true, other/unassigned/cross-entity false, and false-override false; they then separately assert that the CORE02 CLI returns the same fixed projection irrespective of those guard outcomes. Those are separate guard and CLI assertions, NOT integrated authorization, live-session, or revocation behavior. A fixture named revoked/false override models only a synthetic guard decision and does not test revocation persistence or session invalidation. Supplied actor/role/grant fields are rejected, not interpreted as authorization.

No route, UI, persistence, mutation, audit/outbox, asynchronous processing, database migration, network access, global registry, cross-module write, or provider is added. No durable data exists, so audit/outbox/async and migration coverage are not applicable. Module-local assistant coverage is N/A: this is a restricted synthetic read-only diagnostic and is not registered with an AI tool/provider or registry. No user-facing capability, browser interaction, or authentication/permission claim is inferred from fixtures.

## Invocation

From repository root, pass a synthetic fixture on stdin:

```powershell
@'
{"source":{"kind":"customer","id":"fixture-customer","entityId":"fixture-entity"},"links":[{"kind":"project","id":"fixture-project","entityId":"fixture-entity"}],"catalog":[{"kind":"customer","id":"fixture-customer","entityId":"fixture-entity"},{"kind":"project","id":"fixture-project","entityId":"fixture-entity"}]}
'@ | php app/Modules/Organization/Infrastructure/CORE02/record-linkage-diagnostic.php
```

Expected sanitized projection:

```json
{"decision":"allow","code":"core02_linkage_projected"}
```

## Verification results

- `php artisan test --filter=CORE02AccessTest` — PASS, 6 tests / 443 assertions (actual subprocess runs and separate synthetic SEC01 guard assertions).
- `php artisan test --filter=CORE02DomainTest` — PASS, 7 tests / 55 assertions.
- `php vendor/bin/pint --test app/Modules/Organization/Infrastructure/CORE02 app/Modules/Organization/Presentation/CORE02 tests/Feature/Organization/CORE02AccessTest.php` — PASS, 3 files.
- `php vendor/bin/phpstan analyse --no-progress` — PASS, no errors.
- `& './scripts/validate-docs.ps1'` — PASS: tasks=239; PRD_IDs=124; local_links=314; JSON/enums/fields/DAG/reverse_edges/coverage/links valid.

The full-repository Pint failure in 16 neighboring checkout files was reported independently and will not be rerun or repaired here. Foundation snapshots, including `.tools/a0-core-004-foundation-manifest.json`, are immutable prerequisites and are not modified.

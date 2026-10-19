# CORE-01 reference-slice adapter evidence

## Adapter and boundary

`app/Modules/Organization/Infrastructure/CORE01/reference-diagnostic.php` is a local, read-only CLI over synthetic fixtures. It accepts one JSON object from stdin (maximum 4096 bytes, JSON nesting depth 8) and dispatches through `ReferenceDiagnosticAdapter` to the existing `LinkOrganizationReferences` application service and `OrganizationReferenceGuard`. The allowlisted request forms are `link` with `source`/`target` references or `match` with the requested `kind`/`referenceId`/`entityId` and a `reference`; each object must have exactly its documented fields and bounded string identities.

Successful evaluations emit only a fixed decision/code pair. Malformed JSON, excessive size/depth, unsupported shapes/types, and unknown fields emit the generic Arabic message `تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.` and exit 2. A well-formed denied link or nonmatching reference emits its fixed `deny` result and exits 0; allow results also exit 0. No supplied references, payload, exception, trace, or secrets are emitted or logged. Every invocation evaluates the current supplied fixture; no decision is cached.

This CLI provides no authentication, authorization grants, real-record lookup, database/storage access, mutation, audit/outbox, network access, or domain registration. It demonstrates only same-entity link allowance and exact kind/reference-ID/entity-ID matching for CORE-01's 11 supported kinds. The existing guard also requires an `entity` reference's ID to equal its entity ID. It does not establish hierarchy, currency/unit compatibility, numbering or approval policy semantics, persistence constraints, ownership/assignment, or revoked-access behavior. Those are outside the guard's contract; no such claims are made. There is no UI, route, browser/mobile/keyboard interaction, or user business capability. AI-tool coverage is not applicable because this is an internal reference checker diagnostic.

## Sanitized operator invocation

From repository root in PowerShell, paste the synthetic fixture to evaluate:

```powershell
@'
{"operation":"link","source":{"kind":"department","referenceId":"fixture-department","entityId":"fixture-entity"},"target":{"kind":"branch","referenceId":"fixture-branch","entityId":"fixture-entity"}}
'@ | php app/Modules/Organization/Infrastructure/CORE01/reference-diagnostic.php
```

Observed result:

```json
{"decision":"allow","code":"core01_link_allowed"}
```

For a same-shaped request with `target.entityId` changed to `fixture-other-entity`, the sanitized result is:

```json
{"decision":"deny","code":"core01_link_denied"}
```

## Acceptance exercised

`CORE01AccessTest` invokes both the adapter and actual CLI subprocess. Coverage includes all 11 supported kinds and exact matches, same-entity allow and cross-entity deny, unknown kind, blank identity, `entity` self-identity mismatch, reference/entity mismatch, malformed JSON and shapes, wrong types, unknown fields, excessive input size/depth, sanitized output, and a fresh retry after a denial. No authentication or authorization grants are asserted or simulated. Persistence, side effects, asynchronous jobs, and browser/RTL UI checks are not applicable to this read-only CLI.

## Checks actually run

- `php artisan test --filter=CORE01AccessTest` — passed, 5 tests / 78 assertions.
- `php artisan test --filter=CORE01DomainTest` — passed, 5 tests / 21 assertions.
- `php artisan test` — passed, 46 tests / 295 assertions.
- `php vendor/bin/pint --test app/Modules/Organization/Presentation/CORE01 app/Modules/Organization/Infrastructure/CORE01 tests/Feature/Organization/CORE01AccessTest.php` — passed, 3 files.
- `php vendor/bin/phpstan analyse --no-progress` — passed, no errors.
- `& './scripts/test-task-loop.ps1'` — passed.
- `& './scripts/validate-docs.ps1'` — passed, 239 tasks, 124 PRD IDs, and 312 local links validated.

No `.env`, database, credentials, dependency installation, or copied cache was used. This evidence is limited to the implemented reference-guard slice and does not claim CORE-01 master-data completeness.

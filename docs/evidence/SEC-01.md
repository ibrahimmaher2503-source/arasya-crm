# SEC-01 adapter evidence

## Adapter and boundary

`app/Modules/Identity/Infrastructure/SEC01/policy-diagnostic.php` is an explicitly local, read-only diagnostic. It accepts one JSON synthetic-fixture object on stdin, caps input at 4096 bytes, requires exact allowlisted object keys and bounded string/list values, and calls `PolicyDiagnosticAdapter`, which reuses `AuthorizeScopedRecord`. It performs no authentication, database lookup, real-record access, persistence, mutation, audit/outbox write, network access, or logging. Input actor/grants are caller-supplied diagnostic data only and are not authentication or trusted business permissions.

Output is limited to fixed decision/code pairs. Malformed requests exit 2 with a generic localized error; fixture IDs, grants, unknown keys, stack traces, and payload details are not returned. There is no route, UI, job, browser/mobile/keyboard check, or business endpoint; browser checks are not applicable to this local CLI. AI-tool coverage is not applicable because this is an internal authorization-policy diagnostic, not a user business action.

## Sanitized operator invocation

From the repository root in PowerShell, paste a synthetic fixture (replace the here-string body only with synthetic data matching the same schema):

```powershell
@'
{"permission":"crm.lead.read","actor":{"actorId":"fixture-actor","entityId":"fixture-entity","departmentId":"fixture-department","inheritedPermissions":["crm.lead.read"],"overrides":[]},"record":{"recordId":"fixture-record","entityId":"fixture-entity","departmentId":"fixture-department","ownerId":null,"assignedActorId":"fixture-actor"}}
'@ | php app/Modules/Identity/Infrastructure/SEC01/policy-diagnostic.php
```

Observed output:

```json
{"decision":"allow","code":"policy_allowed"}
```

An otherwise valid in-scope fixture assigned to another actor produced:

```json
{"decision":"deny","code":"policy_denied"}
```

## Acceptance exercised

Feature tests invoke the actual CLI subprocess and adapter. They cover assigned/owned access, another owner, unassigned records, cross-entity and cross-department denial, inherited grants, explicit deny precedence, explicit allow without scope bypass, revocation reflected on a subsequent evaluation, unknown keys, malformed shapes, and sanitized output. No mutation exists, so duplicate/stale mutation behavior and asynchronous job failure are not applicable. The pre-existing SEC01 domain tests remain unchanged and pass.

## Checks actually run

- `php artisan test --filter=SEC01AccessTest` — passed, 5 tests / 35 assertions (also passed after final adapter formatting).
- `php artisan test --filter=SEC01DomainTest` — passed, 10 tests / 18 assertions.
- `php artisan test --filter=RtlShellTest` — passed, 5 tests / 33 assertions.
- `php vendor/bin/pint --test app/Modules/Identity/Presentation/SEC01 app/Modules/Identity/Infrastructure/SEC01 tests/Feature/Identity/SEC01AccessTest.php` — passed, 3 files.
- `php vendor/bin/phpstan analyse --no-progress` — passed, no errors.
- `& './scripts/test-task-loop.ps1'` — passed.
- `& './scripts/validate-docs.ps1'` — passed after adding this evidence file; 239 tasks, 124 PRD IDs, 311 local links, and JSON/enums/fields/DAG/reverse edges/coverage/links validated.

No database, credentials, .env, or copied cache were used. No physical/browser verification is claimed. The CLI demonstrates policy evaluation over synthetic input only; it does not establish authentication or expose real-record data.

# ARCH-01 operator evidence

**Scope:** internal, read-only architecture-policy inspection; not end-user authentication, authorization, role enforcement, permission/revocation policy, or write execution. The boundary guard uses its immutable local `organization_references` → `Organization` fixture. Cross-module, entity mismatch, unknown resources, explicit contracts, unsupported operations, and malformed input are denied. Dependency inspection checks caller-supplied inline PHP only; it accepts no file path and reads no source files. No persistence, audit/outbox, or async work occurs. AI/tool exposure is N/A for this restricted internal checker.

**Sanitized operator invocation (PowerShell, project root):**

```powershell
$intent = '{"kind":"boundary","sourceModule":"Organization","sourceEntityId":"org-example","targetModule":"Organization","targetEntityId":"org-example","targetTable":"organization_references","operation":"write"}'
$encoded = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($intent))
php app/Modules/Platform/Presentation/ARCH01/inspect.php $encoded --base64
```

Expected allowed output: `{"decision":"allowed","code":"ARCH01_ALLOWED"}` and exit 0. Denials output only `{"decision":"denied","code":"ARCH01_DENIED"}`, localized generic Arabic stderr, and exit 1. Input fields/IDs/source are never echoed.

**Verification results:**

- `php artisan test --filter=ARCH01AccessTest` — passed.
- `php artisan test --filter=ARCH01DomainTest` — passed.
- `php artisan test --filter=RtlShellTest` — passed.
- `php vendor/bin/pint --test app/Modules/Platform/Presentation/ARCH01 app/Modules/Platform/Infrastructure/ARCH01 tests/Feature/Platform/ARCH01AccessTest.php` — passed.
- `php vendor/bin/phpstan analyse --no-progress` — passed.
- `& './scripts/test-task-loop.ps1'` — passed.
- `& './scripts/validate-docs.ps1'` — passed.

Feature coverage exercises the actual CLI runner with injected output streams: owned resource allow, cross-module/entity/contract denial, unknown fields, malformed/list/scalar/deep/oversized/type-invalid input, invalid Base64, safe/provider/malformed inline PHP, generic localized denial, and repeat-call statelessness. A real standalone operator invocation is also verified separately; no subprocess pipe workaround is used.

# SEC-02 implementation evidence — A0-SEC-004

This slice adds a standalone local CLI diagnostic in `app/Modules/Identity/Infrastructure/SEC02/contact-diagnostic.php`. It accepts at most 4096 stdin bytes, decodes JSON with depth 8, and allows only `channel` plus a fixed `contact` object (`phone`, `whatsapp`, `email`; nullable strings up to 256 bytes). Unknown keys, malformed values, unsupported channels, excessive size, and excessive depth receive the same generic Arabic message and exit status 2. Valid fixtures delegate to the existing application `ProjectContactReadModel` and serialize its fixed hidden DTO. No values are logged or retained beyond the invocation; stdout contains only the DTO and errors contain no input values.

The process-level feature tests build explicit synthetic `ActorAccess` and `ScopedRecord` fixtures and assert the existing SEC01 `AuthorizeScopedRecord` result for: owner allowed; assignee allowed; other actor denied; unassigned record denied; cross-entity record denied; inherited `crm.lead.read` denied by an explicit false override; and privileged-sounding identity denied without a grant. For each fixture the test separately invokes the SEC02 CLI with that fixture's contact and asserts the same fixed hidden projection, irrespective of the guard result. The CLI receives no actor or record context; these are separate guard and masking assertions, not an end-to-end authorization integration. The tests also assert injected actor, record, role, claim, and grant keys are rejected without leaking values, alongside all supported channels, deterministic replay, malformed JSON/schema, oversized/deep input, and absence of contact values from output/errors. The underlying domain test separately asserts fixed JSON/debug serialization.

These are synthetic guard snapshots, not real authentication, query authorization, live/revoked sessions, or proof of system-wide masking. In particular, the false override models a guard decision and does not exercise revocation persistence or session invalidation. No production auth/context fields, permission policy, reveal permission, or business rule was added. No persistence, mutation, async processing, network access, global logs, public route, provider, or package was added. Module-local assistant coverage is N/A: the CLI is a restricted/disabled-by-default synthetic read-only diagnostic, is not registered as a provider/tool, and handles no AI invocation.

Exact checks run at handoff:

- `php artisan test --filter=SEC02AccessTest`
- `php artisan test --filter=SEC02DomainTest`
- `php vendor/bin/pint --test app/Modules/Identity/Infrastructure/SEC02 tests/Feature/Identity/SEC02AccessTest.php`
- `php vendor/bin/phpstan analyse --no-progress`
- `& './scripts/validate-docs.ps1'`

## Verification results

- `php artisan test --filter=SEC02AccessTest` — PASS, 4 tests / 144 assertions (includes real CLI subprocess runs and synthetic SEC01 guard decisions).
- `php artisan test --filter=SEC02DomainTest` — PASS, 5 tests / 19 assertions.
- `php vendor/bin/pint --test app/Modules/Identity/Infrastructure/SEC02 tests/Feature/Identity/SEC02AccessTest.php` — PASS, 3 files.
- `php vendor/bin/phpstan analyse --no-progress` — PASS, no errors.
- `& './scripts/validate-docs.ps1'` — PASS: tasks=239; PRD_IDs=124; local_links=313; JSON/enums/fields/DAG/reverse_edges/coverage/links valid.
- The previously reported full `php vendor/bin/pint --test` check remains a known failure: 54 files scanned with 16 style issues in neighboring/foundation paths, including SEC02 application/domain read-model files. It was not rerun for this focused repair, and those out-of-scope files were not modified.

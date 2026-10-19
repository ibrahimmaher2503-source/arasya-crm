# CRM-01 access adapter evidence

This development slice exposes a read-only synthetic lead-import preview through a server-scoped adapter. It filters by entity and authorized lead IDs, preserves duplicate/consent/archive information, denies revoked or unassigned access, and never returns contact values.

Verification: `php artisan test --filter=CRM01AccessTest`; `vendor/bin/phpstan analyse`.

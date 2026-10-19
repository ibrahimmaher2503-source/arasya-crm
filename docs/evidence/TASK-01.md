# TASK-01 access adapter evidence

The synthetic adapter exposes only tasks matching actor/entity and an explicit server-side task allowlist. Revoked access and other assignments return a sanitized denial; overdue state remains derived by the domain service.

Verification: `php artisan test --filter=TASK01AccessTest`; `vendor/bin/phpstan analyse`.

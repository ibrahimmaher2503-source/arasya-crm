# NFR-01 access adapter evidence

The adapter exposes only the NFR01 guard decision, preserving TLS, secret-store, rate-limit, session, entity, object and field authorization. Sensitive fields are masked and failures are fail-closed.

Verification: `php artisan test --filter=NFR01AccessTest`; `vendor/bin/phpstan analyse`.

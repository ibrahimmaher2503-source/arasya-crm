# COM-01 access adapter evidence

The internal workspace adapter filters by entity and employee assignment, normalizes timestamps through the domain projection, and denies revoked or other-assignment access. External provider delivery remains outside this slice.

Verification: `php artisan test --filter=COM01AccessTest`; `vendor/bin/phpstan analyse`.

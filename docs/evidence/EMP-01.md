# EMP-01 access adapter evidence

The development adapter delegates to the scoped employee read model, masks private notes unless explicitly granted, and denies revoked access without exposing profile data.

Verification: `php artisan test --filter=EMP01AccessTest`; `vendor/bin/phpstan analyse`.

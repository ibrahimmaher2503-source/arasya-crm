# KPI-01 access adapter evidence

The development adapter exposes target definitions only for authorized owners and fails closed for revoked access. Metric dimensions, owner type and decimal values remain domain-owned.

Verification: `php artisan test --filter=KPI01AccessTest`; `vendor/bin/phpstan analyse`.

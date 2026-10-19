# DASH-01 access adapter evidence

The development adapter exposes only an authorized entity projection. Metrics remain source-linked, UTC-normalized and read-only; unauthorized or revoked access returns an empty sanitized result.

Verification: `php artisan test --filter=DASH01AccessTest`; `vendor/bin/phpstan analyse`.

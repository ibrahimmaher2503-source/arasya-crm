# Native Windows synthetic browser checks

Use an already migrated, isolated synthetic database and a separately running
Laravel HTTP server. These tests never reset the database or start a server.
Run both server and fixture generator with the same Laravel environment/key,
`APP_ENV=testing` and `SESSION_DRIVER=file`.

```powershell
npm.cmd ci
npx.cmd playwright install chromium
$env:ARASYA_BROWSER_BASE_URL = 'http://127.0.0.1:8765'
$env:ARASYA_BROWSER_AUTH_STATE = "$PWD/storage/framework/testing/browser-authorized.json"
$env:ARASYA_BROWSER_DENIED_STATE = "$PWD/storage/framework/testing/browser-denied.json"
$env:ARASYA_BROWSER_OTHER_STATE = "$PWD/storage/framework/testing/browser-other.json"
php tests/Browser/create-session.php $env:ARASYA_BROWSER_AUTH_STATE authorized
php tests/Browser/create-session.php $env:ARASYA_BROWSER_DENIED_STATE denied
php tests/Browser/create-session.php $env:ARASYA_BROWSER_OTHER_STATE other-entity
npm.cmd run test:browser
```

The fixture generator creates actual Laravel file sessions and encrypted cookies
without introducing an HTTP authentication bypass. Keep generated storage state
private and ignored; it grants synthetic local access.

Desktop and mobile tests exercise the visible Arabic form, scoped HTTP save,
browser request replay, anonymous denial and permission denial. Screenshots and
saved response attachments are under `test-results`; the report is under
`playwright-report`. Successful HTTP responses alone do not establish durable
database persistence: independently verify the returned record IDs and the
absence of denied records against the same synthetic database after the run.

# استراتيجية الاختبارات

## Repository check commands and test design

The commands below describe the current repository; older planning statements later
in this document are target requirements, not evidence that checks passed.
Follow the [phase contract](../agents/SPECKIT_INTEGRATION.md) and record Test Design
inside the technical plan or lightweight task handoff before implementation.

| Risk / change | Required selection |
| --- | --- |
| Documentation/non-behavioral configuration | Relevant validator/syntax/configuration check; explain behavioral TDD N/A |
| Business calculations or transitions | Unit examples for normal, invalid, boundary and stale states; RED → GREEN → REFACTOR |
| Authorization/privacy | Allowed and denied HTTP/service paths, entity/field scope, counts/options/export masking and no unauthorized mutation |
| Finance/sales/inventory | Applicable money/quantity invariants, rejection, replay/idempotency, atomicity and reversal/period rules; unresolved policies remain gates |
| Cross-module persistence | Feature/integration tests for ownership, transaction rollback, audit/outbox intent and failure paths |
| Existing bug | Reproducible failing regression before fix where practical; explain any exception |
| UI | Relevant PHPUnit/Playwright plus RTL/LTR, mobile, light/dark and keyboard checks against actual authorized data |
| Concurrency/durable delivery | Disposable authorized MariaDB/process tests; SQLite/in-memory stores cannot establish these properties |

Every assertion must protect observable behavior or an invariant, not duplicate the
implementation. Before test authoring assess independent worker ownership, fixtures
and runtime conflicts. RED means the intended behavior assertion fails; record GREEN
after the smallest implementation and recheck after justified refactoring.

Run from the project root using existing tools; do not install or rewrite dependencies
just to perform checks. Use Windows PowerShell 5.1 for the documented coordinator
fixtures and verify Spec Kit compatibility with both supported PowerShell hosts.
The commands require the existing local Composer PHAR, vendor dependencies and npm
dependencies as applicable. A fresh isolated worktree does not inherit these ignored
directories; report unavailable prerequisites rather than claiming execution there.

```powershell
php .tools/composer.phar validate --no-check-publish
php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
php vendor/bin/pint --test
npm.cmd run check:design-system
powershell.exe -NoProfile -File scripts/test-speckit-install.ps1
powershell.exe -NoProfile -File scripts/test-speckit-workflow.ps1
powershell.exe -NoProfile -File scripts/test-task-loop.ps1
powershell.exe -NoProfile -File scripts/test-validate-docs.ps1
powershell.exe -NoProfile -File scripts/validate-docs.ps1
```

PHPUnit environment defaults are not forced: explicitly isolate and restore inherited
settings before any suite using RefreshDatabase. Also ensure no cached application
configuration points to a real database. Never run a migration or seed against an
unverified connection. For safe SQLite tests in a fresh child process:

```powershell
$previousTestEnv = @{}
foreach ($name in 'APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'APP_CONFIG_CACHE') {
    $previousTestEnv[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
}
try {
    $env:APP_ENV = 'testing'
    $env:DB_CONNECTION = 'sqlite'
    $env:DB_DATABASE = ':memory:'
    $env:DB_URL = ''
    $env:APP_CONFIG_CACHE = Join-Path $env:TEMP ('arasya-test-config-' + [guid]::NewGuid().ToString('N') + '.php')
    php vendor/bin/phpunit --configuration phpunit.xml --do-not-cache-result
} finally {
    foreach ($name in $previousTestEnv.Keys) {
        [Environment]::SetEnvironmentVariable($name, $previousTestEnv[$name], 'Process')
    }
}
```

MariaDB helpers `scripts/adr-mariadb-validation.ps1` and `scripts/vertical-integration.ps1`
have distinct setup/connection contracts; inspect them and confirm owned disposable
resources before execution. A skipped MySQL test is not qualification. Playwright
also needs a verified synthetic server/database and a unique output directory;
its default test-results cleanup can invalidate historical screenshot evidence.

For every check record command, runtime/environment, tree/commit, time, exit code,
test/assertion count, artifacts and PASS/FAIL/SKIPPED/NOT RUN. A pre-existing failure
does not become PASS. Independent review and applicable integration gates are required;
do not invent a waiver, coverage target or production signoff. GitHub hosting alone
does not establish a supported CI runner or a passing hosted pipeline.

## Original acceptance requirements

Pyramid: unit للMoney/state guards/calculations؛ feature integration للDB transactions/locks/policies/outbox؛ contract للAPI/providers/events؛ E2E محدود للمسارات الحرجة مع RTL/mobile. fixtures sanitized، clocks deterministic، no live sends/production secrets في CI. اختبارات races على database المعتمدة لا SQLite بديل يخفي lock semantics.

Mandatory: deny by default وrecord/entity/field scope؛ masking لكل surfaces؛ approval bypass/self-approval/stale version؛ finance balance/reversal/closed period؛ reserve/issue/concurrency/negative policy؛ idempotency/replay/crash؛ Arabic bidi/keyboard/overflow؛ uploads/quarantine/export reauth؛ AI injection/no-tools/provider failure/eval. test يعيد حالة DB وoutbox/audit ويثبت عدم mutation عند الرفض.

Fast QA: lint/static/focused permission tests/migration names/workflow smoke. Release QA: full suite/contracts/regression/migrations/restore/security/RTL. Deep Audit: architecture boundaries وAST حيث متوافق وfinance-stock invariants وapproval bypass/privacy/AI/DR. numeric performance thresholds وvolumes OPEN-11، AI quality thresholds OPEN-09؛ لا invent pass bar.

الأوامر المستقبلية: `php artisan test` و`vendor/bin/pint --test` و`vendor/bin/phpstan analyse` بعد scaffolding واعتماد tooling. test filter في كل task اسم متوقع يجب إنشاؤه؛ النجاح يتطلب ran count >0 لا مجرد exit 0. evidence: command/environment/commit-or-tree-hash/time/exit/assertions/expected/actual/artifact/owner. هذه الحزمة لا تدعي تنفيذ tests التطبيق.

| scenario | task | source coverage | expected evidence |
|---|---|---|---|
| AT-01 | A1-AT-01 | CRM-02, SEC-02 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-02 | A1-AT-02 | CRM-05 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-03 | A2-AT-03 | SALE-03, SALE-05 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-04 | A3-AT-04 | INV-03 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-05 | A3-AT-05 | DEL-02 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-06 | A3-AT-06 | PRJ-05, PRJ-02, QA-01 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-07 | A4-AT-07 | FIN-01, FIN-03 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-08 | A4-AT-08 | PAY-02 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-09 | A5-AT-09 | DASH-03 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-10 | A5-AT-10 | AI-03, AI-04 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-11 | A1-AT-11 | COM-09 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-12 | A1-AT-12 | SEC-06, EMP-01 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-13 | A5-AT-13 | NFR-06, NFR-05 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-14 | A0-AT-14 | SEC-03, SEC-04 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |
| AT-15 | A4-AT-15 | FIN-07, FIN-08 | pass criteria الأصلية في PRD + fixture + assertions + audit/DB/UI حسب السيناريو |

## Assertions المطلوبة لكل AT

| ID | assertions القابلة للملاحظة |
|---|---|
| AT-01 | محاولة قراءة lead آخر أو hidden phone عبر API/export تُرفض؛ لا contact value في artifact؛ security event محفوظ |
| AT-02 | قبل قبول recipient يبقى original owner والمسؤولية؛ pending transfer ظاهر؛ القبول يغير المالك مرة واحدة |
| AT-03 | approve quotation/order ينتج order/downstream tasks مرة واحدة مع approval/revision history كاملة |
| AT-04 | reserve لا يقلل on-hand؛ issue يغير ledger مرة واحدة؛ concurrent duplicate لا يكرر issue |
| AT-05 | signed proof مرتبط بالdelivery؛ discrepancy متاحة والمسؤولون تلقوا notification scoped |
| AT-06 | milestone يحمل evidence وQA؛ costs/margin يعاد حسابها من source records وفق السياسة |
| AT-07 | expense يحتاج approval؛ posted debits=credits وaudit/project allocation متطابقان |
| AT-08 | accrual مرة واحدة؛ cancellation ينتج reversal/adjustment وفق policy؛ employee statement يتتبع المصدر |
| AT-09 | KPI يتصالح مع underlying records؛ drill-down لا يوسع scope ويعرض cause/action |
| AT-10 | duplicate expense ينتج explainable finding يراجعه إنسان؛ AI عاجز عن post/delete |
| AT-11 | provider failure يبقي pending/failed الصحيح؛ retry idempotent وoperator يرى الخطأ؛ accepted لا delivered |
| AT-12 | sessions revoked؛ records/docs/communications محفوظة؛ open work أعيد إسناده |
| AT-13 | RTL/numerals/tables/dialogs/forms على mobile دون overflow مع keyboard/focus واضحين |
| AT-14 | محاولة تغيير approval status مباشرة ترفض server-side مع audit ودون state mutation |
| AT-15 | trial balance متصالح؛ balance sheet/P&L من posted entries؛ locked period يمنع posting غير مصرح |

[PRD acceptance](../product/PRD.md) هو مصدر pass criteria، وليس اسم test وحده. AT-13 يتطلب device screenshots وkeyboard review بجانب automated test. AT-15 يحتاج accountant reconciliation من posted entries. AT-10 يتطلب finding explainability وhuman disposition ومحاولة privileged action مرفوضة.

## اختبارات assistant coverage لكل feature (مستقبلية)

كل ميزة أعمال جديدة/متغيرة تربط صفاً محدداً في [AI_TOOL_COVERAGE](AI_TOOL_COVERAGE.md) ضمن task/module tests نفسه، لا اختباراً عاماً يؤجل إلى A5. أثبت لكل tool contract typed/versioned وunknown fields rejection، handler إلى ApplicationService نفسها، deny-by-default/scoped-record/field mapping، التصفية قبل counts/pages/aggregates/exports، صلاحية مساوية أو أضيق من UI/API، وإعادة التفويض عند discovery/execute/history/delivery. اختبر preconditions/version conflict، preview/confirmation/independent approval حيث تنطبق، idempotency/concurrency/outbox/audit وغياب أي mutation عند الرفض. Restricted/disabled entries تختبر عدم ظهورها وعدم invocation؛ schema وحده لا يمر.

إذا كانت الميزة ملائمة للكشف الاستباقي، اختبر detector event/rule/versions/evidence، recipient-specific authorization، scan/episode/delivery dedup، stale supersession، lease fencing، preferences وreauthorization عند القراءة. وإلا دوّن `N/A` وسببه. اختبر prompt injection عربي/إنجليزي، model invalid/timeout/budget وzero-egress fixtures؛ لا tools في advisory output وفق ADR-007. جميع هذه الاختبارات حالياً `NOT RUN`؛ لا تدّع فحص التطبيق أو provider. راجع [معمارية AI](../architecture/AI_ASSISTANT_ARCHITECTURE.md).

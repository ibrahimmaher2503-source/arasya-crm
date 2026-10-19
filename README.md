# My Arasya — Laravel ERP/CRM

الحالة الحالية والاستئناف الآمن: [Safe Git checkpoint](docs/governance/evidence/safe-git-checkpoint.md).
حالة المهام المعتمدة في [tasks.json](docs/backlog/tasks.json)؛ تاريخ الإغلاق وحده لا يثبت قبول المنتج أو الإنتاج.

التطبيق Laravel 13 باستخدام PHP 8.4 وبنية modular monolith. الكود الحالي يتضمن إدارة الهوية والصلاحيات والمؤسسة والموظفين والمهام والعملاء المحتملين، ولوحة متابعة وواجهات Blade ومكونات مشتركة وmigrations واختبارات PHPUnit وPlaywright. بعض الوحدات الأخرى هياكل أو عقود تشخيصية فقط؛ وجود مجلد لا يعني اكتمال الميزة. منطق الأعمال في Domain Records وخدمات Application التي تستخدم Query Builder؛ لا يوجد نموذج Eloquent افتراضي أو طبقة repositories عامة.

يسجل [تفويض الأساس المحلي](docs/governance/evidence/A0-ARCH-901-local-authorization.md) الإذن التاريخي للأساس. راجع الحالة الحالية في [فهرس ADR](docs/architecture/ADR-INDEX.md) و[القرارات](docs/governance/OPEN_DECISIONS.md)؛ التنفيذ المحلي لا يمنح اعتماد قاعدة إنتاج أو استضافة أو مزود خارجي أو سياسة أعمال.

## التشغيل المحلي — Windows PowerShell

يتطلب PHP 8.4. على checkout نظيف، أنشئ Composer داخل `.tools/` فقط، وتحقق من SHA-384 الرسمي قبل تشغيل installer. لا تثبّت أداة عامة ولا تعدّل `PATH`:

```powershell
$ErrorActionPreference = 'Stop'
New-Item -ItemType Directory -Force .tools | Out-Null
$installer = Join-Path $PWD '.tools/composer-setup.php'
$composer = Join-Path $PWD '.tools/composer.phar'
if ((Test-Path -LiteralPath $installer) -or (Test-Path -LiteralPath $composer)) {
    throw 'A local Composer installer or PHAR already exists; inspect and verify it before continuing.'
}
Invoke-WebRequest -Uri 'https://getcomposer.org/installer' -UseBasicParsing -OutFile $installer
$expected = 'c8b085408188070d5f52bcfe4ecfbee5f727afa458b2573b8eaaf77b3419b0bf2768dc67c86944da1544f06fa544fd47'
$actual = (Get-FileHash -LiteralPath $installer -Algorithm SHA384).Hash.ToLowerInvariant()
if ($actual -cne $expected) { throw 'Composer installer SHA-384 mismatch; do not execute it.' }
php $installer --install-dir=.tools --filename=composer.phar --version=2.10.3
if ($LASTEXITCODE -ne 0) { throw 'Project-local Composer setup failed.' }
Remove-Item -LiteralPath $installer

$env:COMPOSER_HOME = Join-Path $PWD '.tools/composer-home'
$env:COMPOSER_CACHE_DIR = Join-Path $PWD '.tools/composer-cache'
php .tools/composer.phar install --no-interaction

if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
    php artisan key:generate
}

php artisan test
php vendor/bin/phpstan analyse --no-progress
php vendor/bin/pint --test
php artisan serve
```

### بيانات تجريبية للعرض المحلي

لإنشاء مجموعة بيانات مصرية واقعية (شركة عرض منفصلة بمعرّف ثابت) بعد ترحيل قاعدة بيانات محلية:

```powershell
php artisan migrate
php artisan demo:seed --size=small   # small | medium | large
php artisan demo:clean               # يحذف شركة العرض وملفاتها فقط
```

- تُكتب بيانات الدخول (`demo.admin@example.test` / `demo.sales@example.test`) في `storage/app/private/demo-credentials.json`، وهو مستثنى من Git.
- يتطلب مفتاح `APP_KEY` محلياً لأن بيانات اتصال العملاء المحتملين تُشفَّر.
- يمنع seed وclean التشغيل في `production` حتى مع `--force`، ويتطلبان `--force` خارج `local`/`testing`.

التنظيف يستخدم معاملة قاعدة بيانات ويستعيد المرفقات عند فشلها؛ ويحذف ملف بيانات الدخول بعد نجاحه.
بيانات العرض مصرية اصطناعية، ومرفقاتها نصوص تجريبية وليست أدلة عمل حقيقية.
لا تستخدم أرقامها لإرسال رسائل أو إجراء اتصالات. أوامر العرض ليست أدوات صيانة لبيانات حقيقية.
راجع [حدود بيانات العرض](docs/architecture/SOLUTION_ARCHITECTURE.md) قبل تشغيل أوامر العرض.

استخدم أوامر PowerShell أعلاه في Windows PowerShell 5.1؛ لا تلصق ملفاً عربياً إلى سكربت بلا BOM. لا تستبدل `.env` موجوداً؛ احتفظ بالأسرار المحلية خارج Git. إذا احتجت إلى إنشاء `.env`، ولّده من المثال ثم أنشئ مفتاحاً محلياً. يوفر `phpunit.xml` مفتاحاً اختبارياً ثابتاً للاختبارات فقط، ويستخدم test bootstrap ملف `.env.example` صراحةً عندما `APP_ENV=testing`. متغيرات البيئة الموروثة قد تتقدم على قيم PHPUnit؛ اتبع [عزل الاختبارات](docs/engineering/TEST_STRATEGY.md). `config/auth.php` يبقي guards/providers الافتراضية فارغة؛ المصادقة والصلاحيات الحالية عبر AccessSessionController وخدمات Identity المخصصة.

يضبط المثال والاختبارات SQLite بقاعدة `:memory:`، مع cache/session محليين وqueue متزامن. SQLite هنا للاختبارات الأولية المحلية فقط؛ لا يثبت سلوك محرك إنتاج أو قفل/تزامن ولا يمنح موافقة على قاعدة بيانات إنتاج. نقطة `/up` ترجع نص حالة بسيطاً ولا تعرض معلومات إعدادات.

Composer يدير Laravel وأدوات الاختبار والفحص والتنسيق، وnpm يدير Playwright وفحوص نظام التصميم.
النسخ المقفلة في `composer.lock` و`package-lock.json`؛ `vendor/` و`node_modules/` و`.tools/`
و`.env` خارج Git. الواجهة Blade وCSS وJavaScript عاديان، يضمّنها shell الحالي؛ لا توجد خطوة Vite/build.

## دورة التطوير والفحوص

ابدأ بـ[AGENTS.md](AGENTS.md) ثم [دورة Spec Kit](docs/agents/SPECKIT_INTEGRATION.md):
Discovery → Specification → Clarification → Technical Plan → Test Design → Implementation → Verification → Review → Integration.
يوجد مسار خفيف للتعديلات الصغيرة؛ منطق الأعمال المناسب يتطلب RED → GREEN → REFACTOR.
المنسّق وحده يملك lifecycle، والمراجعة مستقلة. [استراتيجية الاختبارات](docs/engineering/TEST_STRATEGY.md)
تحدد الأوامر وعزل قواعد الاختبار؛ من الفحوص المحلية:

```powershell
php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
php vendor/bin/pint --test
npm.cmd run check:design-system
powershell.exe -NoProfile -File scripts/test-speckit-workflow.ps1
powershell.exe -NoProfile -File scripts/validate-docs.ps1
```

لا يوجد pipeline CI متتبع حاليًا. نتائج الفحوص المحلية والمتصفح وMariaDB وCI والإنتاج أدلة منفصلة.

## مراجع المشروع

ابدأ بـ [النطاق](docs/product/SCOPE.md)، ثم [القرارات](docs/governance/OPEN_DECISIONS.md)، ثم [المعمارية](docs/architecture/SOLUTION_ARCHITECTURE.md)، ثم [backlog](docs/backlog/BACKLOG.md). المرجع الأصلي [PRD](docs/product/PRD.md) محفوظ حرفياً.

- المنتج: [التتبع](docs/product/REQUIREMENTS_TRACEABILITY.md).
- التصميم: [modules](docs/architecture/MODULE_CATALOG.md)، [data](docs/architecture/DATA_MODEL.md)، [events](docs/architecture/EVENT_CATALOG.md)، [ADR](docs/architecture/ADR-INDEX.md).
- الضوابط: [permissions](docs/security/PERMISSION_MATRIX.md)، [threat model](docs/security/THREAT_MODEL.md)، [workflows](docs/workflows/WORKFLOW_CATALOG.md)، [API](docs/api/API_STANDARDS.md).
- التسليم: [engineering](docs/engineering/ENGINEERING_STANDARDS.md)، [tests](docs/engineering/TEST_STRATEGY.md)، [operations](docs/operations/DEVOPS_RUNBOOK.md).
- الحوكمة: [risks](docs/governance/RISK_REGISTER.md)، [changes](docs/governance/CHANGE_CONTROL.md)، [DoD](docs/governance/DEFINITION_OF_DONE.md).
- agents: [protocol](docs/agents/AGENT_EXECUTION_GUIDE.md)، [tasks](docs/backlog/tasks.json)، [schema](docs/backlog/TASK_SCHEMA.md).

يتجاهل `scripts/validate-docs.ps1` عند فحص الروابط مجلدات الجذر المولدة `.git/` و`.tools/` و`node_modules/` و`vendor/` فقط، ويستمر بفحص Markdown first-party بما فيه المجلدات المخفية. افحص هذا الحد بـ`& './scripts/test-validate-docs.ps1'`، ثم شغّل `& './scripts/validate-docs.ps1'` و`& './scripts/test-task-loop.ps1'` قبل handoff. اعتمد القرارات عبر المالك أولاً؛ لا يحوّل agent كلمة Proposed إلى Approved. الترتيب والاعتماديات لا تعني اعتماد سياسة عمل.

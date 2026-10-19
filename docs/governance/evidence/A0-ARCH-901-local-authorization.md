# تفويض محلي محدود — A0-ARCH-901

## الإذن البشري

- صاحب الإذن: المستخدم، بتوجيه صريح في جلسة التنفيذ؛ لا يُسجل اسم أو توقيع غير معلوم.
- القرار: مسموح إنشاء أساس محلي لـLaravel 13 + PHP 8.4 باتجاه modular monolith، وتنزيل Composer داخل المشروع فقط بعد التحقق من سلامة installer، واستخدام SQLite للاختبارات الأولية المحلية فقط.
- مصدر تحقق Composer: [صفحة التنزيل الرسمية](https://getcomposer.org/download/)؛ SHA-384 المنشورة للـinstaller وقت التنفيذ: `c8b085408188070d5f52bcfe4ecfbee5f727afa458b2573b8eaaf77b3419b0bf2768dc67c86944da1544f06fa544fd47`.

هذا السجل يوثق إذناً محلياً مباشراً من المستخدم، وليس اعتماداً من Engineering أو IT مفترضاً، وليس سجلاً مقبولاً لدى decision verifier لاعتماد ADR أو OPEN.

## الحدود غير المشمولة

- لا تتغير الحالة العامة لـADR-001 أو ADR-002 أو ADR-008 أو أي قرار OPEN؛ لا تُنشأ لها سجلات قبول أو اعتماد.
- Laravel 13 وPHP 8.4 وSQLite اختيارات لهذا الأساس المحلي فقط. SQLite للاختبارات الأولية (`:memory:`) ولا يثبت قفل/تزامن أو سلوك محرك الإنتاج.
- لا موافقة على قاعدة بيانات أو استضافة أو نشر أو بيانات أو أسرار إنتاج أو مزود خارجي.
- لا موافقة على سياسة مالية أو مخزون أو سياسة أعمال؛ لا تنفذ هذه الدفعة ميزات أعمال أو واجهة frontend.
- PHPStan أداة تطوير مطلوبة. الحزم المباشرة محدودة بالإطار وأدوات الاختبار والفحص والتنسيق؛ أضيف `nunomaduro/collision` بعدما أثبت التشغيل أن `php artisan test` غير معرّف بدونه.
- المصادقة وهوية المستخدم مؤجلتان: `config/auth.php` لا يعرّف guard أو provider أو model افتراضياً، ولا يعتمد على `App\\Models\\User` غير الموجود.

## أثر الحوكمة ونطاق المراجع

يسمح الإذن بتعديل بوابة `A0-ARCH-901` وحدها إلى بوابة محلية. تبقى حواف `depends_on` لتقييمات ADR كسجل ترتيب وتتبع، لا كإثبات قبول؛ أزيلت مراجع ADR من `decision_dependencies` لهذه المهمة فقط. لم تتغير بوابات downstream أو حالات مهام أخرى. ولأن المهمة كانت `blocked` بلا lease، سُجل استثناء المنسق `blocked` → `ready` تحت coordinator lock قبل `Claim` الاعتيادي؛ هذا ليس `Release` ولا اعتماد ADR. أحداث `Claim` و`Review` تسجل انتقالات loop المدعومة.

وبتوجيه التصحيح اللاحق من المستخدم، يمتد نطاق هذه المهمة وحدها إلى تغيير محدود في `scripts/validate-docs.ps1` واختباره: يستثني مسح Markdown مجلدات الجذر المولدة `.git/` و`.tools/` و`node_modules/` و`vendor/` فقط، مع استمرار التحقق من جميع مسارات Markdown first-party بما فيها المخفية. تبقى فحوص JSON وDAG والحواف العكسية وتغطية المتطلبات والقرارات بلا تغيير. يثبت `scripts/test-validate-docs.ps1` الاستثناءات، وفشل رابط first-party المكسور، ونجاح fixture سليم؛ لا يعدل الاختبار backlog المشروع ولا ينقل `vendor/`.

## نتائج تحقق الوالد المستقل قبل إصلاح validator

أفاد الوالد بأنه شغّل أوامر التحقق العشرة بصورة مستقلة: نجحت التسعة الأخرى، بينما فشل `& './scripts/validate-docs.ps1'` بسبب 51 رابط Markdown مكسور داخل `vendor/`. هذه نتيجة الوالد السابقة للإصلاح، وليست نتيجة تشغيل العامل المفوض. لا تُعامل كتجاوز مقبول للـvalidator.

## نتائج تنفيذ العامل المفوض

- PHP `8.4.21`؛ Laravel Framework `13.36.0`؛ Composer project-local `2.10.3`.
- طابق SHA-384 المحسوب للـinstaller القيمة الرسمية قبل تنفيذه. SHA-256 للـPHAR: `7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6`.
- الحزم التطويرية المقفلة: PHPStan `2.3.1`، PHPUnit `12.5.39`، Pint `1.32.1`، Collision `8.9.5`.
- في هذه الجلسة تحققنا من PHP `8.4.21` وLaravel Framework `13.36.0` وComposer project-local `2.10.3`. طابق SHA-256 الحالي لـ`.tools/composer.phar` القيمة المسجلة: `7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6`. لم يُعَد تنزيل Composer أو تشغيل installer؛ SHA-384 المنشور/المسجل للـinstaller أعلاه دليل إعداد سابق محفوظ كما هو.
- نتائج الأوامر النهائية: `& './scripts/validate-docs.ps1'` PASS (239 tasks، 124 PRD IDs، 309 روابط محلية)؛ `& './scripts/test-task-loop.ps1'` PASS (fixtures تغطي 21 حدثاً)؛ `php .tools/composer.phar validate --strict` PASS؛ `php .tools/composer.phar check-platform-reqs` PASS على PHP 8.4.21 وكل المتطلبات؛ `php artisan --version` PASS (`Laravel Framework 13.36.0`)؛ `& './scripts/test-validate-docs.ps1'` PASS (استثناءات الجذر وMarkdown المخفي)؛ `php artisan test --filter=FoundationSmokeTest` PASS (3 tests، 10 assertions)؛ `php artisan test` PASS (3 tests، 10 assertions)؛ `php vendor/bin/phpstan analyse --no-progress` PASS (no errors)؛ `php vendor/bin/pint --test` PASS (20 files).
- كشف أول تشغيل smoke أن Laravel يدمج إعدادات auth الأساسية (`web` و`App\\Models\\User`) داخل المصفوفات الفارغة؛ أُزيلت هذه القيم وقت تسجيل `AppServiceProvider`، ثم نجح التشغيلان المركز والكامل. كما صُحح وصول PHPStan إلى config repository typed بعد أن كشف الفحص الأولي خطأ offset؛ النتيجة النهائية أعلاه بعد التصحيح.
- لا يوجد `.env` في جذر المستودع ولا ملف SQLite داخل `database/`. يوجد `.tools/laravel-stage/.env` ضمن مجلد الأدوات المتجاهل؛ لم يُفتح أو يُعدّل. الاختبارات تستخدم `:memory:` ومفتاح PHPUnit ثابتاً للاختبارات فقط، ويختار bootstrap ملف `.env.example` عند `APP_ENV=testing`.
- `& './scripts/task-loop.ps1' -Action Review -TaskId 'A0-ARCH-901' -Owner 'foundation-worker'` COMMITTED Review؛ `& './scripts/task-loop.ps1' -Action RefreshBacklog` PASS؛ و`& './scripts/task-loop.ps1' -Action Status` PASS بعد الانتقال: tasks=239، review=1، in_progress=1، active_leases=2، currently_ready=5، pending_transaction=none. بقي lease `A0-ARCH-901` للمالك `foundation-worker` وlease `A0-ADR-004` للمالك `storage-adr-worker` دون تغيير.
- نتائج البيئة المحلية لا تثبت سلوك قاعدة إنتاج أو التزامن، وليست اعتماداً عاماً أو دليلاً على جاهزية إنتاجية. تبقى المراجعة المستقلة والإغلاق النهائي `Complete` مسؤولية الوالد.

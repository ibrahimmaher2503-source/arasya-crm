# استراتيجية اختيار الحزم — Package Strategy

## الحالة والنطاق

**تفضيل package-first Confirmed من المستخدم** كسياسة هندسية: قيّم Laravel first-party capability أو package ناضجة قبل إعادة بناء وظيفة عامة. هذا تفضيل للتقييم، وليس إلزاماً باستخدام package؛ لا يعتمد stack أو package بعينها ولا يجيز تثبيت dependency تلقائياً. تظل نسخة Laravel وPHP runtime وpackages Proposed حتى قرار معماري مناسب. هذه الوثيقة shortlist بحثية فقط؛ لا تغيّر ADR ولا توافق على إصدار.

الهدف تقليل إعادة تنفيذ وظائف شائعة وصيانتها، والاستفادة من سلوك مألوف واختبارات وتوثيق upstream عند ثبوت جودتها وملاءمتها. package-first لا يعني إضافة dependencies كثيرة: أضف أقل عدد مبرر، ولا تضف شيئاً قبل مراجعة أثره وصيانته.

المراجع الداخلية: [معايير الهندسة](../engineering/ENGINEERING_STANDARDS.md)، [تصميم الحل](SOLUTION_ARCHITECTURE.md)، [فهرس ADR](ADR-INDEX.md)، [مصفوفة الصلاحيات](../security/PERMISSION_MATRIX.md)، و[PRD](../product/PRD.md). Laravel مؤكد من المستخدم؛ modular monolith ونسخة runtime والحزم لا تصبح معتمدة بهذه الوثيقة.

## مبادئ لا تُفوّض إلى package

package قد توفر primitive أو mechanism قابلة لإعادة الاستخدام؛ لكنها لا تصبح مصدر تعريف قواعد Arasya ولا تثبت تحققها. يبقى domain/application code مسؤولاً عن ربطها بعقود النظام، وفرض authorization، والمعاملات، والاختبارات.

- **SoD والاعتماد:** تحقق domain أن المنشئ ليس المعتمد حيث يلزم، وأن الموافقة صالحة لنفس actor/entity/version/amount. دور أو permission عام لا يحقق وحده فصل المهام.
- **Row/field privacy:** طبّق scope وfield masking في query/API/jobs/search/notifications/downloads/exports/logs؛ إخفاء عنصر UI أو دور package لا يكفي. اربط الوصول بالسجل والتعيين والكيان والغرض حسب [مصفوفة الصلاحيات](../security/PERMISSION_MATRIX.md).
- **Money والstock:** لا float؛ العملة والدقة والتقريب والسياسات مصدرها المعتمد. posted journal وstock movements immutable أو تصحيحها reversal مرجعي. uniqueness، locking، الكمية المتاحة والحجز/الإصدار، double-entry والتزامن تحتاج قواعد واختبارات domain مخصصة.
- **Workflow وoutbox/audit:** يسمح domain بالانتقالات ويطبق الموافقات deterministic. ربط aggregate والتغييرات وoutbox وaudit intent في transaction، idempotency/retry/inbox، وتاريخ تدقيق متين لا يُفترض توفره لمجرد وجود state/audit package.
- **Media وRTL:** package تخزين/مرفقات لا تعفي من private access وquarantine/scan/checksum/retention؛ وكل واجهة user-facing تخضع لاختبار Arabic RTL وbidi لاختبار فعلي.

## قائمة التحقق القابلة لإعادة الاستخدام

سجّل لكل بند **Pass / Fail / Unknown / N/A مع rationale**. `Unknown` يعني أن القرار غير مكتمل، وليس موافقة. استعمل نسخة محددة من المصدر ووقت مراجعته عند تقديم قرار لاحق.

| المجال | أسئلة وأدلة لازمة قبل التوصية |
|---|---|
| Fit / scope | هل تحل الوظيفة المطلوبة تحديداً؟ ما الحدود والميزات غير المغطاة؟ هل يمكن دمجها خلف contracts دون تجاوز ملكية modules أو domain rules؟ |
| Compatibility | تحقق من Composer constraints وPHP/Laravel target وإصدارات framework/database/driver/extensions الفعلية؛ لا تستنتج التوافق من اسم الحزمة أو مثال docs. نفّذ spike على stack المحدد. |
| Support / maintenance | راجع support policy، آخر releases، changelog، issue/PR responsiveness، maintainer continuity، EOL وخطة security fixes. الأرقام الشعبية وحدها ليست دليلاً على الدعم. |
| License | اقرأ ملف LICENSE وSPDX metadata للحزمة وكل dependency transitive، ثم وثّق أي شرط تجاري أو توزيع واطلب مراجعة قانونية عند الحاجة. الشارة أو README ليست clearance قانونية. |
| Security / supply chain | افحص advisories/CVEs، provenance وناشري الإصدارات، صلاحيات package، defaults، نقاط upload/auth/data exposure، سياسة الإبلاغ والاستجابة. راجع transitive tree ونتائج `composer audit` عند توفر المشروع. |
| Releases / upgrades | اقرأ release history وbreaking changes وsecurity releases؛ حدد سياسة version constraint، من يراجع التحديث، وكمية التغييرات المطلوبة لكل upgrade. لا تثبّت `latest` تلقائياً. |
| Migrations / data | افحص schema ownership، الأعمدة/indexes، backfill والlocks، upgrade/rollback/recovery، أثر حذف الحزمة، ووسيلة تصدير البيانات. لا تسمح بفقد posted/immutable records أو بتعديلها الصامت. |
| Testability | هل لها contract قابل للاختبار؟ أمثلة واختبارات upstream؟ سهولة feature/integration/security/concurrency/failure-path tests؟ اختبر integration الحقيقية وpermissions ولا تكتفِ باختبارات package. |
| Lock-in / exit | افحص اعتماد التطبيق على traits/facades، schema أو serialized format، وخصوصية storage/API. عرّف adapter أو export/migration/exit path وتكلفته، ووازن ذلك مع فائدة التكامل. |
| Arabic RTL / locale | للواجهات: جرّب Arabic RTL فعلياً، bidi للنص/الأرقام/email، localized validation/errors، keyboard/mobile/accessibility. backend-only: سجّل N/A مع سبب؛ افحص العملة والتواريخ والـlocale إن ظهرت للمستخدم. |
| Operations / cost | قيّم حجم dependencies، الأداء والذاكرة، jobs/network access، التشغيل والنسخ الاحتياطي والمراقبة، الرخص أو الدعم المدفوع، والكلفة الكلية للتحديث والتشغيل. |

## Shortlist أولية — ليست اعتماداً

المصادر أدناه official docs أو repositories قرأتها أثناء إعداد هذه الوثيقة. الحالة لكل عنصر: **Candidate — source inspected; project compatibility and due diligence unverified**. الوصف يقتصر على capability المعلنة في المصدر؛ لا يثبت الجودة أو الدعم الحالي أو fit للـPRD. مسارات docs ذات أرقام إصدارات روابط لمحتوى محدد وليست توصية بإصدار. لا توجد حالياً نسخة runtime/package معتمدة للمقارنة.

| المجال | Candidate ومصدره الرسمي | ما يدعمه المصدر الذي تمت مراجعته | الحالة والحدود التي تحتاج تحققاً |
|---|---|---|---|
| Auth | Laravel [Fortify](https://laravel.com/docs/12.x/fortify) و[Sanctum](https://laravel.com/docs/12.x/sanctum) | Fortify يوثق headless auth routes مثل login/password reset/verification؛ Sanctum يوثق SPA session cookies وAPI tokens/abilities/revocation. يوضح توثيق Sanctum أن authorization على الموارد يبقى عبر policies. | Candidate — المصدر الرسمي inspected؛ نسخة Laravel/runtime، إعدادات الجلسات و2FA/revocation، احتياجات mobile، config/security والتكامل مع IAM/employee-offboarding غير محققة. ليس بديلاً عن authorization أو user lifecycle. |
| RBAC | [spatie/laravel-permission](https://spatie.be/docs/laravel-permission/v6/introduction) و[prerequisites](https://spatie.be/docs/laravel-permission/v6/prerequisites) | توثيق الحزمة يشرح roles/permissions متصلة بـLaravel Gate، ويعرض خيارات مثل teams وmodel policies. | Candidate — docs inspected فقط؛ النسخة المرتبطة تصف نفسها بأنها older version. افحص التوافق المستهدف، schema/migrations/UUIDs، cache invalidation، entity/assignment scoping، field masks وSoD؛ لا تستبدل policies/domain checks. |
| Auditing | [OwenIt Laravel Auditing repository](https://github.com/owen-it/laravel-auditing) و[README source](https://raw.githubusercontent.com/owen-it/laravel-auditing/master/README.md) | README يصف change history لـEloquent models، ويربط للتوثيق الرسمي ومصفوفة دعم حسب Illuminate/PHP. | Candidate — المصدر inspected؛ المصفوفة مرتبطة بإصدار غير مختار ولم تُطابق runtime. تحقق من immutable/tamper-evident retention، actor/effective role/correlation/outcome، secure diffs، field redaction، transaction/outbox integration والlicense/support الحالي. |
| Money | [brick/money README](https://github.com/brick/money) و[raw source](https://raw.githubusercontent.com/brick/money/master/README.md) | README يصف value objects immutable، arithmetic دقيقة، وعملات مع تحكم صريح في rounding؛ يعرض طرق تمثيل المبلغ والعملة. | Candidate — README inspected؛ راجع PHP/extensions والـscale/rounding المطلوبين بعد القرارات المالية، serialization/persistence، وتكاملها مع journal/valuation. المكتبة لا تنشئ accounting policy أو ledger ولا تحسم OPEN-03/04/05. |
| State machines | [spatie/laravel-model-states](https://spatie.be/docs/laravel-model-states/v2/01-introduction) | docs تشرح state classes وallowed transitions على Eloquent models. | Candidate — introduction inspected؛ راجع compatibility، history/versioning، authorization/approval، transaction وoutbox semantics، وتكلفة migration؛ transition API وحده لا يحقق lifecycle أو business gates. |
| Media | [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary/v11/introduction) | docs تصف ربط ملفات بـEloquent models، collections، conversions، وتخزيناً عبر Laravel Filesystem. | Candidate — introduction inspected؛ إصدار runtime/storage لم يحددا. تحقق من private signed access، quarantine/malware scan/checksum، field/record access، retention/version history، backup/export وArabic metadata/UI؛ لا تفترض تغطية security controls. |
| Exports | `maatwebsite/excel` ([Laravel Excel getting started](https://docs.laravel-excel.com/3.1/getting-started/)) | docs تصف exports/imports مع chunking/queueing وقراءة/إدخال batches. | Candidate — docs inspected؛ compatibility والأداء والذاكرة لم تختبرا. يجب أن يحدد التقرير data source/scopes والmasking/authorization وإخفاء السجلات الحساسة وaudit لكل export قبل التنزيل. |
| Static analysis / testing | [Larastan README](https://github.com/larastan/larastan/blob/3.x/README.md) و[Pest installation docs](https://pestphp.com/docs/installation) | Larastan يصف تحليل Laravel/PHPStan؛ Pest يوثق PHP testing framework. | Candidate — source pages inspected؛ لا تثبت التوافق مع PHP/Laravel target أو تبرر استبدال أدوات موجودة. اختر أدوات متوافقة، وأبقِ permission/contract/integration/concurrency/RTL tests ضمن suite؛ لا تُعامل static analysis كبديل للاختبار. |

### حدود الأدلة

المتصفح أتاح مراجعة المصادر الرسمية المذكورة أعلاه، لكن مراجعة docs/README ليست code/security audit ولا dependency approval. بعض صفحات GitHub طويلة أو versioned، والـbranch/docs يتغير؛ لا أقدّم ادعاءً عن أحدث إصدار أو توافق حالي. لم يُحدّد هنا PHP/Laravel/database target، ولم أتحقق لكل candidate من كامل Composer metadata وlicense transitive tree أو سجل vulnerabilities/release cadence، ولم أجرِ install، migration spike، tests، RTL review، legal review، أو اختبار أداء. لذلك تظل جميع الصفوف Candidate، مع project fit وcompatibility وsupport/license/security **Unverified** حتى إكمال القائمة أعلاه وقت القرار.

## Package أم custom؟

| اختر package بعد إثباتها إذا… | اختر custom/domain implementation إذا… |
|---|---|
| الوظيفة عامة ومتكررة، ونطاقها واضح، ويوجد مصدر maintained واختبارات وsupport/compatibility مقبولان؛ integration لا تخترق module ownership أو policy؛ والكلفة الكلية أقل من إعادة البناء والصيانة. | السلوك نفسه خاص بسياسة Arasya أو مسار مالي/مخزني/اعتماد لا يطابق package؛ أو لا توجد compatible/supportable option؛ أو security/license/migrations/lock-in مخاطرها غير مقبولة. |
| يمكن عزل package وراء adapter/contract مع اختبارات integration وexit path، وتركها تحل plumbing/common edge cases. | يجب أن تبقى القاعدة في domain service/guard ولا تُختزل في configuration أو trait عام. اكتب أصغر implementation مخصص لازم، مع owner واختبارات وخطة صيانة؛ لا تعِد بناء وظائف عامة لمجرد تفضيل أسلوبي. |

الخيار الهجين غالباً عملي: package للآلية العامة + adapter صغير + bespoke domain logic للـinvariants. سجّل أسباب custom أيضاً كي لا تتحول إلى إعادة اختراع بلا مبرر.

## قالب Package Decision Record

أنشئ record لكل package مقترحة قبل إدخالها في المشروع؛ إذا أثرت على stack/معمارية قائمة، اربطها بـADR وفق الحوكمة. املأ Unknowns ولا تحولها ضمنياً إلى assumptions.

```text
Capability / use case:
Package name + exact Composer constraint (after target stack is selected):
Owner / review date:
Decision: Candidate | Under review | Approved | Rejected | Deferred
Alternatives: Laravel core/first-party | other packages | custom | none
Required behavior and PRD/domain invariants:
Official source URLs + access/review date + evidence limitations:
PHP / Laravel / database / extension compatibility evidence:
Support/EOL / maintainer / release and upgrade evidence:
SPDX license + transitive license review:
Security/advisory/provenance review:
Migration/data ownership/rollback/backup plan:
Testability + spike/contract/security/concurrency test evidence:
Lock-in / data export / replacement or exit plan:
Arabic RTL / locale result or N/A rationale:
Operational/performance/dependency cost:
Gaps, accepted risks, rationale versus custom:
Approval / ADR reference (if required):
Revalidation trigger, next review date, responsible owner:
```

## Maintenance process

1. عيّن owner لكل direct dependency وسجّل الغرض والحد الأدنى من capabilities المستخدمة ومصدر القرار.
2. عند الإضافة أو التحديث، أعد checklist على الإصدارات المحددة فعلياً؛ راجع upstream releases/changelog وsupport/EOL وlicense/advisories وComposer dependency changes. لا تعتبر lockfile أو README دليلاً على fit.
3. ثبّت قيود الإصدارات و`composer.lock` فقط بعد اعتماد stack، وراجع upgrades في branch منفصل مع automated tests وmigration/forward-recovery checks وsecurity/privacy tests اللازمة. لا تحدّث major أو dependencies عشوائياً.
4. راجع inventory على الأقل مع كل release وبمراجعة دورية ربع سنوية؛ أعد التحقق أيضاً عند security advisory، end-of-life، توقف الصيانة، تغيير Laravel/PHP/DB target، أو تغيير domain requirement.
5. وثّق قرار الاحتفاظ/الترقية/الاستبدال، remediation owner والموعد، والبيانات/exit path. أوقف إضافة ميزات تعتمد على package متروكة حتى يعالج الخطر؛ لا تحذفها أو تغيّر البيانات دون خطة معتمدة واختبارات.

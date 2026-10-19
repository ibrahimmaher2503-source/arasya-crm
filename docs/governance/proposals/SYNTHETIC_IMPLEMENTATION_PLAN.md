# خطة تنفيذ اصطناعية مقترحة — بعد الإذن والبوابات

> **خطة مستقبلية فقط؛ لم تُنفذ أي مهمة تطبيق أو اختبار في هذه الخطة.** لا تعدل المهام الحالية أو DAG ولا تمنح إذناً coding أو lifecycle. يلزم إذن صريح منفصل للتنفيذ وتفويض task-lifecycle من مالك/منسق مخول. كل اختبارات الصفوف أدناه `NOT RUN`. تستخدم fixtures مصطنعة فقط؛ لا بيانات شركة حقيقية أو مزود خارجي أو credentials أو egress.

## 1. حالة البدء وحدودها

- في اللقطة المقروءة `2026-10-16T04:11:06.6802332+03:00` كان `Status`: 239 مهمة، 24 done، 86 blocked، 129 planned، لا leases ولا pending transaction؛ وفي `04:11:08.5661725+03:00` عرض `Ready` مهمة واحدة فقط: `A0-ARCH-001`. يعاد فحص الحالة قبل أي تنفيذ؛ ليست هذه اللقطة تصريحاً أو وعداً باستمرار الجاهزية.
- `A0-ARCH-901` أساس Laravel 13/PHP 8.4 محلياً، و`A0-CORE-001` و`A0-SEC-001` مسجلة done. المصادقة متعطلة عمداً؛ لا تعرض real-user workflows قبل إكمال scoped identity/authentication. لا business migrations أو GitHub workflows مرصودة في خط الأساس، والنسخ الاحتياطية لم تُفحص.
- **الجاهز الحالي فقط:** `A0-ARCH-001` — slice module boundary checker. prerequisite الوحيد `A0-ARCH-901` المنجز؛ لا `decision_dependencies`. نطاقه `app/Modules/Platform/Domain/ARCH01/`, `app/Modules/Platform/Application/ARCH01/`, `database/migrations/ARCH01/` اختيارياً، و`tests/Unit/Platform/ARCH01DomainTest.php`. يمكن إثبات boundaries دون قاعدة أو migration.
- اختبارات هذا المسار المقترحة فقط للمستقبل: `php artisan test --filter=ARCH01DomainTest` و`php vendor/bin/phpstan analyse --no-progress`؛ كلاهما `NOT RUN` هنا. يتطلب أي تنفيذ/فحص تفويض تطبيق منفصل وإذن lifecycle/task غير مشمولين بالإذن الوثائقي الحالي.
- أول 30: 23 done؛ 7 غير منجزة هي `A0-ARCH-902`, `A0-CORE-900`, `A0-SEC-002..006`. `A0-ARCH-901` done؛ لا تعِد فتحه. `A0-CORE-001` و`A0-ARCH-001` خارج أول 30 ولا يُحتسبان لإكمالها.

## 2. شروط ما قبل التنفيذ

1. إذن كتابة/اختبار/تشغيل مستقل يحدد modules والمسارات والبيانات والبيئة؛ والإذن الصريح للـtask lifecycle عند الحاجة. لا `Claim`, `Review`, `Complete`, `RefreshBacklog` ضمن التفويض الحالي.
2. أعد تشغيل `Status` و`Ready` قبل اختيار task، وحافظ على dependencies والقرارات كما هي في مصدرها. لا task status مخزنة `blocked` تصبح جاهزة بمجرد رأي الوثيقة؛ لا إعادة ترتيب أو تجاوز DAG. أي تقسيم/تعديل نطاق يحتاج change control وتحديثاً مخولاً للمهام قبل التنفيذ.
3. كل اختبار DB على MariaDB disposable مع نسخة/driver/transactional engine محددين ومثبتين؛ لا تستنتج locking من SQLite أو mock. اعتمد Finance/Warehouse على نتائج decimal/locks/migrations، ولا تشغل ترحيلاً إنتاجياً.
4. synthetic-only: mail/SMS/WhatsApp/Telegram/browser push/AI كلها fake adapters بلا sockets أو credentials أو egress. لا بيانات حقيقية ولا إرسال/اشتراك push. يمنع التشغيل الخارجي إلى حين تصريح منفصل.
5. موافقة OPEN مستقلة ومثبتة قبل أثر سياسة الأعمال. `RECOMMENDED-DEFAULT` لا تفتح الحواجز. اعتمد fixtures ونتائجها المتوقعة من الأدوار المالكة أولاً؛ المبالغ المذكورة هنا حدود اختبار illustrative وفق اتجاه المالك لا اعتماداً لـpolicy production.

## 3. ترتيب تنفيذ مقترح مع الحفاظ على الاعتماديات

### المسار الجاهز والمستقل

- ابدأ فقط بعد إذن تطبيق منفصل بـ`A0-ARCH-001` كما هو محدد؛ prerequisite `A0-ARCH-901` done، ولا decision dependency. لا حاجة لقاعدة بيانات أو migration إذا لم تتطلبها slice. اختبر حدود module والـDomain بلا framework/provider SDK، ودوّن الأدلة الفعلية. التالي في السجل `A0-ARCH-002` يعتمد على `A0-ARCH-001` **و**`A0-CORE-900`، لذلك لا يفتح قبل إنجاز shell ومطلبه الحالي؛ و`A0-ARCH-003` يعتمد لاحقاً على `A0-ARCH-002` وADR-003.

### أول 30: foundation/auth/approvals/outbox

- خط أساس التطبيق `A0-ARCH-901` done؛ لا إعادة فتح ولا إعادة عدّ.
- `A0-CORE-900` shell عربي يتبع `A0-ARCH-901` ويتطلب قرار ADR-001 حسب السجل الحالي. يبقى محجوباً حتى استيفاء المصدر المخول. لا تخلط shell مع authentication.
- `A0-SEC-002` يأتي بعد `A0-SEC-001` و`A0-CORE-900` لإظهار inherited permissions/employee exceptions؛ `A0-SEC-003` يعتمد على `A0-ARCH-901` و`A0-SEC-002` لحجب الحقول؛ `A0-SEC-004` يتبع `A0-SEC-003` و`A0-CORE-900`. قبل real-user workflows يجب إكمال scoped identity/authentication وobject-level authorization/masking؛ لا تعرض تطبيقاً بمصادقة متعطلة.
- approval chain الحالية لا تُختصر: `A0-SEC-005` يتطلب `A0-ARCH-901` و`A0-SEC-004` وقرار `OPEN-02`; ثم `A0-SEC-006` يتطلب `A0-SEC-005` و`A0-CORE-900` و`OPEN-02`. لا تفعّل عتبات أو تفويض قبل اعتمادها؛ لا self-approval أو autoapprove بسبب انخفاض القيمة.
- `A0-ARCH-902` مستقل وظيفياً عن shell لكنه يتطلب `A0-ARCH-901` وقراري `ADR-002` و`ADR-003`. لا يبدأ قبل حسم بوابة المحرك/queue وفق DAG. المطلوب outbox transaction/crash/replay/inbox dedup دون provider؛ لا قاعدة افتراضية ولا claim exactly-once.
- متطلبات الهوية/authorization المتبقية تنفذ بترتيب task records نفسها (مثل `SEC-007` وما بعده)، ثم audit/offboarding. لا تسقط SEC gate أو تغير sequence بسبب هذه الخطة.

### أساس مشترك ثم A1

- بعد gates السابقة: module boundaries/data contracts → إثبات MariaDB disposable وdecimal/locks/unique constraints/migration forward-recovery/restore evidence → RTL shell/accessibility → scoped identity/auth, field masking, audit, outbox → approvals/SoD/offboarding/master data/private documents. هذا ترتيب عمل مقترح فقط؛ يعتمد فتح كل task على DAG الفعلي لا على الترتيب النصي.
- A1 CRM وworkspace: اتبع prerequisite tasks للأهداف CRM وtask/calendar وKPI كما في `tasks.json`، بما فيها اكتمال authz وoutbox، دون تخطي مهام connector التي تقع بين سلاسلها. المحادثة الداخلية `COM-02` لها سلسلة `A1-COM-003` تعتمد على `A1-COM-002`, `A0-ARCH-901`, `A0-SEC-002`, `A0-ARCH-902`؛ و`A1-COM-004` تعتمد على `A1-COM-003` و`A0-CORE-900`.
- بريد `COM-03`: `A1-COM-005` يعتمد على `A0-ARCH-901`, `A1-COM-004`, `A0-SEC-002`, `A0-ARCH-902`, و`OPEN-07` + `ADR-006`; يتبعه `A1-COM-006`، ثم `A1-COM-007`. SMS `COM-04` لا يبدأ قبل predecessor `A1-COM-006`، و`A1-COM-007` يتطلب `A0-ARCH-901`, `A0-SEC-002`, `A0-ARCH-902` إضافةً إلى predecessor، مع `OPEN-07` + `ADR-006`؛ ثم `A1-COM-008`.
- Telegram `COM-05`: `A1-COM-009` يعتمد على `A1-COM-008`, `A0-ARCH-901`, `A0-SEC-002`, `A0-ARCH-902`, و`OPEN-07` + `ADR-006`; ثم `A1-COM-010`. WhatsApp `COM-06`: `A1-COM-011` يعتمد على `A1-COM-010` ومثلها foundation/auth/outbox والقرارات؛ ثم `A1-COM-012`. الاستبعاد/التأجيل في المسودة لا يعطل dependencies آلياً ولا يمنح permission لتجاوزها. `A1-COM-013/014` (capture) يتبعون COM-06 في DAG الحالي ويعتمدون أيضاً على `OPEN-10`.
- يظل `A1-AT-11` عند نهاية connector path اختباراً للأعطال/retry فقط بعد اكتمال اعتماديه (وتشمل `A1-COM-018`)؛ fake-only contract لا يعد دليلاً لرسالة أو provider حقيقي. `A1-GATE-001` ينتظر كل مهامه ومعايير مالك الإصدار.

### A2/A3/A4/A5 بعد gates المجال

- A2 commercial: quotes/contracts/orders ثم project/budget/work orders حسب المهام الحالية. `A2-SALE-*` يتطلب shell وapproval/auth وفق الحواف؛ المال/المخزون لا يتحركان عند quote approval. موافقات `OPEN-02` و`OPEN-03/05` عند أثرها، وAT-03 لا يسبق مهامه source.
- A3 warehouse/delivery/QA/production: stock ledger، reservation مقابل issue، units/valuation بعد `OPEN-04`, ثم delivery/proof/QA/production. لا تخترع وحدات أو طريقة تكلفة. `A3-AT-04` يعتمد على `A3-INV-006`; `A3-AT-06` يعتمد على `A2-PRJ-010`, `A2-PRJ-004`, `A3-QA-002`; بوابة A3 تتطلب إكمال السجل الحالي و`OPEN-12` ذات الصلة.
- A4 finance/commission: ledger/expenses/receipts/partners أولاً، ثم commission/payroll فقط بعد `OPEN-01/03/05` حسب المهمة. الأدوار المحاسبية تعتمد COA، tax، التقويم، mappings، recognition والتسوية قبل posting؛ لا تفعيل FX أو معدلات عمولة مجهولة. `A4-AT-07` يتطلب `A4-FIN-002` و`A4-FIN-006`; `A4-AT-08` يتبع `A4-PAY-004`; لا تقبل finance gate قبل reconciliation/الموافقات.
- A5 dashboards/AI: dashboards تبنى من مصادر معتمدة ولا تخلط cash/revenue/margin/stock. deterministic AI guard `A5-AI-001` يعتمد صراحة على `A0-ARCH-901`, `A0-SEC-002`, `A0-ARCH-902`, `A4-FIN-006`, `A3-INV-004`; `A5-AI-002` يتطلب `A5-AI-001` و`A0-CORE-900`. priority review `A5-AI-003` يتبع `A5-AI-002` ويعتمد `OPEN-09` و`ADR-007` مع متطلباته الأخرى. لا تقفز من foundation إلى connector أو `A5-AI-003`; استخدم splits مصرحاً بها إذا اختلفت المرحلة.
- `A5-AT-10` يعتمد على `A5-AI-006` و`A5-AI-008`; practical audit لا يعد مكتملاً قبل AT-10 والـA5 gate ومراجعة المالك. AI foundation وحدها لا تحقق mandate العملي أو `AI-02/05/09`.

## 4. مصفوفة قبول مستقبلية — كل النتائج `NOT RUN`

تُنفذ بعد موافقة المالك على fixtures والقواعد وحصول إذن التطبيق/الاختبارات. كل الأرقام أسفل أمثلة اختبار، لا قرار إنتاج؛ لا معدل عمولة أو سياسة مالية/قانونية مخترعة.

| المجال/المعرف المقترح | بيانات وخطوات الاختبار الاصطناعي | معيار النجاح المتوقع | المالك/الحالة |
|---|---|---|---|
| موافقة العتبة 5000 | معاملات قيمة `4999.99`, `5000.00`, `5000.01` لنفس العملية، وactor/approver مستقلان | أقل من العتبة: `CURRENT` authority مع الموافقة العادية؛ يساويها: `CURRENT` authority مع الموافقة العادية؛ الأكبر: إضافة مستوى أعلى. لا autoapprove في أي حالة. | Executive يقر العملية/السلطة؛ `NOT RUN` |
| موافقة العتبة 100000 | `99999.99`, `100000.00`, `100000.01` للعقود | نفس قاعدة strict `>`: current/current/additional؛ `>=` يفشل. | Executive؛ `NOT RUN` |
| خصم العرض 10% | `9.99%`, `10.00%`, `10.01%` | أقل/يساوي: current authority مع approval المعتاد؛ الأعلى: additional authority. لا pass حتى يوافق المالك مقام النسبة وأساس القيمة؛ ليس بالاختبار معنى أن قيمة العتبة معتمدة. | Executive/Product؛ basis `OPEN`; `NOT RUN` |
| تعديل مخزون 10000 | قيم `9999.99`, `10000.00`, `10000.01` على القيمة التي يحددها المالك | current/current/additional وفق `>`؛ لا تجاوز للـWarehouse authority أو approval العادي؛ تعريف valuation basis لازم قبل توقع النتيجة. | Executive + Warehouse/Finance؛ `NOT RUN` |
| fail-closed unknown | عملية أو actor أو كيان أو وحدة/عملة أو إصدار policy غير معروف، نقص authority أو stale state | يرفض mutation أو يحيله لمسار مراجعة معرفاً؛ لا تخمين threshold ولا fallback permissive ولا أثر مالي/مخزني جزئي. | Engineering + Security؛ `NOT RUN` |
| version/reapproval | اعتماد version N ثم تغيير جوهري إلى N+1؛ وحالة تغيير غير جوهري معرفها المالك صراحةً | N لا يعتمد N+1؛ جوهري يطلب موافقة جديدة حتماً؛ لا يقرر النظام جوهرية غير محددة أو يسمح بها تلقائياً؛ كل انتقال مؤرخ ومدقق. | Executive + Product؛ nonmaterial policy `OPEN`; `NOT RUN` |
| delegation/SoD | قائمة delegate معتمدة، خارج القائمة، مفوض ينشئ/يعتمد، محاولة تفويض ذاتي أو تجاوز limit | القائمة المسموحة فقط؛ منع self-approval حسب القاعدة؛ تفويض لا يرفع سقف مال أو SoD أو permission غير قابل للتفويض. كل رفض موثق. | Executive + Security؛ قائمة/مدة/roles `OPEN`; `NOT RUN` |
| break-glass | إجراء allowlisted، actor مدير الشركة/المدير التنفيذي أو بديله التنفيذي المخول، ثم مراجعة لاحقة مستقلة؛ إجراء غير مسموح | المسموح فقط وبسبب/وقت/audit ومراجع مستقل لاحق؛ منع تعطيل ثوابت الحسابات/الخصوصية/المخزون، والرفض للإجراء غير المعرف. | Executive + Security؛ allowlist/reviewer `OPEN`; `NOT RUN` |
| SLA calendar | approval pending حتى 24 business hours بتقويم وساعات شركة معتمدين؛ expiry وdelegate | لا countdown خارج ساعات العمل؛ على expiry إشعار higher authority **وإعادة** الإسناد إلى authorized substitute. إذا لم يوجد بديل مخول: لا autoapprove ولا إسناد لغير مخول؛ تسجل الحالة غير محسومة وتُصعّد للمعالجة. | Executive + HR/IT؛ calendar/roles `OPEN`; `NOT RUN` |
| commission collection | Receipt مرتبط مباشرة بفاتورة eligible؛ Receipt غير مخصص؛ advance غير مطبق؛ قسط جزئي | المصدر فقط collection eligible؛ unmapped accrual صفر؛ advance لا يستحق قبل التطبيق. لا حساب مبلغ قبل اعتماد rate/eligibility fixtures. | Executive + Finance؛ rate `OPEN`; `NOT RUN` |
| commission splits/reallocation | عقد مختلط بتصنيف/معدل واحد معتمد؛ split معتمد قبل posting؛ تعديل split؛ refund/discount وإعادة تخصيص | رفض split غير المعتمد؛ accrual source/idempotency واحد؛ reversal موثق للأصل ثم تطبيق جديد حسب policy؛ لا تحرير صامت أو ازدواج. | Executive + Finance؛ `NOT RUN` |
| refund/closed statement | استرداد عمولة سبق صرفها؛ كشف شهر مقفل؛ adjustment بفترة مفتوحة تشير للأصل؛ موظف مغادر | رصيد settlement مالي لا deduction راتب آلي؛ بيان المقفل immutable؛ التعديل بالفترة المفتوحة مع origin reference؛ حفظ الحق حسب الاتفاق لا حذف بسبب offboarding. | Finance + Executive؛ legal terms `OPEN`; `NOT RUN` |
| عمولة EGP/digits | نصوص decimal لـEGP مع دقة منزلتين، أمثلة half-way/residual بعد fixture يعتمدها Finance؛ محاولة foreign currency/FX | لا `float`؛ نتيجة مطابقة تماماً لقاعدة التقريب والمرحلة التي يوقعها Finance. رفض/تعطيل foreign currency وFX في Phase A. لا expected rounding قبل توقيع Finance. | Finance؛ rounding `OPEN`; `NOT RUN` |
| decimal/MariaDB races | قاعدة disposable على نسخة/driver/engine ثابت؛ معاملات متزامنة reserve/issue/close وunique/idempotency/FK وتجاوز decimal | لا ازدواج أو overdraw أو post بعد close؛ rollback/retry موثوق؛ دقة decimal ثابتة؛ scope/FK يمنع cross-entity. النتائج لا تعمم خارج المحرك/الإصدار المختبر. | Engineering + Finance/Warehouse؛ `NOT RUN` |
| tax/COA/fiscal posting | COA/mappings/period/line tax/partner entries مع expected entries يوقعها المحاسب؛ فترات مفتوحة/مغلقة وعكس | قيود متوازنة ومصدرها approved event؛ رفض ما لا mapping أو rate له؛ closed period لا يقبل posting؛ reconciliation TB/GL مطابق. لا tax rate/account/calendar افتراضي. | Finance + accountant؛ `OPEN-03`; `NOT RUN` |
| stock conversion/valuation | SKU base unit وإصدارات تحويل، receipt/reserve/issue/return/damage/count variance; lot عند الحاجة | حفظ نسخة التحويل ومصدر التكلفة؛ on-hand/reserved/available منفصلة؛ لا سالب متاح حسب السياسة؛ reversal/idempotency؛ لا نتيجة تقييم قبل قرار `OPEN-04`. | Warehouse + Finance؛ `NOT RUN` |
| project recognition | عقد، invoice/receipt، direct material/labor/transport/subcontract/site cost، overhead/tax/WIP/close cases | cash منفصل عن recognized revenue؛ estimated/actual margin منفصل؛ كل التكلفة مرتبطة بمصدر؛ amendment/refund موثق؛ لا journal/metric قبل توقيع recognition/allocation rules. | Finance + Operations؛ `OPEN-05`; `NOT RUN` |
| production/QA/retention | output/input/waste/return linked records؛ QA evidence/partial accept/corrective action/close; retention/delete/legal hold | production لا يكرر المخزون أو يطبق BOM/MRP غير مصرح؛ gate QA لا يتخطى شروط المالك؛ لا مدة/حذف قانوني دون صلاحية/قرار معتمد؛ restore لا يعيد بيانات محذوفة خلاف policy. | Operations + Quality + Legal/Finance؛ `OPEN-06/10/12`; `NOT RUN` |
| providers/notifications | fake mail/SMS/WhatsApp/Telegram/push providers؛ failure/timeout/duplicate callback وrevoked permission | لا socket/egress/credential/message حقيقي؛ idempotency وunknown/failure مرئيان؛ authorization يعاد وقت dispatch؛ contacts masked في UI/API/search/export/logs/previews؛ `accepted` لا يساوي delivered. | Management + IT + Security؛ `NOT RUN` |
| AI advisory | fixture اصطناعي duplicate expense + prompt injection + invalid schema + stale version + outage | finding advisory evidence-linked وversioned، review بشري؛ لا tool/cash/stock/approval mutation؛ unknown/outage fail-closed ويظل deterministic rule نافذاً؛ cost/usage logged أو gap معلن؛ لا provider call أو real data. | Executive + Security؛ `OPEN-09/10`; `NOT RUN` |
| file protection/recovery | ملفات synthetic quarantine/MIME/size/hash/download authorization؛ اختبار backup/restore بعد تحديد الوسيط | لا public URL أو webroot access؛ download auth جديد؛ scanner failure يمنع finalize؛ backup/restore يطابق DB+files+keys/checksum. النسخ القائمة لم تُفحص ولا تعتبر نجاحاً. | IT + Security؛ `OPEN-10/11`; `NOT RUN` |

## 5. مخرجات/حواجز الإصدار

- A0 لا يقبل real-user exposure قبل scoped identity، server authorization، masking، audit/SoD والـRTL؛ لا يكون SQLite concurrency proof للمحرك الإنتاجي.
- A1 لا يقبل connectors حية بناءً على interface أو synthetic provider؛ يبقى `A1-AT-11` اختبار فشل/إعادة محاولة ضمن نطاق مصرح، ولا يكفي mock لإثبات خدمة فعلية.
- A2–A4 لا تعتمد قيمة مالية/مخزون/ضريبة/عمولة أو إقفال دون owner-approved fixtures ومراجعة المحاسب/Warehouse/Operations؛ لا auto-post أو auto-close أو deduce payroll ضمن default مقترح.
- A5 لا يعلن practical AI أو Phase A completion اعتماداً على foundation. يتطلب evidence حقيقي ضمن البيانات/المعالجة المصرح بها، تحقق `AT-10` ومراجعة بشرية وبوابة `A5-GATE-001`؛ وأي مزود حي يحتاج قرار egress/credentials/privacy منفصل.
- Production منفصل عن قبول code: يلزم إثبات الموارد/OS/MariaDB/backups/load/restore/RPO/RTO والمراقبة/security/runbooks، مراجعة أمنية وقانونية واعتماد أصحاب القرار قبل أي نشر. إفادة cPanel/WHM أو VPS لا تثبت الموارد أو الإعداد.

## 6. ما لا تنفذه هذه الخطة

لا code، migrations، dependency changes، deployment، external provider call، credential access، production/VPS access، database install، test execution أو app-state mutation. لا task/backlog/lease/history mutation ولا approval. أي واحد من هذه الأعمال يحتاج تفويضاً منفصلاً؛ الفحوص الواردة أعلاه كلها مستقبلية `NOT RUN`.

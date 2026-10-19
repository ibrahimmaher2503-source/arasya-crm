# مسودات طلبات تغيير نطاق Phase A

> **كل الطلبات أدناه مسودات فقط: لم تعتمد ولم تطبق.** لا تغير PRD أو ADR أو OPEN أو سجل المهام. تحافظ هذه المقترحات على متطلبات المصدر غير المتعارضة، وتطلب من المالك قراراً صريحاً قبل أي إعادة تصنيف للنطاق أو تعديل DAG. لا مواعيد بديلة مفترضة.

## ضوابط القراءة

- الحالة لكل طلب: `OPEN` للمراجعة؛ لا موافقة ضمنية من إنشاء هذه الوثيقة.
- خط الأساس هو [PRD](../../product/PRD.md)، [ADR-006](../../architecture/adr/ADR-006.md)، [ADR-007](../../architecture/adr/ADR-007.md)، [OPEN_DECISIONS](../OPEN_DECISIONS.md) و`tasks.json` كما قُرئت وقت إعداد المسودات.
- متطلبات التشفير/السرية، masking، authorization، ownership، audit، idempotency، retry، provider abstraction، الفشل الآمن وقيود قانونية لا تُلغى بأي طلب.
- لكل اعتماد لاحق يلزم مالك القرار، النطاق المختار، التاريخ، المبرر، evidence، الاختبارات، مراجعة الأدوار والمهام المتأثرة وفق change control. لا ينشئ هذا المستند sign-off ولا يحذف adapters أو acceptance criteria من المصدر.

## CR-EMAIL — COM-03 البريد المؤسسي الحي

**الحالة:** `OPEN` — مسودة؛ لم تعتمد ولم تطبق.

**مراجع خط الأساس:** PRD `COM-03`, `COM-07..09`, `ARCH-02/03`, `NFR-01/03`; `OPEN-07/10/11`; المهام `A1-COM-005 → A1-COM-006 → A1-COM-007` ثم `A1-GATE-001`، و`A1-AT-11`.

**النطاق المقترح:** في Phase A يقتصر التسليم على عقد mailbox/provider قابل للاستبدال، تخزين حالات ورسائل ببيانات تركيبية، واختبارات adapter وهمية بلا اتصال شبكي أو حساب بريد أو أسرار. يؤجل إرسال/استقبال البريد المؤسسي الحي والإدارة الفعلية للصناديق إلى قرار منفصل بعد مراجعة IT/Management والخصوصية.

**خط الأساس القائم:** PRD `COM-03 MUST` يطلب قدرة mailbox مؤسسية تشغيلية تشمل draft/send/receive/reply/forward/inbox/sent/attachments/CC/BCC والربط بالسجلات؛ لا يقتصر على التقييم أو العقد. هذا الالتزام baseline لم يتغير.

**الانحراف المطلوب اعتماده:** يطلب هذا التغيير المقترح حصر تسليم Phase A مؤقتاً في interface وعقد fake-only وبيانات اصطناعية بلا egress أو حساب/أسرار، وتأجيل mailbox الحي. لا يصبح هذا الحد بديلاً عن `MUST` حتى يوافق مالك التغيير صراحة على تغيير نطاق Phase A؛ لا تعديل للمصدر هنا. تظل متطلبات التشغيل الحي المؤجل كاملة، ومنها الأسرار المشفرة والحساب المملوك للشركة والربط بالسجل وmasking وauthorization عند التنفيذ وحالات التسليم وretry/idempotency/audit والاحتفاظ.

`A1-AT-11` baseline هو دلالات فشل/حالة/retry آمن عند فشل provider؛ لا ينص وحده على paid/live send. fake tests لا تثبت توافق أي provider حقيقي، ولا يُفهم منها أنها اجتازت `A1-AT-11`.

**الاعتماديات والبوابات:** لا يُتجاوز أو يُعدل الترتيب الحالي: `A0-ARCH-901` و`A0-SEC-002` و`A0-ARCH-902` و`A1-COM-004` قبل `A1-COM-005`؛ ثم `A1-COM-006`؛ وتظل مراجعة `A1-AT-11` وبوابة `A1-GATE-001` على تعريفهما واعتمادياتهما الحالية. `A1-AT-11` يثبت دلالات فشل/retry، ولا يفرض وحده إرسالاً حياً. أي تقسيم لتسليم synthetic-only يتطلب task split معتمداً وتحديث DAG بواسطة coordinator، ولا يبيح Claim أو تجاوز dependency حالية.

**المخاطر:** العقد الاصطناعي قد يُفهم خطأ كميزة حية؛ تأخير البريد يغير قيمة التسليم؛ احتفاظ الرسائل والوجهات والملفات قد يكشف بيانات أو يخالف قرار الاحتفاظ.

**نص القبول المقترح للانحراف فقط:** «إذا اعتمد Product owner وManagement + IT تغيير حد Phase A، يُقبل حد synthetic-only باختبارات العقد والحالات على بيانات اصطناعية دون egress أو credentials، مع وسم كل حالة بأنها غير حية. يظل PRD `COM-03 MUST` التشغيلي قائماً للمسار المعتمد/المؤجل، ولا تُعلن جاهزية mailbox حية من هذا الدليل. يظل `A1-AT-11` معياراً لدلالات الفشل/retry كما هو؛ الاختبار الوهمي لا يثبت توافق provider حقيقي، ولا يفرض هذا النص إرسالاً حياً مدفوعاً.»

**الأدوار المطلوب اعتمادها:** Management + IT؛ Security/Legal حسب تدفق البيانات والاحتفاظ؛ Product owner لقبول حد Phase A.

## CR-SMS — COM-04 رسائل SMS الحية

**الحالة:** `OPEN` — مسودة؛ لم تعتمد ولم تطبق.

**مراجع خط الأساس:** PRD `COM-04`, `COM-07..09`, `ARCH-02/03`, `NFR-01/03`; `OPEN-07/10/11`; المهام `A1-COM-007 → A1-COM-008 → A1-COM-009`، ثم `A1-GATE-001` و`A1-AT-11`.

**النطاق المقترح:** adapter/interface وحالات delivery باستخدام fake provider وfixtures تركيبية فقط. لا اختيار SMS Misr أو غيره، ولا إرسال فعلي أو حساب أو sender identity أو credentials ضمن Phase A حتى موافقة مستقلة.

**خط الأساس القائم:** PRD `COM-04 MUST` يطلب تكامل SMS تشغيلي بهوية sender الشركة وقوالب/إيصالات delivery وتدقيق وحل الوجهة من سجل العميل؛ لا يقتصر على التقييم أو العقد. هذا الالتزام baseline لم يتغير.

**الانحراف المطلوب اعتماده:** يقترح هذا الطلب Phase A fake provider وعقداً/حالات اصطناعية فقط، دون إرسال أو حساب أو credentials، وتأجيل SMS الحي. لا يُعد هذا تغييراً نافذاً ولا يحذف `MUST` قبل اعتماد صريح لتغيير النطاق. تبقى متطلبات التشغيل المؤجل كاملة: destination server-resolved، sender مملوك للشركة، template controls، audit، receipts، `accepted` لا يعني `delivered`، retry idempotent، privacy/masking وحالات الفشل والlimits.

`A1-AT-11` baseline يختبر دلالات فشل provider وإظهار الحالة وإعادة المحاولة الآمنة؛ لا يفرض وحده إرسالاً حياً مدفوعاً. fake tests لا تثبت توافق provider فعلي ولا تُسجل نجاحاً حياً لـ`A1-AT-11`.

**الاعتماديات والبوابات:** لا تجاوز لـ`A1-COM-007` dependencies (`A0-ARCH-901`, `A1-COM-006`, `A0-SEC-002`, `A0-ARCH-902`) ولا `OPEN-07`/`ADR-006`. يتبع `A1-COM-008` ثم المسار الموجود إلى `A1-GATE-001`. التقسيم المقترح لمهمة fake-only لا يسري دون موافقة task split وتحديث DAG مخول.

**المخاطر:** إيصال كاذب أو تكرار رسالة، وجهة مكشوفة، كلفة أو قواعد provider/consent غير معروفة، والخلط بين قبول الطلب والتسليم.

**نص القبول المقترح للانحراف فقط:** «إذا اعتمد Product owner وManagement + IT تأجيل SMS الحي، يُقبل حد fake-only على بيانات اصطناعية للتحقق من destination resolution وmasking وaudit وحالات queued/accepted/delivered/failed/unknown وidempotency دون egress أو credentials. يبقى PRD `COM-04 MUST` التشغيلي قائماً ولا تعلن جاهزية SMS حية. يظل `A1-AT-11` معيار دلالات الفشل/retry كما هو؛ الاختبار الوهمي لا يثبت توافق provider حقيقي، وهذا الطلب لا يضيف شرط إرسال حي مدفوع.»

**الأدوار المطلوب اعتمادها:** Management + IT؛ Security/Legal وFinance حسب البيانات والكلفة؛ Product owner لحد النطاق.

## CR-WHATSAPP — COM-06 قرار قناة WhatsApp

**الحالة:** `OPEN` — مسودة؛ لم تعتمد ولم تطبق.

**مراجع خط الأساس:** PRD `COM-06`, `COM-07..09`, ملاحظة WhatsApp المعمارية في PRD §9.1، `ARCH-02/03`, `NFR-01/03`; `OPEN-07/10/11`; المهام `A1-COM-011 → A1-COM-012 → A1-COM-013` ثم `A1-GATE-001`.

**النطاق المقترح:** حفظ pluggable contract منفصل وقابل للاختبار ببيانات مصطنعة؛ لا اتصال WhatsApp حي، ولا Meta API أو بديل، ولا QR/Web automation، ولا أرقام/حسابات/credentials. القرار التجاري للقناة وكلفتها وامتثالها مؤجل.

**ما يبقى واجباً:** يبقى `COM-06 MUST` كقرار قناة/تقييم وعقد قابل للتبديل، ولا يحذف تفضيل القناة أو شرط تقييم compliance/reliability/retention/risk قبل production. تبقى رسائل مستقلة عن provider، ملكية الحساب للشركة، حل الوجهة بالـbackend، masking، حالات provider، receipts وretry/reconciliation وopt-in حيث يلزم. لا اعتماد بديل منخفض الكلفة مفترض.

**الاعتماديات والبوابات:** تبقى سلسلة المهام الحالية بما فيها الاعتماد على `A1-COM-010` و`A0-ARCH-901` و`A0-SEC-002` و`A0-ARCH-902`، والقرارين `OPEN-07` و`ADR-006` قبل `A1-COM-011/012`; ثم `A1-COM-013/014` و`A1-GATE-001`. أي تسليم contract-only قبل الحواجز يتطلب task split وتحديث dependency رسمي، لا تنفيذ موازٍ مفترض.

**المخاطر:** حساب/سياسة منصة/بيانات/تكلفة غير معروفة؛ احتمال حظر الحساب أو كشف وجهة/محتوى؛ توهم أن adapter يعني جاهزية provider. حظر صريح على unsupported QR automation.

**نص القبول المقترح:** «يجتاز العقد اختبارات fake-only وprovider-independent، مع عدم وجود اتصالات أو رسائل أو credentials. لا WhatsApp production أو QR automation قبل موافقة مكتوبة منفصلة من Management + IT على المسار الرسمي/المزود والامتثال والكلفة والملكية والاحتفاظ والخصوصية، واستيفاء `OPEN-07/10/11` ومراجعة Security/Legal. لا يغير ذلك `COM-06` كمتطلب تقييم/عقد.»

**الأدوار المطلوب اعتمادها:** Management + IT؛ Security/Legal؛ Product owner.

## CR-TELEGRAM — COM-05 وARCH-03

**الحالة:** `OPEN` — مسودة؛ لم تعتمد ولم تطبق.

**مراجع خط الأساس:** PRD `COM-05` الموسوم `MUST` كقناة Telegram اختيارية، و`ARCH-03` الذي يدرج Telegram ضمن provider abstractions؛ `OPEN-07`; المهام `A1-COM-009 → A1-COM-010 → A1-COM-011` ثم `A1-GATE-001`.

**النطاق المقترح:** استبعاد Telegram من التسليم Phase A الحالي، مع إبقاء provider interface عامة قابلة للاستبدال واختبارها fake-only. لا حذف adapter أو مطلب `MUST` من PRD/ARCH-03 ولا ادعاء أن البند لم يعد مطلوباً؛ المطلوب قرار signed يحدد صراحةً النطاق المؤجل قبل إعادة تصنيفه.

**ما يبقى واجباً:** provider abstraction تبقى مستقلة عن أي vendor ID؛ عدم توفر قناة خارجية لا يعطل العمليات الأساسية؛ سجل الرسائل والصلاحيات والخصوصية وconsent/initial contact ومتطلبات retention تبقى واجبة عند أي تشغيل لاحق.

**الاعتماديات والبوابات:** المهام الحالية `A1-COM-009/010` ما تزال مرتبطة بسلسلة COM وقرار `OPEN-07` و`ADR-006`، ولا تُتخطى أو تعدل من هذا الطلب. يلزم تعديل مخول لـscope/DAG قبل الإزالة/التأجيل، ثم إعادة فحص `A1-GATE-001` وجميع معايير القبول المتأثرة.

**المخاطر:** تعارض التسليم المؤجل مع نص `MUST` الحالي؛ تغير اعتماديات WhatsApp/COM-06 المتسلسلة؛ حذف غير مقصود لكود/عقد لازم لإمكانية الاستبدال.

**نص القبول المقترح:** «لا Telegram live في Phase A. يظل `ARCH-03` interface عامّاً، وتظل `COM-05` مطلباً قائماً إلى أن يوقع المالك تغيير scope يذكر صراحةً تأجيل/حذف adapter Telegram ومبرره وأثره على المهام والبوابات. أي adapter قائم لا يحذف قبل مراجعة dependencies والاختبارات؛ الاختبار الاصطناعي لا يمثل opt-in أو تواصلاً فعلياً.»

**الأدوار المطلوب اعتمادها:** Management + IT؛ Product owner؛ Engineering لتوقيع أثر المعمارية وDAG؛ Security/Legal عند القناة.

## CR-AI — Phase A practical AI Audit

**الحالة:** `OPEN` — مسودة؛ لم تعتمد ولم تطبق.

**مراجع خط الأساس:** PRD §§1.1/11 و`AI-01..09`, `AT-10`, `A5-GATE-001`; ADR-007 و`OPEN-09/10`; مهام `A5-AI-001 → 002 → 003 → 004`، ثم سلاسل `A5-AI-005..018` بحسب الاعتماد الحالي، و`A5-AT-10` يعتمد على `A5-AI-006` و`A5-AI-008`.

**النطاق المقترح:** تأسيس audit/event/rule interfaces وfixtures مصطنعة، قواعد deterministic كأولوية، findings مرتبطة بإصدارات وأدلة، مراجعة بشرية، versioning prompt/rules/schema، وسجل usage/cost أو نقص القياس. لا provider حي ولا egress ولا بيانات شركة حقيقية. الهدف فصل AI Audit foundation الآن عن ادعاء اكتمال AI عملياً.

**ما يبقى واجباً:** لا تتغير عبارة PRD أن Phase A MUST يشحن practical AI Audit على معاملات أولوية؛ هذا الطلب يقترح تسليمه عبر مراحل/بوابات صريحة، لا يخفض المتطلب أو يعيد تعريفه من تلقاء نفسه. تبقى `AI-02/05/09` و`AT-10` و`A5-GATE-001` شروطاً لازمة لقبول practical audit في الحد الذي يقرره المالك. foundation، قواعد حتمية أو fixture demo وحدها **ليست** practical AI completion ولا اجتياز `AT-10` أو A5.

**الاعتماديات والبوابات:** لا تجاوز للحواف الحالية: `A5-AI-001` يتطلب `A0-ARCH-901`, `A0-SEC-002`, `A0-ARCH-902`, `A4-FIN-006`, `A3-INV-004`; `A5-AI-002` يتطلب `A5-AI-001` و`A0-CORE-900`; `A5-AI-003` يتطلب `A5-AI-002` و`OPEN-09/ADR-007` إضافةً إلى متطلباته الأخرى؛ AT-10 يتبع `A5-AI-006/008`. يلزم task splits معتمدة لأي foundation أو connector slice مبكر؛ لا bypass للـDAG. لا تُخفّض `A5-GATE-001` دون change control صريح.

**المخاطر:** تضخيم claims من demo اصطناعي، تسريب prompt/PII، مخرج غير صالح أو stale، cost مجهول، أو AI يتجاوز deterministic rules.

**نص القبول المقترح:** «Foundation الاصطناعية تقبل فقط كطبقة قواعد/عقود ومخرجات advisory قابلة للتتبع؛ لا egress، ولا credentials أو بيانات شركة، ولا تنفيذ أدوات أو اعتماد/ترحيل. لا يعلن practical AI audit أو نجاح `AT-10` حتى يثبت finding على معاملات priority المعتمدة مع subject/version/evidence/reason/severity/confidence، review/disposition بشري، prompt/rule/model versions، failure/invalid-output safeguards وتقييم موثق ضمن `OPEN-09/10` وبوابة `A5-GATE-001`. أي live model يحتاج تصريح منفصلاً قبل الإرسال.»

**الأدوار المطلوب اعتمادها:** Executive + Security لـ`OPEN-09`؛ Legal + HR/Finance لـ`OPEN-10`؛ Product owner لتفسير Phase A؛ Engineering لتنفيذ تغيير النطاق والمهام.

## توضيحات مشتركة غير محسومة

- يطلب baseline توضيح العلاقة بين `LOCAL` private storage وعبارة PRD «encrypted private object storage»؛ المقترح المحلي لا يثبت التشفير at-rest أو key controls.
- Browser Push إضافة نطاق مقترحة. إذا تحول إلى push حي، يلزم قرار مستقل حول egress/provider/keys/subscriptions/privacy؛ fake-only هو الحد الحالي.
- `Web + PWA online-only` متوافق مبدئياً مع PRD إذا حافظ على parity وإعادة اتصال آمنة؛ تجارب الجهاز وحدود التخزين/الخصوصية ما تزال مطلوبة.
- كل سيناريو provider synthetic اختبار عقد محلي فقط؛ لا يحقق acceptance حي أو موافقة قناة.

## CR-AI-ACTIONS — فصل AIActionAssistant وProactiveOperationalIntelligence

**الحالة:** `OPEN` — DRAFT مقترح توضيحي إضافي؛ غير معتمد وغير مطبق، ولا يحل محل مسودة `CR-AI` الخاصة بمتطلب practical AI Audit.

**المراجع:** PRD `AI-08/09`, `AT-10`, SCOPE الذي يؤجل autonomous chat/voice، ADR-007 وOPEN-09/10؛ [معمارية المساعد](../../architecture/AI_ASSISTANT_ARCHITECTURE.md) و[تغطية الأدوات](../../engineering/AI_TOOL_COVERAGE.md). المهام الحالية ذات الصلة تشمل `A5-AI-001..018`, `A5-AT-10`, `A5-GATE-001` بحسب DAG الموجود؛ لا يُنشأ ID أو حافة جديدة هنا.

**النطاق المقترح للمراجعة:** فصل (1) proactive deterministic findings/notifications advisory عن (2) AIActionAssistant typed future-action proposal. لا تنفيذ أدوات، ولا action schema قابل للتنفيذ، ولا registry exposure، ولا تعديل موافقة/حساب/مخزون/بيانات موظف، ولا provider حي. تبقى ضرورة تغطية كل capability مدعومة في سجل tool-coverage عند تنفيذ feature كما تنص وثيقة DoD؛ ذلك ليس تفويضاً لإطلاق assistant.

**المطلوب من المالك:** Product/Executive يحددان معنى وأولوية Phase A practical AI Audit ومتطلبات الـacceptance؛ Security/Legal يراجعان حدود البيانات؛ Engineering يوضح أثر الفصل على deliverables والاختبارات. إن كان المطلوب task split أو إعادة ترتيب/إضافة dependency، فلا يطبق إلا بعد change-control approval وتحديث DAG مخول من coordinator، ثم مراجعة AT-10/A5 gate. لا تقسيم أو تحديث في هذه المسودة.

**معايير قبول مستقبلية غير منفذة:** اختبارات synthetic تثبت أن audit output منفصل عن action-proposal schema، وأن proposals لا تنفذ أدوات ولا تكتب/تعتمد، وأن proactive recipient authorization مستقل ويعاد فحصه؛ fail-closed عند revoke/stale/invalid. أي تغيير لا يخفف AI Audit MUST أو AT-10 ولا يدعي أن foundation يفي بالمتطلب العملي. لا provider/egress أو بيانات حقيقية.

**الأثر/المخاطر/الرجوع:** قد يحدث التباس بين proposal وطلب تنفيذ أو بين foundation وإتمام AI عملي؛ إبقاء كل status disabled/restricted حتى قرار نطاق وتنفيذ منفصل. الرجوع بتعطيل الواجهة/الاقتراحات مع حفظ audit/evidence؛ لا حذف سجل ولا عكس معاملة.

**الأدوار المطلوبة:** Product owner وExecutive؛ Security/Legal حسب تدفق البيانات؛ Engineering لتقدير/توقيع التغيير والاختبارات وDAG.

## CR-STORAGE-PUSH — توضيح storage baseline وحدود Browser Push

**الحالة:** `OPEN` — DRAFT؛ غير معتمد وغير مطبق. يضيف سؤالاً صريحاً لما سبق في التوضيح المشترك؛ لا يغير الاختيارات أو الخمس مسودات السابقة.

**المراجع/الأثر:** PRD `DOC-02`, `ARCH-02`, `NFR-01/03`; ADR-004/006; OPEN-07/10/11; مهام التخزين `A0-DOC-001..004`, ومهام القنوات حسب السجل الحالي `A1-COM-005..014`, `A1-COM-017..018`. المرجع الدقيق وأثر الاعتماد يبقيان في [مصفوفة المهام](../gate-clearance/DECISION_TASK_MATRIX.md). لا حواف أو task splits جديدة.

**القرار المطلوب:** يحدد IT + Security ما إذا كان `LOCAL` خاصاً خارج webroot هدفاً محلياً مؤقتاً أم حد تشغيل مقبول، وكيف يحقق/لا يحقق عبارة PRD `encrypted private object storage`؛ يحدد التشفير at-rest، key custody، isolation، backup/restore consistency والـprovider boundary. ويحدد Management + IT + Security ما إذا كان Browser Push fake-only محصوراً في الاختبارات أو يحتاج قرار egress/provider/keys/subscriptions/retention مستقلاً. لا تعتبر تكوين local disk أو وجود config دليلاً على encryption/backup أو قرار push.

**الحالة والاختبارات:** لا اختبارات storage أو push تشغيلية منفذة هنا. بعد قرار مكتوب وإذن منفصل، التحقق المستقبلي يقتصر على fixtures synthetic: storage auth/private-path/download reauthorization/MIME-size quarantine/hash، وتناسق DB/files في restore معزول؛ وpush fake-only لـpermission revoke، duplicate callback، recipient scoping/expiry. أي egress أو credential أو push حقيقي خارج النطاق وغير مصرح به.

**المخاطر/الرجوع:** فقد ملفات، exposure، أو تسليم إشعار لغير مخول. الرجوع إلى تعطيل download/push وإبقاء الملفات الأصلية/mappings وaudit حتى restore reconciliation؛ لا حذف أو نقل destructive ولا إرسال إشعار فعلي.

**الأدوار المطلوبة:** IT + Security لاعتماد التخزين؛ Management + IT + Security لقرار egress/push؛ Legal/Privacy حسب retention والبيانات؛ Engineering لتقييم أثر architecture. لا قرار أو توقيع أو موعد مفترض.

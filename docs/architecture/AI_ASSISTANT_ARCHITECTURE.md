# معمارية AIActionAssistant وProactiveOperationalIntelligence — نطاق Phase 1

> **الحالة:** مواصفة Phase 1 مع تنفيذ وتفعيل محكومين بالـDAG والبوابات؛ ليست قبول ADR أو `OPEN` أو صلاحية تشغيل. لا تغيّر PRD أو DAG أو `OPEN`/ADR. مرجعها `AI-01..09` و`ADR-007` المفتوح وقرارات الخصوصية والموافقات. لا يوجد حالياً ERP business service أو database migration أو tool قابل للتفعيل؛ لا تصف هذه الوثيقة تنفيذًا موجوداً. لقطة الحوكمة `DECISION_REVIEW_PACK.md` في `2026-10-16T04:11:06.6802332+03:00` سجلت 239 مهمة (24 `done`، 86 `blocked`، 129 `planned`)، و0 leases، ولا pending transaction، ومهمة `A0-ARCH-001` وحدها `currently_ready`; من أول 30 كانت 23 `done` و4 `blocked` و3 `planned`. فحص `Status` و`Ready` read-only في هذه الجلسة وافق الأعداد الأساسية وظلت `A0-ARCH-001` الوحيدة المعروضة؛ جميعها لقطات مؤرخة وليست حالة دائمة أو بديلاً عن إعادة الفحص.

## 1. الغرض وحدود النطاق

- تغطية كل قدرة أعمال مدعومة في ERP بأداة typed محددة أو استثناء موثق. أي ميزة أعمال جديدة/متغيرة لا تعتبر مكتملة قبل تغطية الأدوات/الكشف وفق `AI_TOOL_COVERAGE.md` وDefinition of Done. لا يعني ذلك إطلاق المساعد أو اشتراط تكامل مزود.
- يفصل `AIActionAssistant` (حوار موجّه لاستدعاءات أدوات محدودة لاحقاً) عن `ProactiveOperationalIntelligence` (كشف قواعدي وإشعار/اقتراح استباقي). كلاهما مستهلك لخدمات الأعمال وليس مالكاً لمنطقها.
- Phase A يظل خاضعاً لمتطلب AI audit الاستشاري، و`AT-10` و`A5-GATE-001`. لا يحوّل هذا التصميم AI audit إلى تنفيذ أدوات أو يُعد foundation الاصطناعي إتماماً لـAI audit العملي.
- يشمل Phase 1 الحوار العربي/الإنجليزي، الصوت وspeech-to-text، أدوات القراءة والتحليل، preview/confirmation/actions المحكومة، approval integration، الكشف الاستباقي عبر receivables/inventory/projects/tasks/approvals/deliveries/exceptions، findings/inbox/notifications، واسترجاع المستندات المصرح. لا يوسع نطاق procurement/RFQ أو full HR/attendance أو workshop مستقل أو MRP؛ `AT-10` يحظر AI على post/delete؛ `ADR-007` يفرض مخرجات audit استشارية بلا tools. التنفيذ الحساس يظل typed ومؤكداً وموافقاً عليه، ولا يوجد autonomous execution أو provider egress غير مصرح.
- لا توسعة procurement/RFQ أو full HR/attendance أو workshop مستقل أو MRP، ولا قيم/سياسات أعمال مخترعة. تظل سياسات `OPEN-01..12` ومهام DAG كما هي.

## 2. حقائق التنفيذ ودرجة اليقين

| البيان | الوضع الموثق |
|---|---|
| Runtime المحلي | سجل الحوكمة يوثق Laravel 13 وPHP 8.4 كأساس محلي. هذا لا يثبت جاهزية إنتاج. |
| قاعدة البيانات | `OWNER-SELECTED` مبدئياً في حزمة مراجعة المالك: MariaDB؛ `ULID` هو `RECOMMENDED-DEFAULT` لمعرفات جديدة بشرط التوافق. `ADR-002` ما يزال `OPEN`، وتوافق المحرك/الإصدار للإنتاج يحتاج تحققاً. |
| Queue/cache | `OWNER-SELECTED` مبدئياً في حزمة مراجعة المالك: database queue/cache دون Redis؛ `ADR-003` ما يزال `Proposed` ويحتاج تحققاً، ولا يعني ذلك تفعيل runtime. |
| وحدات التطبيق | `ModuleCatalog` وcomposition seams فقط؛ catalog لا يثبت وجود منطق مجال أو أدوات. |
| Identity | الموجود `SEC01` هو guard in-memory لهوية/كيان/قسم وملكية أو إسناد وpermission exact overrides؛ لا branch support أو query scoping أو field masking. لا مصادقة مستخدم مفعلة؛ `config/auth.php` فارغ و`AppServiceProvider` يعطل defaults. |
| Organization | `CORE01 LinkOrganizationReferences` يتيح فحص مراجع كيان read-only فقط. |
| ERP / AI | لا business migrations أو services أو سياسات/موافقات فعلية أو outbox أو tools assistant قابلة للتمكين. لا provider حقيقي مصرح. سجل التطبيق يذكر route health فقط. |
| لقطات المهام | أرقام `DECISION_REVIEW_PACK.md` وقتية؛ لا يعاد تحريرها أو تحديثها هنا. الحالة الحالية لا تُستنتج منها. |

كل capability في جدول التغطية يبدأ `proposed` أو `restricted/disabled`، ولا يسمى `implemented/enabled` إلا بدليل كود واختبارات حاليين. لا أسماء شيفرات مستقبلية أو أمثلة contracts هنا تعني وجودها.

## 3. البنية المنطقية المقترحة

```text
واجهة ERP RTL
  ├─ AIActionAssistant → Orchestrator واحد → Gateway مجرد → (مزود اختياري بعد OPEN-09)
  │                                            └→ Typed Tool Registry / central allowlist
  │                                               → AuthZ + discovery/execution guards
  │                                               → deterministic ActionExecutor
  │                                               → module-owned ApplicationService (نفس UI/API)
  └─ ProactiveOperationalIntelligence
       Scheduler + DomainEvents/outbox + DB queues
       → deterministic ProactiveRulesEngine → Finding/Delivery + Inbox
       → (reasoning AI اختياري لاحقاً عبر Gateway)
```

- Modular monolith واحد؛ orchestrator واحد وprovider-agnostic gateway خلف interface؛ registry typed ومحددة ومملوكة للتطبيق. لا multi-agent ولا microservices ولا Redis ولا vector DB ولا LLM-owned business logic.
- كل module owner يملك business handler/contract/tests داخل وحدته. مركزيًا يدار فقط allowlist/availability/readiness كتالוג capacidades مرئية؛ لا auto-discovery/reflection ولا تسجيل أدوات من package أو annotations. الـassistant لا يملك aggregates أو صلاحيات أو SQL.
- كل handler يمر إلى نفس `ApplicationService` الذي تستدعيه UI/API مع السياسات والمعاملات نفسها. لا مسارات دومين موازية للمساعد.
- الـGateway لا يملك DB credentials أو network freedom. Provider adapter لا يرى قاعدة البيانات، ويأخذ فقط payload مصرحاً ومقلصاً؛ no provider binding قبل `OPEN-09` وقرارات المعالجة/الخصوصية/الميزانية.
- التحليل الأولي يعمل بقواعد deterministic وfixtures عربية/مصطنعة ومخرجات داخلية؛ reasoning AI اختياري، لاحق ومقيد. إيقاف النموذج أو gateway لا يوقف الثوابت الحتمية ولا يسمح بالـfail-open.
- التخزين المنطقي المستقبلي يستخدم MariaDB/DB queue وفق قرارات موثقة بعد حسم ADRs. لا migrations ضمن هذه الوثيقة.

## 4. عقد الأداة ودورة التنفيذ

### 4.1 سجل capability وعقد typed versioned

كل أداة معرفة صراحة في allowlist وتملك: `name`، `contract_version`، `owner_module`، `handler`، `classification` (`Read` / `Draft` / `Write` / `Approval-required` / `Restricted`)، `input_schema`، `output_schema`، `permission_mapping`، scope/filter/projection، preconditions، source/aggregate version، confirmation/approval policy، idempotency policy، limits، test refs وcoverage status. ترفض الحقول المجهولة، الأنواع أو النسخ غير المعروفة، والمدخلات الناقصة؛ لا تحويل أو تخمين صامت.

- قيم scope وactor تؤخذ من server session/identity، لا من model أو body. الكيان والفرع/القسم والإسناد والحقول المسموحة تُشتق/تُتحقق server-side. لا تفويض اعتماداً على role claims من provider أو نص المحادثة.
- كل صلاحية أداة ≤ الصلاحية المماثلة عبر UI/API، ولا ترفع tool visibility أي حق. تحقق صلاحية الاكتشاف والتنفيذ مجدداً؛ التطبيق يعيد فحص السجل والحقل والسياسة في query قبل `COUNT`, pagination, aggregate, export, detail أو side effect، لا بعد جلب النتائج.
- منع SQL/HTTP غير المقيد، استدعاء controllers عشوائية، model/class code، filesystem أو command execution، `generic execute`, arbitrary URL، role/permission edits أو امتداد أدوات صادر من model. لا سكربت تنفيذ حر.
- أدوات القراءة ليست آمنة افتراضياً: تطبق field projection/masking وscope وتدقيقاً وحصصاً. الاستجابة لا تكشف وجود سجل غير مرئي (denial non-enumerating).
- UI context يمرر فقط مراجع entities مصرح بها ومحدودة؛ لا DOM scraping، ولا استيراد صفحة/قائمة غير مخولة. ملفات ورسائل ومخرجات أدوات غير موثوقة؛ لا تتبع تعليمات بداخلها ولا تُرسلها بوصفها authority.

### 4.2 مراحل تنفيذ الأداة

1. المصادقة الحالية ثم تفويض discovery؛ إظهار الأدوات المفعلة للمستخدم ضمن النطاق الحالي فقط.
2. تفسير intent إلى طلب schema typed؛ التحقق من النسخة والحقول والlimits، ثم resolve للكيانات عبر lookup مصرح. عند غموض الهوية/المبلغ/الكيان اسأل المستخدم؛ لا تختار تخميناً.
3. إعادة التفويض والاستعلام المقيّد قبل preview؛ تقييم domain preconditions وcurrent source version والسياسة عبر الخدمة الحتمية.
4. عرض preview واضح: السجل والمبلغ/الحقول والتغيير المتوقع، الموافقات المطلوبة، التحذيرات، النسخ، والآثار/الخطوات. `Draft` لا يرسل/يحجز/يرحّل ولا ينتج أثراً جانبياً غير معلن.
5. لأي كتابة: تأكيد بشري صريح لنفس plan/action بعد المعاينة. approval المطلوبة مستقلة عن تأكيد المستخدم؛ لا قبول AI ولا قبول الإشعار يعد تأكيداً أو موافقة.
6. عند التنفيذ: re-auth/re-authorize، استرجاع confirmation server-stored والتحقق من actor/entity/tool-version/canonical typed args hash/source refs and versions/plan step/policy version/expiry/one-time nonce. أي تغيير للمعاينة أو المصدر/السياسة يبطل التأكيد ويطلب preview/confirmation جديدة.
7. استدعاء module-owned ApplicationService داخل معاملة قصيرة متزامنة مع audit وoutbox/business effects حسب حدود الخدمة. أعط receipt ونتيجة scoped links فقط.
8. أعد التفويض على قراءة receipt والروابط والسجل المحفوظ لاحقاً. صلاحية المستخدم في الرسائل/السجل/notifications/proactive delivery قابلة للسحب ولا تمنحها صلاحية سابقة.

### 4.3 التأكيد والموافقات والمبالغ

- confirmation تربط actor، entity، `tool_contract_version`، canonical typed args hash، source IDs/versions، preview/plan-step version، policy version، expiry، nonce أحادي الاستخدام ووقت القرار. لا يقبل النظام نص «نعم» من model بديلاً عن فعل user authenticated.
- أي تعديل إلى args/source/approval/policy/tool contract أو expiry يطلب تأكيداً/موافقة جديدة. لا تنفذ action حساس مباشرة من conversation history أو notification.
- موافقة نهائية يتحقق منها workflow/domain server عند آخر لحظة على نفس القيمة/الإصدار/الكيان، مع SoD ومنع self-approval والتحقق المستقل من delegate. التفويض لا يتجاوز SoD أو limit أو scope. break-glass لا يتجاوز invariant ولا يسمح به افتراضياً.
- الاتجاه المسجل في `DECISION_REVIEW_PACK.md` يقول strict `>` للحد الأعلى؛ المساواة `CURRENT` ولا تعني autoapprove، مع بقاء الموافقة العادية. الحالات الأربع فقط كـfixtures توثيقية كما في سجل المراجعة؛ لا تجعل أرقام EGP سياسات مفعلة. `EGP` هو النطاق الحالي، وFX غير مفعل/غير محسوم. لا مخترع threshold أو currency conversion أو statutory rule.
- البداية: `Restricted` لكل عمل حساس. لا auto-post، payment/payroll payout، stock issue/release، personnel change، irreversible action أو approval by AI. أي تمكين لاحق يحتاج scope owner/security approvals، contracts واختبارات مخصصة؛ غير ذلك يظل disabled.

### 4.4 Plans، retries، وatomicity

- خطة متعددة الخطوات في المستقبل typed ومحدودة بعدد وخطوات allowlisted، checkpoint قبل كل كتابة، وpreview/confirm لكل step أو batch معرف صراحة. توقف عند ambiguity/revocation/stale source/approval failure. لا transaction مفتوحة أثناء LLM/network أو انتظار إنسان.
- كل خطوة لها transaction domain مملوكة وatomic؛ لا distributed all-or-nothing وعد. لا compensation إلا عبر إجراء عكسي مصرح، domain-safe ومراجع إلى الأصل؛ لا حذف آلي أو reversal مالي تلقائي بلا صلاحية/مراجعة.
- retries bounded. قيد uniqueness على `(actor, entity, tool_version, step, operation_key)`؛ نفس canonical hash يعيد النتيجة بعد re-authorization، hash مختلف `409 conflict`. معاملة متزامنة تنتج أثراً واحداً. عند انقطاع response اقرأ receipt قبل المحاولة؛ حالة provider/external unknown تُصالح ولا يعاد إرسالها عميانياً.
- outbox at-least-once، لا exactly-once عبر الشبكة. حدّث receipt/published state ذرياً حيث يملكها النظام؛ inbox dedup وحالة واضحة للتكرار/crash/replay. لا provider network داخل DB transaction.

## 5. الاستباقية والإشعارات

- مصدر الكشف الأساسي `Scheduler + DomainEvents/outbox + DB queues` و`ProactiveRulesEngine` deterministic. مستقبل reasoning AI مساعد لتفسير/ترتيب توصية فقط، لا يخلق authority أو يختار recipient من دون policy.
- detector يعمل بأضيق service identity قراءة نظامية؛ لا حساب موظف ذي صلاحية مرتفعة ولا business write. يُسمح له بقراءة معلومات لازمة لكشف حالة، لكن لا تورث النتائج صلاحية ذلك الدور.
- لكل recipient/channel تعمل projection/filtering مستقلة بإعادة authorization قبل إنشاء inbox/notification، الأعداد والـdigest والتصدير، ثم إعادة authorization عند القراءة والتنقل والتنفيذ. لا يكشف payload المركزي بيانات مستخدم غير مخول.
- event evidence scoped ومعرّف وإصدار وسبب rule version. لدى المستخدم خيار يوقف advisory حسب السياسة؛ لا يحذف audit أو يعطل mandatory alerts إلا حكم معتمد. الصمت ليس موافقة، وفتح/قبول الإشعار لا يساوي confirmation أو approval.
- حالات finding: `DETECTED → VALIDATED`, أو `INVALID` / `SUPERSEDED` / `RESOLVED`. التوصية: `RECOMMENDED → ACCEPTED → EXECUTION_PENDING → EXECUTED` أو `FAILED` / `UNKNOWN`، إضافة `DISMISSED` / `EXPIRED` / `SUPERSEDED`. حفظ reviewer/سبب/زمن/source evidence؛ dismissal شخصي ولا يوقف finding عالميًا بلا policy. feedback ليس تدريباً أو تعديل policy آلياً.
- اربط finding بالsubject/source versions، rule/prompt/schema/model versions (عند وجودها)، evidence refs، confidence/uncertainty وreview disposition. فرّق dedup لتكرار scan عن episode المفتوح وعن delivery إلى recipient/channel؛ لا تخلط مفاتيحها.
- هوية finding episode مستقرة بمفتاح condition مثل `(rule_id, entity_id, subject_id, condition_key)` ولا تتغير مع كل aggregate/source version؛ أما evaluation/dedup key لكل فحص فيتضمن source version. إذا حدّثت تغييرات المصدر condition نفسها وهي unresolved، حدّث evidence/last-seen للـepisode القائم ولا تنشئ finding مكرراً. بعد resolve للـepisode، يمكن لحدوث condition جديدة أن ينشئ episode جديداً وفق rule معتمدة. مفاتيح DB تمنع duplicate event consumer وdelivery واحدة لكل finding/recipient/channel key. scheduler ليس lock authority وحده: unique constraints/claim lease fencing تمنع overlap وworker متأخر من commit بعد lease جديدة.
- preferences/digests/quiet period/cooldown/rate caps إعدادات قابلة للتهيئة بموافقة؛ لا مدد أو حدود مخترعة. mandatory alert override يتبع policy موثقة ولا يحذف أثر التدقيق.

## 6. سلامة البيانات والنموذج والخصوصية

- Phase أول: no model; deterministic rules + Arabic/English synthetic fixtures/templates، ومخرجات typed/structured. بعد اعتماد `OPEN-09/10` فقط: server auth/privacy/data allowlist وredaction قبل Gateway، classification، purpose، payload hash/ref. لا raw PII/secret أو prompt خام في logs، ولا provider وصول DB.
- `OPEN-09` ما يزال يحكم provider/region/model/cost/review categories/budget/thresholds. `OPENAI` في حزمة المراجعة هدف تقييم مستقبلي فقط لا live authorization. سجّل model/provider version والـprompt/rule/schema version وsource refs/hash، timing/usage/cost currency/gap/uncertainty/evaluation version/reviewer. لا ادعاء دقة أو كلفة غير مقاسة.
- Gateway kill switch قبل الإرسال، timeouts/response limits، quota/budget reservations، token/input limits، pricing version، unknown cost state، quality thresholds `OPEN`. لا fallback إلى مزود آخر أو نموذج أوسع أو payload أقل خصوصية عند الفشل؛ fail closed، ويدوي review حسب السياسة.
- مخرجات النموذج وtool/file outputs بيانات غير موثوقة. prompt injection بالعربية/الإنجليزية أو quoted content لا يغير system policy ولا يطلب dispatch؛ لا hidden tool calls. mTLS/secrets/config والإقامة/retention تحتاج قراراً لا إفتراضاً.
- Browser Push fake-only منفصل عن assistant/provider. أي egress فعلي أو keys/subscriptions/credentials يحتاج قرار privacy/egress/owner مستقل؛ لا live push مصرح هنا.

## 7. واجهة المستخدم

- يظهر assistant كعنصر عائم دائم داخل ERP بعد المصادقة، مع shell responsive RTL؛ على شاشة الهاتف يتحول إلى full-screen آمن وسهل الإغلاق. لا assistant surface عامة أو public endpoint.
- يمرر المساعد مراجع الصفحة الحالية المصرح بها لتسهيل السياق، لا DOM scraping ولا صلاحية إضافية. بحث اللغة الطبيعية والتقارير المقيدة تعرض مصدر البيانات ووقت freshness؛ لا توسع الوصول بمقارنة النتائج، ولا تلخص إلا projection المصرح.
- dialog المعاينة/التأكيد يعرض ما سيحدث وscope والكيان والقيمة والنسخ والتأثيرات والموافقات، مع زر confirmation صريح. تتبع طلب الموافقة وحالته بوضوح (`draft`, `submitted`, `awaiting approval`, `executed`، و`failed`/`unknown` عند الاقتضاء)؛ هذه الحالة للعرض فقط ولا تعني القبول. اعرض الرفض/الغموض دون كشف وجود سجل محجوب؛ links يولدها الخادم وتخضع لتفويض جديد.
- توطين العربية والأرقام والتاريخ/العملة حسب policy، keyboard/accessibility، bidi isolation للأرقام/معرفات/البريد، وعدم دمج نص غير موثوق في UI. لا تستخدم محادثة مخزنة كـDOM سياق دائم.
- لا تخزين offline للـfinancial/stock/approval commits؛ لا sensitive PWA conversation cache أو background action dispatch. التحقق عند reconnect والقراءة الحالية إلزامي.

## 8. التخزين المنطقي المستقبلي (ليس schema معتمداً)

كيانات/جداول مرشحة فقط: `ai_conversations`, `ai_messages`, `ai_plans`, `ai_plan_steps`, `ai_confirmations`, `ai_executions`, `ai_attempts`, `proactive_rules`, `proactive_runs`, `proactive_findings`, `proactive_evidence`, `proactive_recommendations`, `proactive_deliveries`, `proactive_preferences`, `ai_invocations`.

- أعمدة منطقية أساسية: IDs (مقترح `ULID` جديد بعد تحقق توافق ADR-002)، `entity_id`, owner/recipient/user ref، status، version، UTC timestamps، typed schema/contract version، rule/model/prompt version عند الانطباق، source aggregate id/version، approval ref، canonical args/input hash، evidence/result refs، correlation/idempotency key، expiry/lease/fencing token، error code sanitized، decimal `cost` + `currency` (لا float). لا تحفظ raw prompt/payload كاملاً إلى الأبد.
- compound scoped FKs/uniques على entity وowner/source؛ فهارس recipient/status/time، episode uniqueness، event consumer dedup، delivery dedup، confirmation nonce واحدة، idempotency operation tuple وlease fencing. أسماء DB قصيرة متوافقة مع حد المشروع ≤60 حرفاً. المرجع للبيانات/retention/durability لا cache.
- لا تنسخ `Workflow` أو `Audit` أو event outbox/inbox؛ يعاد استخدام خدماتها حين تُبنى. `OPEN-10` يحسم الاحتفاظ/التشفير/access/legal hold/backup؛ لا مدة عشر سنوات افتراضية أو حذف بلا policy. بيانات محادثة حساسة ومفصولة الصلاحية؛ لا تضمينها تلقائياً في search أو model context.
- لا تنشأ migration أو table من هذه الوثيقة. التنسيق/المفاتيح النهائية ينتظر حسم ADR-002/003 وOPEN-10 ومالكي الأمن/الأعمال.

## 9. مراحل مقترحة — لا تغيّر DAG

| مرحلة استرشادية | نطاق مفاهيمي | الحواجز |
|---|---|---|
| تأسيس/بوابات | إنجاز scoped identity/query authorization وmasking، audit/approvals، module services، outbox/inbox وDB queues | `A0-ARCH-901` مسجل done ويعتمد عليه `A0-ARCH-001` الجاهز في لقطة مراجعة؛ لا تجاوز `A0-ARCH-902` أو auth/SEC chain. سجل phase A0 الحالي ليس إذناً لتنفيذ هنا. |
| 1: أساس المساعد الكامل | واجهة نص/صوت RTL/LTR، registry typed، read/analytics، findings/inbox، preview/confirmation وadapters محكومة | `A5-AI-001..018` مع domain services وpermission/approval/outbox foundations؛ provider egress remains disabled until its gates. |
| 2: تفعيل الأدوات المحكومة | actions قليلة قابلة للتراجع مع explicit confirmation وApproval Engine | scope/security/domain approvals، SoD، tests وتفعيل tool منفصل. Restricted/action-sensitive remains disabled. |
| 4: reasoning AI اختياري | model advisory على payload minimised بعد اعتماد privacy/cost/provider | `OPEN-09/10` و`ADR-007` approval، egress/budget/evaluation/kill switch؛ لا live authorization حالية. |
| 5: checkpoints/automation المحدودة | خطوات متعددة موقعة ومحدودة؛ لا استقلال للقرارات الحساسة | موافقة منفصلة لكل scope وpolicy، reauth/step confirmation، compensation/recovery وreview. لا autonomous payroll/payment/posting. |

الترتيب الفعلي يتبع DAG: `A0-CORE-900` و`A0-SEC-002..` و`A0-ARCH-902` حسب dependencies وADRs، ثم A1/A2/A3/A4 owners. `A5-AI-001` يعتمد `A0-ARCH-901`, `A0-SEC-002`, `A0-ARCH-902`, `A4-FIN-006`, `A3-INV-004`; `A5-AI-002` يتبع الأول و`A0-CORE-900`; `A5-AI-003/004` يتطلبان `OPEN-09`/`ADR-007`; findings/disposition/scheduled/prompt/version/commands/evaluation هي سلاسل `A5-AI-005..018` كما في `tasks.json`. `A5-AT-10` يتطلب `A5-AI-006` و`A5-AI-008`. `DASH-007/008` alert path بعد `DASH-006`. لا task ID جديد أو split أو dependency تعديل هنا؛ لأي foundation مبكر يلزم authorization وtask split مخول دون تجاوز.

## 10. معايير القبول والاختبار الأمني

كل التالي مواصفات اختبارات مستقبلية `NOT RUN`؛ لا يعني وجود test أو implementation. يحتاج الاختبار fixtures معتمدة/مصطنعة، assertions على DB/outbox/audit وإثبات no mutation عند الرفض.

| مجموعة | شروط إثبات إلزامية مستقبلًا |
|---|---|
| Tool registry/contracts | registry allowlist فقط؛ version/type/unknown field/input limit؛ handler resolution ثابت بلا reflection/autodiscovery/generic execute؛ لكل entry owner/coverage/tests؛ mismatch يرفض. |
| حقن/لغة | Prompt injection مباشر/indirect بالعربية والإنجليزية في رسالة/وثيقة/URL/output لا يوسع صلاحية ولا يستدعي tool؛ بيانات غير موثوقة تبقى data. |
| Actor/IDOR/scope | تزوير principal/role من model مرفوض؛ IDOR across user/entity/branch/department/assignment؛ denied IDs لا تظهر؛ field projection/masking قبل counts/pages/aggregates/export/log/history/notification. |
| تكافؤ الصلاحية | الأداة لا تتجاوز UI/API permission؛ check عند discovery وexecution؛ revoke بين preview/execute والرسائل/history/proactive queue/resume يمنع التنفيذ/القراءة. |
| Confirmation/SoD | tamper/replay/expiry/nonce/actor/hash/tool/source/policy/step mismatch؛ تغير المصدر يفرض preview جديد؛ self-approval/delegation/break-glass لا يتجاوز; strict `>`/equality tests لكل أربع عتبات كحالات policy-fixture فقط. |
| stale/determinism | stale source finding/action invalidates; invalid schema/model failure/timeouts/budget unknown fail closed؛ deterministic invariants ترفض بغض النظر عن AI. |
| races/crashes | duplicate/overlap detector scans, concurrent accept/execute, lease expiry/fencing, crash before/after commit/outbox/publish/receipt, response loss; one effect/receipt or explicit unknown/reconcile; no blind resend. |
| recovery/compensation | partial multi-step stop/checkpoints; compensation authorized and source-linked, no silent rollback/deletion/financial reverse; idempotency same hash returns reauthorized receipt, different hash conflict. |
| privacy | no raw PII/secret in prompts/logs/metrics; history access current, revoke/offboard; cache/storage/retention and cross-recipient projection; links reauthorize; minimize before future Gateway. |
| model/provider | Phase A fake/zero-egress assertion; kill switch, bounded timeout/token/cost, no provider fallback leak, sanitized error; optional live provider only after approval and separate egress test. |
| proactive lifecycle | dedup scan vs episode vs delivery; stale evidence supersedes; preferences/quiet/digest/rate caps; mandatory policy; dismissal local; silence/acceptance not consent/confirmation/approval. |
| UI/PWA | Arabic/English/bidi, ambiguous names/numbers clarified, responsive RTL/accessibility; no sensitive offline conversation cache or financial/stock/approval dispatch. |
| coverage | كل row في `AI_TOOL_COVERAGE.md` يملك tests and detector assessment؛ implementation-disabled يثبت handler/schema/permission/tests، ويثبت disabled state/no route exposure؛ tool enabled يحتاج complete code/test/approval evidence. لا readiness من schema وحده. |

حالة كل بند الآن: `NOT RUN`. لا اختبارات تطبيق نفذت أو أدلة تفعيل سجلت بهذه الوثيقة. راجع [خطة الاختبار](../engineering/TEST_STRATEGY.md) و[مصفوفة التغطية](../engineering/AI_TOOL_COVERAGE.md) قبل كتابة أي feature.

## 11. المراجع الداخلية

- [PRD AI-01..09](../product/PRD.md#L502-L545) و[نطاق Phase A](../product/SCOPE.md#L1-L15).
- [ADR-007 (OPEN)](adr/ADR-007.md#L1-L18)، [Solution Architecture](SOLUTION_ARCHITECTURE.md)، [Module Catalog](MODULE_CATALOG.md)، [Event Catalog](EVENT_CATALOG.md).
- [Permission Matrix](../security/PERMISSION_MATRIX.md)، [Engineering Standards](../engineering/ENGINEERING_STANDARDS.md)، [Test Strategy](../engineering/TEST_STRATEGY.md)، [Tool Coverage](../engineering/AI_TOOL_COVERAGE.md).
- [Decision Review Pack](../governance/DECISION_REVIEW_PACK.md) (اتجاهات/لقطات غير معتمدة)؛ [Phase A Scope Change proposals](../governance/proposals/PHASE-A-SCOPE-CHANGES.md)؛ [Synthetic Implementation Plan](../governance/proposals/SYNTHETIC_IMPLEMENTATION_PLAN.md).
- مراجعة التطبيق المعروفة: [ModuleCatalog](../../app/Modules/Shared/Application/ModuleCatalog.php)، [SEC01 guard](../../app/Modules/Identity/Domain/SEC01/ScopedPermissionGuard.php)، [auth config](../../config/auth.php)، [health route](../../routes/web.php). لا تمثل هذه الملفات الأدوات المقترحة أو صلاحية ERP للمستخدم النهائي.

# OPEN-02 — موجز أدلة حدود الاعتماد وسلاسله

| الحقل | القيمة |
|---|---|
| المالك | Executive |
| الحالة | OPEN — بانتظار قرار المالك؛ لا توجد سياسة معتمدة في هذا الموجز |
| الغرض | جمع الأدلة والبدائل وأسئلة القرار فقط؛ ليس تفويضاً بالتنفيذ أو التفعيل |
| نطاق السياسة | عمليات حساسة بحسب نوع العملية والمخاطر والقيمة والدور/القسم؛ جميع الحدود النقدية والعملة: TBC |
| break-glass | معطّل حالياً؛ لا يتغير ذلك بهذا الموجز |

> **تنبيه:** إعداد هذا الموجز لا يعني اعتماد سياسة. لا توجد مبالغ افتراضية أو قواعد auto-approve هنا. تبقى `OPEN-02` مفتوحة، وأي عملية لا يغطيها قرار معتمد تظل محجوبة عن الأثر التجاري/المخزني/المالي حتى حسمها عبر مسار مخول.

## 1. القرار المطلوب ونطاق التأثير

يطلب [OPEN-02](../OPEN_DECISIONS.md#L3-L8) من Executive حسم سلاسل الاعتماد بحسب العملية والمبلغ والقسم، بما فيها self-approval والاستثناءات الطارئة. يوجب [SEC-01..06](../../product/PRD.md#L113-L135) صلاحيات scoped وdeny-by-default، workflow قابل للتهيئة، فصل مقدم الطلب عن المعتمد في العمليات الحساسة، وسجل تدقيق دائم؛ ويطلب [الـ approval baseline](../../product/PRD.md#L140-L151) اعتماداً إدارياً قبل الالتزام التجاري، وضوابط للمخزون والمصروفات/المدفوعات والرواتب وحذف/تصحيح البيانات. يفرض [CORE-03](../../product/PRD.md#L163-L165) انتقالات حالة صريحة، صلاحيات، تحققاً، إلغاء/عكساً وسجلاً، دون تعديل حالة مباشر.

**بوابات التفعيل المتأثرة:**

- **A0:** تصميم IAM/RBAC وapproval engine وaudit، لكن تفعيل سلاسل العمل الفعلية محجوب إلى أن يعتمد Executive السياسة ويُستكمل اختبارها.
- **A2:** موافقة quote/contract/order قبل أي التزام نافذ للعميل؛ تعديل النسخة المعتمدة يحتاج مراجعة جديدة. الموافقة وحدها لا تسجل revenue ولا تخصم stock.
- **A3:** صلاحية warehouse لإصدار المخزون؛ فروقات الجرد والتعديلات الأعلى خطراً تحتاج مساراً معتمداً. الحجز ليس إصداراً مادياً.
- **A4:** مصروفات ومدفوعات وقيود/reversals وفترات مالية ورواتب/خصومات/عمولات؛ موافقة العمل لا تعني الدفع أو posting تلقائياً.
- **A5:** AI finding استشاري؛ لا يوافق أو ينفذ معاملة بدلاً من صاحب صلاحية.

ترتبط هذه البوابات بمبادئ PRD وسيناريوهات القبول `AT-03`, `AT-04`, `AT-07`, `AT-08`, `AT-14`, `AT-15`. سجل المهمة يحدد مخرجاً واحداً هو هذا الملف ولا يصرح بـ downstream task dependencies؛ لذلك فهذه بوابات إطلاق مستنتجة من PRD وقرار OPEN وليست تعديلاً لسجل الاعتماديات. ولا يحسم هذا الموجز قرارات `OPEN-03` المالية أو `OPEN-04` للمخزون أو `OPEN-05` لهوامش المشاريع.

## 2. جرد العمليات الحساسة

| مجال العملية | أمثلة تستلزم تحديد سياسة | سبب الحساسية/حد التحكم المستند إلى PRD |
|---|---|---|
| التزام تجاري | اعتماد quote أو discount/استثناء سعر؛ contract/order؛ amendment أو cancellation بعد الاعتماد | اعتماد إداري قبل التزام العميل؛ revision/reconciliation دون overwrite؛ لا revenue/stock effect من اعتماد quote وحده. |
| ميزانية ومشروع | تغيير project budget أو change order؛ طلب إغلاق المشروع | الصلاحية ضمن النطاق؛ فحص delivery/QA/financial evidence قبل الإغلاق؛ لا إغلاق مع checks ناقصة. |
| حيازة المخزون | issue/release، نقل، إرجاع، damaged stock، receipt/opening balance، negative stock | warehouse authorization؛ movement ledger غير قابل للمحو أو reversal-based؛ reserve لا ينقص on-hand، وissue يخصم مرة واحدة. |
| تسوية المخزون | count variance ثم adjustment | فرق الجرد يتطلب review، وسلطة/threshold adjustment غير محسومة؛ valuation وnegative-stock policy تتبع `OPEN-04`. |
| أموال الشركة | expense، payment/disbursement، refund، partner distribution/drawing، cash/bank movement | فصل managerial authorization عن finance processing؛ expense approval لا تعني paid؛ drawing ليس expense افتراضياً. |
| قيود وإغلاق مالي | journal posting/reversal، تصحيح posting، إغلاق أو إعادة فتح fiscal period | double-entry، immutable/reversal-based corrections، صلاحية للفترة المغلقة، وسجل يربط المصدر. التفاصيل تخضع لـ `OPEN-03`. |
| أجور وحوافز | تغيير compensation/bonus/advance؛ commission payout/adjustment؛ payroll deduction | finance ينفذ الخصم فقط بطلب وموافقة إدارية موثقة؛ لا عقوبة تلقائية على missed target. |
| بيانات وصلاحيات وسياسة | master-data correction/deletion، تغيير role/permission، تعديل أو تفعيل Approval Policy | restricted permission، soft-delete حيث يلائم، full audit؛ لا صلاحيات Super Administrator مستثناة تلقائياً. |

هذا الجرد يحدد نطاق القرار ولا يقرر أن كل حدث يتطلب سلسلة واحدة؛ على Executive اختيار تصنيف المخاطر والاستثناءات لكل صف في الجدول التالي.

## 3. جدول السياسة القابل للتعبئة

كل قيمة غير محسومة أدناه `TBC` عمداً. يعبأ صف مستقل لكل نوع عملية ولكل نطاق department/entity عند الحاجة. لا تُفسر الخانة الفارغة أو `TBC` كحد صفري أو سماح؛ عند غياب حد أو عملة أو معتمد مخول، لا يُنفذ الأثر وتُرفع الحالة للمسار الإداري الذي يحدده المالك.

| العملية / النطاق | المخاطر | المبلغ / الحد / أساس القيمة | العملة | مقدم الطلب (الدور/النطاق) | المعتمد / السلسلة (الأدوار والترتيب) | التفويض delegation (النطاق/المدة) | التصعيد escalation (إلى/متى/الإجراء) | SoD / self-approval | الأدلة المطلوبة قبل القرار |
|---|---|---|---|---|---|---|---|---|---|
| Quote / discount / contract / order جديد | TBC (مالي/التزام عميل) | TBC؛ أساس المبلغ والخصومات TBC | TBC؛ لا default | TBC حسب department/entity | TBC؛ يجب تحديد صلاحية الاعتماد قبل الإرسال/الالتزام | TBC؛ لا توسعة لصلاحية الأصل | TBC؛ لا اعتماد تلقائي عند التأخير | Proposed: مقدم الطلب لا يعتمد طلبه؛ الاستثناء TBC ولا يفعّل هنا | quote/version، بنود وسعر وخصم/ضريبة وشروط، مبرر، customer/department/entity، مصدر السعر |
| Amendment / cancellation لـ quote أو contract/order معتمد | TBC (تغيير التزام قائم) | TBC؛ قياس الأثر التراكمي/الفرق TBC | TBC؛ عملة الأصل وأي تحويل TBC | TBC | TBC؛ سلسلة جديدة مطلوبة بعد التعديل | TBC | TBC؛ يوقف الالتزام المتأثر لحين القرار | Proposed: نفس الفصل؛ لا يرث اعتماد النسخة السابقة | نسخة قبل/بعد، سبب، أثر مالي/تسليمي، موافقات ومراجع downstream |
| Change order / project budget / إغلاق | TBC (تجاوز/قبول) | TBC؛ الميزانية/الانحراف/الالتزام TBC | TBC | TBC (Project/Operations) | TBC؛ المسؤول والمراجع الإداري TBC | TBC | TBC | Proposed: مقدم التغيير لا يعتمد؛ مراجعة مستقلة لـ closure | budget baseline، variance، التكاليف والمبررات، delivery/POD، QA، التسويات وقائمة الإغلاق |
| Stock issue/release / transfer / return / damage | TBC (حيازة/توفر/أثر مالي) | TBC؛ كمية/قيمة وطريقة تقييم TBC | TBC أو لا ينطبق على الكمية؛ valuation currency TBC | TBC (Warehouse/Project حسب العملية) | TBC؛ warehouse authority وأي مستوى أعلى TBC | TBC | TBC؛ لا release/issue إذا لم توجد صلاحية | Proposed: requester منفصل عن warehouse releaser؛ لا تكرار للصرف | approved order/reservation، SKU/unit/lot/location، كمية وسبب ومصدر، custody/return evidence |
| Opening stock / receipt / count variance / adjustment / negative stock | TBC (وجود/تقييم/تلاعب) | TBC؛ فرق الكمية والقيمة وحدود القياس TBC | TBC؛ valuation currency TBC | TBC (Warehouse) | TBC؛ reviewer/Finance عند الحاجة TBC | TBC | TBC | Proposed: العادّ لا يعتمد adjustment لنفسه؛ فصل التنفيذ المخزني عن المراجعة TBC | count sheet، مصدر receipt، provenance، variance، تقييم، سبب، سجل الحركة الأصلية/العكسية |
| Expense / payment / refund / partner distribution | TBC (صرف/استرداد/التزام) | TBC؛ نوع القيمة الإجمالية/الصافية وحدود التكرار TBC | TBC؛ أساس FX وتاريخه TBC | TBC (طالب المصروف/المستفيد/القسم) | TBC؛ managerial authorization ثم Finance processing بحسب الحالة | TBC | TBC؛ موافقة غير مكتملة لا تسمح بالدفع | Proposed: المستفيد/الطالب لا يعتمد؛ finance processor ليس موافقاً منفرداً على طلبه | invoice/receipt، payee، category، date، project/department، tax treatment، approval، حالة الدفع |
| Compensation / commission / bonus / advance / deduction | TBC (بيانات موظف/تعويض) | TBC؛ القاعدة/الفترة/المبلغ TBC | TBC؛ TBC | TBC (manager/Finance) | TBC؛ managerial approval موثق ثم Finance ينفذ | TBC؛ لا تفويض ذاتي | TBC | Proposed: المستفيد لا يطلب ويعتمد خصمه؛ Finance لا ينفذ دون approval reference | الموظف/الفترة/المصدر، calculation أو سبب، أثر صافي، مستند الطلب والاعتماد، سجل audit |
| Journal posting / reversal / period close أو reopen | TBC (ledger/period integrity) | TBC؛ القيمة والـ dimensions TBC | TBC؛ قواعد multi-currency TBC | TBC (Finance) | TBC؛ موافقة مستقلة قبل transition حسب الصلاحية | TBC | TBC؛ الفترة المغلقة تظل مقفلة حتى قرار مخول | Proposed: منشئ القيد لا يعتمد posting/reopen لنفسه؛ posting بعد موافقة سارية | source transaction، balanced debit/credit، dimensions، سبب reversal/reopen، reconciliation، period |
| حذف/تصحيح master data أو تعديل role/permission/Approval Policy | TBC (أثر شامل/خصوصية/تجاوز صلاحية) | TBC أو غير نقدي؛ نطاق التأثير TBC | TBC أو لا ينطبق | TBC؛ لا يمنح Super Administrator إعفاءً تلقائياً | TBC؛ يحدد Executive صلاحيات admin/second review | TBC؛ يحدد بدقة ولا يوسع صلاحية الأصل | TBC | Proposed: منفذ التغيير لا يعتمد استثناءه؛ سجل قبل/بعد إلزامي | object/field، before/after أو secure diff، سبب، صاحب الطلب، أثر الصلاحية، version/hash، audit event |

### حقول تعريف إضافية قبل التفعيل

- تعريف risk levels واختبار إسناد العملية لمستواها: `TBC`؛ التسميات المقترحة للنقاش: routine / medium / high / critical، وليست مستويات معتمدة.
- شرائح المبلغ، حدود inclusive/exclusive، هل يحتسب الطلب وحده أو exposure تراكمي، وطريقة تجميع الطلبات المرتبطة: `TBC` لكل عملية/قسم/entity.
- currencies المسموحة، مصدر وساعة سعر الصرف، التقريب، معالجة currency غير المدعومة، الضرائب والرسوم: `TBC`؛ لا تحويل ضمني إلى عملة افتراضية.
- قاعدة اختيار المسار إذا اجتمع amount band مع risk override أو تعارضت صلاحيات الأدوار: `TBC`؛ المقترح أن يسود المسار الأكثر تشدداً، بعد اعتماد المالك.

## 4. البدائل والمقارنة

| البديل | الوصف | المزايا | العيوب/المخاطر |
|---|---|---|---|
| A — سلسلة ثابتة واحدة | كل عملية حساسة تمر على دور/سلسلة واحدة بصرف النظر عن exposure | بسيطة وسهلة التدريب والتدقيق؛ تقلل اختلاف المسارات | تعطل الأعمال للعمليات منخفضة المخاطر؛ لا تميز بين commitment تجاري، حركة stock، وصرف مالي؛ لا تجيب عن حدود المبلغ. |
| B — تصنيف مخاطر فقط | السلسلة تتبع risk level دون حدود مالية | يلتقط نوع الأثر غير النقدي ويبسّط currency handling | مبالغ كبيرة قد تحصل على المسار نفسه لمجرد تصنيف عام؛ يتطلب تعريف risk مستقر ومراجعة حالات الالتباس. |
| C — عملية + مخاطر + مبلغ + department/entity | policy matrix تحدد المسار بحسب نوع العملية وrisk وamount/currency ونطاق الصلاحية | أقرب إلى SEC-03 وapproval baseline؛ يفرق الالتزام عن الحيازة والـ posting؛ قابل للتدقيق والتوسع | إعداد وصيانة واختبار أكثر؛ يحتاج thresholds وcurrency/FX موثقة؛ قد يستغل التقسيم إن لم تحدد قاعدة exposure المرتبط. |

**Proposed recommendation للنقاش فقط:** اختيار البديل C، مع deny-by-default عند missing policy، وhuman approver مخول لكل انتقال؛ لا توجد auto-approve bands أو حدود افتراضية في هذا الموجز. يحدد Executive كل Threshold/عملة وسلسلة في جدول السياسة، ويحدد Finance/Warehouse التفاصيل التابعة لـ `OPEN-03/04` قبل تفعيل مجالها. وللعمليات الحساسة، يكون عدم تطابق أي scope/amount/currency/version سبباً للحجز والتصعيد، لا fallback إلى مسار أخف.

## 5. قواعد مقترحة للسلسلة والمدة وإعادة الاعتماد

هذه semantics مقترحة على المالك اعتمادها أو تعديلها، وليست مفعلة:

1. **SoD:** لا يعتمد منشئ الطلب طلبه الحساس. يختار المالك حالات المراجعة الثانية، وتظل صلاحية Director/Executive أو Super Administrator مقيدة بالنطاق؛ لا استثناء تلقائياً بسبب المسمى. لا يكفي أن يكون المعتمد مديراً إذا كان خارج department/entity أو لا يملك permission للعملية.
2. **Delegation:** تكون صريحة ومحددة للشخص/الدور والعملية والنطاق والحدود والمدة؛ لا يكتسب المفوّض صلاحية أوسع من المفوِّض. لا يعمل التفويض قبل قبول المفوّض، ويسجل الطرفان والسبب وتاريخ السريان/الانتهاء. الانتهاء أو إلغاء الصلاحية يعيد الطلب للمسار المصرح أو يصعّده؛ لا يوافق بالنيابة تلقائياً.
3. **Expiry/escalation:** يحدد المالك `SLA`, business-hours calendar، مواعيد التذكير، الجهة البديلة والمهلة النهائية لكل risk class: جميعها `TBC`. قبل الانتهاء يرسل النظام تذكيراً ثم يصعّد إلى approver/role مخول. escalation لا تعني approval ولا تغير SoD. عند الانتهاء تنتقل الحالة إلى `expired`، ويبقى الأثر التجاري/المخزني/المالي ممنوعاً؛ إعادة الطلب تتطلب سبباً ومرجعاً جديداً ونسخة حالية. لا auto-approve عند timeout أو تعطل الإشعار.
4. **رفض/إرجاع/إلغاء:** يسجل الرفض مع السبب؛ الإرجاع يتطلب تصحيحاً ونسخة جديدة؛ الإلغاء يحتاج actor مخولاً ويحتفظ بالتاريخ. أي pending/returned/delegated/expired لا يتحول إلى approved ضمنياً.
5. **Version/hash binding:** يرتبط كل قرار بـ `subject_id`, `subject_version`, canonical `subject_hash`, `policy_id`, `policy_version`, `policy_hash`, amount/currency/entity/department، approver identity/effective role، timestamp، reason وaudit/correlation reference. أي تعديل مؤثر في المبلغ/العملة/الطرف/النطاق/البنود/الشروط/المرفقات/الميزانية أو نسخة السياسة ينشئ version/hash جديداً ويلغي صلاحية الاعتماد للنسخة المعدلة؛ يلزم طلب وموافقة جديدة قبل أي أثر. لا يُقبل replay لموافقة قديمة أو منسوخة إلى كيان آخر.
6. **Amendment للسياسة:** كل تعديل ينتج `policy_version` وhash جديدين immutable، مع rationale وeffective-from وowner approval. لا ينفذ draft ولا يستبدل النسخة السابقة بصمت. الطلبات الجديدة تستخدم النسخة السارية؛ الطلبات المعلقة تعاد مطابقتها وتطلب reapproval إذا تغيرت السلسلة/الحدود/الحقول المحمية. السجل المنفذ يحتفظ بالنسخة التي حكمته؛ لا تُغيّر الموافقات التاريخية بأثر رجعي. لا تُفعّل النسخة المعدلة قبل sign-off واختبارات migration/reapproval المتأثرة.
7. **الأثر بعد الموافقة:** approval transition لا تعني payment، journal posting، stock issue أو revenue recognition إلا إذا كان workflow منفصل ومصرحاً به قد تحقق من نفس النسخة والسياسة. كل أثر مالي/مخزني يبقى transactionally validated، idempotent، audited، وقابلاً للعكس وفق قواعد المجال.

## 6. الطوارئ وbreak-glass — لا تفعيل ضمني

الحالة الحالية حسب [PERMISSION_MATRIX](../../security/PERMISSION_MATRIX.md#L16-L20): `break-glass disabled until OPEN-02`; وSuper Administrator ليس معفياً تلقائياً من الضوابط المالية. التوصية الحالية هي **الإبقاء عليه معطلاً** إلى أن يختار Executive صراحةً هل يسمح به ويعتمد قائمة عملياته وحدوده واختباراته. هذا الموجز لا ينشئ استثناءً ولا يمنح أي actor صلاحية طارئة.

إذا قرر المالك دراسة تفعيله لاحقاً، فالحد الأدنى المقترح قبل أي تطبيق: غرض طارئ محدد؛ scope/operation/entity/amount أو quantity ومدة قصيرة كلها `TBC`؛ two-person authorization من شخصين مستقلين؛ منع صاحب الطلب من اعتماد طلبه؛ سبب موثق؛ audit غير قابل للتلاعب وتنبيه فوري للمالك/المراجعين؛ انتهاء تلقائي للصلاحية؛ post-event review خلال مدة يحددها المالك؛ واختبار أن الفشل أو انقضاء الصلاحية يرجع إلى deny. يقرر Executive صراحةً العمليات المسموحة والمستثناة؛ لا تُفترض أهلية posting/payroll/irreversible stock adjustment أو permission changes. عند غياب approver، الخيار الآمن المقترح هو hold/queue وتصعيد موثق، لا تجاوز آلي.

## 7. أمثلة افتراضية غير معتمدة

الأمثلة تستخدم رموزاً فقط؛ لا تمثل مبالغ أو عملات أو حدوداً مقترحة ولا تنشئ default:

- **Quote متغير:** يرفع Sales طلب quote بقيمة `A` وعملة `C`. لأن نطاق `A/C` والسلسلة ما زالا `TBC`، يبقى الطلب غير نافذ ولا يرسل كالتزام حتى يحدد المالك المسار ويعتمده approver مختلف. إذا تغيرت القيمة إلى `A2` بعد القرار، تنشأ نسخة/hash جديدة وتلزم إعادة الاعتماد.
- **Stock variance:** يسجل Warehouse فرق جرد `Q` بوحدة `U` ومصدر count. لا تُجرى adjustment قبل تحديد قاعدة الكمية/التقييم وسلطة المراجعة؛ اعتماد adjustment لا يجيز حذف الحركة السابقة، ويجب ربط الحركة العكسية/التصحيحية بالمصدر.
- **Expense ثم posting:** يرفق مقدم طلب مصروفاً بقيمة `X` وعملة `C`. موافقة المدير لا تعني payment أو posting؛ Finance يراجع الأدلة ثم ينفذ انتقالاً منفصلاً، بعد التحقق من نفس entity/amount/version ومن صلاحية الفترة.
- **انتهاء طلب:** إذا تجاوز طلب pending مهلة `SLA` التي يحددها المالك، ينتقل إلى `expired` ويظل الأثر محجوباً. إعادة الإرسال تنشئ طلباً جديداً وتعيد التحقق من نسخة السياسة والمعاملة؛ لا يصبح الطلب approved بسبب التأخر.

## 8. سيناريوهات abuse واختبارات القبول المطلوبة

| الاختبار | النتيجة المطلوبة |
|---|---|
| منشئ الطلب يحاول الموافقة من UI أو API أو direct status mutation | يرفضه server-side؛ لا يتغير state؛ يسجل actor/effective role والسبب الأمني. |
| معتمد خارج department/entity أو permission revoked يحاول الاعتماد | رفض دون تسريب بيانات؛ إعادة فحص الصلاحية عند transition لا عند فتح الشاشة فقط. |
| تفويض واسع، منتهي، غير مقبول، أو للمفوّض نفسه | رفض/إبقاء الطلب pending ثم تطبيق escalation المعتمد؛ لا يوسع delegation الصلاحية ولا ينفذ تلقائياً. |
| تغيير amount/currency/discount/line item/entity/attachment بعد الاعتماد أو إعادة استخدام approval reference | يفشل hash/version check؛ تنشأ نسخة جديدة ويلزم reapproval؛ لا commit أو side effect. |
| تقسيم expense/order/adjustment إلى طلبات متقاربة لتجاوز حد بعد أن يحدده المالك | يكشف النظام التجميع/الارتباط وفق قاعدة owner-approved؛ لا تعتمد الاختبارات على threshold غير معتمد. |
| عملة غير مدعومة، سعر صرف مفقود/متغير، rounding عند حد، أو تحويل يخفض band | fail closed وتصعيد؛ لا اختيار عملة أو FX افتراضي ولا مسار أخف. |
| طلب منتهي أو escalation متأخر/مكرر، أو تعطل notification/job | يظل غير معتمد؛ retries idempotent؛ لا يبدل expiry إلى approval؛ تظهر exception لصاحب متابعة. |
| إعادة استخدام approval بعد amendment للـ policy أو hash mismatch | لا ينتقل approval إلى النسخة الجديدة؛ pending requests يعاد تقييمها ويطلب reapproval حسب التغيير. |
| Super Administrator يحاول break-glass قبل قرار التفعيل، منفرداً، بعد انتهاء المدة أو خارج scope | يرفض النظام جميع المحاولات عندما تكون disabled؛ بعد أي تفعيل مستقبلي لا يقبل إلا الشروط الثنائية المحددة والموقعة. |
| quote approval يحاول تسجيل revenue/خصم stock؛ expense approval يحاول الدفع أو posting؛ reserve يحاول إنقاص on-hand | ترفض الآثار غير المصرح بها؛ لا تخلط approvals مع fulfillment/finance transitions. |
| duplicate submit أو replay/out-of-order approval/issue/post | أثر واحد كحد أقصى حسب idempotency؛ audit يثبت كل محاولة؛ الرصيد والحركة لا يتضاعفان. |
| تعديل role/policy أو حذف سجل دون سبب، before/after evidence أو second review الذي سيحدده المالك | يرفض أو يحجز حسب الصلاحية المعتمدة؛ يحفظ immutable audit ولا يسمح بتجاوز history. |

يلزم قبل التفعيل اختبار كل مسار مسموح ومرفوض على entity/department/assignment مختلف، مع التحقق عبر API وUI وexports/jobs كما يقتضي [اختبار permission baseline](../../security/PERMISSION_MATRIX.md#L16-L20)، إضافة إلى `AT-14` وقيود [approval workflow](../../workflows/WORKFLOW_CATALOG.md#L4-L17).

## 9. أسئلة قرار محددة للمالك Executive

1. ما فئات العمليات التي تتطلب approval قبل كل أثر، وما الذي يعد transition منفصلاً (approval مقابل posting/payment/issue/commitment)؟ هل تؤكد نطاق الجرد في القسم 2 أو تعدله؟
2. ما risk levels وتعريف كل منها ومعايير التصنيف؟ من يحسم العملية الملتبسة؟ وهل يوجد risk override أعلى من amount band؟
3. ما الحد/الشرائح لكل عملية، department وentity، وما أساس القيمة: gross/net، التراكمي، قيمة العقد كاملة أم delta؟ ما قاعدة تجميع الطلبات المرتبطة ومنع split-to-evade؟
4. ما currencies المسموحة، مصدر/توقيت FX، rounding، tax/fees، وكيف يعالج مبلغ بعملة غير معروفة أو لا تتوفر لها قيمة تحويل؟
5. من يطلب ومن يعتمد كل صف بالترتيب؟ هل تحتاج المخاطر العالية إلى second approver مستقل؟ ما أدوار Finance/Warehouse/Project Manager/Director المصرح بها لكل نطاق؟
6. هل يمنع self-approval بلا استثناء؟ إن طلب استثناء، ما العملية والنطاق والسبب والمدة والبديل الرقابي، وكيف يستوفي SEC-04؟
7. من يجوز له delegation؟ هل يقبل المفوض الطلب قبل انتقال المسؤولية؟ ما حد النطاق والمدة، ومن يراجع التفويض والإلغاء/offboarding؟
8. ما SLA والتقويم ومواعيد التذكير وescalation role والمهلة النهائية؟ عند انتهاء المهلة: هل يظل الطلب expired ومحجوباً حتى إعادة التقديم؟ (التوصية: نعم؛ لا auto-approve.)
9. ما التغييرات التي تبطل الموافقة؟ (التوصية: أي تغيير مؤثر في subject hash يستلزم نسخة واعتماداً جديدين.) وكيف تطبق amendments على الطلبات pending؟
10. هل تسمح بـ break-glass مستقبلاً؟ لأي عمليات/حدود/مدة، ومن الشخصان المستقلان، ومن يتلقى التنبيه، وما مهلة post-event review؟ هل تؤكد إبقاءه disabled حتى اعتماد واختبار منفصلين؟
11. ما الأدلة الإلزامية ومدة الاحتفاظ بها، ومن يحق له الاطلاع على audit دون كشف customer/employee sensitive fields؟
12. ما المراجعين المطلوبين (Finance/Warehouse/Security/Operations) قبل قرارك النهائي، وما تاريخ effective-from وموعد policy review؟

## 10. قالب قرار وتوقيع المالك

> يبقى هذا القالب فارغاً حتى يراجعه المالك. لا أسماء أو توقيعات أو تواريخ اعتماد مفترضة. ملء هذا القالب لا ينفذ التهيئة التقنية وحده؛ يلزم اجتياز بوابات التفعيل والاختبارات المحددة.

- القرار على البدائل: `[A / B / C / بديل موصوف]`
- نطاق العمليات/الأقسام/entities المشمول والمستثنى: `[يُستكمل]`
- جدول الحدود والقيم والعملات / قاعدة FX المعتمدة (مرفق أو إصدار): `[TBC]`
- الأدوار والسلاسل وSoD والاستثناءات المعتمدة: `[TBC]`
- delegation / SLA / expiry / escalation semantics: `[TBC]`
- قرار break-glass: `[يبقى disabled / تفعيل بنطاق وشروط مرفقة — اختر صراحةً]`
- Policy ID / version: `[يُنشأ بعد القرار]` — policy hash: `[يُسجل بعد تثبيت النسخة المعتمدة]`
- تاريخ النفاذ / مراجعة السياسة التالية: `[TBC]`
- أدلة الاختبار، أثر التغيير، والمهام/بوابات الإطلاق التي تتغير: `[مراجع قابلة للتحقق]`
- Executive owner name/title: `[يُملأ من المالك]`
- توقيع المالك: `[فارغ حتى التوقيع الفعلي]` — التاريخ/الوقت: `[فارغ]`
- مراجعة Finance: `[اسم/قرار/تاريخ إن لزم؛ دون أن تحل محل قرار Executive]`
- مراجعة Warehouse / Security / Operations بحسب المجال: `[أسماء/قرارات/تواريخ إن لزم]`
- Coordinator record of accepted decision and affected gates: `[يحدث بعد sign-off؛ ليس ضمن هذا الموجز]`

## المصادر

- [docs/product/PRD.md](../../product/PRD.md#L113-L151) — SEC-01..06 وapproval baseline؛ وكذلك [CORE-03](../../product/PRD.md#L163-L165)، [commercial controls](../../product/PRD.md#L248-L266)، [stock controls](../../product/PRD.md#L303-L329)، [finance/payroll controls](../../product/PRD.md#L354-L409)، [AI no-silent-action](../../product/PRD.md#L506-L526) وacceptance scenarios `AT-03..15`.
- [docs/governance/OPEN_DECISIONS.md](../OPEN_DECISIONS.md#L3-L20) — owner وblocker وشرط بقاء الحالة OPEN.
- [docs/security/PERMISSION_MATRIX.md](../../security/PERMISSION_MATRIX.md#L1-L20) — deny-by-default، scope، SoD، delegation وbreak-glass baseline.
- [docs/workflows/WORKFLOW_CATALOG.md](../../workflows/WORKFLOW_CATALOG.md#L1-L17) — transitions، expiry، delegation وversion/hash binding؛ و[finance posting/reversal](../../workflows/WORKFLOW_CATALOG.md#L81-L92).
- [docs/backlog/tasks.json](../../backlog/tasks.json#L47-L85) — هدف A0-OPEN-02، مخرج الملف، ومعيار بقاء القرار OPEN حتى دليل اعتماد صريح.

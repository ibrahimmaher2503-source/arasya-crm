# عقود ثوابت المالية والمخزون

> المهمة: `A0-ARCH-900` — تصميم مبكر للعقود، لا scaffold أو تنفيذ.  
> الحالة: ضوابط PRD غير القابلة للتفاوض مفصولة عن `Proposed` design. لا تمثل هذه الوثيقة اعتماد Product Owner أو Finance أو Warehouse.  
> النطاق: `FIN-01..08`، `INV-01..05`، `SALE-04`، `PRJ-02`، `ARCH-01..06`، `NFR-02`، مع تدفقات التسليم والمشروعات ذات الصلة.

## 1. القراءة الملزمة وحالة السياسات

الهدف هو عقد حتمي قابل للتحويل إلى domain services واختبارات قبل بناء الوحدات؛ لا يقرر هذا المستند سياسة محاسبية أو تشغيلية معلقة. المرجع الأعلى هو [PRD](../product/PRD.md)، وتُقرأ هذه العقود مع [تصميم الحل](SOLUTION_ARCHITECTURE.md)، [كتالوج الوحدات](MODULE_CATALOG.md)، [نموذج البيانات](DATA_MODEL.md)، [كتالوج الأحداث](EVENT_CATALOG.md)، [كتالوج workflows](../workflows/WORKFLOW_CATALOG.md)، و[القرارات المفتوحة](../governance/OPEN_DECISIONS.md).

تُستخدم الوسوم كما يلي:

- **[MUST]**: invariant مفروض صراحةً من PRD أو حد ملكية/تكامل مثبت في التصميم؛ لا يجوز لمراجعة تنفيذية إضعافه.
- **[Proposed]**: تفصيل تعاقدي أو آلية تنفيذ مقترحة لتصبح قابلة للاختبار؛ يلزم مراجعتها مع مالك الوحدة قبل اعتمادها.
- **[Blocked: OPEN-xx]**: لا يجوز تفعيل السلوك التجاري المعني قبل اختيار موثق من المالك المختص. اسم الخيار أو المعادلة الرمزية أدناه لا يختار سياسة.

| القرار | المالك ونقطة الحجب | ما يبقى غير محدد هنا |
|---|---|---|
| `OPEN-03` | `Finance`؛ تصميم `A0` وقبل `A4` posting | `ChartOfAccounts` الفعلي، الضرائب، الفترات/التواريخ المالية، بنية الشركاء وسياسات الاعتراف والتسجيل والتسوية. لا يفرض هذا المستند حساباً أو معدلاً ضريبياً أو قاعدة قيود. |
| `OPEN-04` | `Warehouse + Finance`؛ تصميم `A0` وقبل `A3` stock semantics | طريقة التقييم (ومنها `FIFO` أو `weighted average`)، وحدات `SKU` والتحويلات، batch/roll handling، وسياسة السماح بالمخزون السالب. لا يختار هذا المستند أياً منها. |
| `OPEN-05` | `Finance + Operations`؛ قبل حساب actual margin في `A2` | توقيت/قاعدة الاعتراف بالإيراد، تخصيص التكلفة المباشرة، overhead، وصيغة ربحية المشروع. لا يجعل العقد أو الفاتورة أو التحصيل إيراداً معترفاً به تلقائياً. |

**حجب صريح:** إلى حين اعتماد القرارات والأدلة المطلوبة في `OPEN_DECISIONS.md`، يمكن تصميم وكتابة اختبارات العقود الرمزية، لكن لا يجوز تفعيل calculation أو journal mapping يعتمد على `OPEN-03/04/05`. كما لا تُخترع thresholds أو approval chains؛ تفعيلها محجوب بـ `OPEN-02`. هذه المهمة لا تحسم ولا تغيّر حالات تلك القرارات.

## 2. حدود الملكية والنطاق المشترك

| المجال | مالك الكتابة الوحيد | الحد التعاقدي |
|---|---|---|
| الحسابات والقيود والفواتير والمدفوعات والمصروفات والفترات | `Finance` | `Finance` وحده ينشئ أو يعكس `JournalEntry` و`JournalLine` ويغلق الفترة. التقارير تقرأ القيود المرحلة ولا تنشئ قيوداً. |
| الرصيد الفعلي والحجوزات وحركات المخزون والجرد والتحويلات | `Inventory` | `Inventory` وحده يكتب `StockMovement` و`StockReservation` ويحسب `on_hand`/`available`. لا يغيّر `Delivery` أو `Projects` رصيداً مباشرةً. |
| العقد والطلب التجاري ومراجعاتهما | `Sales` | `Sales` يملك الموافقة والمراجعة؛ لا يخصم مخزوناً ولا يعترف بإيراد لمجرد `quote.approved` أو `contract.signed` أو `order.approved`. |
| العهدة وإثبات التسليم | `Delivery` | يسجل استلام السائق و`POD` والكميات المقبولة/المرفوضة، ويربطها بمعرّفات حركات المخزون؛ لا ينفذ حركة مخزون بنفسه. |
| المشروع وتخصيص التكلفة/العائد للتقرير | `Projects` لبيانات المشروع؛ `Finance` لمصدر القيود | تكاليف المشروع تشير إلى المصدر ولا تكرر قيوداً مالية. تعريف `actual margin` محجوب بـ `OPEN-05`. |

كل مرجع بين وحدتين هو `typed contract` أو event/projection مملوك للمستهلك؛ ممنوع التعديل المباشر لجداول وحدة أخرى. `entity_id` هو نطاق `LegalEntity` في هذا النظام وليس `tenant_id` لتسجيل SaaS. كل أمر يثبت النطاق من هوية منفذة ومراجع مخزنة معاً؛ لا تكفي قيمة يرسلها العميل لتخويل الوصول.

**مفتاح النطاق المقترح للمخزون:** `(entity_id, warehouse_id, sku_id, stock_unit_id, condition_bucket)`؛ ويُربط كل حجز/حركة بمصدر ثابت ومعرّف سطر. يجب منع FK/علاقة تربط `entity_id` أو `warehouse_id` أو مشروعاً/حساباً من نطاق غير متطابق. كل كمية موجبة، ووحدتها وإصدار التحويل معلومان. لا تحويل ضمنياً بين وحدتين؛ التحويل لا يعمل إلا بإصدار معتمد وفق `OPEN-04`.

**مفتاح النطاق المقترح للمالية:** `(entity_id, journal_id, account_id, currency, posting_period_id)`، مع أبعاد `department_id` و`project_id` و`branch_id` عند انطباقها. يجب أن تكون الحسابات والأبعاد والمصدر والفترة ضمن الكيان نفسه. لا تجميع بين كيانات قانونية أو عملات بلا policy/قيود تحويل معتمدة.

## 3. ثوابت غير قابلة للتفاوض

| ID | المستوى | Invariant قابل للاختبار |
|---|---|---|
| `FS-I-01` | [MUST] | كل انتقال حساس يمر بـ authentication وserver-side authorization على العملية والسجل والـ `entity_id` والأبعاد/المستودع، ثم التحقق الحتمي من الحالة و`expected_version`. رفضه لا يغيّر aggregate؛ ويسجل الحدث الأمني عند وجوبه. |
| `FS-I-02` | [MUST] | كل تعديل مالي أو مخزني ذري وidempotent؛ لا يُقبل `double-submit` كحركة ثانية. تصحيح المنشور/المرحل بحركة أو قيد تعويضي مرجعي، لا بتعديل أو حذف الأصل. |
| `FS-I-03` | [MUST] | لا ينقص `stock.on_hand` عند الحجز. الحجز يخصم من المتاح فقط؛ الإصدار الفعلي يسجل حركة واحدة ويخفض الرصيد الفعلي مرة واحدة. |
| `FS-I-04` | [MUST] | لا يتجاوز مجموع الحجز النشط المتاح في نفس نطاق `(entity, warehouse, SKU, unit)` بعد تسلسل المعاملة. سلوك المخزون السالب العام يظل `OPEN-04`؛ هذا الشرط لا يختار سياسة السماح بالإصدار غير المحجوز. |
| `FS-I-05` | [MUST] | كل `JournalEntry` مرحل متوازن في أساس الموازنة الذي تعتمده Finance، ومصدره وأبعاده وفترته صالحون. لا يُرحّل قيد غير متوازن أو على فترة مغلقة. |
| `FS-I-06` | [MUST] | القيود والحركات المرحلة لا تُحذف ولا تُستبدل بصمت. الانعكاس له سجل جديد ومرجع للأصل وسبب وممثل/زمن؛ يبقى الأصل قابلاً للتدقيق. |
| `FS-I-07` | [MUST] | `approval` لا يساوي `posting` أو `payment` أو `stock issue`. الاعتماد يجيز مرحلة workflow فقط؛ لكل أمر تنفيذ صلاحية، شروط مرحلة، ومفتاح تكرار مستقل. |
| `FS-I-08` | [MUST] | كل تغيير aggregate مع `AuditEvent`/نية التدقيق و`OutboxEvent` المناسب في transaction واحدة. الإرسال بعد commit، والاستهلاك idempotent عبر `Inbox` unique؛ الشبكة `at-least-once` لا `exactly-once`. |
| `FS-I-09` | [MUST] | `Money` قيمة decimal + currency + دقة/قاعدة rounding معتمدة؛ لا binary float. لا جمع أو مقارنة أو conversion بين عملات دون قواعد صريحة. |
| `FS-I-10` | [MUST] | كل نتيجة KPI/تقرير مالي أو مخزني قابلة للتسوية إلى المصدر المرحل/المعتمد، وتعرض الاستثناء أو المصدر المفقود بدلاً من اختلاق رقم نهائي. |

## 4. معادلات الرصيد والحجز والإصدار

هذه المعادلات تعرّف الحدود، لا تقييم المخزون أو السياسة التجارية. في النطاق `k = (entity_id, warehouse_id, sku_id, stock_unit_id)`:

```text
OnHand(k, t) = sum(signed_quantity of all posted StockMovement for k through t)
ActiveReserved(k, t) = sum(remaining_quantity of reservations in active states for k at t)
Available(k, t) = ReservableOnHand(k, t) - ActiveReserved(k, t)
ReservationRemaining(r) = ReservedQuantity(r) - IssuedQuantity(r) - ReleasedQuantity(r)
```

`ReservableOnHand` يعني الكمية المؤهلة للحجز حسب bucket/حالة الجودة المعتمدة؛ الكمية التالفة أو المعزولة تُتبع مادياً لكنها لا تصبح قابلة للحجز بمجرد وجودها. تعريف حالات الجودة والمواضع التفصيلية `Proposed` ويحتاج مراجعة `Warehouse/Quality`. إذا لم يعتمد بعد تصنيف أو تحويل وحدة، يرفض الأمر ولا يخمّن. في المعادلة، كل حدّ يجمع **الوحدة نفسها** فقط.

| الحدث | التغير في `OnHand` | التغير في `ActiveReserved` | نتيجة `Available` |
|---|---:|---:|---:|
| حجز `q` من رصيد متاح | `0` | `+q` | `-q` |
| تحرير `q` غير مُصدر | `0` | `-q` | `+q` |
| إصدار `q` من حجز | `-q` | `-q` | `0` |
| استلام صالح `q` | `+q` | `0` | `+q` |
| إرجاع مستلم صالح `q` | `+q` | `0` | `+q` بعد فحص/قبول؛ التالف لا يدخل الكمية القابلة للحجز |

شرط الحجز الذري: `q > 0` و`q <= Available(k)` لكل سطر عند نقطة commit. عقد `ReserveOrder` المقترح يحجز **كل الأسطر أو لا شيء**؛ لا توجد حيازة جزئية مخفية. شرط `IssueReserved`: `q > 0` و`q <= ReservationRemaining(r)` ويخص الحجز/المستودع/الوحدة ذاتها. نجاح الإصدار ينشئ حركة ثابتة ويخفض `OnHand` و`ReservationRemaining` بنفس `q`، لذلك لا يتغير `Available` بسبب استهلاك الحجز نفسه. أي إصدار خارج هذا العقد يحتاج أمر وسياسة مستقلة؛ لا تُستنتج من `OPEN-04`.

**مثال رمزي معقم:** قبل الحجز `OnHand=Q` و`ActiveReserved=R`، حيث `Q=12` و`R=3` بوحدات مخزون واحدة، فيكون `Available=9`. حجز `q=5` يعطي `OnHand=12` و`ActiveReserved=8` و`Available=4`؛ لا توجد حركة خصم فعلية. إصدار `x=2` من ذلك الحجز يعطي `OnHand=10` و`ActiveReserved=6` و`Available=4`؛ سجل الإصدار `-2` والحجز المتبقي 3 للحجز الجديد. عند تحرير هذا المتبقي 3 يصبح `OnHand=10` و`ActiveReserved=3` و`Available=7`. هذه القيم لا تفترض تكلفة أو valuation.

### التحويل والعهدة والمرتجعات

- **تحويل مستودع:** [Proposed] مرحلتان `ship` ثم `receive` مع `transfer_id` وسطر/كمية ومصدر/وجهة ضمن `entity_id` واحد. عند الشحن يخرج المقدار من المستودع المصدر ويدخل حساباً/موقعاً منطقياً `in_transit` غير قابل للحجز؛ عند الاستلام يخرج من `in_transit` ويدخل الوجهة. `source + in_transit + destination` يحفظ الكمية عبر المرحلتين؛ التحويل ليس شراءً ولا بيعاً. لا تُسوّى الكمية المفقودة أو الزائدة تلقائياً: افتح discrepancy واربط أي تصحيح بحركة معتمدة. يحتاج تمثيل `in_transit` وتدفق الحالات مراجعة Warehouse قبل اعتماده.
- **عهدة التسليم:** لا يبدأ `Delivery` عهدة السائق إلا بمرجع `stock.issued` وكمية مطابقة. `DeliveryProof` يسجل accepted/rejected وسبباً وإثباتاً. الرفع أو التوقيع وحده لا يعيد المخزون. [Proposed] قبل إغلاق التسليم: `issued_qty = accepted_qty + returned_received_qty + unresolved_custody_qty`، ويجب أن تكون `unresolved_custody_qty=0` لإغلاق كامل السطر؛ الفاقد/التلف يُعالج كاستثناء وحركة مصرح بها، لا كإعادة كتابة للكمية.
- **مرتجع:** ينشئ `Inventory` حركة inbound جديدة مرتبطة بـ `original_issue_movement_id` و`delivery_id`/مرجع الإرجاع. لا يعكس حركة الإصدار الأصلية ولا يزيد المتاح قبل الاستلام المادي والفحص. الصالح ينتقل إلى bucket قابل للحجز؛ التالف/المرفوض إلى bucket غير متاح. لا يحسم هذا المستند جودة الإرجاع، ضمانه، أو المبلغ/الضريبة/العمولة المستردة.
- **الجرد والتسوية:** فرق العدّ ليس update لرصيد مخزن. [Proposed] `PostCountAdjustment` يقارن `counted_qty` بلقطة دفترية ذات `aggregate_version` معلومة؛ إذا وجدت حركات بعد اللقطة ولم تُسوَّ، يرفض بـ `INV_COUNT_STALE_RECOUNT_REQUIRED` بدلاً من تطبيق delta على أساس قديم. إذا كان العدد الفعلي أقل من مجموع الحجوزات النشطة بعد التعديل، يسجل discrepancy ويرفض ترحيل adjustment بـ `INV_ACTIVE_RESERVATION_CONFLICT` إلى أن تُحرر/يعاد تخصيص الحجوزات المعنية؛ لا يسمح برصيد متاح سالب مخفي. التعديل حركة جديدة، بسبب، وموافقة وفق policy؛ threshold/chain الفعلية محجوبة بـ `OPEN-02`.
- **حد التقييم:** حركة استلام/إصدار/مرتجع/تسوية مخزون هي source تشغيلي؛ ليست بذاتها مصروفاً أو `COGS` أو إيراداً. لا يضع `Inventory` قيمة مالية من عنده. `Finance` لا ينشئ journal على `stock.issued` إلا بعد قرار valuation ومخطط القيود الموافق عليهما بـ `OPEN-03/04`. `valuation_ref` يظل مرجعاً/version placeholder حتى ذلك الحين، لا طريقة تقييم ضمنية.

## 5. الدقة النقدية والتقريب — placeholders لا سياسة

`Money` في العقود يحمل `amount` كسلسلة decimal معيارية أو قيمة decimal exact، و`currency_code`، ولا يقبل `float`. تحفظ الأعداد بصيغة decimal ذات precision معلن في schema؛ لا يُستنتج عدد المنازل من أمثلة أو من واجهة العرض. قواعد العملات، scale، دقة الحساب الوسيط، نقطة التقريب، `rounding_mode`، وتقسيم الباقي بين الأسطر حقول إعداد **غير محددة** حتى اعتماد Finance ضمن `OPEN-03`.

**شرط التنفيذ قبل التفعيل:** سياسة معتمدة تعرّف لكل currency والعملية: `storage_scale`، `calculation_scale`، مكان التقريب (سطر/مستند/قيد)، `rounding_mode`، طريقة توزيع فرق التقريب في allocations، وأساس الموازنة للقيود متعددة العملات/سعر الصرف عند السماح بها. إلى ذلك الحين لا يصدر النظام فاتورة/قيداً أو margin نهائياً يحتاج تلك القواعد؛ لا يعتمد على default framework أو `HALF_UP` أو منزلتين عشريتين.

**ثوابت قابلة للاختبار بعد ضبط placeholders:**

1. التحويل من تمثيل API إلى decimal لا يفقد أرقاماً قبل نقطة التقريب المعتمدة.
2. تطبق الدالة `round(value, approved_scale, approved_mode)` في المرحلة المعتمدة فقط، وتُسجل `policy_version` والنتيجة؛ لا تقريب متكرر لكل طبقة عرض.
3. يجب أن تتطابق مبالغ allocations مع مصدرها وفق قاعدة توزيع الباقي التي تعتمدها Finance؛ لا ينشأ فرق غير مسجل أو سطر balancing اعتباطي.
4. شرط توازن القيد `sum(debit_i in balancing_basis) = sum(credit_i in balancing_basis)` **exactly** بعد تطبيق policy المعتمدة. أي tolerance/عملة أساس/FX اختيار سياسة ممنوع افتراضه.
5. اختبار حدود rounding يستخدم عينات Finance المعتمدة بعد القرار؛ قبلها اختبار property يثبت حفظ الدقة وعدم وجود float فقط، ولا يدعي قيمة تقريب متوقعة تجارياً.

## 6. عقود أوامر `Inventory`

الغلاف المشترك لكل command: هوية منفذ موثقة، `entity_id` مستنتج/متحقق، `expected_version` لكل aggregate mutable، `idempotency_key` ثابت عبر retry، `correlation_id`، actor/time من الخادم، ومدخلات typed. أرقام/مراجع الطرف العميل لا تثبت الصلاحية. أسماء أخطاء `Proposed` أدناه رموز API ثابتة مقترحة؛ payload الخطأ لا يكشف بيانات مستودع أو عميل غير مخول.

| Command | Inputs → outputs | AuthZ / owner | Source key | Transaction boundary | Errors (proposed) | Events |
|---|---|---|---|---|---|---|
| `ReserveOrder` | `order_id`, `approved_revision_id`, `order_version`, كل `{order_line_id, sku_id, warehouse_id, quantity, stock_unit_id, unit_conversion_version?}`, `idempotency_key` → reservation refs وحالة/كمية كل سطر ولقطة on-hand/reserved/available بعد commit | `Inventory.reserve` + نطاق الكيان والمستودع؛ يتحقق `Inventory` عبر Sales contract من أن الطلب/النسخة approved؛ `Inventory` مالك الكتابة | `entity_id + order_id + revision_id + order_line_id + reservation_generation`; مفتاح إعادة الطلب يطابق payload hash | transaction واحدة تقفل balance rows بترتيب ثابت، تتحقق من جميع الأسطر والوحدة والكمية والمتاح، تحفظ كل الحجوزات أو لا شيء، audit intent وoutbox. لا حركة `StockMovement` ولا تغيير `OnHand` | `AUTH_FORBIDDEN`, `SCOPE_MISMATCH`, `EXPECTED_VERSION_CONFLICT`, `IDEMPOTENCY_KEY_REUSED`, `SOURCE_NOT_APPROVED`, `INV_INSUFFICIENT_AVAILABLE`, `INV_UNIT_UNRESOLVED` | `stock.reserved` بعد commit فقط؛ الحجز لا يعني issue |
| `ReleaseReservation` | `reservation_id`, `reservation_version`, `quantity` أو `release_all`, `reason`, `idempotency_key` → released qty، remaining qty، state/version | `Inventory.release` + scope للمستودع؛ حالة الطلب/إلغاء المصدر مثبتة؛ `Inventory` | `(reservation_id, release_command_key)` | قفل reservation وbalance scope؛ لا يتجاوز المتبقي غير المصدر؛ يسجل release/audit/outbox ذرياً؛ لا يغير `OnHand` | `AUTH_FORBIDDEN`, `EXPECTED_VERSION_CONFLICT`, `INV_RESERVATION_STATE_INVALID`, `INV_RELEASE_EXCEEDS_REMAINING`, `IDEMPOTENCY_KEY_REUSED` | `stock.reservation.released` **امتداد مقترح** للكتالوج |
| `IssueReserved` | `reservation_id`, `reservation_version`, `quantity`, `source_order_line_id`, `project_id?`, `idempotency_key` → `movement_id`, issued qty، المتبقي للحجز، balances بعد commit | `Inventory.issue` + entity/warehouse + دور warehouse؛ صلاحية order/project links متحققة؛ approval المطلوبة تُقرأ من policy ولا تستبدل الصلاحية؛ `Inventory` | `(entity_id, reservation_id, issue_command_key)`؛ ولكل movement unique source action | يقفل reservation ورصيد المخزون، يتحقق من نفس المستودع والوحدة ومن `quantity <= remaining`, ثم ينشئ movement سالباً ويستهلك الحجز بالكمية نفسها ويسجل audit/outbox في transaction واحدة. لا سجل نجاح قبل commit | `AUTH_FORBIDDEN`, `SCOPE_MISMATCH`, `EXPECTED_VERSION_CONFLICT`, `INV_RESERVATION_STATE_INVALID`, `INV_ISSUE_EXCEEDS_RESERVED`, `INV_NEGATIVE_STOCK_POLICY_UNRESOLVED`, `IDEMPOTENCY_KEY_REUSED` | `stock.issued` بعد commit، مع `movement_id`, `reservation_id`, `order_line_id`, `quantity/unit`, `valuation_ref` غير مفسر حتى policy |
| `ReceiveStock` / `ReceiveReturn` | `receipt_source_type/id/version`, `warehouse_id`, `sku_id`, `quantity/unit`, provenance/reason، وللإرجاع `original_issue_movement_id` ومرجع فحص؛ `idempotency_key` → inbound `movement_id`, balances، quality bucket | `Inventory.receive` + entity/warehouse؛ لمصدر الإرجاع تحقق من issued/custody وكمية لم تُستلم سابقاً؛ `Inventory` | `(entity_id, source_type, source_id, source_line_id, receipt_action_key)`؛ الإرجاع unique مقابل الكمية المرجعة من original issue | قفل balance ومصدر الاستلام؛ validate provenance/remaining return؛ insert inbound movement وaudit/outbox ذرية. لا عملية شراء متقدمة ولا قيد محاسبي تلقائي | `AUTH_FORBIDDEN`, `SCOPE_MISMATCH`, `INV_SOURCE_INVALID`, `INV_RETURN_EXCEEDS_ISSUED`, `INV_RETURN_NOT_RECEIVED`, `INV_UNIT_UNRESOLVED`, `IDEMPOTENCY_KEY_REUSED` | `stock.received` و`stock.returned` **امتدادان مقترحان**؛ `stock.issued` لا يعاد نشره كمرتجع |
| `ShipTransfer` | `transfer_id`, `source_warehouse_id`, `destination_warehouse_id`, lines `(sku, qty, unit)`, expected versions، idempotency → shipped movement refs وكمية in-transit | `Inventory.transfer` + صلاحية المصدر والوجهة ضمن entity نفسها؛ `Inventory` | `(entity_id, transfer_id, line_id, ship_action_key)` | يقفل المصدر وينشئ outbound + in-transit transfer record في transaction؛ يتأكد من المتاح وفق قواعد الإصدار المعتمدة؛ حركة واحدة لكل action | `AUTH_FORBIDDEN`, `SCOPE_MISMATCH`, `INV_TRANSFER_STATE_INVALID`, `INV_INSUFFICIENT_AVAILABLE`, `INV_TRANSFER_CROSS_ENTITY`, `IDEMPOTENCY_KEY_REUSED` | `stock.transfer.shipped` **امتداد مقترح** |
| `ReceiveTransfer` | `transfer_id`, `transfer_version`, destination warehouse، qty مستلمة لكل سطر `<= in_transit`, evidence/discrepancy، idempotency → inbound movement refs، remaining in-transit | `Inventory.transfer.receive` + صلاحية الوجهة؛ `Inventory` | `(entity_id, transfer_id, line_id, receive_action_key)` | يقفل transfer/in-transit/وجهة؛ ينقل المستلم فقط، ويترك الفرق in-transit أو discrepancy؛ لا يغير المصدر مرة ثانية | `AUTH_FORBIDDEN`, `EXPECTED_VERSION_CONFLICT`, `INV_TRANSFER_STATE_INVALID`, `INV_RECEIVE_EXCEEDS_IN_TRANSIT`, `IDEMPOTENCY_KEY_REUSED` | `stock.transfer.received` **امتداد مقترح** |
| `PostCountAdjustment` | `count_session_id/version`, scope and `counted_quantity`, `book_aggregate_version`, reason, approval ref، idempotency → signed adjustment `movement_id`, delta | `Inventory.count.adjust` + نطاق entity/warehouse؛ إثبات العد ومراجعة approval وفق policy؛ `Inventory` | `(entity_id, count_session_id, warehouse_id, sku_id, count_revision)` | يقفل scope؛ يرفض لقطة قديمة/حركات غير مسواة؛ يحسب `delta = counted - book_at_snapshot` مرة؛ ينشئ حركة adjustment، audit/outbox | `AUTH_FORBIDDEN`, `EXPECTED_VERSION_CONFLICT`, `INV_COUNT_STALE_RECOUNT_REQUIRED`, `INV_ACTIVE_RESERVATION_CONFLICT`, `INV_COUNT_NOT_APPROVED`, `IDEMPOTENCY_KEY_REUSED` | `stock.count.adjusted` **امتداد مقترح** |

عند إعادة استخدام `idempotency_key` مع payload مطابق، يعاد نفس نتيجة العملية الأصلية. عند اختلاف payload يعاد `IDEMPOTENCY_KEY_REUSED` ولا ينفذ أمر ثانٍ. يجب أن تكون unique constraints/locking هي الحاجز النهائي؛ فحص التطبيق وحده غير كافٍ.

## 7. عقود أوامر `Finance`

كل عملية ترحيل أو إغلاق تتحقق من الصلاحية والسياسة والنسخة والفترة داخل transaction التي تحمي التغيير. `approval_id` دليل انتقال موافقة فقط، لا يثبت صحة القيود ولا يعطي تلقائياً `Finance.post`. يجب أن تتضمن كل نتيجة مرجع مصدر قابلاً للتدقيق. أخطاء هذه الجداول **[Proposed]** أسماء عقد، لا ادعاء بأن API منفذ.

| Command | Inputs → outputs | AuthZ / owner | Source key | Transaction boundary | Errors (proposed) | Events |
|---|---|---|---|---|---|---|
| `PostApprovedSource` | `source_module/type/id/version`, `accounting_event_key`, `posting_purpose`, `posting_date`, approved source snapshot/hash, approved policy versions، journal line intents `{account_id, debit, credit, currency, dimensions}`، approval refs، idempotency → `journal_id`, `journal_version`, `posted_at`, `balance proof`, source refs | `Finance.post` + entity/account/department/project scope؛ approval الصحيح لنسخة المصدر؛ `Finance` وحده ينشئ القيود | unique `(entity_id, source_module, source_type, source_id, source_version, accounting_event_key, posting_purpose)`؛ ليس source id وحده لأن المصدر قد ينتج أحداثاً محاسبية مختلفة | transaction تقفل posting key والفترة، تتحقق من active policy/chart/account/dimensions/currency والـ source version/approval/fiscal period؛ تحسب القيم وفق policy المعتمدة، تثبت debit=credit، تكتب entry+lines+source link+audit+outbox معاً. لا posting من policy ناقصة | `AUTH_FORBIDDEN`, `SCOPE_MISMATCH`, `SOURCE_NOT_APPROVED`, `EXPECTED_VERSION_CONFLICT`, `FIN_ACCOUNT_INVALID`, `FIN_DIMENSION_SCOPE_INVALID`, `FIN_CURRENCY_POLICY_UNRESOLVED`, `FIN_ROUNDING_POLICY_UNRESOLVED`, `FIN_JOURNAL_UNBALANCED`, `FIN_PERIOD_CLOSED`, `FIN_SOURCE_ALREADY_POSTED`, `IDEMPOTENCY_KEY_REUSED` | `finance.journal.posted` **امتداد مقترح**؛ وللفواتير `invoice.posted` حسب الكتالوج، وللمصروف الموافق `expense.approved` يظل حدث اعتماداً لا ترحيلاً |
| `RecordReceiptAndAllocate` | source/external receipt key، payer/customer، `cashbox_or_bank_account_id`, receipt date، `amount/currency`, allocations `(invoice_id, amount)`, evidence، idempotency → `payment_id`, allocation refs, unapplied amount إن سمحت policy، `journal_id` عند الترحيل | `Finance.receive` + entity وcash/bank scope؛ تحقق من صلاحية الفواتير ومبالغها؛ `Finance` | `(entity_id, receipt_source, external_receipt_key)` مع key مستقل لكل allocation؛ نفس المفتاح/نفس payload يعيد نفس receipt | يقفل payment key والفواتير المتأثرة بترتيب ثابت؛ يتحقق من currency/الرصيد المستحق/عدم تجاوز التخصيص؛ يحفظ payment+allocations+القيد المتوازن عند ترحيلها+audit+outbox atomically. لا تحويل عملة ضمني | `AUTH_FORBIDDEN`, `SCOPE_MISMATCH`, `FIN_RECEIPT_DUPLICATE`, `FIN_ALLOCATION_EXCEEDS_BALANCE`, `FIN_CURRENCY_POLICY_UNRESOLVED`, `FIN_PERIOD_CLOSED`, `FIN_JOURNAL_UNBALANCED`, `IDEMPOTENCY_KEY_REUSED` | `payment.received` بعد commit وفق عقد الكتالوج |
| `ReversePostedJournal` | `original_journal_id/version`, reason, original source refs، `reversal_key`, تاريخ الترحيل المطلوب، policy/approval refs → `reversal_journal_id`, `reverses_journal_id`, status، علاقة المصدر | `Finance.reverse` + entity scope؛ approval/reason حسب policy؛ `Finance` | `(entity_id, original_journal_id, reversal_key)`؛ unique لكل reversal request، لا تعديل للأصل | يقفل الأصل وreversal key والفترة الجديدة؛ يتأكد أن الأصل posted وغير reversed بنفس key، ينشئ قيداً تعويضياً مرتبطاً في فترة مسموح بها، يوازن ويكتب audit/outbox. يبقى الأصل كما هو | `AUTH_FORBIDDEN`, `EXPECTED_VERSION_CONFLICT`, `FIN_REVERSAL_ALREADY_EXISTS`, `FIN_PERIOD_CLOSED`, `FIN_PARTIAL_REVERSAL_POLICY_UNRESOLVED`, `FIN_JOURNAL_UNBALANCED`, `IDEMPOTENCY_KEY_REUSED` | `finance.journal.reversed` **امتداد مقترح**؛ حدث/قيد عكسي جديد، لا إعادة نشر/تعديل الحدث الأصلي |
| `CloseFiscalPeriod` | `entity_id`, `period_id/version`, reconciliation checklist/evidence refs، close reason، approver refs → closed state/version، close timestamp وsnapshot refs | `Finance.period.close` + entity؛ فصل واجبات حسب rules؛ `Finance` | `(entity_id, period_id, close_action_key)` | يقفل صف الفترة/مفتاح posting gate؛ ينهي فقط بعد اكتمال checks؛ يكتب حالة الإغلاق وaudit/outbox في transaction. تتنافس كل عمليات post لنفس الفترة على القفل نفسه | `AUTH_FORBIDDEN`, `EXPECTED_VERSION_CONFLICT`, `FIN_PERIOD_NOT_READY`, `FIN_PERIOD_ALREADY_CLOSED`, `FIN_OPEN_RECONCILIATION`, `IDEMPOTENCY_KEY_REUSED` | `finance.period.closed` **امتداد مقترح** |
| `ReopenFiscalPeriod` | `period_id/version`, reason/evidence، approval ref منفصل، requested scope → reopened state/version/audit ref | `Finance.period.reopen` + موافقة مخولة ومستقلة حسب policy؛ `Finance` | `(entity_id, period_id, reopen_request_id)` | يقفل الفترة؛ تحقق من approval/صلاحية reopen وسجل المبررات؛ لا يعدل أي قيد منشور. الترحيل اللاحق يظل منفصلاً ويخضع لقواعد الفترة | `AUTH_FORBIDDEN`, `EXPECTED_VERSION_CONFLICT`, `FIN_REOPEN_APPROVAL_REQUIRED`, `FIN_PERIOD_STATE_INVALID`, `IDEMPOTENCY_KEY_REUSED` | `finance.period.reopened` **امتداد مقترح** |

**حدود ترحيل المصادر:** `PostApprovedSource` يستقبل journal intents من adapter/مصدر موثوق، لكنه لا يختار mapping. `expense.approved` لا يعني paid أو posted. `invoice.posted` حدث invoice بحسب عقد الكتالوج، لكنه لا يحسم هل القيد المقابل `revenue` أو `deferred` أو غير ذلك. `stock.issued` لا يولد journal بقيمة مخمّنة. إذا لم توجد سياسة approved أو account mapping صالح، يرفض posting برمز واضح أو يبقى source reconciliation pending؛ لا يسجل قيداً متوازناً شكلياً بحساب مؤقت غير معتمد.

**الانعكاس والفترة المغلقة:** الإجراء العادي هو reversal/adjusting entry بتاريخ/فترة مسموحين، مع ربط للأصل وسبب وموافقة مطلوبة. لا تعدل `posting_date` أو `JournalLine` الأصلي لتجاوز الإغلاق. Reopening يتطلب actor/سبب/دليل/approval وتاريخاً في history. هل يسمح reversal جزئي، وما تاريخ reversal، وهل يستلزم reopen بدلاً من الفترة الحالية، كل ذلك يظل ضمن policy Finance/`OPEN-03`؛ لا يقفل هذا المستند جواباً.

## 8. الحدث، retry، والتزامن

تحتوي envelope كل event على `event_id`, `event_type`, `event_version`, `entity_id`, `aggregate_type/id/version`, `actor`, `occurred_at` UTC، `correlation_id`, `causation_id`, وpayload أدنى لا يحمل أسراراً أو PII غير لازم. publisher ينشر بعد commit. consumer يحفظ `(consumer,event_id)` unique وأثر المعالجة في transaction واحدة، ويتحقق من scope ونسخة المصدر الحالية؛ لا يثق بصلاحية actor تاريخية. ترتيب الأحداث قد يتغير؛ consumer قديم/مكرر يعيد نفس النتيجة أو يطلب reconciliation صريحة، ولا يتجاهل أثراً مالياً بصمت.

| الحدث | الحالة في `EVENT_CATALOG.md` | العقد/المستهلك المطلوب |
|---|---|---|
| `order.approved` | موجود؛ `Sales → Inventory, Delivery` | يطلب حجزاً، لا issue. نتيجة المخزون/فشل التوفر تبقى ظاهرة لمالك الطلب ولا تنشئ حجزاً جزئياً تحت عقد all-or-none المقترح. |
| `stock.reserved` | موجود؛ `Inventory → Sales, Projects` | بعد commit للحجز؛ payload يحدد `reservation_id`, `order_id/revision`, line refs والكمية/الوحدة؛ `OnHand` ثابت. |
| `stock.issued` | موجود؛ `Inventory → Projects, Finance, Delivery` | بعد commit للحركة؛ يحمل `movement_id`, reservation/source refs وquantity/unit و`valuation_ref` غير محسوم. لا يقرر Finance الاعتراف أو التقييم وحده من وصول الحدث. |
| `invoice.posted`, `payment.received`, `expense.approved` | موجودة | `invoice.posted` و`payment.received` تخصان Finance بعد أثرهما المعرف؛ `expense.approved` اعتماد لا دفع ولا posting. المستهلك dedup عبر inbox. |
| `stock.received`, `stock.returned`, `stock.reservation.released`, `stock.transfer.shipped/received`, `stock.count.adjusted` | **أسماء امتدادات مقترحة** | يلزم إضافة schemas/version/producer/privacy/consumer rules واختبارات توافق إلى `EVENT_CATALOG.md` قبل التنفيذ؛ لا تعدّ جزءاً معتمداً من الكتالوج الحالي. |
| `order.cancellation.requested` | **امتداد مقترح** | يحمل `SalesOrder` version؛ `Sales` لا يثبت `cancelled` قبل إقرار `Inventory` بتسوية الحجوزات/الإصدار. أحداث approval قديمة تصل بعده لا تعيد تفعيل revision ملغاة. |
| `finance.journal.posted/reversed`, `finance.period.closed/reopened` | **أسماء امتدادات مقترحة** | أحداث للأثر المالي/fiscal state؛ يلزم اعتمادها وإضافتها للكتالوج. أحداث `invoice.posted`/`payment.received` لا تستبدل سجل journal العام أو reconciliation. |

### اختبارات تزامن وcrash قابلة للتنفيذ منفردة

لكل اختبار أدناه fixture معزول و`entity_id=E1`، يبدأ workers بحاجز latch لتداخل المعاملتين، ثم يقرأ مباشرة من authority owner. كل retry يستخدم المفتاح نفسه إلا حيث يذكر مفتاحان. يجب تطبيق race tests على قاعدة البيانات/آلية القفل المختارة، لا mock للـ repository فقط. أسماء هذه الحالات معرفات test مقترحة؛ نتائجها شروط قبول دقيقة.

| Test ID | الإجراء المتزامن/الخطوات | النتيجة المتوقعة بالضبط |
|---|---|---|
| `FS-RACE-01 ReserveCompetition` | في `E1/W1/S1/U1`: `OnHand=10`, حجوزات نشطة `6`, available `4`. عاملان يرسلان `ReserveOrder(q=4)` لحجزين مختلفين وبـ keys مختلفين في اللحظة نفسها. | commit واحد فقط؛ الثاني `INV_INSUFFICIENT_AVAILABLE`. بعدهما `OnHand=10`, `ActiveReserved=10`, `Available=0`; سجل حجز جديد واحد و`stock.reserved` واحد. لا حجز سالب/جزئي ولا event للفاشل. |
| `FS-RACE-02 SameReservationReplay` | أرسل طلب الحجز نفسه مرتين بالتوازي مع `idempotency_key=K` وpayload hash واحد، ثم أعده بعد نجاحه. | كل الاستجابات تحمل نفس `reservation_id` ونتيجة commit الأصلية؛ صف حجز واحد، تغير `ActiveReserved` مرة واحدة، و`event_id` واحد. |
| `FS-RACE-03 IdempotencyPayloadMismatch` | بعد نجاح `K` بكمية `q=2` أعد `K` بكمية `q=3` أو warehouse مختلف. | رفض `IDEMPOTENCY_KEY_REUSED`; لا حجز/حركة/تغيير رصيد/event إضافي؛ لا يكشف الرصيد خارج scope. |
| `FS-RACE-04 IssueSameReservation` | حجز متبقٍ `5`؛ عاملان يطلبان إصدار `4` من reservation نفسه بمفتاحين مختلفين، مع latch واحد. | أمر واحد ينجح فقط؛ الآخر `INV_ISSUE_EXCEEDS_RESERVED` أو `EXPECTED_VERSION_CONFLICT` بحسب version contract، ولا يعاد المحاولة تلقائياً بمفتاح جديد. ينشأ movement سالب واحد `-4`؛ ينقص on-hand والمتبقي 4 فقط مرة واحدة، وevent `stock.issued` واحد. |
| `FS-RACE-05 ReserveVersusIssue` | `OnHand=10`, `ActiveReserved=6`, `Available=4`. بالتوازي: حجز جديد `4` وإصدار `4` من حجز قائم. | مع lock/serialization كلا الأمرين ينجحان بصرف النظر عن ترتيب التنفيذ؛ النهاية `OnHand=6`, `ActiveReserved=6`, `Available=0`. لا رفض زائف ولا تجاوز؛ حجز جديد واحد وحركة issue واحدة. |
| `FS-RACE-06 TransferReceiveReplay` | اشحن `5` من W1 إلى transfer in-transit؛ أرسل `ReceiveTransfer(q=5,key=R)` مرتين بالتوازي ثم replay بعد commit. | inbound إلى W2 مرة واحدة فقط؛ `in_transit=0`, المصدر منخفض 5، الوجهة مرتفعة 5، entity total محفوظ؛ نفس response/`movement_id` لكل retry؛ لا استلام يفوق المشحون. |
| `FS-RACE-07 StaleCount` | افتح count snapshot عند aggregate version `v`; سجل حركة issue صحيحة تنتج `v+1`; حاول `PostCountAdjustment` باستخدام snapshot `v`. | يرفض `INV_COUNT_STALE_RECOUNT_REQUIRED`; لا adjustment ولا تغيير رصيد/event. يبدأ عد/مراجعة جديدان؛ لا تطبيق delta على الرصيد الجديد. |
| `FS-RACE-08 DuplicateFinancialSource` | مصدر approved واحد مع `accounting_event_key=A`; عاملان ينفذان `PostApprovedSource` بالتوازي بنفس payload/key. | `JournalEntry` مرحل واحد و`JournalLine` set واحدة؛ مجموع debit=credit حسب fixture policy؛ استجابة التكرار تعيد `journal_id` نفسه؛ posting key unique وحدث واحد. |
| `FS-RACE-09 JournalKeyPayloadMismatch` | بعد نجاح posting key `A` أعده بمبلغ/حساب/نسخة مصدر مختلفة. | يرفض `IDEMPOTENCY_KEY_REUSED` أو source conflict محدد؛ لا journal ثانٍ ولا تعديل للأول ولا event جديد. |
| `FS-RACE-10 UnbalancedJournalRollback` | قدم line intents غير متوازنة عمداً، مع account/source صالحين. | `FIN_JOURNAL_UNBALANCED`; لا entry/lines posted ولا source link ولا outbox event. يبقى المصدر قبل الأمر كما هو، ويسجل audit رفض المحاولة حسب سياسة Audit. |
| `FS-RACE-11 PostVersusClose_PostWins` | فترة مفتوحة؛ يبدأ post ويأخذ قفل period gate أولاً؛ يبدأ close وينتظر؛ اسمح للـ post بالـ commit ثم تابع close. | post واحد ناجح ضمن الفترة؛ close لا يعتمد قبل رؤية posting، وبعد reconciliation الناجح يغلق الفترة متضمناً القيد؛ لا posting بعد وقت commit للإغلاق. |
| `FS-RACE-12 PostVersusClose_CloseWins` | فترة مفتوحة؛ يبدأ close ويأخذ قفل gate أولاً ويعتمد الإغلاق؛ بعد commit يحرر القفل، ثم يحاول post بتاريخ الفترة نفسها. | close ناجح؛ post يرفض `FIN_PERIOD_CLOSED`; لا journal/lines/outbox لعملية post؛ الفترة تظل `closed`. |
| `FS-RACE-13 ReverseReplay` | journal مرحل؛ أرسل reversal command نفسه/key نفسه مرتين بالتوازي ثم retry بعد commit. | reversal journal واحد linked للأصل، الأصل byte/field-wise unchanged، debit/credit reversal balanced؛ كل replay يعيد نفس reversal ID؛ reversal event واحد. |
| `FS-RACE-14 ClosedPeriodReversal` | الأصل posted في فترة مغلقة؛ حاول reverse بتاريخ/فترة مغلقة، دون reopen approval. | يرفض `FIN_PERIOD_CLOSED` أو `FIN_REOPEN_APPROVAL_REQUIRED`؛ الأصل لا يتغير ولا reversal ينشأ؛ لا يجري تعديل posting date إلى فترة مفتوحة تلقائياً. |
| `FS-RACE-15 WrongEntityOrWarehouse` | مستخدم مصرح له بـ E1/W1 يرسل reservation/issue في E2 أو W2 مخفي، مع IDs صالحة فعلاً هناك. | يرفض `SCOPE_MISMATCH`/`AUTH_FORBIDDEN`؛ لا تختلف النتيجة بما يسمح enumeration؛ لا قراءة كمية ولا movement/reservation/audit payload مسرب. |
| `FS-RACE-16 CountVersusReserve` | في `E1/W1/S1/U1`: `OnHand=10`, `ActiveReserved=4`, `Available=6`. بالتوازي ينفذ count adjustment إلى `counted_qty=5` من snapshot صالح، و`ReserveOrder(q=4)`. | إذا commit adjustment أولاً: يصبح `OnHand=5`, `ActiveReserved=4`, `Available=1` ويرفض الحجز `INV_INSUFFICIENT_AVAILABLE`. إذا commit الحجز أولاً: يصبح `OnHand=10`, `ActiveReserved=8`, `Available=2` ويرفض adjustment `INV_ACTIVE_RESERVATION_CONFLICT` وتبقى count discrepancy مفتوحة. لا نتيجة تسمح `ActiveReserved > ReservableOnHand`. |
| `FS-RACE-17 CancelVersusReserve` | لطلب approved revision `v4` دون إصدار، ابدأ `ReserveOrder` وطلب Sales للإلغاء بالتزامن؛ أعد أيضاً تسليم event approval `v4` بعد بدء cancellation. | إذا cancellation version/tombstone سبق الحجز: الحجز يرفض `SOURCE_NOT_APPROVED`، لا reservation، ويثبت Sales الإلغاء بعد تأكيد Inventory عدم وجود حجز. إذا reservation سبق: يدخل Sales `cancel_pending` ولا يثبت `cancelled`؛ يحرر Inventory الحجز غير المصدر مرة واحدة، ثم يؤكد Sales الإلغاء. إعادة approval event الأقدم لا تعيد الحجز. `OnHand` لا يتغير في الفرعين. |
| `FS-CRASH-01 BeforeCommit` | ارمِ process بعد insert business rows وقبل commit للـ DB transaction. | rollback كامل: لا reservation/movement/journal ولا audit intent/outbox event قابل للنشر؛ إعادة الأمر تنفذ مرة واحدة. |
| `FS-CRASH-02 AfterCommitBeforePublish` | دع transaction تحفظ stock/journal+audit+outbox ثم أوقف publisher قبل الإرسال. | mutation موجودة مرة واحدة وoutbox pending؛ بعد restart ينشر بنفس `event_id` مرة قابلة لإعادة التسليم؛ لا يعاد تنفيذ mutation. |
| `FS-CRASH-03 AfterPublishBeforeAck` | انشر الحدث إلى transport ثم أوقف publisher قبل تعليم outbox delivered. | إعادة الإرسال ممكنة بنفس `event_id`; consumer inbox unique يمنع الأثر المكرر؛ لا فقد ولا قيد/حركة ثانية. |
| `FS-CRASH-04 ConsumerRollbackAndRetry` | consumer يقرأ `stock.issued`/`payment.received`; اقطع العملية بعد محاولة business-side effect وقبل commit لـ inbox+side effect. | transaction consumer تتراجع معاً؛ replay يعيد المعالجة مرة واحدة؛ إذا commit حصل، inbox والأثر كلاهما موجودان ولا يعاد الأثر. لا حالة inbox ناجحة مع side effect مفقود. |

للتوقيت النقدي: يضاف `FS-TEST-MONEY-01` بعد توقيع Finance على policy version؛ مدخلات الاختبار هي cases معتمدة وتوقعه ناتج السياسة نفسها. قبل التوقيع لا تُكتب أرقام منازل/half-way أو ضريبة كـ expected business result.

## 9. Orchestration بين الوحدات والتسوية

| المسار | أمر/حدث والمالك | السلوك الحتمي والحدود |
|---|---|---|
| `Sales → Inventory` | `Sales` يعتمد `SalesOrder`/revision ثم يكتب `order.approved`؛ consumer يستدعي `ReserveOrder` | حدث approval لا يخصم مخزوناً. الحجز أمر `Inventory` منفصل؛ مخزون غير متاح ينتج exception/طلب معلّق قابل للتسوية، لا issue ضمني ولا تعديل من Sales. إلغاء الطلب يبدأ `cancel_pending` بversion/event؛ لا يثبت Sales `cancelled` قبل إقرار Inventory بتسوية الحجوزات والإصدارات. يتحقق Inventory من أحدث revision/tombstone؛ event approval أقدم يصل متأخراً لا يعيد حجزها. إذا سبق الحجز طلب الإلغاء، يحرر Inventory غير المُصدر مرة واحدة؛ وإذا سبق الإلغاء الحجز، يرفض الحجز بـ `SOURCE_NOT_APPROVED`. إذا سبق issue، يبقى الإلغاء معلقاً حتى خطة تعويض/مرتجع مصرح بها. `Delivery` لا يصبح ready/released إلا بمراجع الحجز/الإصدار المناسبة. |
| `Inventory → Delivery` | `Inventory` يصدر reservation وينشر `stock.issued`; `Delivery` ينشئ/يحدث custody بمرجع `movement_id` | لا `Delivery` double-decrements. تتطابق الكميات/الوحدة/المستودع/الطلب قبل إعطاء السائق العهدة؛ تأخير event يبقي الحالة غير مكتملة، وإعادة الحدث لا تنشئ عهدة ثانية. |
| `Delivery → Inventory` | `Delivery` يسجل `POD` وaccepted/rejected؛ المرتجع الفعلي يطلب `ReceiveReturn` | POD ليس إرجاعاً للمخزون. `Inventory` فقط يثبت ما استلمه وفحصه؛ الشحنة غير المحسومة تبقى استثناء custody. لا تعالج refund/credit note/commission في Delivery. |
| `Inventory → Projects` | event `stock.issued` يحمل `project_id` أو order line → `Projects` | ينشئ Projects material/cost source reference واحدة لكل `movement_id/line`. يسجل المستعمل/المتبقي/المرتجع/التالف كـ allocation/evidence؛ إعادة مخزون المتبقي بأمر Inventory جديد. لا يعيد Projects إصدار `StockMovement` أو يختار valuation. |
| `Inventory → Finance` | consumer لـ `stock.issued`/الاستلام/التعديل يطابق مصدر الحركة مع valuation/posting policy | بعد اعتماد `OPEN-03/04` فقط يمكن إنشاء journal من policy mapping. قبلها يسجل reconciliation item غير مُسعّر/غير مُرحل، أو يحجب تفعيل التكامل المالي بحسب release gate؛ لا `COGS` أو expense افتراضي. `Finance` authority للقيد، و`Inventory` authority للكمية. |
| `Sales/Projects → Finance` | `contract.signed`, `project.milestone.completed`, invoice/payment source refs | التوقيع أو milestone لا يقرر الإيراد؛ Finance posts invoice/journal وفق source version و`OPEN-03/05`. `cash collected` مستقل عن revenue. لا revenue recognition بحدث quote approval. |
| `Finance → Compensation/Reporting` | `invoice.posted`, `payment.received`, journal/report projections | الاستهلاك deduplicated ومقيد النطاق. أساس commission trigger محجوب بـ `OPEN-01`; فواتير/تحصيلات لا تمنح عمولة تلقائياً. الأرقام التنفيذية drill-down إلى القيود/المصادر وتعرض lag/reconciliation pending. |

### قواعد المطابقة والمصالحة

1. **مخزون تشغيلي:** لكل `(entity, warehouse, SKU, unit)`، إعادة جمع signed `StockMovement` تساوي authority balance. مجموع `StockReservation.remaining` في الحالات النشطة يساوي `ActiveReserved`، و`Available = ReservableOnHand - ActiveReserved`. اختلاف projection لا يصلح authority بصمت؛ يُنتج discrepancy قابل للإسناد وإعادة البناء.
2. **العهدة:** لكل line/issue، يطابق مجموع POD accepted + returned received + unresolved custody إجمالي issued حسب عقد `Delivery`; لا يغلق المشروع/التسليم مع كمية مجهولة. التلف/الفقد يتطلب evidence وقرار حركة/تكلفة منفصل.
3. **مالية المصدر:** لكل `accounting_event_key`, توجد نتيجة posting واحدة أو حالة معلقة/مرفوضة مع سبب؛ source reference، journal، period، approval/policy version قابلة للتنقل في الاتجاهين. `TrialBalance` و`GL` وP&L/Balance Sheet ناتجة من `posted JournalLine` لا من drafts أو events وحدها.
4. **مخزون إلى GL:** بعد اعتماد policies فقط، يطابق snapshot valuation/stock subledger مع حسابات inventory/adjustment/COGS المحددة. قبلها يسجل `OPEN-03/04` كمسبب block ويمنع عرض رصيد مالي على أنه reconciled. لن تُقرر هذه الوثيقة توقيت قيد الاستلام أو الإصدار.
5. **المشروع:** `PRJ-02` يحافظ على فصل `contracted_value`, `recognized_revenue`, `invoiced_amount`, `collected_amount`, `direct_cost` و`estimated_margin`. `actual_margin = recognized_project_revenue - allocated_actual_direct_cost` هو شكل الحساب المطلوب في PRD فقط؛ سياسة اختيار مصدر الإيراد/تخصيص التكلفة/overhead محجوبة بـ `OPEN-05`، لذلك لا تحسب قيمة نهائية دون `policy_version` approved.
6. **إعادة التشغيل:** reconciliation job يقرأ authority ويكتب تقرير discrepancy مع IDs/versions. إعادة بناء projection مسموحة من ledger/events؛ لا يعيد إصدار أمر مالي/مخزني، ولا ينشئ قيوداً تصحيحية من تلقاء نفسه.

## 10. `Proposed` مقابل متطلبات التنفيذ والمراجعة

تُعد `decimal` بدلاً من float، transaction+idempotency، immutable/reversal-based ledger، owner boundaries، scope، event outbox/inbox، عدم خصم الحجز، وتوازن القيود/حماية الفترة ضوابط إلزامية. آليات all-or-none في `ReserveOrder`، in-transit transfer، bucket الجودة، أسماء API/error/event، مفاتيح المصدر، count stale/recount، وعدد locks وترتيبها تفاصيل `Proposed` تستلزم مراجعة Finance/Warehouse/Engineering قبل تثبيتها في schema/API. لا يجوز اعتماد آلية `Proposed` بما يؤدي إلى خرق أي `MUST` أعلاه.

**المراجعات المطلوبة قبل التنفيذ:**

- `Finance`: اعتماد `OPEN-03`، وخريطة source→journal، أساس موازنة/عملة ودقة وتقريب كل عملية، حسابات وأبعاد، posting dates/periods، reversals وreopen rules، وتسوية invoice/payment/inventory/project reports.
- `Warehouse + Finance`: اعتماد `OPEN-04`، وحدات `SKU` والتحويلات وbatch/roll، سياسة المخزون السالب، valuation وحالات الاستلام/المرتجع/التلف، ومصالحة stock subledger مع GL.
- `Finance + Operations`: اعتماد `OPEN-05` وتعريف actual project margin والـ direct cost/overhead والـ recognition triggers.
- اعتماد `OPEN-02` من مالكه قبل تشغيل approval chains/thresholds/self-approval/break-glass؛ إضافة event extensions المقترحة ومراجعة payloads/schema compatibility قبل تنفيذها.

هذه المواصفة لا تمنح `Product Owner acceptance` ولا تغلق أي قرار مفتوح. نتيجة النجاح لهذه المهمة هي عقد قابل للمراجعة والاختبار؛ يظل sign-off وقرارات Finance/Warehouse والأدلة التجارية عملاً متبقياً.

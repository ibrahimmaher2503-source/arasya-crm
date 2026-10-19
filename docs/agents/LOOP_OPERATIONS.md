# تشغيل local task loop

هذه الأداة تنسّق backlog التوثيق فقط؛ لا تنشئ تطبيق Laravel، ولا تثبّت أدوات، ولا ترسل إلى providers، ولا تعتمد سياسة عمل أو ADR. المصدر الوحيد للحالة هو `docs/backlog/tasks.json`. اقرأ هذا الدليل و[بروتوكول التنفيذ](AGENT_EXECUTION_GUIDE.md) قبل claim.

## التقارير read-only

```powershell
& './scripts/task-loop.ps1' -Action Status
& './scripts/task-loop.ps1' -Action Ready
& './scripts/task-loop.ps1' -Action Status -TaskId 'A0-ARCH-900'
```

`Status` و`Ready` لا يأخذان coordinator lock ولا يغيران الملفات. readiness يعاد حسابها من `depends_on` و`decision_dependencies`؛ لا تعرض الأداة task بحالة `blocked` كجاهزة، حتى لو تغيّر دليل قرارها لاحقاً. اختر task واحدة في كل مرة.

## دورة المهمة والأوامر

```powershell
& './scripts/task-loop.ps1' -Action Claim -TaskId 'A0-ARCH-900' -Owner 'finance-stock-worker' -LeaseHours 12
& './scripts/task-loop.ps1' -Action Renew -TaskId 'A0-ARCH-900' -Owner 'finance-stock-worker' -LeaseHours 12
& './scripts/task-loop.ps1' -Action Review -TaskId 'A0-ARCH-900' -Owner 'finance-stock-worker' -Evidence 'المخرجات والملفات والفحوص المنفذة.'
& './scripts/task-loop.ps1' -Action Complete -TaskId 'A0-ARCH-900' -Owner 'finance-stock-worker' -Reviewer 'independent-reviewer' -Evidence 'مراجعة مستقلة ونتائج القبول.'
```

`Claim` يعيد فحص المتطلبات ويكتب status `in_progress` مع lease. `Review` لا يحرر lease. `Complete` يتطلب `review` ودليل handoff ومراجعاً مستقلاً، ويعيد فحص `depends_on` و`decision_dependencies` لحظة الإنهاء؛ الجاهزية عند `Claim` لا تكفي. مهام brief البحثية لا تحمل dependencies قرار، ولا يغيّر `Complete` سجل OPEN أو ADR ولا يحوّل brief إلى اعتماد قرار. إذا أصبح prerequisite غير محلول قبل final state commit، recovery يعيد status إلى `review` ويبقي lease. احفظ فقط ما أُنجز فعلاً؛ لا تسجل command غير منفذ كـ passed.

عند blocker استخدم بيانات قابلة للمتابعة، وابقِ lease حتى release صريح:

```powershell
& './scripts/task-loop.ps1' -Action Block -TaskId 'A0-ARCH-900' -Owner 'finance-stock-worker' -Blocker 'وصف الحاجز' -BlockerOwner 'مالك الحاجز' -NextAction 'أصغر إجراء تالي' -Evidence 'المصدر أو الأثر الملحوظ.'
& './scripts/task-loop.ps1' -Action Release -TaskId 'A0-ARCH-900' -Owner 'finance-stock-worker' -Reason 'سبب إنهاء أو استئناف الحجز' -Evidence 'ما تغير وما بقي.'
```

`Release` يعيد الحالة إلى `ready` فقط إذا اكتملت dependencies والقرارات، وإلا إلى `planned` أو `blocked`. لا يمدد انتهاء lease تلقائياً؛ lease المنتهي يظل حاجزاً حتى يحرره مالكه أو يراجع coordinator الحالة. لا توجد سرقة lease بالتقادم.

إذا انتهى lease سابقاً وبقيت المهمة `blocked` بعد زوال blocker، استخدم `Reassess` بعد التحقق من المتطلبات؛ لا يحتاج lease لكنه يرفض أي lease موجودة، حتى المنتهية:

```powershell
& './scripts/task-loop.ps1' -Action Reassess -TaskId 'A0-ARCH-900' -Owner 'coordinator' -Reason 'سبب إعادة التقييم' -Evidence 'المتطلبات والأدلة التي أُعيد فحصها.'
```

ينقل `Reassess` المهمة من `blocked` إلى `ready` فقط عندما تكون كل dependencies مكتملة وكل decision approvals مقبولة فعلياً. لا يعتمد قرارات ولا يغير سجلاتها؛ عند بقاء أي gate غير محلول لا يغير الحالة. يسجل actor والسبب والدليل في event وhandoff؛ التكرار على `ready` لا يضيف حدثاً.

## تصحيح إغلاق غير صحيح

`Reopen` يصحح `done` إلى `blocked` فقط بعد تفويض مالك النطاق. يتطلب actor وسبباً وblocker ومالكه والخطوة التالية ودليل التصحيح؛ يرفض أي lease للمهمة أو scope متداخل، حتى المنتهية. يحتفظ بكل الأحداث وملفات handoff السابقة ويضيف حدثاً وhandoff جديدين، دون اعتماد قرار أو تعديل descendants تلقائياً. استخدم `Reassess` لاحقاً بعد حل الحاجز وإعادة فحص الجاهزية.

```powershell
& './scripts/task-loop.ps1' -Action Reopen -TaskId 'A0-ARCH-900' -Owner 'coordinator' -Reason 'سبب تصحيح الإغلاق' -Blocker 'دليل قبول غير مكتمل' -BlockerOwner 'مالك الحاجز' -NextAction 'استكمال دليل القبول' -Evidence 'تفويض التصحيح ونتائج المراجعة.'
```

`Reopen` يستخدم journal وhash guards والاستعادة نفسها أدناه؛ لا تعدّل backlog يدوياً لتصحيح الإغلاق.

## معاملات معلقة والاستعادة

كل `Claim` و`Renew` و`Review` و`Complete` و`Block` و`Release` و`Reassess` ينشئ أولاً `docs/governance/loop/pending-transaction.json` ذرياً، وفيه hashes المتوقعة لـ`tasks.json` و`state.json`، وتغيير lease المقصود، وevent وhandoff content/path. بعدها تكتب الأداة handoff، status token عند الحاجة، ثم الحالة النهائية؛ لا يُحذف intent إلا بعد مطابقة الملفات النهائية. يحتوي event المحفوظ على `handoff_path`. هذه عدة ملفات وليست معاملة filesystem ذرية واحدة؛ intent يجعل حدود الانقطاع المعروفة قابلة للتشخيص والاستعادة، ولا يتجاوز تغييرات خارجية مجهولة.

إذا أظهر `Status` قيمة `pending_transaction` أو `recovery=pending-recovery`، لا تبدأ mutation أخرى؛ `Ready` يعرض `currently_ready=0` حتى تُعالج. شغّل الاستعادة فقط بالـtask والمالك المسجلين في intent:

```powershell
& './scripts/task-loop.ps1' -Action Recover -TaskId 'A0-ARCH-900' -Owner 'finance-stock-worker'
```

الاستعادة idempotent وتقبل hashes ما قبل المعاملة أو إحدى نتائجها المحددة فقط. تتحقق من checksum للـintent ومن handoff content؛ أي hash أو محتوى غير متوقع يوقف العملية ويُبقي journal والملفات كما هي للفحص. لا تحذف journal ولا تعدّل الملفات يدوياً لتجاوز الفشل، ولا توجد سرقة lease أو موافقة قرار تلقائية. إذا أصبحت متطلبات `Claim` غير مستوفاة أثناء الانقطاع، `Recover` يتوقف ويبقي intent؛ أصلح السبب أو اطلب مراجعة coordinator قبل المتابعة. إذا أصبح dependency قرار `Complete` غير محلول قبل commit، تُسجل `CompleteCancelled`، ويُستعاد status `review` مع إبقاء lease؛ لا يُثبت `done`.

`RefreshBacklog` تحديث snapshot منفصل عن event في `state.json`؛ replacement لكل ملف hash-guarded وذري، لكن الملفين ليسا معاملة واحدة. إذا انقطع refresh، تحقق من `tasks.json` ثم أعد `RefreshBacklog`؛ يظل `tasks.json` مصدر status.

## القفل والنطاق والحالة المحفوظة

كل mutation و`RefreshBacklog` تنشئ `docs/governance/loop/coordinator.lock` بـ`CreateNew` تحت قفل حصري، وتحذف قفلها فقط بعد إتمام العملية. عند تعارض القفل انتظر ولا تكتب بالتوازي. إذا انتهت العملية قسرياً وبقي القفل، تحقق يدوياً من `owner` و`pid` والوقت وأن العملية غير نشطة قبل أي تدخل؛ لا توجد استعادة أو حذف تلقائي للقفل.

تُحفظ leases وسجل الأحداث في `docs/governance/loop/state.json`، وتشمل owner وtask وpaths/modules ووقت claim/expiry. تعارض أي module أو مسار متداخل (أب/ابن) يمنع claim؛ `parallel_group` ليس إذناً. انتهاء الوقت لا يلغي lease. لا تعدّل `state.json` أو `tasks.json` يدوياً أثناء وجود coordinator؛ إذا فشل hash compare توقف وأعد القراءة وأعد تطبيق delta فقط بعد فهم التغيير.

تغيّر الأداة status token الخاص بالمهمة وحده داخل JSON، وتتحقق من hash قبل atomic replacement؛ تبقي بقية نص JSON دون إعادة تنسيق. الأحداث في state، وأدلة handoff في ملفات مستقلة تحت `docs/governance/loop/handoffs/`. لا تحذف تاريخ الأحداث أو ملفات handoff. سجّل في handoff الملفات والنطاق والنتائج وexact checks والقرارات المتبقية والمخاطر والخطوة التالية.

## مرجع decision_dependencies دون اعتماد آلي

التحقق read-only ويفشل مغلقاً. OPEN لا تصبح معتمدة إلا إذا كان status في صفها `Approved`، ويوجد `docs/governance/decision-approvals/OPEN-xx.md` وفيه كل الحقول التالية، مع دليل منفصل موجود داخل المستودع:

```text
Decision: Approved
Owner: اسم/صفة المالك الذي اعتمد
Approved on: yyyy-MM-dd
Approved option: الخيار المعتمد
Evidence: docs/.../evidence-file.md
Affected tasks: IDs المتأثرة
Tests: الفحوص والنتائج
Review: المراجع/المراجعة
```

ADR لا يمر إلا إذا كان status `Accepted` في كل من `docs/architecture/ADR-INDEX.md` وملف ADR، وبسجل `docs/governance/decision-approvals/ADR-xxx.md` مماثل يستخدم `Decision: Accepted`. لا ينشئ task-loop سجلات اعتماد ولا يغيّر هذه الحالات؛ لا تضف سجلاً أو تغيّر status نيابة عن المالك. سجل brief الأدلة في ملفه المخصص وحده.

## Founder-Led gate model

`docs/governance/gate-policy.json` is the coordinator's deterministic gate contract. Tasks default to `DEVELOPMENT`; only explicit task entries may use founder-authorized unresolved decision evidence for bounded synthetic/local work. `INTEGRATION` and `PRODUCTION` never inherit that exception. The policy does not remove dependencies, alter decision records, or create independent review; Ibrahim cannot review his own decision. Reassess remains the only status transition path.

## لقطة BACKLOG والفحوص

جدول الحالات في `docs/backlog/BACKLOG.md` لقطة مولّدة من `tasks.json`؛ `tasks.json` هو المصدر. حدّث اللقطة بعد تغييرات الحالة:

```powershell
& './scripts/task-loop.ps1' -Action RefreshBacklog
```

يفشل refresh إذا لم يجد صفاً وحيداً لكل task أو تغيّر hash الملف أثناء العملية.

نفّذ هذه الأوامر في Windows PowerShell 5.1 من جذر المستودع قبل handoff:

```powershell
& './scripts/test-task-loop.ps1'
& './scripts/validate-docs.ps1'
& './scripts/task-loop.ps1' -Action Status
```

الأول يستخدم fixtures مؤقتة خارج backlog الحقيقي ويتحقق من القفل والتداخل والاعتماديات والقرارات والانتقالات وUTF-8. الثاني يتحقق من JSON وDAG والتغطية وروابط Markdown، ويقبل `ready` بقرار فقط مع سجل اعتماد ودليل صحيحين. الثالث تقرير read-only. لا يوجد Git في النسخة الحالية؛ لا تهيّئه لأجل التشغيل ولا تتجاوز اختلافات أو تغييرات متزامنة.

## ADR-002/003 architecture-stage gate

The [owner-authorized staged amendment](../governance/CHANGE_CONTROL.md#adr-002003-staged-acceptance-amendment--2026-10-17) applies only to ADR-002/003. Matching Accepted states and all existing evidence fields remain required, plus Acceptance stage: Architecture, Implementation scope: Bounded local only and named, dated Architecture ROLE approval fields. ADR-002 requires Engineering, Finance, Operations; ADR-003 requires Engineering, IT, Security, Legal, Operations. Pending/missing approvals fail closed. No other ADR/OPEN gate changes. Reassess uses this shared verifier; local task eligibility/completion does not grant application or production acceptance, deployment, real integrations, real data use or irreversible operations. Mandatory downstream role/policy approvals remain as defined by the amendment.

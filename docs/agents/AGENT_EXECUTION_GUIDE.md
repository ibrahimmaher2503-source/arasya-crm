# بروتوكول Loop

دورة المواصفة والتنفيذ موثقة في [تكامل Spec Kit](SPECKIT_INTEGRATION.md)، مع مسار خفيف
للتغييرات الصغيرة. قبل التنفيذ سجّل Test Design، وطبّق RED → GREEN → REFACTOR لمنطق
الأعمال المناسب وللأعطال القابلة لإعادة الإنتاج. لا تتجاوز عبارة tests optional في قالب
عام هذه القاعدة. وثّق سبب عدم انطباق TDD على تعديل غير سلوكي وفحصه البديل.

1. اقرأ `../../AGENTS.md` أولاً، ثم [النطاق](../product/SCOPE.md) و[المهام](../backlog/tasks.json) و[القرارات](../governance/OPEN_DECISIONS.md) وملفات instruction الموجودة وقت التنفيذ. اكتشف واقرأ المهارات ذات الصلة قبل التنفيذ؛ لا تخترع مساراً أو اسماً غير موجود. افحص git status إن وجد دون init/reset، وسجل الملفات غير المملوكة لك. لا scaffold قبل اعتماد بوابة التصميم والstack.
2. اختر task واحدة ready: كل depends_on done مثبتة وكل decision_dependencies Approved بدليل؛ status المحفوظ قد يصبح قديماً لذلك أعد الحساب. لا تختَر blocked أو تفسر عدم وجود blocker دليلاً على اعتماد.
3. coordinator واحد فقط يعدل `tasks.json`. استخدم [أوامر loop المحلية](LOOP_OPERATIONS.md): `Status` و`Ready` read-only؛ `Claim` ينشئ coordinator lock وlease بـCreateNew ويحفظ owner/task/scopes/time/expiry/hash في `docs/governance/loop`. إذا القفل موجود انتظر ولا تكتب بالتوازي. lease المنتهي لا يبيح السرقة؛ تحقق من صاحبه أولاً.
4. preflight: اقرأ الملفات قبل edit، قارن hash الحالي بالسابق، تحقق overlap مع كل lease. قبل كتابة أي test code قيّم delegation صراحة؛ dispatch فقط أعمالاً مستقلة بملكية ملفات وfixtures وruntime منفصلة، واستخدم worktree معزولاً عند الحاجة. parallel_group مجرد ترشيح لا إذن؛ أي scope متداخل أو shared migration/router/package/config يمنع التوازي. سجل سبب عدم delegation إذا كان غير آمن أو غير متاح. تقسيم المهمة إذا تجاوزت جلسة أو تطلبت أكثر من aggregate مستقل.
5. حوّل إلى in_progress تحت lock، نفذ داخل scope فقط؛ لا تعديل مباشر لmodule غير مالك، لا assumption hidden. عند اكتشاف requirement غامض توقف في الجزء المتأثر واطلب قرار مالكه، لا توقف أعمالاً مستقلة.
6. شغّل checks المتفق عليها وسجل exact commands وexit/environment/artifact. إذا command غير موجود لأنها مرحلة مبكرة سجله not-run وخذ مهمة scaffold المناسبة، لا تضع passed. فشل unrelated يسجل ولا يصلح دون طلب.
7. handoff: changed files، refs، acceptance evidence، checks passed/failed/not-run، pending decisions، residual risks، next ready tasks. راجع diff مع reviewer ثم done؛ لا self-approve business policies.
8. استخدم task-loop لتغيير الحالة: يعيد قراءة JSON وhash تحت lock، يحدّث status token وحده، ويتحقق من DAG/status ثم atomic replace بشرط hash unchanged. عند stale hash توقف وأعد القراءة وأعد تطبيق delta فقط بعد فهم التغيير؛ لا overwrite لتغييرات مجهولة. شغّل validator الكامل للروابط وcoverage قبل handoff. احتفظ بأدلة handoff منفصلة، وحرر lease بأمر `Release` فقط بعد التسليم أو إنهاء موثق.
9. لكل capability أعمال جديدة/متغيرة حدّث [سجل tool coverage](../engineering/AI_TOOL_COVERAGE.md) داخل task module نفسها: عقد typed/versioned إلى نفس ApplicationService، permission/scope، الاختبارات، registry allowlist، detector assessment أو `N/A` مسبب. ميزة غير مدعومة/حساسة/غير جاهزة تبقى `Restricted/disabled`؛ schema وحدها ليست readiness، ولا generic execute أو auto-discovery. اتبع [معمارية المساعد](../architecture/AI_ASSISTANT_ARCHITECTURE.md). لا تغيّر PRD/ADR/DAG أو تعتبر الوثائق موافقة تفعيل.

Lifecycle: planned → ready → in_progress → review → done؛ أي حالة تنفيذ → blocked مع blocker owner/action/evidence؛ بعد الحل إلى planned ثم readiness check. لا unblock descendants اعتماداً على مجرد انتهاء predecessor: يجب done وموافقة القرارات. قدم blocker مختصراً مع أصغر خطوة؛ لا retry بلا حد أو توسيع نطاق تلقائي.

Handoff evidence additionally identifies requirement → backlog task → test/evidence,
the actual RED/GREEN results, each worker's exact files/worktree/runtime, independent
review findings, integration verification and newly ready tasks. Use gpt-6-luna / xhigh
for dispatched workers; report runtime unavailability without silent fallback.
The coordinator serializes shared fixture/database mutations. Existing failed gates
remain failures; an in-memory or local synthetic pass never establishes production readiness.

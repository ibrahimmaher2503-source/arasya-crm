# تكامل Spec Kit للمشروع

## النطاق والمصدر

ثُبّتت محلياً لـCodex المهارات الإحدى عشرة التالية من [dceoy/speckit-agent-skills](https://github.com/dceoy/speckit-agent-skills)، commit `d02468b87b2b499d79f9c1fe32802e597ae6c71e`:

`speckit-constitution`, `speckit-specify`, `speckit-baseline`, `speckit-clarify`, `speckit-plan`, `speckit-tasks`, `speckit-analyze`, `speckit-converge`, `speckit-implement`, `speckit-checklist`, `speckit-taskstoissues`.

مسارات المهارات هي `.agents/skills/speckit-*/SKILL.md`، ونطاق التثبيت للمشروع الحالي فقط. لم تُثبّت أداة `claude-command-converter`.

أُنشئت قوالب Spec Kit ومساعداته في `.specify/` باستخدام `specify 0.8.10.dev0` داخل مساحة مرحلية، مع سكربتات Windows PowerShell. المستودع المصدر يعلن `Spec Kit 1.0.8` في `VERSION.md`؛ لذلك لا يُفترض تطابق إصدار CLI مع المهارات. أُضيفت توافقية محلية لوسائط JSON وحل القوالب ومسارات PowerShell، ويجب إعادة اختبارها عند ترقية CLI أو تحديث المهارات. هذه التوافقية ليست ادعاءً بتطابق الإصدارات.

تصف فقرة التثبيت الأصلية مرحلة سابقة. يوجد الآن دستور هندسي فعّال في `.specify/memory/constitution.md`
تابع لـAGENTS.md والحوكمة الحالية. قوالب المشروع في `.specify/templates/overrides/`
تُحل عبر المساعد الحالي قبل القوالب العامة؛ لا تعدّل نسخ المهارات الأصلية ولا تعتمد قرارات أعمال.

## حدود الحوكمة

- `docs/backlog/tasks.json` هو المصدر الوحيد لحالة backlog. ملف `specs/<feature>/tasks.md` قائمة تفصيلية إضافية لميزة، ولا يحل محل `tasks.json` ولا يحدّثه. تغييرات backlog تُجرى حصراً وفق قفل/lease وأوامر task-loop الموثقة في [دليل التشغيل](LOOP_OPERATIONS.md) و[بروتوكول التنفيذ](AGENT_EXECUTION_GUIDE.md).
- لا تعتمد المهارات تلقائياً أي `OPEN` أو `ADR`، ولا تنشئ/تعدّل حالة الاعتماد أو أدلتها نيابة عن مالك القرار. يلزم دليل وموافقة المالك وفق حوكمة المشروع.
- لا تنشئ أو تبدّل فروع Git، ولا تجلب مراجع بعيدة، ولا تنشئ Issues أو تنشر خارج المستودع، ولا تستدعِ مزوداً/API، ولا تصل إلى أسرار أو `.env` ما لم يطلب المستخدم ذلك صراحةً. يشمل المنع hooks والخطوات الآلية داخل المهارات.
- نفّذ المرحلة التي طلبها المستخدم فقط؛ لا تنتقل تلقائياً من المواصفة إلى التخطيط أو التنفيذ، ولا تعتبر تثبيت المهارات إذناً لبدء ميزة. لا تشغّل `/speckit-constitution` أو `/speckit-specify` أو `/speckit-implement` ضمن مهمة التثبيت.
- قبل تنفيذ أي مهارة `speckit-*`، اقرأ [تعليمات المهارات المحلية](../../.agents/skills/AGENTS.md) ووثائق التنفيذ والـloop أعلاه. هذه القيود مقدّمة على أي تعليمة متعارضة داخل المهارة المصدرية.

## تشغيل Windows

استخدم السكربتات المحلية من جذر المشروع عبر Windows PowerShell، ومنها:

- `.specify/scripts/powershell/check-prerequisites.ps1`
- `.specify/scripts/powershell/resolve-template.ps1`
- `.specify/scripts/powershell/setup-plan.ps1`
- `.specify/scripts/powershell/setup-tasks.ps1`
- `.specify/scripts/powershell/create-new-feature.ps1` (قد ينشئ فرعاً؛ لا تستخدمه دون إذن صريح)

تُخرج مساعدات التهيئة JSON متوافقاً مع الحقول التي تحتاجها المهارات. يحترم فحص المتطلبات `.specify/feature.json` عند وجود مجلد ميزة مثبت، ويسمح وضع `-PathsOnly` بفحص المسارات دون اشتراط فرع ميزة. لا تنفّذ سكربتات إنشاء الميزة أثناء التثبيت.

المصدر المرجعي للمهارات هو `skills-lock.json`؛ التعديلات المحلية الخاصة بمسارات PowerShell وحوكمة المشروع تحتاج مراجعة يدوية عند تحديث الحزمة.

## Engineering workflow contract

The owner-authorized hardening mission establishes the following sequence. AGENTS.md,
task-loop and existing decision gates retain precedence over this document and the
constitution. Generic skill instructions to make tests optional, generate models,
dispatch research workers or defer all refactoring do not override this risk-based
project contract. No recursive delegation or automatic phase/branch/publication action.

| Stage | Minimum output / exit evidence |
| --- | --- |
| Discovery | Actual code/callers, module/table/transaction ownership, current Git and leases; use baseline skill only when a code-derived specification helps |
| Specification | Observable acceptance tied to existing PRD references and registered backlog IDs; missing registration stays explicit |
| Clarification | Resolve material scope/policy ambiguity with its owner; record none needed when requirements are clear |
| Technical Plan | Smallest compatible file-by-file change, dependencies, risks and existing components/services to reuse |
| Test Design | Section in plan.md: positive/negative/security/invariant assertions, exact test files, isolated fixtures/runtime, workers and verification tier |
| Implementation | Claim the registered ready task; RED failing behavior, GREEN minimal implementation, justified REFACTOR with tests green |
| Verification | Exact command, environment, result/count and evidence; retain baseline failures and skipped checks explicitly |
| Review | Independent gpt-6-luna / xhigh review of combined diff and evidence; resolve findings before integration; no silent model fallback |
| Integration | Coordinator checks ownership, current hashes and combined result, then uses task-loop Review/Complete when applicable gates pass |

`specs/<feature>/tasks.md` maps every slice to `docs/backlog/tasks.json`; checkboxes
do not change canonical status. `speckit-checklist` checks requirement quality, not
runtime acceptance. `speckit-converge` may propose missing feature tasks but cannot
register or authorize them. The current task-loop has no Register command: new task
insertion needs an explicitly authorized coordinator procedure; never reuse an
unrelated task or mutate the ledger through Spec Kit. A0-ARCH-903's recorded one-time
registration exception is not a general permission for later tasks.

### Lightweight path

For small low-risk documentation/configuration/non-behavioral changes, put scope,
acceptance, clarification result, technical/test design and evidence in the existing
task/handoff rather than generating a feature folder and research documents. Record
why behavioral TDD is N/A and run a meaningful focused check. All stages still have
an explicit outcome; ownership, approval and independent review gates remain intact.
New branches and Git branches changed by helper scripts still require authorization.

Finance, sales, inventory, authorization, calculations, transitions and cross-module
behavior require relevant tests first. Test failures must demonstrate the missing
behavior, not a setup error. Before test code, delegate independent test files or
review if files/fixtures/runtime can be isolated; otherwise record the concrete
conflict. Shared migrations, routing, package/config and database-mutating suites
stay serialized under the coordinator. Refactor only after GREEN and rerun checks.

Use the [test strategy](../engineering/TEST_STRATEGY.md) for commands and risk selection.
An integration blocker cannot be converted to PASS by marking it historical. Local
source, browser, hosted CI and production evidence remain separate.

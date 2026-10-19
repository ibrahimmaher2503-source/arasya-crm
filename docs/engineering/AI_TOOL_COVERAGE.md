# سجل تغطية أدوات المساعد والذكاء التشغيلي

## CR-CYCLE03-DEV-001 — CRM application candidates

`CRM-01.leads.v1`, `CRM-02.assignment.v1`, `CRM-03.taxonomy.v1`,
`CRM-04.activity.v1`, `CRM-05.handover.v1`, `CRM-06.conversion.v1` describe
the typed/versioned `LeadDirectory` human application contracts in
[Cycle03 checkpoint](../governance/evidence/cycle03-persistence.md).
All assistant discovery/invocation remains **Restricted / disabled**. No tool
handler, registry allowlist, provider connection or automatic action is enabled.

Core grants: crm.read/write/manage/assign/taxonomy, separate contact.reveal with
explicit reason and audit. Company/branch/department and current assignment are
filtered before projection; lists always mask contacts. Unknown fields and stale
versions reject mutations. Persisted sessions/grants are reloaded inside the
company transaction lock. Core tests: LeadSecurityTest and core LeadWorkflowTest;
executed results are in the checkpoint, never inferred from this catalog entry.

Activity/transfer/conversion passed independent CRM014 source/regression review.
CRM015 adds authenticated HTTP using the same service; browser integration remains
deferred. [Phase2 evidence](../governance/evidence/cycle03-http.md) records actual
HTTP/security checks and bounded scoped pages. All assistant tools remain disabled;
no new registry or provider activation. Grants crm.write for
activity and named-recipient response, crm.transfer for initiation, crm.convert
for conversion; recipient-only acknowledgement,
original ownership until acceptance, safe self-reminder through Tasks, and
duplicate-safe Customer identity without fabricated downstream Sales records.
Proactive detector assessment: N/A for this local slice; deterministic follow-up
Tasks are explicit human requests, no AI scanner or notification delivery.
Provider/retention/private attachments remain separately gated.
Current focused CRM014 evidence: 25 tests/239 assertions on isolated MariaDB;
not consolidated acceptance, no enabled assistant adapter or full-cycle VERIFIED.

## CR-CYCLE02-DEV-001 employee candidate

`EMP-01.directory.v1` and `EMP-02.attribution.v1` use the Employees-owned
`EmployeeDirectory` application service, tracked by A1-EMP-005. Persisted session
authorization uses the existing Identity catalog: `employees.read`,
`employees.write`, `employees.manage_all`, `employees.evidence.write` and separate
`employee.private`. Company, branch, department and supervisor/self scope apply
before list counts or projection. Task evidence links resolve through the Tasks
application service; no non-login worker impersonation or cross-module writes.

AI registry/discovery/invocation remain **Restricted / disabled**: no handler or
provider is enabled. These names document versioned application capabilities,
not enabled assistant tools. Detector assessment: N/A for this persistence
slice; personnel decisions and offboarding cannot run automatically. Unknown
fields, scope denials, private masking, stale versions, account links and actual
actor attribution are covered by the candidate EmployeePersistenceTest and
EmployeePersistenceSecurityTest; executed status is recorded separately in
[Cycle02 evidence](../governance/evidence/cycle02-persistence.md). UI/browser,
Documents, performance and offboarding acceptance remain pending.

## CR-TASK-DEV-001 bounded development implementation

`TASK-01.lifecycle.v1` is the owner-approved DEVELOPMENT slice tracked by
`A1-TASK-003`: create/start/private evidence/complete use the Tasks-owned
`TaskCycle` application service and existing `ManageTasks` domain transitions.
Read/write permissions are `tasks.read`/`tasks.write`, bound to session entity,
department and owner/assignee. A1-CORE-018 adds an explicit `tasks.assign` grant
for human creation/reassignment through the same service; no implicit administrator
grant. Eligible recipients are active in the same company/branch/department and
have effective `tasks.read`/`tasks.write`, including role grants and user denies.
Every accepted mutation requires an idempotency key and audit receipt.
Implementation and executed evidence are recorded in
[Tasks acceptance](../governance/evidence/tasks-cycle-acceptance.md).

`TASK-01.assignment.v1` describes `TaskCycle::assign(actor, taskId, assigneeId,
expectedVersion, idempotencyKey)`: locked current-session write/assign authority,
existing owner/assignee scope, immutable creator ownership, task version and
target-bound replay. Permission-role editing continues through `AccessControl`;
contact disclosure reuses `contact.reveal`, scoped lead reads and audited reason.
See [role permission integration](../governance/evidence/role-business-permissions.md)
and `TaskAssignmentPermissionsTest` / `RoleBusinessPermissionsTest` for executed
acceptance. All assistant discovery, invocation and registry allowlisting for this
contract remain **Restricted / disabled**. Detector assessment: N/A, explicit human
assignment only; no scanner, notification provider or automatic reassignment.

`TASK-01.start-preview.v1` remains **Restricted / runtime execution disabled**.
Task-local deterministic preview and confirmation handler code can be tested in
isolation with `tasks.assist` plus write authority, source version, actor/scope,
expiry and replay checks, calling the same TaskCycle service. HTTP confirmation
returns `503 / shared_ai_gateway_blocked`, never dispatches the handler, and
cannot activate a provider or central tool. No central allowlist registration is
claimed. Shared gateway, server-stored confirmation/policy context, registry,
approval integration and A5-AI-015/016 dependencies remain outstanding. The full
AI-native cycle is NOT VERIFIED by these local handler tests. Detector assessment:
N/A for this bounded user-requested start preview; no scheduled detection,
notification, escalation, reminder or policy threshold is activated.

> **سجل متطلبات مستقبلية، لا سجل تنفيذ حالي.** تواريخ/حالات الأدوات الحالية غير موجودة: لا أدوات ERP قابلة للتفعيل الآن. كل صف أدناه يبدأ `proposed` أو `restricted/disabled`; لا يُستنتج `implemented`, `enabled` أو `tested evidence` من schema أو هذا المستند. يحافظ السجل على PRD وSCOPE وDAG و`ADR-007` المفتوح؛ لا يعتمد scope/ADR ولا يطلب تفعيل provider. المرجع التفصيلي [AI Assistant Architecture](../architecture/AI_ASSISTANT_ARCHITECTURE.md).

## 1. قاعدة التغطية الملزمة

### Phase 1 capability map

The expanded Phase 1 Action Assistant scope reuses the existing task pairs: bilingual text and voice/STT surfaces plus governed tool discovery/execution are `A5-AI-015/016`; deterministic ERP reads, analytics and explanations are `A5-AI-001..006`; protected preview, confirmation and Approval Engine flow are `A5-AI-007/008` and `A5-AI-015/016`; proactive receivables, inventory, projects, tasks, approvals, deliveries and anomaly detection are `A5-AI-009/010` with the corresponding domain read-model prerequisites; findings, evidence links, inbox and notifications are `A5-AI-005/006`; document retrieval with authorization is `A5-AI-011/012`; audit, idempotency, replay, provider gateway, budget, kill switch and controlled failures are `A5-AI-013/014` and `A5-AI-017/018`. These mappings expand Phase 1 scope without changing task IDs or enabling providers.

1. **كل قدرة أعمال مدعومة موجودة أو جديدة أو متغيرة** تسجلها الوحدة المالكة هنا، بصف/معرف coverage محدد، قبل إعلان اكتمال المهمة. إن كانت capability قابلة لتفاعل المساعد: عقد tool versioned/typed + handler adapter إلى **نفس module-owned ApplicationService** الذي تستعمله UI/API، permission mapping واختبارات وإدخال registry المركزي. إن كان كشفاً استباقياً مناسباً: assessment موثق لقاعدة/event/scheduler/المستفيدين/الدليل/الخصوصية وtests أو `not applicable` مسبب. لا تجميع عدة ميزات متميزة تحت صف عام لإخفاء فجواتها.
2. يجب أن تحتوي كل entry: module owner، source feature/PRD ID والقدرة الدقيقة، classification، contract/version أو سبب الاستثناء، handler/service reference، permission/scope/field projection، preconditions/source version، confirmation/approval/idempotency/limits، input/output schema، registry allowlist key، detector/evidence/recipient policy أو سبب N/A، tests ومعرفات الأدلة، status، approver/enablement evidence عند الحاجة.
3. Definition of Done لكل **module feature task** يفرض تسليم coverage في نفس التغيير؛ لا backlog mutation أو task IDs جديدة مطلوبة بهذا المستند. UI-only change يربط tool/coverage entry قائم أو يشرح لماذا لا ينشئ capability أعمال جديدة (`N/A` مع rationale). تغطية feature ليست ملحقاً مؤجلاً لـA5.
4. دورة status: `proposed` → `implemented-disabled` (كود contract/adapter/service bridge + permission/policies + tests + allowlist entry موجود، لكن غير مكشوف/قابل للتنفيذ للمستخدم) → `enabled` (بعد موافقات النطاق والأمن والسياسة والتفعيل الصريح) → `tested-evidence` (enabled-scope المعتمد واجتاز اختبارات التفعيل المحددة مع evidence/version/date/owner). `restricted/disabled` حالة دائمة حتى إزالة السبب بموافقة مالك. `tested-evidence` لا يعني أن إجراء خارجياً/إنتاجياً صالح إن لم يشمله الاختبار.
5. لا feature `assistant-ready` من schema-only. لا تُفعّل الأدوات عبر reflection/autodiscovery/plugin discovery؛ registry allowlist مركزي capability availability فقط. module owner يكتب handler وtests داخل وحدته. لا يملك assistant business logic.
6. كل feature غير مدعومة/مؤجلة أو حساسة/غير منفذة لها `Restricted/disabled` contract/entry يبين reason/gate/owner، أو exclusion موثق صريح مع PRD scope reference. لا generic unsafe execute لملء الخانة. لا توسيع procurement/RFQ/full HR/workshop/MRP/voice-autonomy.
7. read tools تخضع أيضاً لنفس permission/scope/masking/logging، وإعادة التفويض قبل query counts/pages/aggregates/export/history/notification. تمنع النتائج الجانبية من enumeration. التغطية لا تسمح بصلاحية أعلى من UI/API.

## 2. سجل feature coverage حسب module

كل الأوصاف هي capabilities من PRD/MODULE_CATALOG/SCOPE؛ أنواع العقود/الأدوات هنا مرشحة وليست أسماء registry مفعلة. حيث لم يعرف PRD سياسة رقمية، تبقى `OPEN` ويفشل التنفيذ مغلقاً. لم تُنفذ هذه الاختبارات (`NOT RUN`).

| الوحدة / المالك | الميزات التي يجب تغطيتها واحدةً واحدة | قدرات assistant المرشحة / التصنيف الابتدائي | كشف استباقي وقيود التغطية | الحالة الحالية |
|---|---|---|---|---|
| Identity / Identity | أدوار/permissions/inheritance/employee overrides؛ scoped record access؛ revoke sessions/tokens/offboarding | `Read` للصلاحية الذاتية الحالية فقط بعد وجود identity حقيقي؛ role/config/revoke `Restricted` | كشف revoke يوقف queued/history/proactive access؛ actor server-derived، لا تداة لتعديل role/permission | `restricted/disabled`: الموجود SEC01 in-memory guard فقط؛ لا authentication أو query scope أو branch support |
| Organization / Organization | company/entity/department/branch/site/warehouse/currency/unit/status/numbering refs؛ cross-reference validation | `Read` lookup allowlisted؛ تعديل التنظيم/config `Restricted` | entity-scope mismatch مرفوض؛ لا يكشف وجود entity | `proposed`; CORE01 reference checks فقط، وليس branch/department query permission |
| Workflow / Workflow | approval request/state/decision/delegation/expiry/history | قراءة الطلبات المسندة و`Draft` طلب موافقة؛ قرار approve/reject `Approval-required` بفاعل مخول مستقلاً عن assistant | stalled approvals حسب due rules المعتمدة؛ no autoapprove؛ SoD/version/policy recheck؛ thresholds/roles/SLA ما زالت مفتوحة | `restricted/disabled`; no approval service implemented |
| Audit / Audit | business audit event/history/search access & AI audit finding | `Read` scoped sanitized history؛ إنشاء finding advisory فقط؛ تعديل/حذف audit `Restricted` | findings لها evidence/version/reviewer؛ logs لا raw PII/prompts/secrets | `proposed`; durable audit غير موجود في foundation المرصودة |
| Documents / Documents | upload/quarantine/scan/version/link/download/signature/export | `Read` metadata/reference scoped؛ `Draft` upload intent لاحقاً؛ download/reveal/delete/sign `Restricted` بحسب scope/policy | نقص evidence/attachment risk فقط عند records مصرح بها؛ file contents untrusted؛ download/export reauth؛ لا public URL | `restricted/disabled`; no real document services/migrations |
| CRM / CRM | lead/customer library/import/archive/dedup; assignment; status/activity/timeline; follow-up; handover request/ack/reject; conversion/linkage | read authorized lead/customer masked; `Draft` activity/follow-up/handover؛ handover acceptance `Write` بتأكيد المستلم؛ contact reveal `Restricted` | missed follow-up، lost opportunity/lead stalls، duplicate/anomalous attribution؛ لا contact reveal عام أو transfer قبل acknowledgement | `proposed`; service/authz/masking غير مكتملة |
| Tasks & Calendar / Tasks | assigned tasks/reminders/calendar appointments/calls/site visits/deadlines/reschedule/completion evidence/escalations | read own/assigned; `Draft` task/reschedule؛ complete مع explicit confirmation/evidence | overdue/upcoming workload rules من policy، لا إنشاء أوقات/quiet periods مفترضة | `proposed`; no task domain service evidence |
| Performance / Performance | individual/department targets; metric/unit/period/threshold/weight; approved-event result; trajectory/history/evaluation | read scoped target/snapshot؛ target configuration `Restricted`; no KPI mutation based on model suggestion | lagging trajectory/missed follow-ups/deadline alerts snapshot-linked؛ missed target لا يخصم راتباً آلياً | `proposed`; HR sensitivity and metric policy gates |
| Sales / Sales | quotation draft/review/approval/send/accept/reject/expire/revise/cancel; contracts/signatures/amendments; supply/work orders; quote-to-sale pipeline | `Read`/`Draft` quote or revision؛ send/commit/approval/order transition `Approval-required`/`Restricted`; no revenue or issue at quote approval | unusual discount/quote-delay/stalled order/commitment anomalies only against approved policy/version; no invented discount basis | `proposed`; no Sales service |
| Projects / Projects | project/site/team/schedule/milestones/budget/resource/work order; progress/evidence/variance/cost; custody reconciliation; closure preflight | scoped `Read`; `Draft` note/progress/change request; budget/close/commit `Approval-required`; no direct stock issue or margin edits | delay/cost/blocker/milestone risk, grounded in evidence; actual margin waits OPEN-05 recognition/allocation policy | `proposed`; no projects service; maintenance work types do not authorize a standalone workshop module, which remains excluded |
| Inventory / Inventory | SKU/warehouse/balance; movement/receipt/reservation/issue/transfer/return/damage/count/reconcile/valuation/alert | scoped `Read`; `Draft` count/receipt/reservation request; issue/adjust/release `Restricted` or approval-required; no on-hand mutation from assistant | low stock/overstock/aging/expiry **only if tracked and policy set**; no invented reorder/expiry/legal thresholds; OPEN-04 valuation/unit/negative policy | `restricted/disabled`; no inventory services/migrations; foundation only module catalog |
| Purchasing / غير موجود كمالك module حالي | مرشح مستقبلي فقط: تأخر الموردين وموافقات طلبات الشراء المعلقة؛ لا procurement/RFQ أو دورة شراء ضمن النطاق الحالي | `Restricted/disabled`; أي tool/action مستبعد حتى اعتماد scope procurement منفصل وتنفيذ خدماته وسياساته | detector مرشح فقط لتأخر المورد/موافقة معلقة، بعد اعتماد scope منفصل ووجود services/evidence مخولة؛ لا task أو PRD تغيير ضمن هذا السجل | `FUTURE EXCLUDED / Restricted`; لا module/service/task/PRD capability معتمدة |
| Delivery / Delivery | dispatch assignment/custody; delivery note/schedule; proof signature/photo/quantities/discrepancy/return | `Read` assigned delivery; `Draft` dispatch/exception; confirm custody/POD only authorized assignee + explicit action; no stock adjustment | delayed delivery/missing signed proof/accepted-rejected discrepancy from source versions; never expose customer destination beyond necessary masked field | `proposed`; no delivery service |
| Quality / Quality | inspection/checklists/measurements/nonconformity/corrective action/acceptance/warranty-ready evidence | `Read` scoped inspection; `Draft` inspection/corrective action; acceptance/signoff `Approval-required` | QA failure/incomplete evidence/repair diagnostics; customer approval captured as separate authorized evidence; no portal assumption | `proposed`; QA-02 is warranty-ready record only, not customer portal |
| Production / Production | explicitly limited production output/material consumption if policy requires | `Read` output/consumption; `Draft` limited record only after OPEN-06; `Write` restricted and invokes inventory service, never independent stock ledger write | input/output/waste/return reconciliation only after units/cost rules approved; excludes MRP/BOM/routing/machine planning | `restricted/disabled`; OPEN-06 unresolved, no broad manufacturing |
| Finance / Finance | COA/account dimensions; expense; invoice/receipt/payment allocation; cash/bank reconciliation; partner ledger; journals/post/reverse/period close; statements/reports | authorized `Read` projection; `Draft` expense / reconciliation proposal; post/pay/reverse/close `Restricted` and separate approval; no AI posting/payment | duplicate expense, invoice/collection risk, reconciliation anomaly, receivable aging only with approved finance rules; balanced ledger, policy OPEN-03; no float | `restricted/disabled`; no Finance implementation; EGP only, FX inactive/unresolved in review direction |
| Employees / Employees | employee profile/admin docs/reporting/targets/evaluation; supervisor attribution for non-login workers; offboarding work reassignment | least-field `Read` profile; `Draft` task reassignment/request; personnel change/doc reveal `Restricted` | staffing/admin exceptions only for existing admin profiles; privacy/OPEN-10; no inferred attendance/full HR/biometrics or personnel decision automation | `restricted/disabled`; full HR scope excluded; no service |
| Compensation / Compensation | simple salary summary; commission eligibility/accrual/reversal/statement; approved bonus/advance/deduction; payroll summary | `Read` authorized summary; `Draft` commission review/deduction request; payroll/post/payout/deduction `Restricted`/approval-required; no automatic pay/deduction | commission anomaly/cancelled eligible sale and collection-risk review; OPEN-01 basis/rates and OPEN-03 accounting; no statutory rates | `restricted/disabled`; no compensation service; no commission policy approved |
| Communications / Communications | internal message/workspace; corporate email; SMS; optional Telegram; WhatsApp decision/connector; capture/history/masked destination/failure retry | read authorized internal thread; `Draft` internal message; external sends disabled until channel gates/owner permission; no user-supplied raw destination | delivery failures/unknown/retry reconciliation; accepted ≠ delivered; contact masking in headers/quoted content/logs/previews/export; egress approvals separately | `restricted/disabled`; no real provider; external channels/gates OPEN; push fake-only suggestion |
| Reporting / Reporting | KPI dashboards/role views/drilldown/reports/filters/exports/freshness/source reconciliation/alert center | `Read` scoped projection/report; `Draft` saved filter only if business feature exists; export requires same field-level permission and confirmation; never authoritative mutation | cross-module risk/collection/project delay only from authorized projections with source refs; every count/aggregate/export scope-filtered server-side | `proposed`; no business dashboard service; no model-made metrics |
| AI / AI | deterministic guard; AI priority audit; explainable finding; human disposition; scheduled/continuous review; data minimization; version/cost/evaluation; Phase 1 typed commands and voice/text surfaces | advisory finding outputs per ADR-007 contain no tools; governed action proposal/execution remains confirmation/approval gated; `Restricted` action execution | stalled review/duplicate/variance checks via deterministic events/rules; stale finding invalidates; evaluations/kill switch/cost limits OPEN | `restricted/disabled`; no tool registry/provider; ADR-007 OPEN |

### 2.1 خريطة PRD feature-by-feature

كل معرف أدناه صف coverage مستقل إلزامي عند تنفيذ/تغيير capability المقابلة؛ أسماء الأدوات illustrative وليست registry APIs موجودة. الحالات كلها `proposed` أو `restricted/disabled`، وقرارات الأموال/الأدوار/الاحتفاظ تظل OPEN. يجب أن يحتوي سجل التنفيذ الفعلي حقل contract/version أو سبب الاستثناء والاختبارات حسب checklist.

| PRD ID | feature محددة | coverage / ملاحظة البداية |
|---|---|---|
| SEC-01 | inherited roles/permissions وrecord-level ownership/assignment | `Restricted/disabled`; identity service + query-level scope غير متوفرين |
| SEC-02 | masking contact data عبر UI/API/search/export/log/notification/download | `Restricted/disabled`; contact projection/masking غير منفذ |
| SEC-03 | approval workflow states/rules/escalation | `Restricted/disabled`; approval policy service غير موجود |
| SEC-04 | SoD/self-approval/break-glass | `Restricted/disabled`; لا approval authority أو policy مفعلة |
| SEC-05 | durable audit trail/correlation/before-after | `Restricted/disabled`; لا durable audit implementation |
| SEC-06 | offboarding/revoke/reassign/retain history | `Restricted/disabled`; لا identity sessions/jobs auth |
| CORE-01 | company/entity/departments/branches/warehouses/sites/currencies/units/status/config | `Restricted/disabled`; foundation CORE01 link check فقط، no org management tool |
| CORE-02 | stable IDs and secure linkage لكل business record | `Restricted/disabled`; CORE01 يفحص references فقط لا business records |
| CORE-03 | workflow states/transitions/validators/cancel/reversal/history | `Restricted/disabled`; لا cross-domain transition service |
| CORE-04 | authorized global search and masked results | `Restricted/disabled`; no query scope/field filter/index |
| CORE-05 | task/reminder/due/escalation/exception center | `proposed`; no task or notification service |
| DOC-01 | central document repository/link/type/visibility/retention | `Restricted/disabled`; no document service |
| DOC-02 | versioned private upload/download/signature/audit/export controls | `Restricted/disabled`; no storage authorization/quarantine implementation |
| CRM-01 | lead library/create/import/edit/archive/dedup/consent | `proposed`; module/tool/service absent |
| CRM-02 | assigned-only agent/customer access and hidden contact | `Restricted/disabled`; in-memory SEC01 alone is insufficient |
| CRM-03 | configurable lead stage/activity outcome taxonomy/history | `proposed`; no CRM service |
| CRM-04 | activity timeline and follow-up reminders | `proposed`; needs CRM+Tasks contracts |
| CRM-05 | handover request/recipient ack/reject/accountability | `proposed`; write needs recipient confirmation |
| CRM-06 | lead→opportunity/customer→inspection→quote→contract/order→project/delivery→invoice/collection | `proposed`; each transition/link gets own typed coverage and owner |
| TASK-01 | calendar/to-do/appointment/deadline/reschedule/reminder/evidence | `proposed`; task owner service absent |
| KPI-01 | target definition/period/metric/threshold/weight/owner | `Restricted/disabled`; target configuration not delegated to assistant |
| KPI-02 | approved outcome result calculations | `proposed`; deterministic approved-event projection only |
| KPI-03 | target trajectory/upcoming/missed/final alerts | `proposed`; threshold/calendar/recipient policy required |
| KPI-04 | current/historical performance/evaluations | `Restricted/disabled`; sensitive profile projection; no payroll penalty automation |
| SALE-01 | quotation lifecycle/items/pricing/discount/tax/validity | `proposed`; drafts only candidate; no approved price/discount policy assumed |
| SALE-02 | contract source/revision/signature/payment schedule/approval | `Restricted/disabled`; commitment/approval requires domain policy |
| SALE-03 | supply/install/maintenance work order generation/approval | `Restricted/disabled`; no fulfillment from unapproved order |
| SALE-04 | separation of quote approval vs revenue/physical stock | `Restricted/disabled`; must prove no automatic post/issue |
| SALE-05 | revision/cancellation/downstream reconciliation | `Restricted/disabled`; immutable history and approved compensation required |
| PRJ-01 | project identity/customer/site/contract/team/schedule/milestones/budget | `proposed`; scoped project read/draft only candidate |
| PRJ-02 | estimated vs actual profitability/source costs | `Restricted/disabled`; OPEN-05 recognition/allocation policy |
| PRJ-03 | installation/maintenance work orders/evidence/progress | `proposed`; assignee validation and service contract required |
| PRJ-04 | project stock custody/used/unused/returned/damaged | `Restricted/disabled`; inventory owns movements |
| PRJ-05 | milestone progress/delay/blocker/budget variance/change order | `proposed`; evidence-backed; no generated progress truth |
| PRJ-06 | project closure preflight/QA/financial/warranty/docs | `Restricted/disabled`; closure only domain-owned approved transition |
| INV-01 | SKU/warehouse/on-hand/reserved/available/valuation | `Restricted/disabled`; scoped inventory reads unavailable; OPEN-04 valuation |
| INV-02 | opening/receipt/reserve/issue/transfer/return/damage/count movements | `Restricted/disabled`; ledger write limited to Inventory service |
| INV-03 | reservation versus physical issue | `Restricted/disabled`; assistant cannot issue stock |
| INV-04 | cycle/full count/review/threshold/adjustment/discrepancy/alerts | `Restricted/disabled`; thresholds and stock adjustment approvals unresolved |
| INV-05 | opening stock and controlled receipt provenance (not purchasing) | `Restricted/disabled`; no procurement/RFQ or unapproved intake |
| DEL-01 | approved order→pick/release→delivery→custody→proof→exception/return | `Restricted/disabled`; stages separately authorized; no assistant stock release |
| DEL-02 | signed POD/person/time/accepted-rejected qty/photo/reason | `proposed`; capture only by authorized user, no signature fabrication |
| DEL-03 | lightweight dispatch/driver/vehicle/schedule | `proposed`; no route optimization/GPS |
| QA-01 | inspection/checklist/photo/measurement/nonconformity/corrective action/acceptance | `proposed`; sign-off approval remains separate |
| QA-02 | warranty-ready record/installation date/accepted scope/evidence | `proposed`; no customer self-service portal |
| MFG-01 | limited output/material-consumption trace if required | `Restricted/disabled`; only after OPEN-06; no MRP/BOM/routing |
| FIN-01 | double-entry posting/period/reversal/immutable journal | `Restricted/disabled`; no AI post/reverse; accountant policy/tests required |
| FIN-02 | chart of accounts/dimensions | `Restricted/disabled`; Finance owns account configuration |
| FIN-03 | expense entry/category/vendor/project/approval/payment state | `Draft` expense candidate only; post/payment remain `Restricted` |
| FIN-04 | cash/bank/receivable/receipt/allocation/reconciliation | `Restricted/disabled`; no receipt/payment action |
| FIN-05 | inventory accounting / valuation and recognition bridge | `Restricted/disabled`; OPEN-03/04 and ledger invariants |
| FIN-06 | partner contribution/drawing/settlement/distribution | `Restricted/disabled`; separate partner policy, no arbitrary write |
| FIN-07 | budget/P&L/balance sheet/trial balance/GL/cash flow/aging/exports | `Read` only after scoped reports; no unapproved metric or export bypass |
| FIN-08 | period close/lock/reopen/adjusting entries/reversal | `Restricted/disabled`; approval and closed-period gate |
| EMP-01 | employee 360 profile/status/role/docs/targets/evaluation/admin notes | `Restricted/disabled`; least-field access, no private-doc exposure |
| EMP-02 | supervisor assignment/evidence attribution for non-login workers | `proposed`; attribution actor and work scope must be explicit |
| PAY-01 | base salary/commission/bonus/advance/approved deduction/net summary | `Restricted/disabled`; payroll/payout not an assistant action |
| PAY-02 | commission eligibility/accrual/reversal/recalculate on refund | `Restricted/disabled`; OPEN-01 trigger/rates unknown |
| PAY-03 | finance review and authorized deduction from documented approval | `Restricted/disabled`; no AI deduction or salary penalty |
| PAY-04 | configurable local tax/insurance deductions after accountant validation | `Restricted/disabled`; no invented legal rates |
| COM-01 | employee workspace of assigned work/calendar/targets/approvals/messages/history | `Read` projection only after recipient-specific auth |
| COM-02 | internal staff messages/groups/files/history/notifications | `Draft` send candidate; scoped membership, attachment permissions, explicit send |
| COM-03 | corporate email inbox/draft/send/receive/reply/forward/attachments/CC/BCC | `Restricted/disabled`; fake-only if authorized scope change; no credentials/egress |
| COM-04 | SMS template/send/status/provider | `Restricted/disabled`; fake-only until OPEN-07 and separate egress authorization |
| COM-05 | optional Telegram with opt-in/platform constraints | `Restricted/disabled`; no live channel or implicit user consent |
| COM-06 | WhatsApp decision/compliant provider contract | `Restricted/disabled`; no QR automation/live account/Meta API authorization |
| COM-07 | message capture/channel/actor/direction/provider IDs/history | `Restricted/disabled`; retention OPEN-10; minimize sensitive content |
| COM-08 | masked destination resolution/send by customer record | `Restricted/disabled`; server resolves destination, never raw user-provided recipient |
| COM-09 | queued send/retry/provider error/delivery state | `Restricted/disabled`; accepted ≠ delivered; no live retry egress |
| DASH-01 | near-real-time executive command center KPIs/exceptions | `Read` only from authorized projections after data freshness established |
| DASH-02 | role-specific employee/manager/finance/warehouse/project dashboards | `Read` per active role and row/field permissions |
| DASH-03 | source-linked authorized drill-down and next actions | `Read` + existing tool link; links reauthorize; no action inferred from alert |
| DASH-04 | threshold/overdue/stock/approval/follow-up/budget/QA/commission alerts | deterministic `proposed` detector; each trigger/policy/recipient separate, no invented thresholds |
| DASH-05 | report filters/exports/masking/access logs/reconciliation | `Read`/export entitlement tested for each field; counts/pages/exports scope-filtered first |
| AI-01 | deterministic validation/authorization/invariants | deterministic domain owner, not LLM; future tool adapters cannot bypass |
| AI-02 | priority transaction audit and findings | advisory only per ADR-007; no tools in output |
| AI-03 | linked finding trigger/evidence/reason/severity/confidence/next step/reviewer | typed advisory finding; versions and access limited |
| AI-04 | human review/no silent action/approval/payment/stock/contact protection | action execution remains `Restricted/disabled`; human workflow independent |
| AI-05 | sync advisory + async/scheduled audit and outage behavior | deterministic guards continue; unavailable AI never means allowed |
| AI-06 | minimization/redaction/untrusted-doc prompt injection boundary | no provider until OPEN-09/10; fake fixtures only |
| AI-07 | model/rule/prompt/input hash/output/cost/reviewer/evaluation version | `proposed`; privacy-safe metadata only, no raw prompts assumed |
| AI-08 | future typed command interface and validate/preview/confirm/approve/commit/audit flow | contract proposal only; tool execution disabled pending scope/ADR amendment |
| AI-09 | labeled eval set/FP/FN/latency/cost/tuning/disable audits | `proposed`; acceptance thresholds OPEN-09; no auto-learning policy |

### 2.1.1 Task traceability for the Action Assistant

The executable task pairs for the requirements above are fixed as follows; these mappings do not enable providers or change task state:

| Requirement | Existing task IDs | Required foundation/dependency boundary |
|---|---|---|
| AI-01 deterministic validation and authorization | `A5-AI-001`, `A5-AI-002` | module-owned deterministic services; no model authority |
| AI-02 priority transaction review | `A5-AI-003`, `A5-AI-004` | Finance/Inventory read models and `ADR-007`/`OPEN-09` gates |
| AI-03 explainable findings and lifecycle | `A5-AI-005`, `A5-AI-006` | scoped evidence, recipient authorization, notification/inbox delivery |
| AI-04 human review and no silent action | `A5-AI-007`, `A5-AI-008` | Approval Engine integration and protected-action denial paths |
| AI-05 proactive and scheduled detection | `A5-AI-009`, `A5-AI-010` | `A0-SEC-006`, `A0-CORE-010`, Finance/Inventory/Tasks read models; deterministic first |
| AI-06 minimization and prompt security | `A5-AI-011`, `A5-AI-012` | `OPEN-09`/`OPEN-10`; provider and egress remain disabled |
| AI-07 versioned audit and execution receipts | `A5-AI-013`, `A5-AI-014` | outbox/receipt primitive `A0-ARCH-902`; no raw prompts/secrets |
| AI-08 typed registry, bilingual interface and governed actions | `A5-AI-015`, `A5-AI-016` | `A0-SEC-006`, `A0-CORE-010`; explicit confirmation/approval; no autonomous execution |
| AI-09 evaluation and tuning | `A5-AI-017`, `A5-AI-018` | `OPEN-09`; labeled synthetic/sanitized cases and safe disable |

The approved PRD does not define a “17-module” count. Its architecture layer names the domain modules in `PRD.md`; `MODULE_CATALOG.md` additionally lists shared capability owners such as Audit, Reporting and AI. This coverage register therefore follows the PRD scope plus the ownership rows, without inventing or claiming a separate 17-module set.

### 2.2 اكتمال تغطية ميزات PRD

تشمل خريطة الميزات أعلاه النواة/التنظيم/الهوية/سير العمل/التدقيق/الوثائق/البحث/المهام/الإشعارات؛ CRM والأهداف والتقويم؛ عروض الأسعار/العقود/الطلبات؛ المشروعات والتنفيذ وحيازة المواد؛ المخزون والتسليم والجودة والإنتاج المحدود؛ الحسابات والذمم والمصروفات وحسابات الشركاء والتعويضات؛ الاتصالات؛ لوحات المعلومات والتقارير والتصدير؛ ومراجعة AI. يجب أن يكون لكل انتقال مستخدم ولكل قراءة/إسقاط مدعوم صف coverage مستقل أو سبب `N/A` صريح؛ تجميع معرفات PRD للتلخيص لا يلغي التسجيل التفصيلي. صف الوحدة وحده ليس دليلاً على التغطية. البحث نفسه capability للأداة/التقرير: تطبق سياسات السجل والحقل قبل المطابقة/العدد/الصفحات، ويعاد التفويض لكل نتيجة أو تصدير. الإشعارات والروابط أسطح وصول مستقلة ولا ترث صلاحية detector.

### 2.3 تقييم تغطية الكواشف الاستباقية

| الكاشف / سؤال الأعمال | مصدر التشغيل/الدليل المطلوب | قاعدة المستلم/الوصول | التقييم الحالي |
|---|---|---|---|
| Neglected follow-up / lost opportunity | CRM activity + next follow-up + versioned status/assignment; dedup episode per lead/activity version | Assigned owner and separately authorized manager; only scoped evidence | `proposed`; no rule thresholds or CRM service |
| Quote delay / unusual discount / stalled orders | Quote revision/status/approved discount basis and age policy; order transition events | Sales assignee/authorized approver, independently scoped | `proposed`; discount denominator/timings not approved |
| Supplier delay / pending purchase approvals | Supplier delivery/status and purchase-approval events only after approved procurement scope and implemented authorized services | Authorized procurement approver/owner, scoped per supplier/order; no recipient surface before scope approval | `FUTURE EXCLUDED / Restricted`; no current PRD capability/task/service; no live detector until separate scope approval |
| Invoice, reconciliation, collection risk | posted invoice, due/receipt allocation and Finance reconciliation source | Finance scoped recipient; no account/customer leakage to sales unless permission | `proposed`; OPEN-03/finance policies missing |
| Low/overstock / aging / expiry | authorized stock balance, reservation, lot/expiry only when data exists, approved unit/reorder policy | warehouse/project recipients by assigned warehouse/site; per-recipient projection | `restricted/disabled`; OPEN-04 and tracked expiry data unknown |
| Project delay/cost/blockers | milestone/schedule/evidence/budget/cost version, approved OPEN-05 calculations | assigned PM/team/manager; project scope checked at dispatch/read | `proposed`; no margin formula/rate assumptions |
| Repair diagnostics / customer approval | maintenance work order, inspection evidence, customer-approved acceptance evidence | assigned service/quality; customer identity/contact minimum; human confirmation | `restricted/disabled`; maintenance workflow may be supported but no standalone workshop invention |
| Pending admin HR/staffing | admin employee profile/work assignment/open task versions only | HR/authorized manager; least fields; no attendance inference | `restricted/disabled`; no full HR or attendance |
| Executive cross-module risks | documented scoped source references from Finance/Sales/Inventory/Projects/Quality with each projection authorized independently | executive permissions per module and item; detector global read does not grant global delivery | `proposed`; no global bypass or unapproved margin metrics |

Any detector requiring missing policy, threshold, due date, valuation, legal expiry or business meaning stays `unconfigured/disabled`; it must not guess. The phrase “aging/expiry” does not authorize legal or inventory expiration decisions.

## 3. قائمة فحص عقد الأداة وسجل registry

يجب استكمال الحقول التالية في سجل `coverage_id` قبل نقل الحالة من `proposed`:

| Field | Required content |
|---|---|
| Identity | stable coverage ID, exact capability/PRD ref, owner module, contract name/version, current status and change date |
| Classification | `Read` / `Draft` / `Write` / `Approval-required` / `Restricted`, risk reason, whether runtime exposure is disabled |
| Binding | typed handler adapter and same `ApplicationService` reference; explicit central allowlist key; no reflection/discovery |
| Typed schemas | strict input/output schema and version; unknown-field rejection; size/rate/page limits; sanitized errors |
| Security | permission map ≤ UI/API, record/entity/branch/department/functional assignment scope, field projection, non-enumerating denial, fresh auth at discovery/execute/read |
| Preconditions | source IDs/aggregate versions, deterministic invariants, approval/policy version, business-state constraints and stale behavior |
| Side effects | preview, user confirmation, independent approval, idempotency key/hash, transaction boundary, outbox/audit and receipt behavior |
| Proactive assessment | detector/rule/event/job or explicit `N/A` reason; evidence refs/versions; recipient policy; episode/scan/delivery dedup; preference/mandatory behavior |
| Tests/evidence | contract/schema, auth/IDOR/masking, domain service, failure/no-mutation, idempotency/race, proactive tests; exact evidence and test status |
| Enablement | owner + security/product approvals, scope/ADR gate where needed, tool visibility/kill-switch rule; no production/provider enablement inferred |

لا يجوز أن يجعل تسجيل العنصر في الدليل قابلاً للاستدعاء لمجرد إضافة PHP class أو schema. يثبت اختبار الوحدة واجهتها ودلالاتها، ويثبت اختبار registry المركزي أن الظاهر فقط هو ما أُدرج صراحة في allowlist وحالته معتمدة. مالك allowlist المركزي لا يصبح مالك handler الأعمال داخل الوحدة.

## 4. Definition of Done إلزامي لكل ميزة

لكل تسليم مستقبلي لميزة مدعومة، يجب أن تتضمن task مالك الميزة **ضمن التغيير نفسه** ما يلي:

- إضافة سجل لكل قدرة أعمال جديدة أو متغيرة، لا للـPRD epic فقط، مع تحديد مالك الوحدة؛
- عقد tool صارم typed/versioned وhandler adapter إلى `ApplicationService` القائمة في الوحدة، أو entry مسبب `N/A`/`Restricted` حين لا يناسب النطاق أو الأمن؛
- ربط الصلاحيات بالفاعل/الكيان/الفرع/القسم/الإسناد المستمد server-side وبـfield projection؛ مساواتها أو تضييقها عن UI/API؛ وفحصها قبل استعلام العدد/الصفحات/التجميع/التصدير ثم قبل التنفيذ/السجل/التسليم؛
- تحديد source-version/precondition والتحقق الحتمي، ومعاينة/receipt typed، وتأكيد صريح وموافقة مستقلة عند اللزوم، وidempotency/concurrency/audit/outbox لأي mutation؛
- إدخال صريح في central allowlist مع `implemented-disabled` حتى اعتماد rollout، ومنع auto-discovery. أضف اختباراً يثبت عدم استدعاء handler المعطل عبر أي route عامة أو tool؛
- اختبارات contract والصلاحية/abuse والمنطق/failure وDB/outbox ذات الصلة، بملكية الوحدة؛ لا readiness من schema غير مختبر؛
- تقييم proactive detector: event/rule/scheduler واختبارات evidence/version/recipient/reauthorization/dedup عند الصلة، أو سبب `N/A` موثق ومراجع أمني؛
- تحديث status/evidence وسياسات `OPEN` المعروفة دون ادعاء تشغيل اختبار أو موافقة أو جاهزية feature أو استخدام model/provider لم يحدث؛
- ربط تغيير UI-only بتغطية قائمة أو توثيق أنه لا ينشئ business capability؛ وتظل أسطح البحث/العدد/التصدير/الإشعار/الحقول خاضعة لمراجعة permission/masking؛
- إبقاء الميزة restricted/unsupported/unimplemented/sensitive في `Restricted` أو disabled مع contract/exclusion وبوابة موافقة واضحة. لا تضف generic execute لمجرد استيفاء عدد التغطيات.

**التفعيل منفصل عن DoD الميزة:** يتطلب `enabled` اعتماد scope/product والأمن والسياسة صراحة، وخدمات الأعمال/المصادقة، وregistry entry متحققاً، واختبارات ملائمة، ومراقبة/kill switch، وتوثيق المستخدمين والنطاقات. كما أن provider egress يتطلب `OPEN-09/10` والموافقات اللازمة مستقلاً؛ سجل coverage لا يمنحه.

## 5. التقدم المعروف وحدود الدليل

- لا توجد أداة حالتها `enabled` أو `tested-evidence` هنا؛ لا ندعي وجود assistant tools أو تفعيلها.
- `ModuleCatalog` يسرد وحدات composition فقط. اختبار/guard `SEC01` يعمل ببيانات in-memory ولا يثبت حماية auth/session/query/database أو وجود tool adapters للوحدات. `CORE01` يثبت ربط مراجع organization للقراءة فقط. سجل الأساس المحلي يوثق Laravel 13/PHP 8.4، وتعطيل auth، ووجود health route فقط وعدم وجود business migrations/services. لا يثبت ذلك اكتمال أي business feature.
- Phase A ما زال يتطلب AI Audit عملياً وفق PRD؛ لا تحوّل fake fixtures إلى إنجاز `AI-02/05/09` أو `AT-10` أو `A5-GATE-001`. لا تتغير task/lease/history أو gate/DAG هنا؛ لا تنفذ `Claim` أو `Review` أو `Complete` أو `RefreshBacklog`.
- المنتجات المؤجلة تبقى كما في [SCOPE](../product/SCOPE.md): لا procurement/RFQ، ولا full HR/attendance، ولا MRP/BOM/routing، . الصيانة نوع عمل مذكور في PRD، وليست تفويضاً بإنشاء Workshop مستقل.
- مراجع الاعتماديات والاختبارات: [PRD](../product/PRD.md)، [MODULE_CATALOG](../architecture/MODULE_CATALOG.md)، [PERMISSION_MATRIX](../security/PERMISSION_MATRIX.md)، [TEST_STRATEGY](TEST_STRATEGY.md)، [DECISION_REVIEW_PACK](../governance/DECISION_REVIEW_PACK.md)، ومقترحات scope/الخطة الاصطناعية. الاختبارات المذكورة في هذا السجل `NOT RUN`.

## CR-CYCLE01-DEV-001 — organization and access administration

Status: Restricted / disabled for AI. Human-only local DEVELOPMENT organization and user administration reuses AccessControl and OrganizationDirectory; every HTTP write checks persisted grants and scope. Role grants/revocations are sensitive and have no enabled assistant adapter, registry allowlist entry, provider call or generic execute route. Future typed/versioned tools must call these same services, reauthorize inside the transaction, and pass the shared gateway gates first. Proactive detector: N/A, access assignment is an explicit human security operation, never an autonomous suggested grant. Evidence: docs/governance/evidence/cycle01-acceptance.md. OPEN-02 approval workflow remains blocked; no policy or AI activation is inferred.

## Cycle01 department defaults — A1-CORE-012

Human-only DEVELOPMENT capability: AccessControl::assignDepartmentRoles, called
by the existing access-administration controller and organization form. Server
checks organization.write + roles.write under company lock, company/branch and
non-self scope, actor grant ceiling, expected department version and role IDs;
all changes record before/after and actor role/admin provenance. Effective grants
combine department/direct roles with explicit user denies last, reloaded per request.
No AI tool is registered or allowlisted: sensitive access administration remains
Restricted/disabled to the assistant. Detector assessment N/A: deterministic access
control, not an AI finding. Acceptance: AccessScopeCompletenessTest and real
access-scope.spec.ts desktop/mobile department assignment/removal with MariaDB.
No OPEN-02 policy, external provider or production activation is authorized.
# CRM Phase 3 presentation boundary

`A1-CRM-016` adds authenticated human HTML projections and native forms only.
The routes call the same reviewed `LeadDirectory` contracts as CRM015, with
persisted identity, scope, optimistic versions, CSRF, masking and audited reveal.
Overview is a bounded read projection of authorized lead/activity/transfer data.
No assistant registry, handler, generic executor or provider is enabled by these
views. Existing CRM tools remain **Restricted/disabled**; detector activation is
**N/A** for this human presentation adapter. Tests and independent technical
review are recorded in [Phase 3 evidence](../governance/evidence/cycle03-ui.md).


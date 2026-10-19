# كتالوج workflows — states Proposed ما عدا ضوابط PRD
كل transition يمر authorization + scope + expected version + deterministic guards + transaction + immutable history/audit + outbox. رفض الانتقال لا يغير aggregate ويولد security evidence عند الحساسية. لا status PATCH مباشر. identifiers أدناه أسماء تصميمية لا schema نهائي. OPEN-02 يحجب تشغيل chains الفعلية.

## approvals
```mermaid
stateDiagram-v2
 [*] --> pending
 pending --> approved: authorized different approver
 pending --> rejected: reason
 pending --> returned: changes required
 returned --> pending: new version
 pending --> delegated: scoped delegation
 delegated --> pending: recipient accepts
 pending --> cancelled: authorized withdrawal
 pending --> expired: policy timer
```
approved مربوط subject version/hash؛ revision يلغي صلاحيته للنسخة الجديدة. delegated/returned يحتفظان history ولا يتحولان approve ضمنياً. expired escalation لا يعتمد تلقائياً. self-approval/break-glass وفق OPEN-02.

## lead handover
```mermaid
stateDiagram-v2
 assigned --> handover_pending: sender requests
 handover_pending --> assigned_new_owner: recipient accepts atomically
 handover_pending --> assigned: reject or cancel
```
المالك القديم مسؤول حتى commit القبول، لا pending duplicate متنافس. attachments scoped وtask تذكير وصلاحيات new owner بعد القبول. AT-02.

## quote → contract → order
```mermaid
stateDiagram-v2
 draft --> review
 review --> approved: policy approval
 approved --> sent
 sent --> accepted
 sent --> rejected
 sent --> expired
 approved --> revised: new immutable revision
 accepted --> contract_review
 contract_review --> signed: approval and evidence
 signed --> order_approved: authorized fulfillment
 order_approved --> cancelled: reconciled reversal plan
```
quote cancelled متاح بpolicy قبل الالتزام؛ بعد الالتزام cancellation يتطلب downstream reconciliation. العقد من authorized source كذلك ممكن؛ لا يشترط quote لكل الحالات. signature وحدها لا revenue ولا physical issue؛ OPEN-03/05/01 تضبط downstream effects. AT-03.

## stock reserve/issue
```mermaid
stateDiagram-v2
 approved_order --> reserved: available check and lock
 reserved --> issued: warehouse authority
 reserved --> released: cancel reservation
 issued --> returned: compensating movement
 issued --> damaged: approved movement
```
on_hand لا ينقص عند reserve، available=on_hand-active_reservations. issue يستهلك reservation ويخصم physical مرة؛ units/negative/valuation OPEN-04. count variance review ثم adjustment approval، receipts provenance لا procurement. AT-04.

## delivery / POD
```mermaid
stateDiagram-v2
 ready --> picked
 picked --> released: stock issue reference
 released --> in_custody: driver acknowledgement
 in_custody --> handed_over
 handed_over --> proof_recorded: signature or scanned note
 proof_recorded --> completed: quantities reconcile
 handed_over --> exception: rejected quantities
 exception --> resolved: return or authorized correction
 resolved --> completed
```
POD recipient/time/accepted/rejected/reason وإثبات خاص؛ no complete لمجرد upload بدأ. فقد اتصال retry idempotent، no offline stock commit. AT-05.

## project closure
```mermaid
stateDiagram-v2
 active --> closure_review: manager requests
 closure_review --> blocked: missing evidence or finance checks
 blocked --> active: corrective work
 closure_review --> closed: authorized acceptance
```
checks: delivery/POD، QA، acceptance checklist، materials used/unused/returned/damaged، source costs، financial requirements، warranty handoff؛ OPEN-12 وOPEN-05 تضبط التفاصيل. reopened change يتطلب authority/history لا overwrite. AT-06.

## finance posting / reversal / period close
```mermaid
stateDiagram-v2
 draft --> pending_approval
 pending_approval --> approved
 approved --> posted: balance and open period locked
 posted --> reversed: linked compensating journal
 open_period --> closing: reconciliation
 closing --> closed_period: authorized lock
 closed_period --> reopened: approved reason
```
post atomic، debits=credits، unique source، valid dimensions؛ expense approved لا paid تلقائياً. reversal بتاريخ مسموح لا تعديل original؛ close/post race lock موحد. partner drawing ليس expense، intake ليس operating expense افتراضياً. OPEN-03/04/05. AT-07/15.

## commission reversal
```mermaid
stateDiagram-v2
 source_event --> evaluated: approved policy version
 evaluated --> accrued: eligible unique source
 accrued --> reviewed
 reviewed --> paid: authorized payout
 accrued --> reversed: cancellation or refund
 paid --> adjustment_due: cancellation or refund
```
OPEN-01 يحدد trigger/splits/tiers/refunds؛ لا اختيار contract أو collection ضمناً. deduction تحتاج documented managerial approval، missed target لا عقوبة تلقائية. AT-08.

## offboarding
```mermaid
stateDiagram-v2
 active --> disabled: authorized deactivation
 disabled --> access_revoked: sessions and tokens
 access_revoked --> reassignment_pending
 reassignment_pending --> offboarded: work reassigned and verified
```
disable يمنع استخدام أي token فوراً حتى background revocation؛ لا delete records، conversations/company channels محفوظة. failure يصعد task ولا يعيد access. AT-12.

## messaging failure
```mermaid
stateDiagram-v2
 draft --> queued: permission and destination resolve
 queued --> sending
 sending --> accepted: provider accepted
 accepted --> delivered: verified receipt
 sending --> retry_pending: transient failure
 retry_pending --> sending: same idempotency key
 sending --> failed: permanent failure
 retry_pending --> dead_letter: exhausted budget
 accepted --> failed: verified failure receipt
```
unknown timeout يتطلب provider lookup إن متاح قبل resend؛ لا exactly-once خارجي مفترض. DLQ visible للoperator، headers masked، delivery receipts dedup/out-of-order guarded. OPEN-07. AT-11.

## AI review
```mermaid
stateDiagram-v2
 queued --> reviewing: minimized read model
 reviewing --> finding_created: versioned evidence
 reviewing --> failed: provider unavailable
 failed --> queued: bounded retry
 finding_created --> human_review
 human_review --> accepted_finding
 human_review --> dismissed: reason
 human_review --> needs_information
```
قبول finding لا ينفذ transaction؛ أي action أمر منفصل بصلاحيات وموافقات حتمية. stale subject version يستدعي re-review. synchronous advisory عند latency مناسب فقط ولا bypass عند outage. OPEN-09 وAT-10.
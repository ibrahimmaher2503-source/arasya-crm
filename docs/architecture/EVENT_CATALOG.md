# Event contracts — أسماء PRD مؤكدة، payloads أدناه Proposed
```json
{"event_id":"opaque","event_type":"quote.approved","event_version":1,"entity_id":"legal-entity-id","aggregate_type":"Quotation","aggregate_id":"quote-id","aggregate_version":4,"actor":{"id":"employee-id","kind":"human"},"occurred_at":"2026-01-01T00:00:00Z","correlation_id":"opaque","causation_id":"opaque","payload":{"revision_id":"revision-id","approval_id":"approval-id"}}
```
entity_id هنا legal scope؛ aggregate_id هو business record لتجنب غموض لفظ entity في PRD. schema version وaggregate version منفصلان. إضافة optional field compatible؛ تغيير معنى/حذف يرفع version وdual-consumption migration. لا raw phone/email/secrets/documents؛ references scoped، consumers لا يستخدمون actor الأصلي لتجاوز policy الحالية.

| event | producer → expected consumers | payload business minimal / invariant |
|---|---|---|
| lead.assigned | CRM → Tasks, Reporting | lead_id, assignee_id, assignment_version؛ بعد assignment commit |
| lead.handover.requested | CRM → Tasks, Notifications | handover_id, sender_id, recipient_id؛ ownership لم تتغير |
| lead.handover.accepted | CRM → Tasks, Reporting | handover_id, old_owner_id, new_owner_id؛ transition atomic |
| quote.approved | Sales → Tasks, AI | quote_id, revision_id, approval_id؛ لا revenue أو issue |
| contract.signed | Sales → Projects, Compensation | contract_id, revision_id, evidence_id؛ commission حسب OPEN-01 |
| order.approved | Sales → Inventory, Delivery | order_id, approved_revision, line_refs؛ reserve request لا issue تلقائي |
| stock.reserved | Inventory → Sales, Projects | reservation_id, order_id, quantity, unit؛ on-hand ثابت |
| stock.issued | Inventory → Projects, Finance, Delivery | movement_id, reservation_id, quantities, valuation_ref؛ posting حسب OPEN-04/03 |
| delivery.signed | Delivery → Quality, Projects, Tasks | delivery_id, proof_id, accepted/rejected line refs؛ signature evidence موجود |
| project.milestone.completed | Projects → Reporting, Quality | milestone_id, project_id, evidence_refs؛ لا revenue بلا OPEN-05 |
| inspection.failed | Quality → Tasks, Projects, Reporting | inspection_id, finding_ids, severity؛ لا contact |
| invoice.posted | Finance → Reporting, Compensation | invoice_id, journal_id, currency, total؛ balanced posting |
| payment.received | Finance → Reporting, Compensation | payment_id, allocation_refs, journal_id؛ no duplicate receipt |
| commission.eligible | Compensation → Tasks, Finance | accrual_id, policy_version, source_id؛ review وليس payout |
| expense.approved | Finance → AI, Tasks | expense_id, approval_id, approved_version؛ posting guard مستقل |
| audit.finding.created | AI → Tasks, Reporting | finding_id, review_run_id, subject_version, severity؛ advisory |

كل consumer يتعامل مع duplicates/out-of-order عبر inbox unique وaggregate version. لا يرفض event سابقاً بصمت إن ضاع أثر مالي: reconciliation واستدعاء authority. outbox row في نفس transaction ثم publisher بعد commit؛ crash قبل/بعد publish يعيد بنفس event_id. DLQ يحتفظ reason sanitized/attempts ويعاد بعد إصلاح وتفويض، لا تغيير ID لكسر dedup. metrics: outbox age، retry، DLQ، consumer lag. retention وreplay windows OPEN-10/11. contracts اختبارات schema/backward compatibility/privacy plus commit/publish crash tests. Audit trail إلزامي لكل module حتى لو لا يوجد event مثال في هذه القائمة؛ الأحداث الإضافية تتطلب عقداً لا أسماء مفترضة معتمدة.
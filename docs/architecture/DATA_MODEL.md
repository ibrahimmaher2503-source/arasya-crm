# Logical ERD — Proposed وليس schema نهائياً
الرسوم تحدد روابط business لا إلزام شكل الجداول أو PK type. كل aggregate يحتفظ entity_id وstable ID وversion وUTC created_at/updated_at وactor عند الانتقال. IDs provider ليست PK. FK تشمل scope عند الحاجة لمنع ربط entities مختلفة. الحقول التفصيلية وindexes تعتمد مع migrations بعد ADR.

```mermaid
erDiagram
 Organization ||--o{ LegalEntity : contains
 LegalEntity ||--o{ Department : scopes
 Department ||--o{ Employee : employs
 Employee ||--o{ RoleAssignment : granted
 Role ||--o{ RoleAssignment : assigned
 Role }o--o{ Permission : allows
 ApprovalPolicy ||--o{ ApprovalRequest : governs
 Employee ||--o{ ApprovalRequest : requests
 Employee ||--o{ AuditEvent : acts
 Attachment ||--|{ DocumentVersion : versions
```

```mermaid
erDiagram
 Customer ||--o{ Lead : linked
 Lead ||--o{ LeadAssignment : history
 Lead ||--o{ LeadActivity : records
 Lead ||--o{ Handover : transfers
 Lead ||--o{ Opportunity : converts
 Opportunity ||--o{ Quotation : quotes
 Quotation ||--|{ QuoteRevision : retains
 Quotation ||--o{ Contract : commits
 Contract ||--|{ ContractRevision : retains
 Contract ||--o{ SalesOrder : authorizes
 SalesOrder ||--o{ SupplyOrder : fulfills
 Employee ||--o{ Task : assigned
 Employee ||--o{ CalendarEvent : scheduled
 Target ||--o{ TargetResult : measures
```

```mermaid
erDiagram
 Contract ||--o{ Project : funds
 Project ||--o{ WorkOrder : schedules
 Project ||--o{ ProjectMilestone : tracks
 Project ||--o{ ProjectBudget : versions
 Project ||--o{ ProjectCost : allocates
 Warehouse ||--o{ StockMovement : locates
 SKU ||--o{ StockMovement : changes
 SKU ||--o{ StockReservation : reserves
 SupplyOrder ||--o{ StockReservation : requests
 Warehouse ||--o{ StockCount : reconciles
 SupplyOrder ||--o{ Delivery : dispatches
 Delivery ||--o{ DeliveryNote : documents
 Delivery ||--o{ DeliveryProof : evidences
 Project ||--o{ Inspection : verifies
 Inspection ||--o{ QualityFinding : raises
 Project ||--o{ WarrantyRecord : hands_over
```

```mermaid
erDiagram
 ChartAccount ||--o{ JournalLine : classifies
 JournalEntry ||--|{ JournalLine : balances
 Invoice ||--o{ Payment : allocated_via_relation
 Expense }o--o| JournalEntry : posted_as
 PartnerAccount ||--o{ JournalLine : dimensions
 Employee ||--o{ CompensationRule : eligible
 CompensationRule ||--o{ CommissionAccrual : calculates
 Employee ||--o{ PayrollSummary : summarizes
 CommunicationChannel ||--o{ Conversation : routes
 Conversation ||--o{ Message : records
 IntegrationAccount ||--o{ CommunicationChannel : configures
 Employee ||--o{ Notification : receives
 AIReviewRun ||--o{ AIAuditFinding : produces
```

PaymentAllocation associative relation مقترحة لتعدد دفعات/فواتير؛ الرسم المبسط لا يفرض one-payment-one-invoice. DocumentLink وAudit subject وAI finding subject مراجع typed مع authorization لا foreign key polymorphic غير محكوم. OutboxEvent/ConsumerReceipt infrastructure aggregates مع uniqueness `(consumer,event_id)`.

Ownership مطابق [catalog](MODULE_CATALOG.md): ledger immutable في Finance، stock movements في Inventory، contract revisions في Sales. Monetary value = decimal amount + currency + approved rounding؛ لا float ولا جمع currencies بلا conversion policy. quantities بوحدة وتحويل versioned حسب OPEN-04. UTC timestamps مع timezone للعرض/مواعيد التقويم؛ posting_date وفترة مالية business dates مستقلة.

soft-delete/archive للmaster data المسموح مع uniqueness/history وإبقاء FK؛ لا delete customer عند offboarding، لا تعديل journal/stock posted أو approved revision. corrections عبر reversal/revision وsource reference. immutable audit وapproval history لا تحمل raw secrets. entity_id ليس multi-tenant signup؛ حسابات/مخزون/مشروعات scoped منذ التصميم. تاريخ الاحتفاظ/erase legal OPEN-10 وقد يتطلب anonymization دون كسر financial archive.
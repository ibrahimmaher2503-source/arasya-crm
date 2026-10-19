# MY ARASYA

# MASTER PRODUCT REQUIREMENTS DOCUMENT

> Internal Operations Platform | Web + Mobile | AI-Auditable by Design

Version: 1.0 - Development Baseline | Date: 15 October 2026

Prepared for: Arasya executive management and the software development team

Classification: Confidential - internal product and engineering specification

Status: Requirements baseline. Items marked OPEN require business confirmation; they must not be silently assumed by developers.

### Product thesis

A private, Arabic-first, company-controlled operating system for Arasya that unifies sales, leads, contracts, projects, execution, warehouse, delivery, quality, finance, employee performance, approvals, communication, and executive intelligence. It must prevent employee ownership of customer relationships, make every sensitive action accountable, and be extensible for future AI-assisted execution and customer-facing services.


<!-- End of PDF page 1 -->

### Document control and reading guide

```text
   Field                      Definition
   Product                    My Arasya - private internal business platform
   Primary deployment         Arasya only; not a SaaS or marketplace
   Primary language           Arabic RTL at launch; English-ready architecture
   Users                      Executive management, department heads, sales, finance, warehouse, supervisors, operations
                              and authorized support
   Platforms                  Responsive web, mobile experience/application; parity of authorized critical workflows
   Requirement notation       MUST = contractual requirement; SHOULD = recommended; OPEN = decision needed
   Business status            Based on stakeholder discussion; not a claim of approved implementation or deployed code
```

### Contents

- 01 Executive objectives and scope
- 02 Actors, security and governance
- 03 Core master data and common workflows
- 04 Leads, CRM, targets and calendars
- 05 Quotations, contracts and orders
- 06 Projects, installation and field execution
- 07 Inventory, delivery and quality
- 08 Finance, employee profiles and commissions
- 09 Omnichannel communications
- 10 Executive command center and reporting
- 11 AI audit and future AI actions
- 12 Architecture, data and integration
- 13 Nonfunctional requirements and QA
- 14 Release plan and acceptance tests
- 15 Open decisions and delivery governance
## 01 | Executive objectives and scope

### 1.1 Business outcomes

- Provide a single source of truth for customers, operations, commitments, documents, financial movements and employee performance.
- Ensure central ownership of customer records and communication channels; no uncontrolled personal contact by default.
- Support approval-driven, auditable workflows while avoiding unnecessary approvals for low-risk routine actions.
- Provide executive visibility into revenue, collection, project margin, delivery risk, employee targets and operational exceptions.

<!-- End of PDF page 2 -->

- Deploy practical AI review in Phase A; architect safe AI-driven text/voice commands for later phases.
- Keep daily workflows simple and task-oriented; avoid heavyweight ERP/HR/manufacturing features not approved for the initial release.
### 1.2 Scope classification

```text
   In Phase A                                  Foundation / limited                      Deferred
   Identity, RBAC, approvals, audit,           Basic production traceability, basic      Advanced purchasing/vendor
   employee workspace, CRM, targets,           stock intake, salary/commission ledger,   procurement, advanced
   calendar, quotes, contracts, projects,      messaging connectors, company/entity      manufacturing/MRP, full HR/time
   delivery, warehouse, quality, accounting,   extensibility                             attendance, advanced fleet,
   commissions, dashboards, documents,                                                   customer-facing
   full AI Action Assistant                                                                              warranty/portal/loyalty

```

Scope rule: "Foundation / limited" means the initial system must preserve identifiers, records and extension points, not implement a hidden full-scale module. Deferred capabilities must not be represented as completed in Phase A.

### 1.3 Mandatory product principles

- Private single-enterprise deployment. Future affiliated entities are possible, but no tenant signup, billing or SaaS administration.
- Arabic RTL is the production interface; labels, notifications, dates and financial display must be localized.
- Every sensitive business transition is server-validated, authorization-checked and auditable.
- Approved transactions are authoritative for KPIs, stock, commission and accounting; drafts do not inflate performance.
- Role/department defaults plus individual permission exceptions, with least privilege and segregation of duties.
- Mobile and web use the same secured domain services and workflow rules.
- Never rely on AI probabilistic output to replace deterministic financial, security or accounting controls.

<!-- End of PDF page 3 -->

## 02 | Actors, permissions, governance and audit

### 2.1 Actor model

```text
   Actor                          Typical access / responsibility
   Super Administrator            Platform configuration, department and role management, integrations, security, controlled
                                  support access
   Company Director / Executive   Cross-department oversight, high-value approvals, delegation, strategic targets, dashboards
   Department Manager             Department KPIs, work assignment, reviews and approvals within granted scope
   Sales / Marketing Agent        Assigned leads, activities, authorized messaging, quotes and sales-related tasks
   Finance / Accounting Manager   Ledger, receipts, expenses, treasury, payroll summary, commissions and approved
                                  deductions
   Warehouse Officer              Inventory counts, reservations, picking, release, stock movements and delivery proof
   Project / Operations Manager   Project budgets, scheduling, materials, resources, delivery and execution progress
   Field Supervisor / Driver      Assigned jobs, proof of delivery, installation evidence, field completion; subordinate workers
                                  managed by supervisor
   Quality / Service Officer      Inspections, QA checklists, acceptance, defect follow-up, warranty handoff
```

#### SEC-01 | Configurable RBAC + record-level access [MUST]

Create/edit departments, roles and permissions; assign inherited defaults and explicit employee overrides. Deny by default. Enforce row-level ownership/assignment for leads, projects and communications at API/query level.

#### SEC-02 | Sensitive contact data masking [MUST]

Customer phone, WhatsApp number and email must be hidden from unauthorized users in UI, APIs, search, exports, logs, notification previews and file downloads. Backend resolves approved outbound destinations. Exceptions require explicit audited permission.

#### SEC-03 | Approval workflow engine [MUST]

Define approval rules by operation, amount/risk, role, department and optional escalation. Support pending, approved, rejected, cancelled, returned-for-changes, delegated and expired states; record approver, reason, timestamp and immutable history.

#### SEC-04 | Separation of duties [MUST]

Creator and approver must be distinguishable; configurable rules prevent self-approval for sensitive transactions. Super Admin is not automatically exempt from financial control without documented break-glass procedure.

#### SEC-05 | Durable audit trail [MUST]

Record actor, effective role, action, entity ID, before/after values or secure diff, approval path, timestamps, correlation ID, origin channel and outcome. Protect against tampering; log administrative access and AI recommendations.

#### SEC-06 | Employee offboarding [MUST]

Disable access, revoke sessions/tokens, reassign open tasks/leads/projects, retain communication history and company-owned channel configuration. Offboarding must not delete customer records.


<!-- End of PDF page 4 -->

### 2.2 Approval baseline

```text
   Action                                       Minimum expected control
   Lead ownership transfer                      Recipient acknowledgement; original owner remains accountable until
                                                acceptance
   Quotation/contract commitment                Configured managerial approval before effective customer commitment
   Inventory release and adjustments            Warehouse authorization; higher-risk adjustments require approval
   Expense/payment/payroll deduction            Finance processing with required management authorization
   Project budget changes / closure             Responsible project authority and required managerial review
   Master-data deletion / corrections           Restricted permission, soft-delete where appropriate, full audit
```

## 03 | Shared master data, workflow and documents

#### CORE-01 | Company and organizational master data [MUST]

One company at launch; entity_id-capable data model for future affiliates. Configure departments, branches, warehouses, sites, currencies, units, statuses, expense categories, document numbering and approval policies.

#### CORE-02 | Unified identity and record linkage [MUST]

Each customer, lead, employee, project, contract, quotation, stock item, invoice, expense and document receives a stable unique ID. Link related records rather than duplicating free-text data.

#### CORE-03 | Workflow state machine [MUST]

All consequential entities must have explicit allowed states, transition permissions, validators, timestamps, cancellation/reversal behavior and history. No direct status changes that bypass the domain workflow.

#### CORE-04 | Global search [MUST]

Search only authorized records across leads, customers, contracts, projects, tasks, orders and documents. Mask sensitive fields; prevent side-channel enumeration of hidden contact data.

#### DOC-01 | Central document repository [MUST]

Store employment documents, quotes, contracts, delivery notes, invoices, project photos, inspection reports and acceptance/warranty evidence. Associate each with entity, document type, version, owner, visibility and retention policy.

#### DOC-02 | Versioning and controlled access [MUST]

Support secure upload/download, MIME and malware validation, version history, optional signatures, checksum, audit trail and nonpublic object storage. Never expose direct public storage URLs for private documents.

#### CORE-05 | Tasks, notifications and exception center [MUST]

Common engine for assigned tasks, reminders, approvals, due dates, escalations and failed background jobs; display pending actions and exceptions in role-specific workspaces.


<!-- End of PDF page 5 -->

## 04 | Leads, CRM, calendar and targets

#### CRM-01 | Central lead library [MUST]

Authorized managers create/import/edit/archive leads with name, organization, phones, email, WhatsApp and notes; maintain source, duplicate detection, consent/communication preferences and history.

#### CRM-02 | Assigned-only sales access [MUST]

Sales agents can view and work only on assigned leads/customers and linked authorized transactions. They cannot browse the full lead library or retrieve hidden contact data.

#### CRM-03 | Configurable lead statuses and activity outcomes [MUST]

Admin manages dropdown taxonomy. Distinguish overall lead stage from each interaction outcome (interested, later, refused, invalid number, project already completed, etc.). Preserve history when taxonomy changes.

#### CRM-04 | Activity timeline [MUST]

Every call, message, meeting, note, quotation and site visit has timestamp, author, outcome, next step and linked customer/lead. Follow-up dates generate tasks and reminders.

#### CRM-05 | Handover with acknowledgement [MUST]

Sender requests transfer to another employee with context and attachments. Recipient accepts/rejects; pending transfer is prominently visible. Ownership changes only after acceptance. Preserve accountability and transfer audit.

#### CRM-06 | Conversion pipeline [MUST]

Link lead -> opportunity/customer -> site inspection -> quote -> approved contract/order -> project/delivery -> invoice/collection, as applicable. Prevent duplicate customer creation.

#### TASK-01 | Employee calendar and to-do [MUST]

Combine personal and manager-assigned tasks, calls, appointments, site visits, deadlines and approvals. Include overdue, reschedule, reminders, completion evidence and department visibility.

#### KPI-01 | Individual and department targets [MUST]

Managers assign concurrent individual and departmental targets with metric, unit, period, threshold, owner, weight and measurable completion rule.

#### KPI-02 | Approved-outcome measurement [MUST]

Calculate performance from real approved events such as contracted leads, collections, completed jobs and expense reductions, not self-declared completion.

#### KPI-03 | Proactive performance alerts [MUST]

Compare elapsed time with target attainment, alert employee/manager on lagging trajectory, upcoming deadline, missed target and final result. Preserve historical snapshots and source drill-down.

#### KPI-04 | Performance history [MUST]

Employee profile shows current and historical achievements, exceptions and evaluations. Do not automatically translate missed targets into payroll penalties.


<!-- End of PDF page 6 -->

## 05 | Quotations, contracts and commercial orders

### 5.1 Supported commercial work types

```text
   Work type                          Required downstream capability
   Product-only sale                  Quote -> contract/order -> inventory -> delivery -> invoice/collection
   Supply + installation              Quote -> contract/order -> materials -> delivery -> installation project -> QA/acceptance
                                      -> billing
   Maintenance                        Service request/order -> schedule -> field work -> evidence -> completion -> billing
   Site inspection                    Request -> assigned visit -> inspection report -> quotation opportunity, if applicable
```

#### SALE-01 | Quotation lifecycle [MUST]

Draft, internal review, approved, sent, accepted/rejected, expired, revised and cancelled. Itemize products, services, quantities, pricing, discounts, taxes, delivery/installation terms and validity.

#### SALE-02 | Contract lifecycle [MUST]

Contract created from approved quotation or authorized source; capture customer, scope, milestones, payment schedule, attachments, signatures/evidence, amendments, approvals and state transitions.

#### SALE-03 | Supply and work orders [MUST]

Generate linked supply orders and installation/maintenance work orders as applicable; maintain quantities, project/site, owner, due date, approval and status. No unapproved fulfillment.

#### SALE-04 | Financial/stock consistency [MUST]

Quote approval does not automatically recognize revenue or deduct physical stock. Trigger accounting and stock events at explicitly defined fulfillment/billing stages.

#### SALE-05 | Revision and cancellation [MUST]

Changes to approved quotes/contracts/orders must create controlled revisions, recalculations and downstream reconciliation. Never silently overwrite historical agreed terms.

## 06 | Projects, installation and execution

#### PRJ-01 | Dedicated project record [MUST]

Each project has unique code, customer, contract, site/location, scope, manager, team/supervisor, schedule, milestones, budget, resource plan, documents and status.

#### PRJ-02 | Project profitability [MUST]

Track contracted value, recognized revenue, invoiced amount, collected amount, direct materials, labor allocations, transport, subcontracting and other direct costs. Display estimated and actual gross margin separately.

#### PRJ-03 | Project work orders [MUST]

Assign installation and maintenance work to teams/supervisors; support schedule, checklists, materials issued, photos, field notes, progress and completion/exception reports.


<!-- End of PDF page 7 -->

#### PRJ-04 | Site resources and material custody [MUST]

Link stock issues/transfers and site-held material to project; reconcile used, unused, returned and damaged quantities. Project material movements must affect stock correctly.

#### PRJ-05 | Progress and variance [MUST]

Record milestones, percent completion backed by evidence, delays, blockers, budget overruns, change orders and accountable owner. Escalate material deviations.

#### PRJ-06 | Project closure [MUST]

Require configured acceptance, delivery, QA and financial checks before final closure; preserve warranty/service handoff and complete document package.

### 6.1 Profitability definitions

Estimated margin = approved contract revenue less estimated direct project cost. Actual project margin = recognized project revenue less allocated actual direct costs. Collected cash is a separate liquidity metric and must not be misrepresented as revenue. Finance must define tax treatment, overhead allocation and revenue recognition policy before go-live.

## 07 | Warehouse, delivery, quality and basic production

#### INV-01 | Multi-warehouse-ready inventory [MUST]

One main warehouse initially, with schema/UI support for additional warehouses and sites. Maintain SKU, units, stock movements, on-hand, reserved, available and inventory valuation policy.

#### INV-02 | Stock movement ledger [MUST]

Immutable or reversal-based stock movements for opening balance, receipts, reservations, issues, transfers, returns, damages and count adjustments; every movement links to user, reason and source transaction.

#### INV-03 | Reservation versus physical issue [MUST]

Reserve quantities against approved order without decrementing physical on-hand; warehouse issue decrements available physical stock according to defined transaction semantics.

#### INV-04 | Stock count and reconciliation [MUST]

Cycle/full counts, variance review, approval thresholds, adjustments, discrepancy history and stock-level alerts.

#### INV-05 | Basic stock receipt without advanced purchasing [MUST]

Support opening stock and controlled receipts with provenance and valuation. Advanced RFQ, purchase order, vendor performance and procurement workflow are deferred.

#### DEL-01 | Delivery workflow [MUST]

Approved supply order -> warehouse pick/release -> delivery note -> driver custody -> customer handover -> signed proof returned/recorded -> exception/return resolution.

#### DEL-02 | Proof of delivery [MUST]

Capture recipient name, date/time, signature or scanned signed note, optional photo, quantities accepted/rejected and discrepancy reason. Driver and warehouse accountability must be explicit.

#### DEL-03 | Lightweight dispatch [MUST]

Assign driver/vehicle reference, delivery schedule, destinations and status; no advanced route optimization, fleet maintenance or GPS surveillance in Phase A.


<!-- End of PDF page 8 -->

#### QA-01 | Inspection and acceptance [MUST]

Configurable field inspection and installation QA checklists, photos, test/measurement records, nonconformities, corrective actions and customer acceptance documents.

#### QA-02 | Warranty-ready foundation [MUST]

Capture installation completion date, accepted scope, products/batches where available, warranty terms and linked evidence; customer self-service activation is deferred to Phase B.

#### MFG-01 | Minimal production traceability [MUST]

Manufacturing exists but advanced manufacturing is deferred. Provide at most a limited, explicitly scoped record of basic production output and material consumption if required for stock correctness. No MRP, BOM explosion, routing, machine planning or complex costing in Phase A.

## 08 | Finance, accounting, employees and commissions

### 8.1 Accounting foundation

#### FIN-01 | Double-entry ledger [MUST]

All posted accounting transactions must balance debits and credits; use chart of accounts, journal entries, fiscal periods, posting dates, reversals and controlled corrections. Never edit posted entries silently.

#### FIN-02 | Chart of accounts and dimensions [MUST]

Assets, liabilities, equity, revenue, cost of goods sold and expenses. Support department, project, branch and optional entity dimensions; classify costs independently by function and fixed/variable behavior.

#### FIN-03 | Income and expenses [MUST]

Record categorized operating/administrative expenses, salaries, transport, maintenance and other costs with vendor/payee, date, project/department, tax treatment, attachment, approval and payment status.

#### FIN-04 | Cash, banks and receivables [MUST]

Record cashboxes, bank accounts, customer invoices, receipts, outstanding balances, supplier payables where needed, payment allocations and bank/cash reconciliation basics.

#### FIN-05 | Inventory accounting integration [MUST]

Inventory purchases/receipts, cost of goods sold and stock adjustments must follow approved valuation and recognition rules; stock intake is not automatically an immediate operating expense.

#### FIN-06 | Partner accounts [MUST]

Track contributions, drawings, settlements and approved distributions, with partner-level ledger and supporting documents. Do not equate drawings with operating expenses.

#### FIN-07 | Budgets and statements [MUST]

Department/project budgets, actual-versus-budget, P&L, balance sheet, trial balance, general ledger, cash flow, receivables aging, expense breakdown and partner statements. Reports must drill down to source entries.

#### FIN-08 | Period close and reversals [MUST]

Lock closed periods by permission, use documented adjusting entries, and record approval for reopenings. Exports must reconcile to posted ledger.


<!-- End of PDF page 9 -->

### 8.2 Employee profile and limited payroll

#### EMP-01 | Employee 360 profile [MUST]

Identity, role, department, reporting line, hire date, employment status, documents, target history, performance evaluations and authorized administrative notes.

#### EMP-02 | Supervisor-managed workers [MUST]

Not every laborer/technician needs a login in Phase A. Supervisor can manage assignments and work evidence for non-login workers with attribution.

#### PAY-01 | Simple compensation [MUST]

Record base salary, commission rules, bonuses, advances, approved deductions and net payable summary. No attendance, biometric clock, comprehensive leave/permission or full HR suite in Phase A.

#### PAY-02 | Commission engine [MUST]

Link employee to eligible approved sales outcomes. Configurable future basis: contract, invoicing, collection, fixed amount, percentage or tiers. Prevent duplicate credit and recalculate/reverse on cancellation or refunds.

#### PAY-03 | Approval and payroll adjustment [MUST]

Accounting manager reviews compensation and executes deductions only on documented managerial request/approval; record effective period, amount, reason and audit history.

#### PAY-04 | Tax and insurance extensibility [MUST]

Allow structured deduction categories and configurable calculations when validated by local accountant. Do not hard-code Egyptian statutory rates without current legal verification and explicit sign-off.

### 8.3 Commission example (illustrative only)

Approved eligible sale -> attribution to sales agent -> policy-based eligibility evaluation -> accrued commission record -> finance review -> authorized payout or adjustment. The exact trigger (contract signature, invoice issuance, customer collection or completion) remains an OPEN business policy.

## 09 | Communication hub and customer privacy

#### COM-01 | Unified employee workspace [MUST]

Dashboard contains assigned work, calendar, targets, approvals, announcements, internal conversations, customer communications and action history.

#### COM-02 | Internal staff messaging [MUST]

One-to-one and group messages, CC/manager visibility where authorized, files, conversation history, notifications and retention. Separate internal conversations from customer communications.

#### COM-03 | Corporate email client [MUST]

Per-employee company mailbox via centrally managed provider configuration; send/receive, reply, forward, inbox, sent, drafts, attachments, CC/BCC and message linkage to customer/project. Store secrets encrypted; prefer OAuth/app passwords where available.

#### COM-04 | SMS integration [MUST]

Integrate SMS Misr or approved provider with authorized company sender identity, template controls, delivery status, audit and customer-record-based destination resolution.


<!-- End of PDF page 10 -->

#### COM-05 | Telegram optional [MUST]

Official Telegram bot/business integration as an optional channel. Document customer opt-in/initial contact and platform constraints; do not assume all customers use Telegram.

#### COM-06 | WhatsApp channel decision [MUST]

WhatsApp is a preferred customer channel in Egypt, but routine official Meta API usage is cost-sensitive. Meta marketing/campaign channel may be separate. Do NOT assume an approved low-cost alternative exists; keep pluggable connector and evaluate compliance, reliability, data retention and account-blocking risk before production rollout.

#### COM-07 | Message capture and access [MUST]

Store channel, customer ID, staff actor, message direction, content/media references, timestamps, provider IDs and delivery status where legally and technically available. Managers can review scoped history.

#### COM-08 | No exposed destination [MUST]

Authorized agents select a customer record and channel; backend sends to masked destination. Apply masking to message headers, quoted replies, logs, notifications, search and exports where controllable. Explicitly document limitations if customers reveal details in message content.

#### COM-09 | Channel failure handling [MUST]

Queue sends, retry safely with idempotency, record provider errors and notify responsible users. Never mark a message delivered solely because the API request was accepted.

### 9.1 WhatsApp architectural decision record

Do not build core operational availability on unsupported WhatsApp Web/QR automation without separate written risk acceptance. A 100-number-per-employee design is not a Phase A prerequisite. Model numbers/channels separately from agents, support one-to-many assignment, and preserve customer conversations independent of any provider.

## 10 | Executive command center, analytics and alerts

#### DASH-01 | Executive command center [P0] [MUST]

Director sees actionable, near-real-time company-wide overview: pipeline, contracted sales, recognized revenue, collection, expenses, cash, project margins, stock, deliveries, approvals, overdue work, staff targets and AI audit alerts.

#### DASH-02 | Role-specific dashboards [MUST]

Employee: my leads, tasks, targets and messages. Manager: team activity and exceptions. Finance: receivables, expenses and postings. Warehouse: fulfillment and variances. Project manager: progress, materials and margin.

#### DASH-03 | Actionable drill-down [MUST]

Every KPI and alert links to authorized underlying transactions, evidence and permitted next action. Define calculation, source and refresh timestamp; no decorative untraceable numbers.

#### DASH-04 | Alert engine [MUST]

Thresholds, overdue timers, stock shortages, stalled approvals, missed follow-ups, budget overruns, quality failures, commission anomalies and incomplete delivery evidence. Include severity, owner, acknowledgement, escalation and resolution.

#### DASH-05 | Reports and exports [MUST]

Filter by time, employee, department, customer, project and status. Export permitted data with field-level masking and access logs. Reports must reconcile to source-of-truth ledger and transaction states.


<!-- End of PDF page 11 -->

### 10.1 KPI definitions

```text
   Metric                            Definition / safeguard
   Sales achieved                    Value/count of transactions at configured approved commercial stage; exclude drafts
   Collection rate                   Collected eligible receivables divided by due eligible receivables for period
   Project margin                    Recognized revenue minus allocated actual direct costs, with explicit accounting policy
   Target attainment                 Approved eligible output divided by target, within target period
   Pending approvals                 Open actionable approval requests by responsible approver and age
   Inventory availability            On-hand less active reservations; never confuse with physical stock
   full AI Action Assistant backlog                  Unresolved findings by severity, module and owner
```

## 11 | AI Action Assistant, proactive intelligence and governed action execution

### 11.1 AI design mandate

Phase 1 MUST ship the full AI Action Assistant across the in-scope ERP domains: Arabic/English conversational text with RTL/LTR support, voice input with speech-to-text, governed ERP read access, typed allowlisted tools, analytics and explanations, action preview/confirmation/execution, Approval Engine integration, proactive operational detection, findings/inbox/notifications, permission-aware document retrieval, and provider/privacy/budget/kill-switch controls. All modules MUST emit structured auditable events and expose controlled read models. Sensitive actions remain deny-by-default and require server-side authorization, explicit confirmation and any applicable approval; live provider egress remains separately gated.

#### AI-01 | Deterministic rules before AI [MUST]

Validate authorization, required fields, balanced entries, stock limits, workflow transitions, approval thresholds, duplicate transaction keys and immutable accounting invariants in deterministic services.

#### AI-02 | AI review of priority transactions [MUST]

Review selected quotes, contracts, project cost variances, expenses, stock anomalies, delivery/QA evidence and commission exceptions. Flag missing clauses, contradictory dates, unusual values, inconsistent scope or suspicious patterns.

#### AI-03 | Explainable findings [MUST]

Every finding includes linked entity/version, trigger, evidence pointers, reasoning summary, severity, confidence/uncertainty indicator, recommended next step, status, reviewer and disposition.

#### AI-04 | Human review and no silent action [MUST]

AI may suggest hold/review but cannot independently approve payments, change balances, release inventory, alter commissions, disclose private customer data or execute irreversible actions.

#### AI-05 | Continuous + scheduled audit [MUST]

Support synchronous pre-approval advisory checks where latency permits and asynchronous event/periodic reviews. AI service outages must not bypass mandatory deterministic controls.

#### AI-06 | Data minimization and prompt security [MUST]

Only pass authorized necessary fields to model providers. Redact secrets/PII as feasible; treat documents and messages as untrusted input; prevent prompt injection from invoking privileged tools.


<!-- End of PDF page 12 -->

#### AI-07 | Model and rule version tracking [MUST]

Record provider/model identifier, prompt/rule version, inputs hash or secure reference, output, timestamps, costs, reviewer outcome and evaluation results for traceability.

#### AI-08 | Action-ready governed command interface [MUST]

Provide typed command schemas and domain APIs for Phase 1 chat and voice requests. Execution pipeline: authenticate -> interpret -> validate entities -> check RBAC -> deterministic business validation -> preview -> user confirmation -> approvals -> commit -> audit. Voice and conversational input never bypass typed validation, confirmation, approval or audit.

#### AI-09 | AI evaluation and tuning [MUST]

Maintain a labeled set of real or sanitized business cases; measure false positives, false negatives, latency and cost; allow directors to tune thresholds and disable specific audits safely.

### 11.2 Initial AI audit priority

```text
   Priority       Workflow                                 Sample finding
   P0             Financial expenses and commissions       Duplicate expense; inconsistent approval; commission awarded on
                                                           cancelled sale
   P0             Quotations/contracts                     Missing term, inconsistent totals, unusual discount or margin
   P0             Projects and inventory                   Material issue exceeds approved need; project cost overrun
   P1             Delivery and quality                     Missing signed delivery proof; incomplete inspection evidence
   P1             Lead follow-up and performance           Repeated missed follow-ups; anomalous conversion attribution
```

### 11.3 Future voice/chat example

"Record a transport expense of EGP 2,500 against Nile Company Project and submit it for director approval." The assistant must resolve the correct project, request missing proof, check finance rights, show an exact preview, create a draft/approval request only, and wait for authorized approval before posting or paying.

## 12 | Technical architecture and data design

### 12.1 Recommended baseline architecture

Use a modular monolith initially: one deployable backend with strongly separated business modules, domain services, policy enforcement and asynchronous workers. This is an architectural recommendation, not a mandated programming language or framework. Select the implementation stack during technical design and record the decision.

```text
   Layer                          Required responsibilities
   Clients                        Arabic RTL responsive web and mobile application; shared design system; secure session
                                  management
   API / application              Versioned authenticated APIs, policy enforcement, validation, idempotency and transaction
                                  boundaries
   Domain modules                 Identity, CRM, Sales, Projects, Warehouse, Delivery, QA, Finance, Employee, Communications,
                                  Documents, Workflow, AI
```


<!-- End of PDF page 13 -->

```text
   Layer                     Required responsibilities
   Shared services           Event outbox, notifications, search, approvals, audit log, attachments, report engine, background
                             jobs
   Data                      Relational transactional database; encrypted private object storage; cache/queue; analytics read
                             models as needed
   Integrations              SMTP/IMAP or mail provider API, SMS provider, optional Telegram, evaluated WhatsApp connector,
                             AI provider adapter
   Operations                CI/CD, automated tests, backups, metrics, logs, alerts, secret management and recovery drills
```

#### ARCH-01 | Module boundaries [MUST]

Each domain owns its entities and invariants. Cross-module updates occur through explicit service contracts and reliable events; prohibit arbitrary writes to another module tables.

#### ARCH-02 | Transactional outbox [MUST]

Publish business events reliably after database commit; consumers idempotent with retries and dead-letter handling. Avoid lost commission, alert or audit events.

#### ARCH-03 | Provider abstraction [MUST]

Email/SMS/Telegram/WhatsApp/AI connectors implement replaceable interfaces. Never bake provider-specific IDs into primary business identifiers.

#### ARCH-04 | Entity-ready schema [MUST]

Include controlled legal-entity dimension for future affiliates without SaaS tenant lifecycle or cross-company data leakage. Use separate company scope on accounting, inventory and projects when enabled.

#### ARCH-05 | Secure record linkage [MUST]

Stable IDs, referential integrity, controlled soft deletes, monetary decimal types, UTC timestamps plus local display, multi-warehouse location dimension and immutable posted financial movements.

#### ARCH-06 | Schema naming standard [MUST]

Use short explicit table/index/constraint/foreign-key identifiers, preferably under 60 characters for MySQL/MariaDB compatibility; verify identifier lengths in migrations and avoid gratuitous renames.

### 12.2 Key logical entities

Organization, LegalEntity, Department, Employee, Role, Permission, RoleAssignment, ApprovalPolicy, ApprovalRequest, AuditEvent, Customer, Lead, LeadAssignment, LeadActivity, Handover, Task, CalendarEvent, Target, TargetResult, Opportunity, Quotation, QuoteRevision, Contract, ContractRevision, SalesOrder, SupplyOrder, WorkOrder, Project, ProjectMilestone, ProjectBudget, ProjectCost, SKU, Warehouse, StockMovement, StockReservation, StockCount, Delivery, DeliveryNote, DeliveryProof, Inspection, QualityFinding, WarrantyRecord, ChartAccount, JournalEntry, JournalLine, Invoice, Payment, Expense, PartnerAccount, CompensationRule, CommissionAccrual, PayrollSummary, CommunicationChannel, Conversation, Message, Attachment, DocumentVersion, Notification, AIAuditFinding, AIReviewRun, IntegrationAccount.


<!-- End of PDF page 14 -->

### 12.3 Event contract examples

lead.assigned, lead.handover.requested, lead.handover.accepted, quote.approved, contract.signed, order.approved, stock.reserved, stock.issued, delivery.signed, project.milestone.completed, inspection.failed, invoice.posted, payment.received, commission.eligible, expense.approved, audit.finding.created. Each event must include event ID, version, entity ID, actor, timestamp, correlation ID and business payload with appropriate privacy controls.

## 13 | Nonfunctional requirements, security and QA

#### NFR-01 | Access and privacy [MUST]

Enforce server-side RBAC, object-level authorization, sensitive-field masking, encrypted transport and at-rest secrets, least-privilege service accounts, rate limiting and session revocation.

#### NFR-02 | Operational integrity [MUST]

All money and stock mutations are transactional, idempotent and reversible via compensating entries; prevent double-submit, race conditions and negative stock where policy forbids it.

#### NFR-03 | Reliability and recoverability [MUST]

Scheduled encrypted backups, tested restore procedure, documented RPO/RTO targets, queue monitoring and provider-failure handling. Final numeric targets remain OPEN for infrastructure planning.

#### NFR-04 | Performance [MUST]

Define and test realistic concurrency and data volumes. Interactive workflows should feel responsive; heavy reports and AI reviews should use asynchronous jobs and visible progress.

#### NFR-05 | Mobile field usability [MUST]

Support camera/photo upload, signature capture, intermittent connectivity handling and safe retries; do not promise offline transaction posting without conflict-resolution design.

#### NFR-06 | Arabic RTL and accessibility [MUST]

Right-aligned Arabic content, correct bidi behavior for numbers/emails, readable tables, keyboard access, mobile responsiveness, localized errors and notifications; architecture supports future English.

#### NFR-07 | Observability [MUST]

Structured logs without sensitive leakage, traces, operational metrics, audit/queue dashboards, error alerts and integration health checks.

#### NFR-08 | Data retention and compliance [MUST]

Define record retention, employment data privacy, customer consent, communication policies, financial archiving and lawful access in collaboration with Arasya legal/accounting owners.

### 13.1 I.S. QA Standard - delivery gates

```text
   Gate                        Minimum checks
   Fast QA (every change)      Static analysis, formatting/lint, focused tests, permission tests, migrations/schema checks, key
                               workflow smoke
   Release QA                  Full automated suite, regression, API contract, data migrations, security/privacy tests,
                               backup/restore readiness, RTL/mobile review
   Deep Audit (major change)   Architecture review, CodeGraph/AST-assisted analysis where compatible, finance/stock invariants,
                               approval bypass, data leakage, AI prompt/tool security and disaster recovery
```


<!-- End of PDF page 15 -->

For PHP/Laravel implementations, use PHPStan/Larastan and appropriate unit/integration tooling; use ast-grep/CodeGraph where supported. The exact stack must match the selected architecture. All releases require evidence of test execution, not unverified statements.

### 13.2 Admin UX baseline

Apply the I.S. SKIT Admin Standard to administrative/backoffice dashboards: structured collapsible sidebar, grouped navigation, clean topbar, profile/status controls, KPI cards, professional data tables, filters, responsive layouts and light/dark readiness. Public customer-facing pages are outside this admin standard.

## 14 | Phased delivery and acceptance

### 14.1 Recommended implementation increments

```text
   Increment            Delivery focus                                               Exit evidence
   A0 Foundation        Identity, RBAC, audit, approvals, shared documents,          Access/approval tests; audited
                        configuration, Arabic shell, event infrastructure            transactions; backup and monitoring
   A1 CRM +             Lead pool, assignment/handover, calendar/tasks, internal     End-to-end lead follow-up, hidden contacts,
   Workspace            messaging, targets, email/SMS base                           handover acceptance
   A2 Commercial +      Quotes, contracts, orders, project cost/budget, field work   Approved quote-to-project traceability and
   Projects             and milestone tracking                                       project margin
   A3 Warehouse +       Stock ledger, multi-warehouse-ready operations, proof of     Reconciled stock and signed
   Delivery + QA        delivery, field QA, warranty foundation                      delivery-to-acceptance path
   A4 Finance +         Double-entry, expenses, cash/bank, receivables, partner      Balanced ledger, reports reconciliation and
   Compensation         accounts, basic salary/commission                            approved payroll adjustments
   A5 Executive + AI    Executive command center, alerts, audit finding              AI findings reviewed, source-linked KPIs,
   Audit                workflow, priority AI checks, hardening                      security/acceptance sign-off
 Sequencing note: Foundational finance and stock models must be designed in A0 even if their complete
 user-facing modules ship later. The AI event/audit foundation begins in A0; practical AI auditing is accepted in A5
 and can be piloted earlier.
```


<!-- End of PDF page 16 -->

### 14.2 End-to-end acceptance scenarios

```text
   ID         Scenario                                              Pass criteria
   AT-01      Sales agent attempts to view another agent's lead     Denied; no leaked contact value; security event recorded.
              or retrieve hidden phone via API/export
   AT-02      Agent requests lead transfer; recipient has not       Original owner retains ownership and follow-up
              accepted                                              accountability; pending transfer is visible.
   AT-03      Manager approves quotation and supply order           Correct order and downstream tasks created once; full
                                                                    approval and revision history.
   AT-04      Warehouse reserves then releases stock                Reservation does not reduce on-hand; physical issue
                                                                    updates stock ledger once; no double issue.
   AT-05      Driver obtains customer signed delivery note          Signed proof attached to delivery; discrepancy workflow
                                                                    available; responsible users notified.
   AT-06      Installation team completes project milestone         Evidence/QA recorded; project costs and margin recalculate
                                                                    from source records.
   AT-07      Finance records expense and posts entry               Required approval enforced; balanced posting; audit and
                                                                    project allocation consistent.
   AT-08      Approved sale qualifies for commission, later         Accrual generated once and reversed/adjusted per policy;
              cancelled                                             employee statement traceable.
   AT-09      Director opens dashboard alert                        KPI value reconciles to records; drill-down shows authorized
                                                                    cause and action.
   AT-10      AI detects duplicate expense                          Explainable finding created; human reviewer resolves; AI
                                                                    cannot post or delete records.
   AT-11      Email/SMS provider fails during send                  Message stays in correct pending/failed state, retries
                                                                    idempotently, operator sees error.
   AT-12      Employee is deactivated                               Sessions revoked; records, documents and communications
                                                                    preserved; work reassigned.
   AT-13      User accesses Arabic mobile view                      RTL layouts, numerals, tables, dialogs and forms remain
                                                                    usable without overflow.
   AT-14      Unauthorized user attempts to alter approval status   Server rejects transition; audit captures attempt; no state
              directly                                              change.
   AT-15      Month-end finance reporting                           Trial balance reconciles; balance sheet and P&L derive from
                                                                    posted entries; locked period protected.
```

### 14.3 Definition of Done

- Requirement implemented and mapped to an acceptance test and product owner sign-off.
- Permission, masking, audit and approval tests cover both happy path and abuse cases.
- Database migration tested with rollback/forward strategy; no unapproved production migration.
- Arabic RTL responsive UI verified on desktop and mobile.
- Relevant reports reconcile to transactional sources and show data freshness.
- Operational monitoring, backup, documentation and support/runbook updated.

<!-- End of PDF page 17 -->

- AI-specific functionality includes evaluation examples, failure handling and reviewer controls.
## 15 | Open decisions, risks and governance

### 15.1 Business decisions that MUST be resolved before relevant module build

```text
   ID               Decision required                                                      Owner / impact
   OPEN-01          Commission eligibility trigger: contract signed vs invoiced vs         Executive + Finance / compensation
                    collected vs completed; reversals, splits and thresholds
   OPEN-02          Approval thresholds and chains by transaction, amount,                 Executive / workflow
                    department, role and emergency override
   OPEN-03          Chart of accounts, tax treatment, fiscal periods, partner structure    Finance / ledger
                    and accounting policies
   OPEN-04          Inventory valuation method, SKU units, conversion, batch/roll          Warehouse + Finance / inventory
                    handling, negative-stock policy
   OPEN-05          Project revenue recognition, direct cost allocation, overhead and      Finance + Operations / project
                    profitability formula                                                  reporting
   OPEN-06          Minimum production tracking scope and material transformation          Operations / manufacturing boundary
                    rules for Phase A
   OPEN-07          Customer communication channels: mail provider, SMS Misr               Management + IT / integration
                    account, Telegram pilot and compliant WhatsApp path
   OPEN-08          Mobile delivery approach (PWA vs native/hybrid), device support,       Engineering / mobile
                    field offline policy
   OPEN-09          AI provider, data-processing constraints, budget, review categories    Executive + Security / AI
                    and audit thresholds
   OPEN-10          Legal/finance record retention, customer consent and employee          Legal + HR/Finance / compliance
                    document access policy
   OPEN-11          Production hosting, availability targets, RPO/RTO, initial data        IT + Engineering / operations
                    migration and expected user counts
   OPEN-12          Project acceptance checklists, warranty terms and sign-off authority   Operations + Quality / delivery
```


<!-- End of PDF page 18 -->

### 15.2 Key delivery risks

```text
   Risk                                  Required mitigation
   Scope inflation                       Protect Phase A boundaries; change-control requests and deferred backlog
   Customer privacy leakage              Object and field-level authorization, export tests, masked communications, security
                                         review
   Incorrect financial reporting         Double-entry source of truth, accounting sign-off, reconciliation acceptance
   AI hallucinations or false findings   Deterministic gates, evidence-linked findings, human review, evaluation and kill
                                         switches
   WhatsApp dependency/cost              Channel adapter, provider assessment, no unsupported dependency for core operations
   Cross-module inconsistency            Transactional outbox, idempotency, reversal semantics and integration tests
   Mobile field reliability              Retry-safe workflows, uploads, explicit offline limitation and device testing
   Unclear manufacturing boundary        Explicit minimal-scope decision before implementation
```

### 15.3 Governance and handoff instructions

Engineering team MUST: (1) produce a solution architecture and ERD mapped to this PRD; (2) write API contracts, workflow diagrams and permission matrix before implementation; (3) identify all OPEN policies with business owners; (4) implement in gated increments with tests; (5) present demo and acceptance evidence per increment; (6) document deviations in an approved change log. Do not interpret recommendations as authorization to silently expand scope.

Product owner MUST: approve policy decisions, screen/workflow prototypes, prioritized release backlog, financial calculation definitions, and each release acceptance report.

### 15.4 Final success criteria

- A director can see what is happening across the company, understand why, and act on exceptions from one command center.
- Employees can execute authorized work without uncontrolled access to customer contact details or company data.
- Every commercial commitment, material movement, project cost, financial posting and commission has a traceable origin and approval history.
- The system can introduce advanced manufacturing, purchasing, customer services and safe AI commands without re-platforming.
- The initial release remains operationally practical and supportable, rather than becoming an overbuilt ERP.
END OF MASTER PRD - VERSION 1.0


<!-- End of PDF page 19 -->

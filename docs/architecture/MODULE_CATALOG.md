# Module ownership — Proposed
كل صف مالك وحيد للكتابة؛ القراءة cross-module عبر contract scoped أو projection محلي. لا shared mutable Eloquent model. أسماء المسارات المقترحة في backlog لا تحسم packages.

| module | aggregates / مسؤولية | contract المستهلك والحد |
|---|---|---|
| Identity | roles/permissions/assignments/sessions | authorize/revoke؛ ليس صلاحية تجاوز finance |
| Organization | legal entities/departments/sites/units/numbering | scoped reference lookup؛ لا SaaS lifecycle |
| Workflow | ApprovalPolicy/ApprovalRequest/history | request/decision؛ التنفيذ النهائي في domain مالكه |
| Audit | append-only AuditEvent | append/read scoped؛ لا secrets/raw contacts |
| Documents | Attachment/DocumentVersion | authorize upload/download؛ business owner يقرر visibility |
| CRM | Customer/Lead/Assignment/Activity/Handover/Opportunity | assign/acknowledge/resolve recipient داخلياً |
| Tasks | Task/CalendarEvent/Notification | create reminder/complete evidence |
| Performance | Target/TargetResult/snapshots | approved-event projection؛ لا payroll deduction |
| Sales | Quotation/Revision/Contract/Revision/SalesOrder/SupplyOrder | approve/revise؛ لا stock issue أو revenue تلقائي |
| Projects | Project/Milestone/Budget/Cost/WorkOrder | schedule/record progress/closure؛ costs من source |
| Inventory | SKU/Warehouse/Movement/Reservation/Count | reserve/issue/return؛ يملك on-hand |
| Delivery | Delivery/Note/Proof/custody | dispatch/sign/discrepancy؛ لا issue مباشر |
| Quality | Inspection/Finding/WarrantyRecord | inspect/accept؛ لا customer portal |
| Production | limited output/consumption reference | استدعاء Inventory عند OPEN-06؛ لا MRP |
| Finance | ChartAccount/Journal/Invoice/Payment/Expense/PartnerAccount | post/reverse/close؛ authority ledger |
| Employees | Employee/non-login worker/profile | attribution/offboard coordination؛ Identity يلغي access |
| Compensation | CompensationRule/CommissionAccrual/PayrollSummary | accrue/reverse/approve adjustment؛ Finance يدفع |
| Communications | Channel/Conversation/Message/IntegrationAccount | send by customer ID؛ provider IDs ثانوية |
| Reporting | KPI/read models/alerts/exports | authorized projection؛ لا authoritative mutation |
| AI | ReviewRun/AuditFinding/evaluation | review/disposition؛ no financial tools |

Shared infrastructure: Clock/IDs/Money وoutbox transport وprovider interfaces. Audit ليس AI audit: الأول حتمي ودائم والثاني findings استشارية. WorkOrder تحت Projects، SupplyOrder تحت Sales، وكل مرجع بينهما typed contract لا كتابة مشتركة. دورة تحديث متعارضة تُحل orchestration/compensation لا circular domain dependencies.
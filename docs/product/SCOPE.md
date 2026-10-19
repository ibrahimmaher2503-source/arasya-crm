# نطاق Phase A
Confirmed من PRD: نشر خاص لشركة واحدة، Arabic RTL، تجربة web/mobile مؤمنة، RBAC/audit/approvals، CRM، أهداف وتقويم، مبيعات ومشروعات، مستودع وتسليم وQA، مالية وتعويضات محدودة، اتصالات، dashboards وAI audit استشاري فعلي.

| increment | ناتج قابل للعرض | بوابة الخروج |
|---|---|---|
| A0 | هوية وصلاحيات وتدقيق وموافقات ووثائق وRTL وoutbox؛ تصميم finance/stock مبكراً | رفض غير المصرح، انتقال مدقق، restore تجريبي |
| A1 | CRM/handover/tasks/targets/internal messaging/email/SMS | تسليم ملكية بالإقرار، اتصال مخفي، فشل provider آمن |
| A2 | quotes/contracts/orders/projects | التزام معتمد مرتبط بمشروع وتكلفة |
| A3 | inventory/delivery/QA | ledger مخزون متصالح وPOD وتوثيق قبول |
| A4 | ledger/receivables/expenses/partners/compensation | توازن القيود وعكس العمولة وإغلاق الفترة |
| A5 | command center/full AI Action Assistant/hardening | مساعد عربي/إنجليزي نصي وصوتي، أدوات ERP محكومة، كشف استباقي، اقتراحات وتنفيذ مؤكد وموافق عليه، وأدلة AT |

Foundation فقط: production output/consumption عند الحاجة، stock intake، salary summary، entity extension. لا procurement/RFQ، MRP/BOM/routing، HR attendance/biometrics، fleet optimization/GPS، SaaS signup/billing، customer portal/loyalty. يشمل Phase 1 المساعد النصي والصوتي، لكن كل تنفيذ حساس يبقى typed ومؤكداً ومفوضاً وموافقاً عليه؛ لا autonomous execution. QA-02 يعني warranty records لا portal.

كل إضافة تقدم change request يتضمن المرجع والمالك والكلفة والأثر على DAG والاختبارات والقرارات؛ يُرفض إخفاء scope جديد تحت اسم foundation. لا يؤخر ترتيب A4 تصميم invariants المالية في A0. لا تُعرض قيمة actual margin مع سياسة OPEN-05 غير معتمدة.

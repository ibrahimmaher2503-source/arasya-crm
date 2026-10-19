# baseline صلاحيات — Proposed حتى OPEN-02/10
Deny by default. الرموز R قراءة scoped، W إنشاء/طلب انتقال، A اعتماد ضمن policy، C إعداد، — منع. كل سماح مقيد entity/department/assignment وfield scope؛ لا تعني executive رؤية أسرار أو contacts تلقائياً.

| Actor | IAM | CRM/contact | Sales | Project | Stock/Delivery | Finance/payroll | QA | Messaging | AI |
|---|---|---|---|---|---|---|---|---|---|
| Super Administrator | C | — | — | — | — | — | — | C accounts دون content | C دون بيانات |
| Director | R roles | R scoped/masked | A | A | A high risk | A لا posting تلقائي | R | R scoped | R/review |
| Department Manager | R team | W assign/masked | A scoped | W scoped | R | R budget masked | R | R team | review scoped |
| Sales / Marketing | — | W assigned/masked | W assigned | R linked | R linked | R authorized invoice | — | W assigned masked | R own findings |
| Finance Manager | — | R minimum/masked | R approved | R cost | R ledger | W posting/reversal | R acceptance | W authorized | review finance |
| Warehouse Officer | — | — | R approved order | R material | W assigned | — | R discrepancy | W internal | R stock findings |
| Project Manager | — | R linked/masked | R scope | W own projects | W request لا issue | R project cost | W request | W linked | review project |
| Field Supervisor / Driver | — | R job destination minimum | — | W assigned evidence | W custody/POD لا adjustment | — | W evidence | W assigned | — |
| Quality / Service | — | R linked/masked | R acceptance terms | R scope | R delivery | — | W inspections | W linked | review QA |

record scope يطبق في SQL/query وAPI وjobs وexports وsearch counts. field grants منفصلة: contact.reveal، employee.private، compensation.read، finance.post؛ default masking للphone/WhatsApp/email والرواتب والوثائق الطبية/العمالية. customer-selected outbound يحل destination في backend. أي contact reveal بسبب عمل مشروع يحتاج explicit grant + reason + audit؛ لا يُشتق من role عام.

SoD: requester لا approver في العمليات الحساسة؛ warehouse release مستقل عن request، finance posting يتطلب approval صالح لنفس version/amount/entity؛ manager deduction موثق والfinance ينفذه. التفويض محدد المدة والنطاق ولا يوسع صلاحيات الأصل. break-glass disabled حتى سياسة OPEN-02: dual authorization، سبب/مدة/تنبيه ومراجعة لاحقة، وليس صلاحية Super Admin افتراضية.

اختبار كل خلية مسموحة وممنوعة عبر own/other department/other entity/unassigned/revoked؛ تحقق أن masking واحد في UI/API/export/log/notification/download/header/quoted reply حيث قابل للتحكم. قيود محتوى يكتبه العميل توثق ولا يدّعى إخفاؤها كاملاً. offboarding يسحب الجلسات والـtokens والjobs scopes ويحتفظ بالسجلات.
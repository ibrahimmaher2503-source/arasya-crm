# سجل المخاطر — baseline Proposed
الاحتمال/الأثر تقدير أولي H/M/L لا تقييم معتمد. الحالة لجميع البنود Open؛ يراجع المالك عند كل increment ويضيف evidence وresidual acceptance.

| ID | خطر / trigger | likelihood/impact | owner | mitigation / دليل الإغلاق |
|---|---|---|---|---|
| R01 | scope creep بإضافة MRP/HR/portal | H/H | Product | CR معتمد وscope review لكل demo |
| R02 | contact leak عبر export/search/header | H/H | Security | AT-01 وجميع masking surfaces اختبرت |
| R03 | posted ledger/report غير متوازن | M/H | Finance | OPEN-03/05 وAT-07/15 reconciliation |
| R04 | AI false finding أو privileged execution | H/H | Security | no-tools/eval/human review وAT-10 |
| R05 | WhatsApp block/cost/unsupported channel | H/H | IT | OPEN-07 وعزل connector وعدم اعتماد core عليه |
| R06 | duplicate/lost event أو race | M/H | Engineering | crash/replay/concurrency evidence وinbox/outbox |
| R07 | mobile connectivity/POD lost | H/M | Operations | OPEN-08 وdevice retries وAT-05/13 |
| R08 | production يتحول MRP | H/M | Operations | OPEN-06 minimal scope sign-off |
| R09 | restore غير قابل للتنفيذ | M/H | IT | OPEN-11 measured restore وليس backup فقط |
| R10 | agent overwrite أو parallel overlap | M/H | Engineering | single coordinator/leases/hash conflict tests |
| R11 | OPEN سياسة تأخرت فتوقف critical path | H/H | Product | evidence tasks أولاً وتصعيد owner عند بوابة البناء |
| R12 | retention يخالف legal/privacy | M/H | Legal | OPEN-10 matrix وتحقق access/archive |
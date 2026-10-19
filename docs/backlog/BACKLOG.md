# Backlog التنفيذي

المصدر الوحيد للحالة [tasks.json](tasks.json)، و[schema](TASK_SCHEMA.md) يحدد lifecycle. جدول الفهرس أدناه لقطة مولّدة؛ حدّث status columns بعد أي تغيير عبر `& './scripts/task-loop.ps1' -Action RefreshBacklog`. العدد 275 مهمة XS/S؛ الحالات الحالية من tasks.json وtask-loop Status. وصف عدم وجود تطبيق أو مهام done يعود إلى اللقطة الأصلية ولا يصف الحالة الحالية. كثرة المهام لا تبيح اعتماد decisions أو حجم أكبر من جلسة؛ split قبل claim عند الحاجة.

## الترتيب والـ critical path
Decision briefs وADR reviews وتصميم finance/stock في A0 قابلة للبدء؛ scaffold محجوب بـ ADR-001/002/008. بعدها identity/SoD/approvals/audit/outbox/documents/RTL. A1 CRM/handover/workspace/targets/comms وemployee profile؛ A2 Sales ثم Projects؛ A3 Inventory ثم Delivery ثم QA؛ A4 Finance ثم Compensation؛ A5 Reporting/AI/security hardening.

Critical path: stack approval → scaffold → RBAC/approval/outbox → CRM conversion → approved Sales → Inventory/POD/QA → finance reconciliation/commission → final acceptance. Actual margin يتوقف على OPEN-05 مبكراً؛ stock/finance models مصممة A0 لا تنتظر UI A3/A4. Gates تجمع أدلة increment ولا تعني حظر تصميم module لاحق مستقلاً؛ release لا يتجاوز gate سابقة دون موافقة موثقة.

## Parallel lanes
Decision briefs المنفصلة يمكن توازيها مع ADR evidence/design. بعد prerequisites يمكن CRM/Employees/Tasks في modules منفصلة؛ بعد Sales يمكن Projects وInventory؛ Finance implementations يمكن إعدادها بالتوازي بعد contracts/decisions دون تجاوز acceptance reconciliation. كل epic متسلسل داخلياً لحماية schema وshared scope؛ parallel_group اقتراح فقط. لا توازي في shared router/config/migrations/package lock أو نفس aggregate. coordinator واحد للحالة وleases إلزامية.

| increment | tasks | حالة البدء |
|---|---|---|
| A0 | 72 | راجع status/depends_on/decision_dependencies وقت claim |
| A1 | 67 | راجع status/depends_on/decision_dependencies وقت claim |
| A2 | 25 | راجع status/depends_on/decision_dependencies وقت claim |
| A3 | 27 | راجع status/depends_on/decision_dependencies وقت claim |
| A4 | 29 | راجع status/depends_on/decision_dependencies وقت claim |
| A5 | 52 | راجع status/depends_on/decision_dependencies وقت claim |

## فهرس المهام

| ID | title | status | predecessors |
|---|---|---|---|
| A0-OPEN-01 | جمع أدلة OPEN-01 وعرض البدائل على المالك | done |  |
| A0-OPEN-02 | جمع أدلة OPEN-02 وعرض البدائل على المالك | done |  |
| A0-OPEN-03 | جمع أدلة OPEN-03 وعرض البدائل على المالك | done |  |
| A0-OPEN-04 | جمع أدلة OPEN-04 وعرض البدائل على المالك | done |  |
| A0-OPEN-05 | جمع أدلة OPEN-05 وعرض البدائل على المالك | done |  |
| A0-OPEN-06 | جمع أدلة OPEN-06 وعرض البدائل على المالك | done |  |
| A0-OPEN-07 | جمع أدلة OPEN-07 وعرض البدائل على المالك | done |  |
| A0-OPEN-08 | جمع أدلة OPEN-08 وعرض البدائل على المالك | done |  |
| A0-OPEN-09 | جمع أدلة OPEN-09 وعرض البدائل على المالك | done |  |
| A0-OPEN-10 | جمع أدلة OPEN-10 وعرض البدائل على المالك | done |  |
| A0-OPEN-11 | جمع أدلة OPEN-11 وعرض البدائل على المالك | done |  |
| A0-OPEN-12 | جمع أدلة OPEN-12 وعرض البدائل على المالك | done |  |
| A0-ADR-001 | تقييم ADR-001 وتوثيق دليل الاعتماد | done |  |
| A0-ADR-002 | تقييم ADR-002 وتوثيق دليل الاعتماد | done |  |
| A0-ADR-003 | تقييم ADR-003 وتوثيق دليل الاعتماد | done |  |
| A0-ADR-004 | تقييم ADR-004 وتوثيق دليل الاعتماد | done |  |
| A0-ADR-005 | تقييم ADR-005 وتوثيق دليل الاعتماد | done |  |
| A0-ADR-006 | تقييم ADR-006 وتوثيق دليل الاعتماد | done |  |
| A0-ADR-007 | تقييم ADR-007 وتوثيق دليل الاعتماد | done |  |
| A0-ADR-008 | تقييم ADR-008 وتوثيق دليل الاعتماد | done |  |
| A0-ARCH-900 | تصميم invariants المالية والمخزون وعقود الأثر | done |  |
| A0-ARCH-901 | إنشاء Laravel skeleton بعد اعتماد stack | done | A0-ARCH-900, A0-ADR-001, A0-ADR-002, A0-ADR-008 |
| A0-ARCH-902 | تهيئة outbox publisher وconsumer receipt primitive | done | A0-ARCH-901 |
| A0-CORE-900 | إنشاء shell عربي RTL قابل للوصول | done | A0-ARCH-901 |
| A0-SEC-001 | بناء slice: الصلاحيات الموروثة واستثناء الموظف | done | A0-ARCH-901 |
| A0-SEC-002 | ربط واختبار slice: الصلاحيات الموروثة واستثناء الموظف | done | A0-SEC-001, A0-CORE-900 |
| A0-SEC-003 | بناء slice: حجب حقول الاتصال | done | A0-ARCH-901, A0-SEC-002 |
| A0-SEC-004 | ربط واختبار slice: حجب حقول الاتصال | done | A0-SEC-003, A0-CORE-900 |
| A0-SEC-005 | بناء slice: انتقال طلب الموافقة | done | A0-ARCH-901, A0-SEC-004 |
| A0-SEC-006 | ربط واختبار slice: انتقال طلب الموافقة | blocked | A0-SEC-005, A0-CORE-900 |
| A0-SEC-007 | بناء slice: منع self-approval | blocked | A0-ARCH-901, A0-SEC-006 |
| A0-SEC-008 | ربط واختبار slice: منع self-approval | blocked | A0-SEC-007, A0-CORE-900 |
| A0-SEC-009 | بناء slice: append-only audit | planned | A0-ARCH-901, A0-SEC-008 |
| A0-SEC-010 | ربط واختبار slice: append-only audit | planned | A0-SEC-009, A0-CORE-900 |
| A0-SEC-011 | بناء slice: إلغاء وصول الموظف | planned | A0-ARCH-901, A0-SEC-010 |
| A0-SEC-012 | ربط واختبار slice: إلغاء وصول الموظف | planned | A0-SEC-011, A0-CORE-900 |
| A0-CORE-001 | بناء slice: مراجع الشركة والتنظيم | done | A0-ARCH-901 |
| A0-CORE-002 | ربط واختبار slice: مراجع الشركة والتنظيم | done | A0-CORE-001, A0-CORE-900 |
| A0-CORE-003 | بناء slice: stable record linkage | done | A0-ARCH-901, A0-CORE-002 |
| A0-CORE-004 | ربط واختبار slice: stable record linkage | done | A0-CORE-003, A0-CORE-900 |
| A0-CORE-005 | بناء slice: حارس transition مشترك | done | A0-ARCH-901, A0-CORE-004 |
| A0-CORE-006 | ربط واختبار slice: حارس transition مشترك | done | A0-CORE-005, A0-CORE-900 |
| A0-CORE-007 | بناء slice: بحث scoped | done | A0-ARCH-901, A0-CORE-006 |
| A0-CORE-008 | ربط واختبار slice: بحث scoped | blocked | A0-CORE-007, A0-CORE-900 |
| A0-DOC-001 | بناء slice: ربط وثيقة ونسختها بالمالك | blocked | A0-ARCH-901 |
| A0-DOC-002 | ربط واختبار slice: ربط وثيقة ونسختها بالمالك | blocked | A0-DOC-001, A0-CORE-900 |
| A0-DOC-003 | بناء slice: quarantine وتنزيل خاص | blocked | A0-ARCH-901, A0-DOC-002 |
| A0-DOC-004 | ربط واختبار slice: quarantine وتنزيل خاص | blocked | A0-DOC-003, A0-CORE-900 |
| A0-CORE-009 | بناء slice: task/reminder/exception record | planned | A0-ARCH-901, A0-CORE-008 |
| A0-CORE-010 | ربط واختبار slice: task/reminder/exception record | planned | A0-CORE-009, A0-CORE-900 |
| A1-CRM-001 | بناء slice: إدخال lead والتحقق من التكرار | done | A0-ARCH-901, A0-SEC-002, A0-ARCH-902 |
| A1-CRM-002 | ربط واختبار slice: إدخال lead والتحقق من التكرار | done | A1-CRM-001, A0-CORE-900 |
| A1-CRM-003 | بناء slice: استعلام leads المسندة | done | A0-ARCH-901, A1-CRM-002, A0-SEC-002, A0-ARCH-902 |
| A1-CRM-004 | ربط واختبار slice: استعلام leads المسندة | done | A1-CRM-003, A0-CORE-900 |
| A1-CRM-005 | بناء slice: taxonomy version history | done | A0-ARCH-901, A1-CRM-004, A0-SEC-002, A0-ARCH-902 |
| A1-CRM-006 | ربط واختبار slice: taxonomy version history | done | A1-CRM-005, A0-CORE-900 |
| A1-CRM-007 | بناء slice: activity مع follow-up | done | A0-ARCH-901, A1-CRM-006, A0-SEC-002, A0-ARCH-902 |
| A1-CRM-008 | ربط واختبار slice: activity مع follow-up | done | A1-CRM-007, A0-CORE-900 |
| A1-CRM-009 | بناء slice: acknowledgement handover | done | A0-ARCH-901, A1-CRM-008, A0-SEC-002, A0-ARCH-902 |
| A1-CRM-010 | ربط واختبار slice: acknowledgement handover | done | A1-CRM-009, A0-CORE-900 |
| A1-CRM-011 | بناء slice: conversion identity link | done | A0-ARCH-901, A1-CRM-010, A0-SEC-002, A0-ARCH-902 |
| A1-CRM-012 | ربط واختبار slice: conversion identity link | done | A1-CRM-011, A0-CORE-900 |
| A1-TASK-001 | بناء slice: تقويم وتغيير الموعد | done | A0-ARCH-901, A0-SEC-002, A0-ARCH-902 |
| A1-TASK-002 | ربط واختبار slice: تقويم وتغيير الموعد | done | A1-TASK-001, A0-CORE-900 |
| A1-KPI-001 | بناء slice: تعريف target متعدد | done | A0-ARCH-901, A0-SEC-002, A0-ARCH-902 |
| A1-KPI-002 | ربط واختبار slice: تعريف target متعدد | done | A1-KPI-001, A0-CORE-900 |
| A1-KPI-003 | بناء slice: projection للنتائج المعتمدة | done | A0-ARCH-901, A1-KPI-002, A0-SEC-002, A0-ARCH-902 |
| A1-KPI-004 | ربط واختبار slice: projection للنتائج المعتمدة | done | A1-KPI-003, A0-CORE-900 |
| A1-KPI-005 | بناء slice: snapshot trajectory alert | done | A0-ARCH-901, A1-KPI-004, A0-SEC-002, A0-ARCH-902 |
| A1-KPI-006 | ربط واختبار slice: snapshot trajectory alert | done | A1-KPI-005, A0-CORE-900 |
| A1-KPI-007 | بناء slice: عرض achievement history | done | A0-ARCH-901, A1-KPI-006, A0-SEC-002, A0-ARCH-902 |
| A1-KPI-008 | ربط واختبار slice: عرض achievement history | done | A1-KPI-007, A0-CORE-900 |
| A2-SALE-001 | بناء slice: quote revision وحساب totals | planned | A0-ARCH-901, A0-SEC-002, A0-ARCH-902, A1-CRM-012, A0-SEC-006 |
| A2-SALE-002 | ربط واختبار slice: quote revision وحساب totals | planned | A2-SALE-001, A0-CORE-900 |
| A2-SALE-003 | بناء slice: contract version والمرفقات | planned | A0-ARCH-901, A2-SALE-002, A0-SEC-002, A0-ARCH-902, A1-CRM-012, A0-SEC-006 |
| A2-SALE-004 | ربط واختبار slice: contract version والمرفقات | planned | A2-SALE-003, A0-CORE-900 |
| A2-SALE-005 | بناء slice: approved supply/work order | planned | A0-ARCH-901, A2-SALE-004, A0-SEC-002, A0-ARCH-902, A1-CRM-012, A0-SEC-006 |
| A2-SALE-006 | ربط واختبار slice: approved supply/work order | planned | A2-SALE-005, A0-CORE-900 |
| A2-SALE-007 | بناء slice: عزل approval عن stock/revenue | planned | A0-ARCH-901, A2-SALE-006, A0-SEC-002, A0-ARCH-902, A1-CRM-012, A0-SEC-006 |
| A2-SALE-008 | ربط واختبار slice: عزل approval عن stock/revenue | planned | A2-SALE-007, A0-CORE-900 |
| A2-SALE-009 | بناء slice: revision reconciliation request | planned | A0-ARCH-901, A2-SALE-008, A0-SEC-002, A0-ARCH-902, A1-CRM-012, A0-SEC-006 |
| A2-SALE-010 | ربط واختبار slice: revision reconciliation request | planned | A2-SALE-009, A0-CORE-900 |
| A2-PRJ-001 | بناء slice: project aggregate والمراجع | planned | A0-ARCH-901, A0-SEC-002, A0-ARCH-902, A2-SALE-006 |
| A2-PRJ-002 | ربط واختبار slice: project aggregate والمراجع | planned | A2-PRJ-001, A0-CORE-900 |
| A2-PRJ-003 | بناء slice: قراءة margin من مصادره | blocked | A0-ARCH-901, A2-PRJ-002, A0-SEC-002, A0-ARCH-902, A2-SALE-006 |
| A2-PRJ-004 | ربط واختبار slice: قراءة margin من مصادره | blocked | A2-PRJ-003, A0-CORE-900 |
| A2-PRJ-005 | بناء slice: field work order evidence | planned | A0-ARCH-901, A2-PRJ-004, A0-SEC-002, A0-ARCH-902, A2-SALE-006 |
| A2-PRJ-006 | ربط واختبار slice: field work order evidence | planned | A2-PRJ-005, A0-CORE-900 |
| A2-PRJ-007 | بناء slice: custody reconciliation | planned | A0-ARCH-901, A2-PRJ-006, A0-SEC-002, A0-ARCH-902, A2-SALE-006 |
| A2-PRJ-008 | ربط واختبار slice: custody reconciliation | planned | A2-PRJ-007, A0-CORE-900 |
| A2-PRJ-009 | بناء slice: milestone variance | planned | A0-ARCH-901, A2-PRJ-008, A0-SEC-002, A0-ARCH-902, A2-SALE-006 |
| A2-PRJ-010 | ربط واختبار slice: milestone variance | planned | A2-PRJ-009, A0-CORE-900 |
| A2-PRJ-011 | بناء slice: closure preflight | blocked | A0-ARCH-901, A2-PRJ-010, A0-SEC-002, A0-ARCH-902, A2-SALE-006 |
| A2-PRJ-012 | ربط واختبار slice: closure preflight | blocked | A2-PRJ-011, A0-CORE-900 |
| A3-INV-001 | بناء slice: SKU وwarehouse scoped balances | blocked | A0-ARCH-901, A0-SEC-002, A0-ARCH-902 |
| A3-INV-002 | ربط واختبار slice: SKU وwarehouse scoped balances | blocked | A3-INV-001, A0-CORE-900 |
| A3-INV-003 | بناء slice: stock movement append/reverse | blocked | A0-ARCH-901, A3-INV-002, A0-SEC-002, A0-ARCH-902 |
| A3-INV-004 | ربط واختبار slice: stock movement append/reverse | blocked | A3-INV-003, A0-CORE-900 |
| A3-INV-005 | بناء slice: atomic reserve واستهلاك issue | blocked | A0-ARCH-901, A3-INV-004, A0-SEC-002, A0-ARCH-902 |
| A3-INV-006 | ربط واختبار slice: atomic reserve واستهلاك issue | blocked | A3-INV-005, A0-CORE-900 |
| A3-INV-007 | بناء slice: stock count variance approval | blocked | A0-ARCH-901, A3-INV-006, A0-SEC-002, A0-ARCH-902 |
| A3-INV-008 | ربط واختبار slice: stock count variance approval | blocked | A3-INV-007, A0-CORE-900 |
| A3-INV-009 | بناء slice: receipt provenance | blocked | A0-ARCH-901, A3-INV-008, A0-SEC-002, A0-ARCH-902 |
| A3-INV-010 | ربط واختبار slice: receipt provenance | blocked | A3-INV-009, A0-CORE-900 |
| A3-DEL-001 | بناء slice: custody transitions | planned | A0-ARCH-901, A0-SEC-002, A0-ARCH-902, A3-INV-006 |
| A3-DEL-002 | ربط واختبار slice: custody transitions | planned | A3-DEL-001, A0-CORE-900 |
| A3-DEL-003 | بناء slice: POD مع الكميات | planned | A0-ARCH-901, A3-DEL-002, A0-SEC-002, A0-ARCH-902, A3-INV-006 |
| A3-DEL-004 | ربط واختبار slice: POD مع الكميات | planned | A3-DEL-003, A0-CORE-900 |
| A3-DEL-005 | بناء slice: dispatch assignment | planned | A0-ARCH-901, A3-DEL-004, A0-SEC-002, A0-ARCH-902, A3-INV-006 |
| A3-DEL-006 | ربط واختبار slice: dispatch assignment | planned | A3-DEL-005, A0-CORE-900 |
| A3-QA-001 | بناء slice: inspection وcorrective action | blocked | A0-ARCH-901, A0-SEC-002, A0-ARCH-902, A3-DEL-004 |
| A3-QA-002 | ربط واختبار slice: inspection وcorrective action | blocked | A3-QA-001, A0-CORE-900 |
| A3-QA-003 | بناء slice: warranty handoff record | blocked | A0-ARCH-901, A3-QA-002, A0-SEC-002, A0-ARCH-902, A3-DEL-004 |
| A3-QA-004 | ربط واختبار slice: warranty handoff record | blocked | A3-QA-003, A0-CORE-900 |
| A3-MFG-001 | بناء slice: سجل output/consumption محدود | blocked | A0-ARCH-901, A0-SEC-002, A0-ARCH-902 |
| A3-MFG-002 | ربط واختبار slice: سجل output/consumption محدود | blocked | A3-MFG-001, A0-CORE-900 |
| A4-FIN-001 | بناء slice: journal posting invariant | blocked | A0-ARCH-901, A0-SEC-002, A0-ARCH-902 |
| A4-FIN-002 | ربط واختبار slice: journal posting invariant | blocked | A4-FIN-001, A0-CORE-900 |
| A4-FIN-003 | بناء slice: account dimensions | blocked | A0-ARCH-901, A4-FIN-002, A0-SEC-002, A0-ARCH-902 |
| A4-FIN-004 | ربط واختبار slice: account dimensions | blocked | A4-FIN-003, A0-CORE-900 |
| A4-FIN-005 | بناء slice: expense approval request | blocked | A0-ARCH-901, A4-FIN-004, A0-SEC-002, A0-ARCH-902 |
| A4-FIN-006 | ربط واختبار slice: expense approval request | blocked | A4-FIN-005, A0-CORE-900 |
| A4-FIN-007 | بناء slice: payment allocation ومطابقة المصدر | blocked | A0-ARCH-901, A4-FIN-006, A0-SEC-002, A0-ARCH-902 |
| A4-FIN-008 | ربط واختبار slice: payment allocation ومطابقة المصدر | blocked | A4-FIN-007, A0-CORE-900 |
| A4-FIN-009 | بناء slice: stock-accounting bridge | blocked | A0-ARCH-901, A4-FIN-008, A0-SEC-002, A0-ARCH-902 |
| A4-FIN-010 | ربط واختبار slice: stock-accounting bridge | blocked | A4-FIN-009, A0-CORE-900 |
| A4-FIN-011 | بناء slice: partner ledger attribution | blocked | A0-ARCH-901, A4-FIN-010, A0-SEC-002, A0-ARCH-902 |
| A4-FIN-012 | ربط واختبار slice: partner ledger attribution | blocked | A4-FIN-011, A0-CORE-900 |
| A4-FIN-013 | بناء slice: statement projection من posted | blocked | A0-ARCH-901, A4-FIN-012, A0-SEC-002, A0-ARCH-902 |
| A4-FIN-014 | ربط واختبار slice: statement projection من posted | blocked | A4-FIN-013, A0-CORE-900 |
| A4-FIN-015 | بناء slice: period lock وreversal | blocked | A0-ARCH-901, A4-FIN-014, A0-SEC-002, A0-ARCH-902 |
| A4-FIN-016 | ربط واختبار slice: period lock وreversal | blocked | A4-FIN-015, A0-CORE-900 |
| A1-EMP-001 | بناء slice: profile scoped read model | done | A0-ARCH-901, A0-SEC-002, A0-ARCH-902 |
| A1-EMP-002 | ربط واختبار slice: profile scoped read model | done | A1-EMP-001, A0-CORE-900 |
| A1-EMP-003 | بناء slice: non-login worker attribution | done | A0-ARCH-901, A1-EMP-002, A0-SEC-002, A0-ARCH-902 |
| A1-EMP-004 | ربط واختبار slice: non-login worker attribution | done | A1-EMP-003, A0-CORE-900 |
| A4-PAY-001 | بناء slice: net payable summary | blocked | A0-ARCH-901, A0-SEC-002, A0-ARCH-902, A4-FIN-002 |
| A4-PAY-002 | ربط واختبار slice: net payable summary | blocked | A4-PAY-001, A0-CORE-900 |
| A4-PAY-003 | بناء slice: commission eligible source key | blocked | A0-ARCH-901, A4-PAY-002, A0-SEC-002, A0-ARCH-902, A4-FIN-002 |
| A4-PAY-004 | ربط واختبار slice: commission eligible source key | blocked | A4-PAY-003, A0-CORE-900 |
| A4-PAY-005 | بناء slice: approved deduction | blocked | A0-ARCH-901, A4-PAY-004, A0-SEC-002, A0-ARCH-902, A4-FIN-002 |
| A4-PAY-006 | ربط واختبار slice: approved deduction | blocked | A4-PAY-005, A0-CORE-900 |
| A4-PAY-007 | بناء slice: deduction category extension | blocked | A0-ARCH-901, A4-PAY-006, A0-SEC-002, A0-ARCH-902, A4-FIN-002 |
| A4-PAY-008 | ربط واختبار slice: deduction category extension | blocked | A4-PAY-007, A0-CORE-900 |
| A1-COM-001 | بناء slice: workspace task projection | done | A0-ARCH-901, A0-SEC-002, A0-ARCH-902 |
| A1-COM-002 | ربط واختبار slice: workspace task projection | done | A1-COM-001, A0-CORE-900 |
| A1-COM-003 | بناء slice: internal conversation membership | done | A0-ARCH-901, A1-COM-002, A0-SEC-002, A0-ARCH-902 |
| A1-COM-004 | ربط واختبار slice: internal conversation membership | done | A1-COM-003, A0-CORE-900 |
| A1-COM-005 | بناء slice: corporate mailbox adapter | blocked | A0-ARCH-901, A1-COM-004, A0-SEC-002, A0-ARCH-902 |
| A1-COM-006 | ربط واختبار slice: corporate mailbox adapter | blocked | A1-COM-005, A0-CORE-900 |
| A1-COM-007 | بناء slice: SMS destination resolution | blocked | A0-ARCH-901, A1-COM-006, A0-SEC-002, A0-ARCH-902 |
| A1-COM-008 | ربط واختبار slice: SMS destination resolution | blocked | A1-COM-007, A0-CORE-900 |
| A1-COM-009 | بناء slice: optional Telegram adapter | blocked | A0-ARCH-901, A1-COM-008, A0-SEC-002, A0-ARCH-902 |
| A1-COM-010 | ربط واختبار slice: optional Telegram adapter | blocked | A1-COM-009, A0-CORE-900 |
| A1-COM-011 | بناء slice: WhatsApp pluggable contract | blocked | A0-ARCH-901, A1-COM-010, A0-SEC-002, A0-ARCH-902 |
| A1-COM-012 | ربط واختبار slice: WhatsApp pluggable contract | blocked | A1-COM-011, A0-CORE-900 |
| A1-COM-013 | بناء slice: message capture record | blocked | A0-ARCH-901, A1-COM-012, A0-SEC-002, A0-ARCH-902 |
| A1-COM-014 | ربط واختبار slice: message capture record | blocked | A1-COM-013, A0-CORE-900 |
| A1-COM-015 | بناء slice: masked outbound destination | planned | A0-ARCH-901, A1-COM-014, A0-SEC-002, A0-ARCH-902 |
| A1-COM-016 | ربط واختبار slice: masked outbound destination | planned | A1-COM-015, A0-CORE-900 |
| A1-COM-017 | بناء slice: send retry state | blocked | A0-ARCH-901, A1-COM-016, A0-SEC-002, A0-ARCH-902 |
| A1-COM-018 | ربط واختبار slice: send retry state | blocked | A1-COM-017, A0-CORE-900 |
| A5-DASH-001 | بناء slice: executive metric projection | done | A0-ARCH-901, A0-SEC-002, A0-ARCH-902 |
| A5-DASH-002 | ربط واختبار slice: executive metric projection | done | A5-DASH-001, A0-CORE-900 |
| A5-DASH-003 | بناء slice: role dashboard selection | done | A0-ARCH-901, A5-DASH-002, A0-SEC-002, A0-ARCH-902 |
| A5-DASH-004 | ربط واختبار slice: role dashboard selection | done | A5-DASH-003, A0-CORE-900 |
| A5-DASH-005 | بناء slice: authorized drill-down | done | A0-ARCH-901, A5-DASH-004, A0-SEC-002, A0-ARCH-902 |
| A5-DASH-006 | ربط واختبار slice: authorized drill-down | done | A5-DASH-005, A0-CORE-900 |
| A5-DASH-007 | بناء slice: alert lifecycle | done | A0-ARCH-901, A5-DASH-006, A0-SEC-002, A0-ARCH-902 |
| A5-DASH-008 | ربط واختبار slice: alert lifecycle | done | A5-DASH-007, A0-CORE-900 |
| A5-DASH-009 | بناء slice: masked export artifact | done | A0-ARCH-901, A5-DASH-008, A0-SEC-002, A0-ARCH-902 |
| A5-DASH-010 | ربط واختبار slice: masked export artifact | done | A5-DASH-009, A0-CORE-900 |
| A5-AI-001 | بناء slice: deterministic guard pipeline | planned | A0-ARCH-901, A0-SEC-002, A0-ARCH-902, A4-FIN-006, A3-INV-004 |
| A5-AI-002 | ربط واختبار slice: deterministic guard pipeline | planned | A5-AI-001, A0-CORE-900 |
| A5-AI-003 | بناء slice: priority review adapter | blocked | A0-ARCH-901, A5-AI-002, A0-SEC-002, A0-ARCH-902, A4-FIN-006, A3-INV-004 |
| A5-AI-004 | ربط واختبار slice: priority review adapter | blocked | A5-AI-003, A0-CORE-900 |
| A5-AI-005 | بناء slice: finding evidence model | planned | A0-ARCH-901, A5-AI-004, A0-SEC-002, A0-ARCH-902, A4-FIN-006, A3-INV-004 |
| A5-AI-006 | ربط واختبار slice: finding evidence model | planned | A5-AI-005, A0-CORE-900 |
| A5-AI-007 | بناء slice: human disposition boundary | planned | A0-ARCH-901, A5-AI-006, A0-SEC-002, A0-ARCH-902, A4-FIN-006, A3-INV-004 |
| A5-AI-008 | ربط واختبار slice: human disposition boundary | planned | A5-AI-007, A0-CORE-900 |
| A5-AI-009 | بناء slice: scheduled review dispatch | blocked | A0-ARCH-901, A5-AI-008, A0-SEC-002, A0-ARCH-902, A4-FIN-006, A3-INV-004 |
| A5-AI-010 | ربط واختبار slice: scheduled review dispatch | blocked | A5-AI-009, A0-CORE-900 |
| A5-AI-011 | بناء slice: untrusted prompt boundary | blocked | A0-ARCH-901, A5-AI-010, A0-SEC-002, A0-ARCH-902, A4-FIN-006, A3-INV-004 |
| A5-AI-012 | ربط واختبار slice: untrusted prompt boundary | blocked | A5-AI-011, A0-CORE-900 |
| A5-AI-013 | بناء slice: versioned review run | planned | A0-ARCH-901, A5-AI-012, A0-SEC-002, A0-ARCH-902, A4-FIN-006, A3-INV-004 |
| A5-AI-014 | ربط واختبار slice: versioned review run | planned | A5-AI-013, A0-CORE-900 |
| A5-AI-015 | بناء slice: typed future command schema | planned | A0-ARCH-901, A5-AI-014, A0-SEC-002, A0-ARCH-902, A4-FIN-006, A3-INV-004 |
| A5-AI-016 | ربط واختبار slice: typed future command schema | planned | A5-AI-015, A0-CORE-900 |
| A5-AI-017 | بناء slice: labeled evaluation harness | blocked | A0-ARCH-901, A5-AI-016, A0-SEC-002, A0-ARCH-902, A4-FIN-006, A3-INV-004 |
| A5-AI-018 | ربط واختبار slice: labeled evaluation harness | blocked | A5-AI-017, A0-CORE-900 |
| A0-ARCH-001 | بناء slice: module boundary checker | done | A0-ARCH-901 |
| A0-ARCH-002 | ربط واختبار slice: module boundary checker | done | A0-ARCH-001, A0-CORE-900 |
| A0-ARCH-003 | بناء slice: outbox recovery contract | done | A0-ARCH-901, A0-ARCH-002 |
| A0-ARCH-004 | ربط واختبار slice: outbox recovery contract | blocked | A0-ARCH-003, A0-CORE-900 |
| A0-ARCH-005 | بناء slice: provider adapter interface | planned | A0-ARCH-901, A0-ARCH-004 |
| A0-ARCH-006 | ربط واختبار slice: provider adapter interface | planned | A0-ARCH-005, A0-CORE-900 |
| A0-ARCH-007 | بناء slice: entity dimension guard | planned | A0-ARCH-901, A0-ARCH-006 |
| A0-ARCH-008 | ربط واختبار slice: entity dimension guard | planned | A0-ARCH-007, A0-CORE-900 |
| A0-ARCH-009 | بناء slice: schema integrity conventions | planned | A0-ARCH-901, A0-ARCH-008 |
| A0-ARCH-010 | ربط واختبار slice: schema integrity conventions | planned | A0-ARCH-009, A0-CORE-900 |
| A0-ARCH-011 | بناء slice: migration identifier check | planned | A0-ARCH-901, A0-ARCH-010 |
| A0-ARCH-012 | ربط واختبار slice: migration identifier check | planned | A0-ARCH-011, A0-CORE-900 |
| A5-NFR-001 | بناء slice: session/privacy/rate-limit guard | done | A0-ARCH-901, A0-SEC-002, A0-ARCH-902 |
| A5-NFR-002 | ربط واختبار slice: session/privacy/rate-limit guard | done | A5-NFR-001, A0-CORE-900 |
| A5-NFR-003 | بناء slice: concurrent mutation harness | done | A0-ARCH-901, A5-NFR-002, A0-SEC-002, A0-ARCH-902 |
| A5-NFR-004 | ربط واختبار slice: concurrent mutation harness | done | A5-NFR-003, A0-CORE-900 |
| A5-NFR-005 | بناء slice: restore drill procedure | blocked | A0-ARCH-901, A5-NFR-004, A0-SEC-002, A0-ARCH-902 |
| A5-NFR-006 | ربط واختبار slice: restore drill procedure | blocked | A5-NFR-005, A0-CORE-900 |
| A5-NFR-007 | بناء slice: load profile harness | blocked | A0-ARCH-901, A5-NFR-006, A0-SEC-002, A0-ARCH-902 |
| A5-NFR-008 | ربط واختبار slice: load profile harness | blocked | A5-NFR-007, A0-CORE-900 |
| A5-NFR-009 | بناء slice: field retry protocol | blocked | A0-ARCH-901, A5-NFR-008, A0-SEC-002, A0-ARCH-902 |
| A5-NFR-010 | ربط واختبار slice: field retry protocol | blocked | A5-NFR-009, A0-CORE-900 |
| A5-NFR-011 | بناء slice: RTL accessibility fixtures | planned | A0-ARCH-901, A5-NFR-010, A0-SEC-002, A0-ARCH-902 |
| A5-NFR-012 | ربط واختبار slice: RTL accessibility fixtures | planned | A5-NFR-011, A0-CORE-900 |
| A5-NFR-013 | بناء slice: sanitized telemetry | planned | A0-ARCH-901, A5-NFR-012, A0-SEC-002, A0-ARCH-902 |
| A5-NFR-014 | ربط واختبار slice: sanitized telemetry | planned | A5-NFR-013, A0-CORE-900 |
| A5-NFR-015 | بناء slice: retention policy enforcement | blocked | A0-ARCH-901, A5-NFR-014, A0-SEC-002, A0-ARCH-902 |
| A5-NFR-016 | ربط واختبار slice: retention policy enforcement | blocked | A5-NFR-015, A0-CORE-900 |
| A1-AT-01 | توثيق acceptance evidence لـ AT-01 | planned | A1-CRM-004, A0-SEC-004 |
| A1-AT-02 | توثيق acceptance evidence لـ AT-02 | planned | A1-CRM-010 |
| A2-AT-03 | توثيق acceptance evidence لـ AT-03 | planned | A2-SALE-006, A2-SALE-010 |
| A3-AT-04 | توثيق acceptance evidence لـ AT-04 | planned | A3-INV-006 |
| A3-AT-05 | توثيق acceptance evidence لـ AT-05 | planned | A3-DEL-004 |
| A3-AT-06 | توثيق acceptance evidence لـ AT-06 | planned | A2-PRJ-010, A2-PRJ-004, A3-QA-002 |
| A4-AT-07 | توثيق acceptance evidence لـ AT-07 | planned | A4-FIN-002, A4-FIN-006 |
| A4-AT-08 | توثيق acceptance evidence لـ AT-08 | planned | A4-PAY-004 |
| A5-AT-09 | توثيق acceptance evidence لـ AT-09 | planned | A5-DASH-006 |
| A5-AT-10 | توثيق acceptance evidence لـ AT-10 | planned | A5-AI-006, A5-AI-008 |
| A1-AT-11 | توثيق acceptance evidence لـ AT-11 | planned | A1-COM-018 |
| A1-AT-12 | توثيق acceptance evidence لـ AT-12 | planned | A0-SEC-012, A1-EMP-002 |
| A5-AT-13 | توثيق acceptance evidence لـ AT-13 | planned | A5-NFR-012, A5-NFR-010 |
| A0-AT-14 | توثيق acceptance evidence لـ AT-14 | planned | A0-SEC-006, A0-SEC-008 |
| A4-AT-15 | توثيق acceptance evidence لـ AT-15 | planned | A4-FIN-014, A4-FIN-016 |
| A0-GATE-001 | تجميع أدلة خروج A0 ومراجعة المالك | planned | A0-ARCH-900, A0-ARCH-901, A0-ARCH-902, A0-CORE-900, A0-SEC-001, A0-SEC-002, A0-SEC-003, A0-SEC-004, A0-SEC-005, A0-SEC-006, A0-SEC-007, A0-SEC-008, A0-SEC-009, A0-SEC-010, A0-SEC-011, A0-SEC-012, A0-CORE-001, A0-CORE-002, A0-CORE-003, A0-CORE-004, A0-CORE-005, A0-CORE-006, A0-CORE-007, A0-CORE-008, A0-DOC-001, A0-DOC-002, A0-DOC-003, A0-DOC-004, A0-CORE-009, A0-CORE-010, A0-ARCH-001, A0-ARCH-002, A0-ARCH-003, A0-ARCH-004, A0-ARCH-005, A0-ARCH-006, A0-ARCH-007, A0-ARCH-008, A0-ARCH-009, A0-ARCH-010, A0-ARCH-011, A0-ARCH-012, A0-AT-14 |
| A1-GATE-001 | تجميع أدلة خروج A1 ومراجعة المالك | planned | A1-CRM-001, A1-CRM-002, A1-CRM-003, A1-CRM-004, A1-CRM-005, A1-CRM-006, A1-CRM-007, A1-CRM-008, A1-CRM-009, A1-CRM-010, A1-CRM-011, A1-CRM-012, A1-TASK-001, A1-TASK-002, A1-KPI-001, A1-KPI-002, A1-KPI-003, A1-KPI-004, A1-KPI-005, A1-KPI-006, A1-KPI-007, A1-KPI-008, A1-EMP-001, A1-EMP-002, A1-EMP-003, A1-EMP-004, A1-COM-001, A1-COM-002, A1-COM-003, A1-COM-004, A1-COM-005, A1-COM-006, A1-COM-007, A1-COM-008, A1-COM-009, A1-COM-010, A1-COM-011, A1-COM-012, A1-COM-013, A1-COM-014, A1-COM-015, A1-COM-016, A1-COM-017, A1-COM-018, A1-AT-01, A1-AT-02, A1-AT-11, A1-AT-12 |
| A2-GATE-001 | تجميع أدلة خروج A2 ومراجعة المالك | planned | A2-SALE-001, A2-SALE-002, A2-SALE-003, A2-SALE-004, A2-SALE-005, A2-SALE-006, A2-SALE-007, A2-SALE-008, A2-SALE-009, A2-SALE-010, A2-PRJ-001, A2-PRJ-002, A2-PRJ-003, A2-PRJ-004, A2-PRJ-005, A2-PRJ-006, A2-PRJ-007, A2-PRJ-008, A2-PRJ-009, A2-PRJ-010, A2-PRJ-011, A2-PRJ-012, A2-AT-03 |
| A3-GATE-001 | تجميع أدلة خروج A3 ومراجعة المالك | planned | A3-INV-001, A3-INV-002, A3-INV-003, A3-INV-004, A3-INV-005, A3-INV-006, A3-INV-007, A3-INV-008, A3-INV-009, A3-INV-010, A3-DEL-001, A3-DEL-002, A3-DEL-003, A3-DEL-004, A3-DEL-005, A3-DEL-006, A3-QA-001, A3-QA-002, A3-QA-003, A3-QA-004, A3-MFG-001, A3-MFG-002, A3-AT-04, A3-AT-05, A3-AT-06 |
| A4-GATE-001 | تجميع أدلة خروج A4 ومراجعة المالك | planned | A4-FIN-001, A4-FIN-002, A4-FIN-003, A4-FIN-004, A4-FIN-005, A4-FIN-006, A4-FIN-007, A4-FIN-008, A4-FIN-009, A4-FIN-010, A4-FIN-011, A4-FIN-012, A4-FIN-013, A4-FIN-014, A4-FIN-015, A4-FIN-016, A4-PAY-001, A4-PAY-002, A4-PAY-003, A4-PAY-004, A4-PAY-005, A4-PAY-006, A4-PAY-007, A4-PAY-008, A4-AT-07, A4-AT-08, A4-AT-15 |
| A0-VINT-001 | shared persistence and migration contract | done | A0-ARCH-901, A0-ARCH-902 |
| A0-VINT-002 | shared runtime identity and authorization contract | done | A0-SEC-002 |
| A0-VINT-003 | shared HTTP validation and controller contract | done | A0-CORE-900 |
| A0-VINT-004 | shared Arabic RTL UI contract | done | A0-CORE-900 |
| A5-VINT-001 | shared governed AI gateway and action contract | planned | A0-SEC-002, A0-ARCH-902, A5-AI-001 |
| A5-VINT-002 | shared MariaDB browser/database acceptance harness | blocked | A0-ARCH-901, A0-CORE-900, A5-NFR-004 |
| A0-VINT-101 | organization users employees approvals | planned | A0-SEC-006, A0-CORE-010, A0-VINT-001, A0-VINT-002, A0-VINT-003, A0-VINT-004, A5-VINT-001, A5-VINT-002 |
| A1-VINT-101 | CRM and sales conversion | planned | A1-CRM-012, A2-SALE-010, A0-VINT-001, A0-VINT-002, A0-VINT-003, A0-VINT-004, A5-VINT-001, A5-VINT-002 |
| A2-VINT-101 | projects installations maintenance quality | planned | A2-PRJ-012, A3-QA-004, A0-VINT-001, A0-VINT-002, A0-VINT-003, A0-VINT-004, A5-VINT-001, A5-VINT-002 |
| A3-VINT-101 | inventory warehouses deliveries | planned | A3-INV-010, A3-DEL-006, A0-VINT-001, A0-VINT-002, A0-VINT-003, A0-VINT-004, A5-VINT-001, A5-VINT-002 |
| A4-VINT-101 | finance treasury collections commissions | planned | A4-FIN-016, A4-PAY-008, A0-VINT-001, A0-VINT-002, A0-VINT-003, A0-VINT-004, A5-VINT-001, A5-VINT-002 |
| A1-VINT-102 | communications privacy workflow | planned | A1-COM-018, A0-VINT-001, A0-VINT-002, A0-VINT-003, A0-VINT-004, A5-VINT-001, A5-VINT-002 |
| A1-VINT-103 | tasks KPI executive dashboard | planned | A1-TASK-002, A1-KPI-008, A5-DASH-010, A0-VINT-001, A0-VINT-002, A0-VINT-003, A0-VINT-004, A5-VINT-001, A5-VINT-002 |
| A5-VINT-101 | documents and audit workflow | planned | A0-DOC-004, A0-VINT-001, A0-VINT-002, A0-VINT-003, A0-VINT-004, A5-VINT-001, A5-VINT-002 |
| A5-VINT-102 | full AI action assistant cycle | planned | A5-AI-018, A0-VINT-001, A0-VINT-002, A0-VINT-003, A0-VINT-004, A5-VINT-001, A5-VINT-002 |
| A1-TASK-003 | Owner-approved Tasks DEVELOPMENT lifecycle and deterministic action | done | A1-TASK-002, A0-DS-002 |
| A1-CORE-011 | Owner-approved Cycle01 organization users and RBAC | done | A0-CORE-002, A0-SEC-002, A0-VINT-001, A0-VINT-002, A0-VINT-003, A0-VINT-004, A0-DS-002 |
| A1-CORE-012 | Cycle01 scope completeness audit and navigation correction | done | A1-CORE-011 |
| A1-DASH-003 | Reference dashboard DEVELOPMENT presentation | ready | A0-DS-002 |
| A1-DASH-004 | Dashboard full-height sidebar visual correction | done | A0-DS-002 |
| A1-DASH-005 | Short dashboard account display name | done | A0-DS-002 |
| A1-CRM-013 | Cycle03 durable lead library assignment and contact masking | done | A1-CRM-012, A1-CORE-012, A1-TASK-003, A0-DS-002 |
| A1-CRM-014 | Cycle03 lead activity acknowledgement and customer conversion | done | A1-CRM-013 |
| A1-DASH-006 | Consistent shared application sidebar | done | A0-DS-002 |
| A1-CORE-015 | Owner screenshot administration UI and bounded directory browsing | ready | A1-CORE-011, A0-DS-002 |
| A1-CRM-015 | Cycle03 authenticated CRM HTTP integration and security | done | A1-CRM-014 |
| A5-GATE-001 | تجميع أدلة خروج A5 ومراجعة المالك | planned | A5-DASH-001, A5-DASH-002, A5-DASH-003, A5-DASH-004, A5-DASH-005, A5-DASH-006, A5-DASH-007, A5-DASH-008, A5-DASH-009, A5-DASH-010, A5-AI-001, A5-AI-002, A5-AI-003, A5-AI-004, A5-AI-005, A5-AI-006, A5-AI-007, A5-AI-008, A5-AI-009, A5-AI-010, A5-AI-011, A5-AI-012, A5-AI-013, A5-AI-014, A5-AI-015, A5-AI-016, A5-AI-017, A5-AI-018, A5-NFR-001, A5-NFR-002, A5-NFR-003, A5-NFR-004, A5-NFR-005, A5-NFR-006, A5-NFR-007, A5-NFR-008, A5-NFR-009, A5-NFR-010, A5-NFR-011, A5-NFR-012, A5-NFR-013, A5-NFR-014, A5-NFR-015, A5-NFR-016, A5-AT-09, A5-AT-10, A5-AT-13 |
## Owner-approved design-system workstream — 2026-10-17

| Task | Title | Status | Scope |
|---|---|---|---|
| A0-DS-001 | Shared tokens, shell and UI components | done | DEVELOPMENT only |
| A0-DS-002 | ERP/AI presentation and showcase | done | DEVELOPMENT only |
| A0-DS-003 | Reuse policy and browser acceptance | done | DEVELOPMENT only |
## CR-TASK-DEV-001 — owner-approved bounded Tasks DEVELOPMENT

| Task | Scope | Status source |
|---|---|---|
| A1-TASK-003 | Create, start, private evidence, complete; task-local preview; shared AI execution disabled | Authoritative tasks.json and loop handoffs |

## CR-CYCLE01-DEV-001 — bounded organization and access DEVELOPMENT

| Task | Scope | Status source |
|---|---|---|
| A1-CORE-011 | Company, branches/departments, real login, users, roles, permissions, revocation and local audit | Authoritative tasks.json and loop handoffs |

| A1-EMP-005 | Cycle02 employee persistence and supervisor service | done | A1-EMP-004, A1-CORE-011 |
| A1-EMP-006 | Cycle02 employee controller and shared UI preparation | done | A1-EMP-005, A1-CORE-012 |
| A1-CRM-016 | Cycle03 bounded CRM four-screen HTML frontend integration | done | A1-CRM-015 |
| A1-CRM-017 | Delivered CRM visual polish and complete functional QA | ready | A1-CRM-016 |
| A0-ARCH-903 | Architecture and AI development workflow hardening | done | — |
| A0-ARCH-904 | Consolidate reviewed local project and verify Egyptian demo | done | — |
| A0-ARCH-905 | Clear historical Laravel Pint formatting debt | done | — |
| A1-CRM-018 | CRM reference lead activities notes and linked tasks workspace | done | A1-CRM-016 |
| A1-CRM-019 | Private CRM files and reference file workspace | done | A1-CRM-018 |
| A2-SALE-011 | Bounded synthetic quotation drafts and PDF previews | done | A1-CRM-019, A0-ARCH-902, A0-SEC-002 |
| A1-CRM-020 | Bounded opportunity pipeline and six-screen reference acceptance | blocked | A2-SALE-011 |
| A0-ARCH-906 | Provisional system inventory and QA checkpoint | done | — |
| A1-CORE-017 | Request-local verified authorization reuse | ready | - |
| A1-CORE-018 | Role-granted contact visibility and task delegation integration | in_progress | A1-TASK-003 |
| A1-TASK-004 | Daily Tasks reference workspace using shared UI | ready | A1-TASK-003, A0-DS-002 |
| A1-EMP-007 | Employee work follow-up reference page | blocked | A1-EMP-006, A0-DS-002 |
| A1-HELP-001 | Standalone tutorial engine and Help Center foundation | ready | A0-DS-002 |
| A1-HELP-002 | Tutorial application integration and complete screen acceptance | planned | A1-HELP-001 |
| A1-CORE-016 | Isolated user detail and edit reference presentation | ready | A1-CORE-012, A0-DS-002 |
| A1-EMP-008 | Employee profile and edit reference refinement | ready | - |
| A0-ARCH-907 | Identity SEC01 SEC02 evidence-based QA specification | blocked | - |
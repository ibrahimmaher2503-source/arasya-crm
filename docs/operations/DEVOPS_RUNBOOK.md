# دليل التشغيل المقترح
Environments: local/test/staging/production مع accounts وsecrets وstorage مستقلة، بيانات production لا تنسخ إلا sanitized ومصرحاً. hosting/runtime/providers وuser counts وnumeric SLO/RPO/RTO كلها OPEN-11. لا نشر قبل owners وتوثيق thresholds وon-call.

CI: validate-docs → formatting/static analysis → unit/feature/policy/contract → migration وfinance/stock race tests → artifact immutable → staging smoke/RTL/security/restore → موافقة release → deploy تدريجي ومراقبة. لا تفترض وجود pipeline الآن.

Secrets: مدير أسرار مقترح، rotation/revocation واختبار فشل credentials، لا repo/logs. service accounts مستقلة وleast privilege. deployments توقف استقبال الأعمال الجديدة بأمان وتدع workers drain؛ schema متوافق مع النسختين خلال rollout.

Backups: DB + private documents + مفاتيح الاستعادة المحمية + manifests/checksums؛ encryption وoffsite/retention حسب OPEN-10/11. إجراء restore: اختر snapshot، اعزل الشبكة/providers، استعد DB/files، تحقق integrity وjournal/stock reconciliation وpermissions، قس الوقت وفقد البيانات، وثق نتائج فعلية واعتماد المالك قبل traffic. backup ناجح لا يثبت restore.

Queues: راقب age/depth/retry/DLQ وoutbox lag؛ backoff مع jitter وحدود معتمدة لاحقاً. DLQ ticket بالـ correlation وسبب sanitized. أصلح السبب ثم replay scoped مع نفس event ID؛ لا حذف أو mark-success لتخفيف dashboard. provider accepted لا delivery؛ webhooks متأخرة/out-of-order تستدعي reconciliation.

Observability: structured logs/traces بمراجع IDs دون PII، dashboards للlatency/errors، ledger imbalance المفترض صفر، stock reconciliation، failed approvals، audit delivery، upload quarantine وAI cost. numeric alert thresholds تعتمد بعد baseline/load tests.

Incident: قائد محدد، احتواء revocation/provider disable، حفظ evidence، تصنيف الأثر ومراجعة legal للإبلاغ، recovery وتحقيق postmortem. rollback code فقط عند compatibility؛ المالي والمخزون بتعويضات لا حذف. لإفساد بيانات: maintenance mode، snapshot قبل الإصلاح، خطة مصرح بها، reconciliation ثم reopening. سجل أوامر runbook ووقتها والنتيجة وليس ادعاء التشغيل.
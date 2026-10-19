# نموذج التهديد
الأصول: contacts، employee documents، financial/stock ledgers، approvals، provider credentials، signed evidence، audit history، AI inputs. الخصوم: موظف يتجاوز نطاقه، account مخترق، provider/webhook مزيف، ملف/رسالة عدائية، خطأ تشغيل أو replay.

Trust boundaries: device→API، API→domain/database، DB→outbox/worker، worker→provider، upload→quarantine→private store، authorized read model→AI، production→backup/support. لا ثقة في client IDs أو role claims أو document text؛ support access مؤقت ومدقق.

| Abuse | mitigation | اختبار مطلوب |
|---|---|---|
| IDOR/contact enumeration عبر search/export | query scope قبل count وfield allowlist وrate limit | AT-01: other assignment ووجود phone في response/log/file forbidden |
| headers/quoted replies/notification leakage | sanitize وحجب address fields وpreview controls | raw provider payload لا يصل agent؛ content limitation موثقة |
| approval PATCH/self-approval/stale amount | domain transition + SoD + version binding | AT-14 رفض دون state change مع audit |
| finance double submit/closed period race | transaction/unique key/locking/period guard | parallel post/close لا unbalanced أو late post |
| stock double issue/negative available | atomic reservation consumption وlocks | AT-04 race وإعادة نفس key دون تكرار |
| export job ينفذ بعد revoke | reauthorize at execution/download وexpiry | revoke قبل التنفيذ/download يمنع التسريب |
| polyglot/path traversal/oversize upload | MIME magic/size/quarantine/scan/random keys | EICAR fixture آمنة وmismatch و../ وunauthorized download |
| forged delivery webhook | signature/time window/replay dedup | replay/out-of-order لا delivered مبكر |
| prompt injection في invoice أو message | untrusted delimiter/minimization/no privileged tools | نص يطلب تحويل مال لا ينتج command منفذ أو PII |
| tampered audit/admin access | append-only access + integrity monitoring | تغيير سجل مرفوض وتنبيه وصول admin |
| stolen secret/backup | encryption/rotation/separate accounts | revoke credentials وrestore بصلاحية محدودة |
| cross-entity cache/index leak | entity in key + reauth + no PII index | same ID/filter عبر entity ثانية لا نتيجة |

Residual risks: موظف يرى بيانات مصرحاً بها قد ينسخها؛ عميل يضع phone في content؛ provider يحتفظ بالبيانات حسب عقد؛ AI false positives/negatives. مسؤول Security يوثق قبول المخاطر مع Legal/Finance، لا agent. release gate لا exceptions غير موثقة، incident owner وخطة rollback وevidence محفوظة تحت retention policy.
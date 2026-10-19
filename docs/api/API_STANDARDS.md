# معايير API — عقود Proposed
لا قائمة endpoints نهائية. version prefix مقترح `/api/v1`؛ breaking contract يرفع major ولا يغير معنى event قائم. authentication adapter يعتمد بعد ADR؛ browser session يحتاج CSRF، tokens محدودة وقابلة للإلغاء. تحقق policy وrecord/field/entity scope في كل query/command/download/export وjob وقت التنفيذ، لا عند إنشاء الطلب فقط.

```json
{"command":"SubmitApproval","aggregate_id":"opaque-id","expected_version":3,"idempotency_key":"client-generated","payload":{"reason":"review"}}
```
```json
{"error":{"code":"transition_conflict","message":"تعذر الانتقال","fields":{},"correlation_id":"opaque-id"}}
```
هذه أمثلة shape لا endpoints أو اعتماد naming. 401 غير موثق، 403 غير مصرح، 404 لإخفاء وجود سجل عندما يلزم، 409 version/idempotency conflict، 422 validation، 429 rate limit مع Retry-After، 503 unavailable. لا stack traces/SQL/provider secrets؛ error code ثابت ورسالة مترجمة.

Idempotency-Key مربوط actor/entity/operation/request hash؛ إعادة نفس المحتوى تعيد النتيجة، اختلافه conflict. مدة الحفظ OPEN مرتبطة business retry window. optimistic version/If-Match للأوامر؛ atomic locks للمال والمخزون. لا PATCH مباشر لـ approved/posted.

cursor pagination مستقرة مع tie-breaker ID؛ max page size/configurable export limits Proposed لا رقم معتمد. allowlist filters/sorts ولا بحث phone لغير المخول حتى لو النتيجة masked. query scope قبل count. response DTO allowlist لا serialize ORM بالكامل؛ mask داخل headers/quoted replies حيث يمكن ولا وعد بمحو ما كتبه العميل في محتوى الرسالة.

Uploads: initiate مع owner/type/size، quarantine، MIME signature/scan، finalize checksum، download authorization؛ filenames غير موثوقة. لا public storage URL. audit للأوامر والمحاولات الحساسة وexport/download، correlation صادر من server أو قيمة مدققة. webhooks signature/replay validation وprovider event ID dedup؛ accepted ليس delivered.
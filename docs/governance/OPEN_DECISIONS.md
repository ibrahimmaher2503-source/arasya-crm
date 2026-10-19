# القرارات المفتوحة

كل status = OPEN؛ owners أدوار PRD وليست أسماء أشخاص مفترضة. تاريخ الاعتماد والدليل غير متوفرين. المقترحات ليست قرارات نهائية. يجمع agent الأدلة فقط؛ المالك يعتمد ثم coordinator يحدّث blocked tasks.

| ID | owner | نقطة الحجب | options/tradeoffs | required evidence | status |
|---|---|---|---|---|---|
| OPEN-01 | Executive + Finance | A4 قبل commission calculation | contract / invoice / collection / completion؛ سرعة الاستحقاق مقابل مخاطر refund | أمثلة split/tier/refund ومصفوفة attribution وإقرار trigger | OPEN |
| OPEN-02 | Executive | A0 قبل policy activation | chains حسب risk/amount/department؛ مرونة مقابل SoD وتعقيد delegation | جدول thresholds وself-approval exceptions وbreak-glass موقع | OPEN |
| OPEN-03 | Finance | A0 تصميم؛ A4 قبل posting | chart/tax/fiscal/partner policy؛ توافق قانوني مقابل تبسيط المرحلة | chart معتمد وعينات قيود وتسويات وضريبة ورأي محاسب | OPEN |
| OPEN-04 | Warehouse + Finance | A0 تصميم؛ A3 قبل stock semantics | FIFO / weighted average، units/batches/negative stock؛ دقة مقابل تشغيل | عينات receipts/issues/returns والتحويلات وسياسة negative stock | OPEN |
| OPEN-05 | Finance + Operations | A2 قبل actual margin | recognition عند fulfillment أو milestones وفق policy؛ cash ليس revenue | أمثلة project costs/overhead/tax وتوقيع formula | OPEN |
| OPEN-06 | Operations | A3 قبل production slice | output-only أو consumption/output minimum؛ منع hidden MRP | حالات transformation وstock correctness وحد scope صريح | OPEN |
| OPEN-07 | Management + IT | A1 قبل live connectors | mail API / SMTP-IMAP، SMS Misr أو approved، Telegram pilot، WhatsApp compliant؛ cost مقابل retention/reliability | accounts وصلاحيات وconsent وعقود provider وrisk assessment؛ لا QR unsupported بلا قبول مكتوب | OPEN |
| OPEN-08 | Engineering | A0 design؛ A3 field release | PWA / native / hybrid؛ deployment مقابل camera/signature/offline | device matrix وتجربة uploads/retry/conflict؛ offline posting لا يفترض | OPEN |
| OPEN-09 | Executive + Security | A5 قبل external AI data | provider/processing region/models/audit categories؛ privacy مقابل quality/cost | DPA وredaction eval وbudget وlabeled cases وthreshold sign-off | OPEN |
| OPEN-10 | Legal + HR/Finance | A0 قبل retention/access activation | record-specific retention/consent/access؛ deletion مقابل financial archive | retention schedule وسجل legal basis وemployee document grants | OPEN |
| OPEN-11 | IT + Engineering | A0 قبل production infrastructure | managed/self-hosted، availability/backup/restore/capacity/import؛ cost مقابل recovery | user counts/load profile وnumeric SLO/RPO/RTO وخطة migration وrestore drill | OPEN |
| OPEN-12 | Operations + Quality | A3 قبل acceptance/closure | checklists حسب work type وwarranty/sign-off authority؛ standardization مقابل field exceptions | نماذج قبول وPOD/QA exceptions وwarranty terms وتوقيع authority | OPEN |

قرار تقني إضافي ليس OPEN-13 مختلقاً: تسجله ADR. لا يصبح Proposed نافذاً بالتقادم. لرفع الحجب يلزم owner + date + approved option + evidence + affected tasks + tests + review؛ تحديث status وحده لا يكفي.

# تقرير التحقق من الحزمة

الحزمة planning-only؛ لم ينشأ Laravel project أو Git repository أو commits. لا تطبيق أو provider اتصال فعلي أو production deployment اختُبر. لا business OPEN ولا technical ADR اعتمد في هذه الحزمة.

| check | النتيجة الفعلية |
|---|---|
| `pwsh -NoProfile -File scripts/validate-docs.ps1` | فشل تشغيل الأمر: `pwsh` غير موجود في PATH؛ لم يُشغّل validator بهذه الصيغة |
| `& './scripts/validate-docs.ps1'` داخل Windows PowerShell 5.1 | PASS: tasks=239; PRD_IDs=124; local_links=42; JSON/enums/fields/DAG/reverse_edges/coverage/links valid |
| أمر المستخدم لحساب tasks عبر `Get-Content -Raw ... \| ConvertFrom-Json` ثم حد 160 | نجح: tasks=239 |
| أمر المستخدم لمقارنة SHA256 للمصدر والنسخة | نجح: PRD_SHA256=DD616A2AA4CEB50B5DCF3D95213C1D51339582098F87EDB067D9300C22ED1028 |
| فحص control characters غير المرغوبة وmojibake في docs Markdown | لا matches بعد تصحيح الترميز |

97 functional/architecture/NFR IDs + 12 OPEN + 15 AT = 124. فحص الروابط best-effort يتجاهل URLs/anchors/code fences بما فيها Mermaid؛ لا يثبت صحة anchor target أو rendering. لم يشغّل Mermaid renderer أو اختبارات Laravel أو static analysis للتطبيق؛ أوامر المهام أسماء وتوقعات مستقبلية.

القيد المتبقي: توفير PowerShell 7 (`pwsh`) ثم إعادة الأمر الحرفي المطلوب. نجاح Windows PowerShell البديل ليس ادعاء نجاح ذلك الأمر. حالة هذه الحزمة لا تعني قبول المنتج أو اجتياز AT-01..AT-15.

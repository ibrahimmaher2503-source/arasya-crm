# حزم مراجعة ADR — غير موقعة وغير معتمدة

> وثائق تحضير للمراجعة فقط؛ لا تمثل موافقة أو تفويض تنفيذ. لا تنشئ سجلات تحت `decision-approvals/` ولا تغيّر [فهرس ADR](../../architecture/ADR-INDEX.md) أو أي status أو task أو DAG. كل الاختيارات أدناه تبقى كما وُصفت في [حزمة مراجعة القرارات](../DECISION_REVIEW_PACK.md): لا تُحوّل توصية إلى `OWNER-SELECTED` ولا ترفع توصية إلى اعتماد.

## قواعد الحالة

- `READY FOR OWNER REVIEW` يعني أن بدائل التصميم والحدود موثقة بما يكفي لمراجعة قرار تصميمي؛ لا يعني أن قراراً اتخذ أو أن شروط task/lifecycle تحققت.
- `NOT READY FOR APPROVAL` يعني أن مدخلات مالك/دليل/نطاق لازمة للاختيار أو التحقق ما زالت غائبة. قبول التصميم لا يتطلب برهان تشغيل أو قاعدة إنتاج إذا لم يكن ذلك ضمن نطاق القرار؛ أما تفعيل التشغيل فيتطلب تحققاً منفصلاً.
- لكل قرار يختار المالك المخول `APPROVE`, `REJECT` أو `DEFER` مع خيار محدد، المبرر، التاريخ، evidence، الاختبارات، المهام المتأثرة، ومراجعة مستقلة. هذه الحقول غير معبأة هنا؛ لا يوجد توقيع أو خيار قرار مفترض.

| الحزمة | اختيار المالك غير المعبأ | الخيار/المبرر/التاريخ/evidence/tests/review |
|---|---|---|
| ADR-001 | [ ] `APPROVE` [ ] `REJECT` [ ] `DEFER` | فارغ — يملأه Engineering + Product |
| ADR-002 | [ ] `APPROVE` [ ] `REJECT` [ ] `DEFER` | فارغ — يملأه Engineering + Finance |
| ADR-003 | [ ] `APPROVE` [ ] `REJECT` [ ] `DEFER` | فارغ — يملأه Engineering + IT |
| ADR-004 | [ ] `APPROVE` [ ] `REJECT` [ ] `DEFER` | فارغ — يملأه IT + Security |
| ADR-005 | [ ] `APPROVE` [ ] `REJECT` [ ] `DEFER` | فارغ — يملأه Engineering |
| ADR-006 | [ ] `APPROVE` [ ] `REJECT` [ ] `DEFER` | فارغ — يملأه Management + IT |
| ADR-007 | [ ] `APPROVE` [ ] `REJECT` [ ] `DEFER` | فارغ — يملأه Executive + Security |
| ADR-008 | [ ] `APPROVE` [ ] `REJECT` [ ] `DEFER` | فارغ — يملأه IT + Engineering |

## ADR-001 — modular monolith

- الحالة المصدرية: `Proposed`; owner: Engineering + Product. اتجاه المراجعة `OWNER-SELECTED`: Laravel 13 / PHP 8.4، modular monolith بحدود ملكية domain؛ ليس اعتماداً عاماً للـstack ولا قبولاً للتنفيذ.
- البدائل غير المختارة هنا (لا رفض مالك): monolith غير منظم؛ microservices كبداية؛ package/scaffold بعينه. يفضل الحد المنظّم حدود الملكية وتجنب توزيع مبكر بين خدمات دون حاجة؛ لا دليل مقارن يثبت رفض أي بديل نهائياً.
- PRD alignment/conflict: PRD `ARCH-01` يطلب modular structure؛ لا تعارض معروف في هذا النطاق التصميمي. اختيار package/scaffold والـruntime والبيانات خارج هذا القبول.
- دليل قابل للقراءة: [PRD §§12.1 / ARCH-01](../../product/PRD.md)؛ سجل التفويض المحلي `A0-ARCH-901` يثبت أساس Laravel 13/PHP 8.4 وملفات المشروع الموجودة محلياً. الرصد التاريخي السابق عن غياب Composer/scaffold قديم ومختلف عن الحالة المحلية الحالية. لا مصادقة/خدمات أعمال/سياسات/موافقات/outbox مثبتة هنا.
- النطاق: مراجعة معمارية وتصميم module boundaries فقط؛ لا يلزم runtime أو DB إنتاجي لإقرار شكل المعمارية. لا يشمل قبول MariaDB أو queue/cache أو hosting أو إطلاق ميزات الأعمال.
- أدلة قبول القرار: تأكيد Engineering + Product للملكية وحدود الموديولات، سبب اختيار modular monolith، البدائل والمخاطر والحدود، وتاريخ ومراجعة موثقان. تحقق scaffold أو قاعدة البيانات ليس شرطاً لقرار معماري صرف؛ هو شرط منفصل لأي تنفيذ/قرار مختلط يتضمنه.
- المخاطر/رجوع آمن: coupling أو shared writes؛ الرجوع عبر مراجعة ADR وتصحيح الحدود المستقبلية، دون حذف بيانات أو تراجع migrations. لا توجد خطوة تنفيذ أو rollback مُفعلة.
- المهام: مباشر `A0-CORE-900`; الأثر المتعدي موثق في [مصفوفة المهام](DECISION_TASK_MATRIX.md). ليس لـ`A0-CORE-900` أي unmet task prerequisite أو decision gate إضافي؛ بوابته المباشرة الوحيدة هي ADR-001 نفسه. مع ذلك حالته المحفوظة `blocked`، والقرار وحده لا يجعله `ready`.
- المطلوب/المخاطر المتبقية: تأكيد صاحب النطاق والمبرر وحدود القبول وسجل reviewer. **READY FOR OWNER REVIEW** لمراجعة تصميمية فقط؛ لا اعتماد مسجل.

## ADR-002 — قاعدة البيانات والمعرفات

### Architecture approval handoff — 2026-10-17 (unsigned)

Status: preparation complete for human review; architecture approval remains BLOCKED by missing decisions/signoffs. ADR source remains OPEN. Governing rule: [owner-authorized staged amendment](../CHANGE_CONTROL.md#adr-002003-staged-acceptance-amendment--2026-10-17). The historical pre-staging “all tests before acceptance” requirement is superseded only as specified there; no PRD requirement is removed.

**Exact baseline presented for Engineering confirmation (not an approved option):**

- Bounded local Laravel 13.36.0 (`composer.lock`), PHP 8.4.21, PDO `mysql` using mysqlnd 8.4.21, Laravel `mariadb` connection, native MariaDB 10.4.32 Win64. Runtime versions were read without a database connection on 2026-10-17; installed versions alone do not prove their provenance for every earlier run or production suitability.
- Synthetic DDL records InnoDB, utf8mb4 and utf8mb4_unicode_ci. Project MariaDB configuration has strict mode enabled and `engine = null`; the harness checks strict SQL mode. It does not capture session isolation or assert engine on every table. Engineering must explicitly choose/confirm InnoDB and the required isolation baseline; REPEATABLE READ is a candidate requiring confirmation, not an observed session value. No inference of production support or version lifetime is made.
- Decide ULID versus UUIDv7 for new identifiers and the PK/FK/morph/serialized representation. Both synthetic PK/FK round trips passed; ULID remains a recommendation, not approved. No renumbering of existing identifiers.
- DECIMAL(20,6) is a synthetic storage candidate only; Finance must confirm application precision/scale, fixtures and OPEN-03 policy before affected behavior is accepted. Domain owners must decide business-key uniqueness scopes.
- Application writes remain transaction-bound, scoped by legal entity, with DB uniqueness/FK barriers, deterministic lock order and bounded retry/recovery. Forward-first migrations and isolated backup/reconciliation are required; no production operation is authorized.

**Evidence and limits:** [existing MariaDB record](../evidence/ADR-002-003-environment-check.md), final 2026-10-17 bounded batch: 32 main assertions + 2 races + 3 queue assertions + 3 restore assertions = 40/40. This is the prior executed result, not a fresh run. Reserve/issue races, transaction/visibility, exact decimal primitives, FK/unique negatives, identifiers and synthetic forward/recovery/restore passed within their fixture scope. Actual period-close interleavings, concurrent same-source uniqueness, Finance-approved rounding, full model/route/serialization/relationship compatibility and project migrations remain NOT TESTED. Operational encryption/restore permissions and measured RPO/RTO remain NOT TESTED. No SQLite result substitutes for MariaDB behavior.

**Authority checklist — every item unchecked unless explicitly stated:**

- Existing: Ibrahim's Product and Engineering design-direction approval, 2026-10-16, in the [approval record](../decision-approvals/ADR-002.md). Existing: Ibrahim's Product/Engineering staged-governance authorization, 2026-10-17. Neither is exact-baseline acceptance.
- [ ] Engineering (Ibrahim or explicitly delegated authority): approve/reject/defer the exact runtime/driver/engine/isolation/identifier option above; identify unresolved evidence limits, rationale and compatibility boundary. Record name/date/evidence/review.
- [ ] Finance authority (not yet named): architecture review of decimal/identifier/reference safety and fixture/uniqueness boundaries; keep OPEN-03 business policies pending until their own approval.
- [ ] Operations authority (not yet named): architecture recovery/version/ownership boundaries; operational restore signoff and OPEN-11 targets remain separate production gates.
- [ ] Applicable domain owners (not yet named): explicit business-key scope decisions before affected application integration acceptance. These do not replace Finance/Operations approval.

Use the existing approval record only after explicit human decisions. The shared verifier requires matching Accepted states, all normal evidence fields, Architecture/Bounded-local scope, and named/dated Engineering, Finance and Operations architecture approval fields. This package grants none of them. Current `Decision: Pending` and all Pending fields stay unchanged.

## ADR-003 — queue/cache/search

### Architecture approval handoff — 2026-10-17 (unsigned)

Status: preparation complete for human review; architecture approval remains BLOCKED. ADR source remains Proposed. Same [staged amendment](../CHANGE_CONTROL.md#adr-002003-staged-acceptance-amendment--2026-10-17) applies. No live provider, deployment, real/customer data or irreversible operation is authorized.

**Exact baseline presented for Engineering/IT confirmation (not approved):** the ADR-002 runtime/DB candidate above, Laravel first-party database queue and database cache; MariaDB indexed SQL search with server-enforced legal-entity/record/field scopes; no initial Redis or external search. Database domain records remain authoritative. Outbox plus domain/audit intent commits atomically; inbox/dedup plus consumer effect commits atomically; publish occurs after commit. Preserve event_id, version checks and reconciliation. Reauthorize current actor/entity/assignment/purpose/version at execution. Cache/index cannot grant authority or expose unmasked contact data.

Failed jobs use Laravel `database-uuids`; authorized retry identifies the failed job by UUID, preserves the event ID and audits the authorization. Retry is bounded and ambiguous external outcomes require reconciliation rather than blind resend. The queue fixture overrides retry_after to 1 second and uses tries=1/2 solely for synthetic scenarios; project config defaults retry_after to 90 seconds. Neither is an approved operational timeout/backoff/lease/supervision schedule. Engineering + IT must decide those contracts and their boundaries. Stop-when-empty/lease recovery is proven synthetically, not production graceful deployment.

**Evidence and limits:** same 40/40 record; real Laravel worker kill/lease redelivery, failed_jobs, authorized UUID replay and drain passed 3/3. Synthetic atomicity/dedup/version handling, scoped search/count/export masking and revoked direct-query denial passed. Application enqueue/publish and projection contention, queued reauthorization, crash before/after commit/publish/inbox, projection rebuild, provider rejection/timeout/lost-response simulation and deletion leakage remain NOT TESTED. Outages, production drain/security/load/capacity are NOT TESTED. Independent security signoff and OPEN-10/11 approval are absent.

**Authority checklist:**

- Existing: Ibrahim's Product + Engineering direction approval (2026-10-16) and staged-governance authorization (2026-10-17); [approval record](../decision-approvals/ADR-003.md). No exact-baseline acceptance or independent signoff is implied.
- [ ] Engineering: confirm exact baseline, event/transaction/authorization/replay/version contracts, retry/lease limits and integration acceptance criteria.
- [ ] IT authority (not yet named): confirm local backend and worker/recovery supervision boundaries; approve operational schedules separately before activation.
- [ ] Independent Security authority (not yet named): architecture threat boundaries for scopes/masking, revocation, secrets/logs, replay authorization and fail-closed behavior. Later configuration/TLS/least-privilege and application security acceptance remain required.
- [ ] Legal authority (not yet named): architecture retention/replay/deletion boundaries; no implied approval of an OPEN-10 retention schedule.
- [ ] Operations authority (not yet named): recovery/drain/replay accountability and safe failure boundaries; later operational restore/deployment signoff remains required.
- [ ] OPEN-10 Legal + HR/Finance owners: approve retention/legal basis/access/deletion before affected activation. OPEN-11 IT + Engineering: approve workload/SLO/RPO/RTO before production infrastructure acceptance. Domain owners approve affected application contracts. All are still pending.

Required architecture fields: Engineering, IT, Security, Legal and Operations named/dated approvals plus existing normal acceptance/evidence fields. Preserve `Decision: Pending` until legitimate decisions exist; agent cannot provide signoffs or treat application/production NOT TESTED as PASS.

### Shared blocked-task dependency matrix

All statuses below remain blocked; no reassessment mutation is performed in this documentation phase.

| Task | Exact ADR gate | Exact task predecessors and observed status | Eligibility after legitimate architecture approval |
|---|---|---|---|
| A0-ARCH-902 | ADR-002 + ADR-003 | A0-ARCH-901 done | Eligible for coordinator reassessment only after both ADR gates pass |
| A0-CORE-007 | ADR-003 | A0-ARCH-901 done; A0-CORE-006 done | Eligible for reassessment after ADR-003 gate passes |
| A0-CORE-008 | ADR-003 | A0-CORE-007 blocked; A0-CORE-900 done | Also requires A0-CORE-007 done |
| A0-ARCH-003 | ADR-003 | A0-ARCH-901 done; A0-ARCH-002 done | Eligible for reassessment after ADR-003 gate passes |
| A0-ARCH-004 | ADR-003 | A0-ARCH-003 blocked; A0-CORE-900 done | Also requires A0-ARCH-003 done |

Source: [tasks.json](../../backlog/tasks.json). Done ADR-evaluation tasks are not ADR acceptance. Existing dependencies and OPEN gates are not removed. Coordinator reassessment and claim remain separate authorized operations after valid approvals.

### Evidence completeness, provenance and Git handoff

- The evidence record contains chronological failed runs and stale initial NOT RUN observations. Use the final bounded batch for its limited synthetic scope. Its “no kill/crash injection” statement concerns commit/publish/inbox boundaries; a leased worker kill was tested. Its revoked-access PASS is a direct query, not queued reauthorization. Apparent contradictions must not be collapsed into full PASS.
- The final batch summary decomposes to 40 assertions but does not retain a full machine-readable aggregate/child transcript or exact PHP/PDO/server/session-isolation manifest for that run. Current runtime inspection is distinct evidence. The retained queue JSON is ignored local diagnostics, not durable approval evidence; sanitized reviewable content is summarized in the existing record. No credentials/raw logs/dumps should be staged. Further evidence collection, if an authority requires it, needs separate scope; no harness rerun is part of this handoff.
- Governing ADR files retain explicitly historical prose about missing tooling/older runtime and earlier combined acceptance gates. Their 2026-10-17 governing amendment takes precedence; those historical statements are not current runtime facts. Approval records remain the source for actual signoffs. Do not read the package's historical introductory “unsigned” language as revoking existing design-direction approvals.
- Lifecycle compatibility: PowerShell 7.6.5 date coercion was reproduced against committed baseline; preserve-string parsing fixes it without changing SHA-256/intent contracts. Prior executed verification: timestamp regression 3/3, staged assertions 16/16, full lifecycle suite PASS (27 fixture events), governance/DAG/coverage/links PASS. This phase does not rerun those suites or MariaDB. Lifecycle source and evidence fingerprints below bind the current review materials, not the earlier historical execution environment.
- Tracked modified files: governing ADR-002/003, CHANGE_CONTROL, LOOP_OPERATIONS, task-loop.ps1 and decision-evidence.ps1. Existing test-task-loop.ps1 is tracked. Untracked intended review files include this package, both approval records, MariaDB evidence, harness/fixture sources, test-adr-fixture.ps1, test-loop-timestamp-roundtrip.ps1, test-staged-acceptance.ps1, composer.json and composer.lock. Git readiness is INCOMPLETE. No staging/commit is performed in this documentation/read-only phase; do not stage unrelated work, credentials, local diagnostics, preserved baseline directories or persisted governance history automatically.
- Repository loop revision remains 124. state.json SHA-256: `5D3BB4FF723AEDA4184568D1F6A6637774591D89C0F88CAD8117756D186F15DB`; tasks.json: `2E5F2C302BA56B7DBF53BABE71EBBF4EA14706EE22B9FD1D4E1C9ED049F3AEE0`. This phase must preserve approval records and all loop-history bytes.

| Review file | SHA-256 (2026-10-17) |
|---|---|
| scripts/task-loop.ps1 | 88746EC9A19CCC0D48A9A80B1C28D5AF9D843110B4070DFEBEAE1C8CB5BCD198 |
| scripts/decision-evidence.ps1 | A08E3CF16FA88E3CEA943408920FB2D8AD158D2692A17BFC80A0FD539B519700 |
| scripts/test-loop-timestamp-roundtrip.ps1 | BD753363DDA4C2888848DAD10E813E0A7D151DC2D6B3BD056C90DDEC9E6FC3AE |
| scripts/test-staged-acceptance.ps1 | 238E150FC1BEF98C59D379BBB7BABBBCE73887E977FD81DCFCBD90D7A9657E36 |
| scripts/test-task-loop.ps1 | 37A5239B356935250FD1FD46DEABB4FF7FFF1B0A8AA72C37990A9963C0E2276E |
| scripts/adr-mariadb-validation.ps1 | 28529A6685E25379D1B8D22E4A0AB9E8E84A306A0ECF94088D03FAD6FEC88968 |
| scripts/adr-validation/queue-lifecycle.php | 51325A694DF9C933547A7EEDE5DFAB501688B87FE255E7A66B61EAF61406CC4C |
| composer.lock | 6102AC0EA6995B03B1AC6672BF87A6212FF33FCEF08C28434C64E825F8D9DA9E |

**Next executable human action:** Ibrahim confirms or rejects the exact local baseline (including unresolved isolation and identifier selections) and names the Finance/IT/independent Security/Legal/Operations reviewers. Each reviewer then returns approve/reject/defer with scope, rationale, authority, date and evidence. This package is the handoff; do not accept ADRs, record missing approvals as granted, stage unrelated files or start implementation automatically.
## ADR-004 — private storage

- الحالة `OPEN`; owner IT + Security. اتجاه الحزمة `OWNER-SELECTED`: `LOCAL` خاص خارج webroot كبداية؛ هذا لا يثبت تشفير at-rest ولا يحسم baseline `encrypted private object storage`.
- البدائل/سبب عدم الاختيار النهائي: private encrypted object storage يطابق عبارة PRD لكنه يتطلب تحديد مزود/مفاتيح/صلاحيات واستعادة؛ `LOCAL` اختيار owner-direction مبدئي للبداية المحلية فقط ولا يثبت تحقيق تلك العبارة. المكتبة المرشحة ليست اعتماداً أمنياً، ولم يرفض المالك أي خيار إنتاجي.
- PRD alignment/conflict: [PRD `DOC-02`, `ARCH-02`, `NFR-01/03`](../../product/PRD.md) يذكر private encrypted object storage. `LOCAL` خارج webroot لا يثبت التشفير؛ هذا تعارض/فجوة baseline فعلية يلزم حسمها من IT + Security، لا مجرد نقص اختبار.
- الدليل الحالي: ملفات/إعداد محلي لا تثبت صلاحيات production، مفاتيح/تشفير، scanner أو backup/restore متسق للملفات وDB.
- النطاق: قرار architecture/storage boundary منفصل عن قبول تشغيل التخزين. لا اختبار على ملفات حقيقية أو storage خارجي.
- مطلوب: IT + Security يحددان تعريف LOCAL مقابل object baseline، key custody/encryption، access/download authorization، malware/MIME/size/hash quarantine، retention/backup consistency، ونتائج restore معزول وfixtures تركيبية.
- مخاطر/رجوع: تسرب/فقد ملفات أو عدم اتساق المراجع؛ rollback غير مدمر عبر تحويل provider مع حفظ IDs والنسخ وإعادة تحقق الروابط، دون حذف الملفات الأصلية قبل restore proof.
- مباشر `A0-DOC-003`, `A0-DOC-004` ويتطلبان OPEN-10 أيضاً. **NOT READY FOR APPROVAL**: خصوصية/تشفير/نسخ فعلية غير مثبتة وتعارض عبارة baseline يحتاج حسم مالك.

## ADR-005 — Mobile delivery

- الحالة `OPEN`; owner Engineering. اتجاه الحزمة `OWNER-SELECTED`: Web + PWA online-only؛ ليس اختيار جهاز أو تأكيد parity ميداني.
- البدائل غير المختارة (لا رفض مالك): native أو hybrid؛ بقيت دون اختيار لأن device matrix واختبارات camera/upload/signature/reconnect غير موجودة، لا لأن PRD يستبعدها.
- PRD alignment/conflict: [PRD `NFR-05/06` و`AT-13`](../../product/PRD.md#L646-L652) يطلب responsive web/mobile parity وRTL والكاميرا/الرفع والتوقيع والتعامل مع الاتصال المتقطع؛ PWA online-only متسق تصميمياً فقط إذا حقق المتطلبات والـsafe retry، ولا تعارض مثبت ضمن هذا الشرط. أدلة الأجهزة والسلوك شرط قبول release وليست شرط موافقة الشكل المعماري.
- دليل متاح: قرار اتجاه موثق فقط؛ لا اختبارات أجهزة أو offline conflicts. لا offline financial/stock/approval commit في أي خيار.
- النطاق: قرار design قابل للفصل عن release؛ قبول قابلية الأجهزة يتطلب device matrix ونتائج فعلية مصرح بها، وليس production proof.
- مطلوب: Engineering يختار baseline/البديل ويحدد الأجهزة/المتصفحات والوظائف الحرجة، ثم تجربة تركيبية مصرح بها لـRTL/session/revoke/upload/retry/conflict/accessibility. دون حفظ offline لمعاملات حساسة.
- المخاطر/الرجوع: عدم دعم جهاز أو فقد upload؛ rollback إلى web responsive مع المحافظة على البيانات/المرفقات وعدم إعادة إرسال أثر مكرر.
- مباشر `A5-NFR-009`, `A5-NFR-010`; لكل منهما OPEN-08 أيضاً. **NOT READY FOR APPROVAL** حتى تحديد device support وأساس choice.

## ADR-006 — messaging providers

- الحالة `OPEN`; owner Management + IT. اتجاه الحزمة `OWNER-SELECTED`: محادثة داخلية في Phase A، إشعارات داخل التطبيق وBrowser Push بعقود ومزودين مجردين واختبارات اصطناعية؛ البريد المؤسسي وSMS/WhatsApp الحي مؤجلون، و`TELEGRAM` مستبعد من التسليم الحالي. هذا اتجاه مراجعة غير معتمد ولا يخفض PRD MUST.
- البدائل/سبب عدم الاختيار النهائي: mail API مقابل SMTP/IMAP، SMS Misr أو مزود معتمد، ومسار WhatsApp الرسمي؛ لم تُختَر لأن حسابات الشركة والعقود والتكلفة والاحتفاظ/الموافقة وسلوك التسليم غير مثبتة. `TELEGRAM` ليس بديلاً مختاراً لهذا التسليم وفق اتجاه المالك؛ QR/Web automation غير مدعوم ومستبعد لأسباب امتثال/استقرار، لا كحل مُعتمد.
- PRD alignment/conflict: [PRD `COM-03/04/06`](../../product/PRD.md) يطلب البريد المؤسسي وSMS كـ`MUST` تشغيلي وقرار/تقييم WhatsApp؛ تأجيل التشغيل الحي يمثل انحراف Phase A يحتاج change-control approval صريحاً قبل اعتماد الحد أو تقسيم المهام. الاختبارات الوهمية لا تلبي MUST ولا تثبت provider حي. استبعاد Telegram متسق مع كونه اختياراً اختيارياً في PRD، دون إلغاء أي requirement إلزامي.
- المتاح: PRD يلزم corporate email وSMS MUST وWhatsApp channel decision/evaluation؛ proposals الحالية ما تزال مسودات، و`AT-11` يطلب دلالات فشل/retry لا paid send بذاته. لا provider credentials/account/webhook أو consent/evidence.
- النطاق: اعتماد design/provider abstraction لا يعني live egress. أي تقسيم fake-only يحتاج change control وDAG منفصل؛ هذه الوثيقة لا تعدل gate.
- مطلوب: Management + IT يختاران القناة ومسار الامتثال والحساب/الملكية/التكلفة/retention؛ Security/Legal يراجعان البيانات والموافقة؛ اختبارات contract/failure/duplicate/revocation fake-only. اعتماد تشغيل حي يتطلب تفويض egress/credentials وprovider اختبار مستقل خارج هذه المهمة.
- مخاطر/رجوع: رسائل مكررة/وجهة مكشوفة/accepted≠delivered/حظر حساب؛ وقف dispatch، reconcile حالة `unknown` مع provider دون إعادة إرسال عمياء، لا رسائل حقيقية في الاختبار.
- مباشر `A1-COM-005..012`; OPEN-07 أيضاً. **NOT READY FOR APPROVAL**: مدخلات القنوات والمالكين/الامتثال غائبة.

## ADR-007 — AI provider and privacy

- الحالة `OPEN`; owner Executive + Security. اتجاه الحزمة `OWNER-SELECTED`: تأسيس AI Audit داخلي، و`OPENAI` هدف تقييم مستقبلي لا تكامل حي؛ قواعد حتمية أولاً توصية.
- البدائل غير المختارة (لا رفض مالك): hosted مقابل self-hosted وأي مزود/model/region؛ لا يوجد DPA أو data-class review أو budget/quality evidence يرجح خياراً. لا data egress أو provider call.
- PRD alignment/conflict: [PRD `AI-02..09`](../../product/PRD.md) يطلب AI audit عملياً في Phase A، بينما [SCOPE](../../product/SCOPE.md) يؤجل autonomous chat/voice؛ تأسيس foundation أو فتح OpenAI evaluation لا يثبت fulfillment العملي ولا يجيز egress. لا تعارض في قرار تصميم advisory-only ضمن حد التصميم، لكن الفجوة التشغيلية باقية.
- الموجود: وثائق AI architecture/coverage مستقبلية؛ كل tools proposed/restricted-disabled ولا ERP services أو approvals/outbox فعلية. `AT-10` يمنع AI من post/delete.
- النطاق: تصميم audit advisory لا يمنح tools. اعتماد مزود/معالجة يتطلب DPA/privacy/region/budget/threshold review؛ لا تشترط production proof لمراجعة شكل الواجهة، لكن يلزم evidence لمعالجة خارجية أو إطلاق عملي.
- مطلوب: Executive + Security يحددان provider/region/models/data classes/retention/budget/kill switch؛ Legal حسب المعالجة؛ synthetic evaluation schema/prompt-injection/outage/invalid output/cost instrumentation tests، ثم approval منفصل لأي live egress.
- مخاطر/رجوع: PII leakage أو hallucination/cost؛ kill switch fail closed، إيقاف provider وعدم استخدام output لأثر أعمال؛ لا حذف audit أو عكس journal/stock.
- مباشر `A5-AI-003`, `A5-AI-004`, `A5-AI-009`, `A5-AI-010`; OPEN-09 كمانع قرار provider. **NOT READY FOR APPROVAL** لأي provider أو action scope؛ تصميم الأدوات لا يفعّلها.

## ADR-008 — hosting runtime and operations

- الحالة `OPEN`; owner IT + Engineering. اتجاه حزمة المراجعة: GitHub Actions→SSH وVPS/cPanel/WHM/root SSH هي إفادة مالك غير متحقق منها، لا حقيقة تشغيلية أو اعتماد hosting. single-VPS مجرد توصية تقييم.
- البدائل/سبب عدم الاختيار النهائي: managed مقابل self-hosted غير محسوم لغياب resource/load profile وOS/DB/backup/restore proof؛ إفادة VPS/cPanel لا تجعل self-hosted اختياراً مثبتاً. اتجاه GitHub Actions→SSH و`single-VPS` توصيف/توصية المراجعة، لا رفض للـmanaged ولا قرار hosting نافذ.
- PRD alignment/conflict: [PRD `NFR-03/04`](../../product/PRD.md) يطلب تشغيل/استعادة/availability/security evidence ولا يفرض مزود نشر بعينه؛ لا تعارض معروف في توصية التصميم، لكن اختيار الإنتاج والـnumeric RPO/RTO والـrestore drill أدلة لازمة للتشغيل وليست شرطاً لمراجعة شكل التصميم.
- دليل متاح: local runtime فقط؛ لا production access أو VPS checks ضمن الإذن. لا تخلط ذلك مع سجل تفويض A0-ARCH-901 المحلي.
- النطاق: قرار تصميم/تقييم hosting لا يتطلب اتصال production من هذه المراجعة؛ اختيار الإنتاج والتشغيل يحتاج IT proof على بيئة مملوكة ومصرح بها قبل أي نشر.
- مطلوب: IT + Engineering يقدمان resource/load profile، OS/MariaDB/queue/storage، availability/security/backup/restore evidence، numeric RPO/RTO، staging/prod separation، deploy/recovery runbook وreview. لا أرقام مختلقة.
- مخاطر/رجوع: capacity أو restore failure أو exposed SSH؛ rollback موثق غير مدمر على بيئة اختبار، لا نشر أو اتصال هنا ولا حذف بيانات.
- مباشر `A5-NFR-005`, `A5-NFR-006`; OPEN-11 أيضاً. **NOT READY FOR APPROVAL** لغياب ملف السعة والأهداف وبيانات الاستضافة المؤكدة.

## القرار اللاحق

كل حزمة غير موقعة؛ لا `APPROVE` أو `REJECT` أو `DEFER` مختار. [Change control](../CHANGE_CONTROL.md) منفصل عن ADR، وسجلات الاعتماد المستقبلية يجب أن تتبع شروط verifier في [LOOP_OPERATIONS](../../agents/LOOP_OPERATIONS.md#L57-L72). لا يوجد سجل اعتماد أنشئ هنا.

## Governance readiness review — 2026-10-17

This review grants no approval. ADR-002 remains OPEN and ADR-003 Proposed. Loop state revision 124 and all approval/history files are preserved. Nothing is staged or committed; preserved baseline copies and the diagnostic directory are not deleted.

### Technical recommendation for authority review

Current read-only inspection confirms PHP 8.4.21, Laravel 13.36.0, PDO mysql/mysqlnd 8.4.21 and native MariaDB 10.4.32 Win64. This establishes installed versions today, not a complete historical run manifest. The previously executed 40/40 synthetic result is unchanged and was not rerun.

MariaDB 10.4 reached community maintenance end on 2024-06-18 according to the [official maintenance table](https://mariadb.org/about/). Treat 10.4.32 as the existing historical/local disposable test target, not an endorsed production baseline. Engineering + IT must explicitly choose a supported intended target and decide the compatibility evidence required for that target; no upgrade or alternate version is selected here.

The retained synthetic CREATE TABLE output proves InnoDB for the recorded table; config engine=null delegates selection to the server and is not an engine guarantee for all tables. Historical synthetic FK cross-entity rejection (1452) and uniqueness rejection (1062) are evidence within their fixture scope, not application authorization. MariaDB documents [InnoDB foreign-key enforcement](https://mariadb.com/docs/server/architecture/server-constraints/foreign-key-constraints) and [REPEATABLE READ as its default isolation](https://mariadb.com/docs/server/reference/sql-statements/transactions/transactions-repeatable-read). No live server/session isolation was queried or captured by this review or the retained run manifest. Recommend explicit InnoDB and a reviewed isolation baseline, with period-close/deadlock/idempotency integration evidence required later. A documented default is not an observed session setting.

Identifier recommendation: retain ULID as a candidate for new IDs where compact text representation is valuable; UUIDv7 is equally available through installed Laravel HasUuids (Str::uuid7). Installed HasUlids lowercases its generated strings. Choose one canonical representation consistently across PK/FK/morph/serialization and define case/collation behavior; do not renumber existing identifiers. Both synthetic PK/FK variants passed, but full route/model/serialization/relationship compatibility and comparative performance were not tested. Engineering must choose explicitly; neither recommendation nor this review ratifies either identifier.

### Git inventory and proposed commits — authorization required

Read-only inventory before this review: 18 modified tracked files, 309 untracked files, zero staged files. All paths below are relative to repository root. Proposed commits are review sets, not authorization to commit.

1. Timestamp compatibility: `scripts/task-loop.ps1` and `scripts/test-loop-timestamp-roundtrip.ps1`. Preserve the existing exact-hash guard and regression. No loop state/history changes.
2. Staged architecture governance: `scripts/decision-evidence.ps1`, `scripts/test-staged-acceptance.ps1`, `docs/governance/CHANGE_CONTROL.md`, `docs/agents/LOOP_OPERATIONS.md`, `docs/architecture/adr/ADR-002.md`, `docs/architecture/adr/ADR-003.md`, `docs/governance/decision-approvals/ADR-002.md`, `docs/governance/decision-approvals/ADR-003.md`, and this approval packet. Review inherited non-ADR-002/003 sections in this currently untracked shared packet before authorizing whole-file inclusion; the proposed set is not yet approved as a self-contained repository snapshot.
3. Synthetic validation evidence and fixtures: `scripts/adr-mariadb-validation.ps1`, `scripts/adr-validation/concurrent-race.php`, `scripts/adr-validation/queue-lifecycle.php`, `scripts/test-adr-fixture.ps1`, `docs/governance/evidence/ADR-002-003-environment-check.md`. These are test sources and sanitized evidence, not generated databases or runtime fixtures. This commit depends on separately reviewed existing Laravel foundation/runtime files if a clean checkout must execute it; do not bundle the unrelated application tree automatically.

Exclude from all three sets: `docs/governance/loop/state.json`, `docs/backlog/tasks.json`, generated BACKLOG snapshots and loop handoffs; `.env`, credentials/keys, `.tools` diagnostics/dumps/run directories; temporary baseline copies; unrelated application, skills, Spec Kit, README, ADR-001/index and validator edits. Those existing changes require their own provenance/scope review. Existing regression source `scripts/test-task-loop.ps1` is already tracked and unchanged. Runtime dependency files composer.json/lock are currently untracked and are a separate foundation review dependency, not implicitly approved for inclusion.

An exact 16-file proposed-set scan found no private-key blocks, AWS access-key IDs, GitHub-token patterns or OpenAI-key patterns, and no forbidden runtime/state/temp path. This is a bounded static check, not a guarantee that arbitrary secrets or inherited unrelated material are absent. Human diff review remains mandatory before staging. No git add -A, staging, commit or source-file overwrite occurred.

Preserved external baseline directory: `C:\Users\N\AppData\Local\Temp\arasya-baseline-ae40b8be00f547bb8e976f1874873c99`. Preserved disposable diagnostic identifier: `7cbfc70d33764011ac6df35bcff7d2c0`. Both are excluded from proposed commits.

### Link-count reconciliation and current dependency eligibility

The validator currently counts local inline/reference Markdown destinations, ignoring fenced code, external URLs and pure anchors, and prunes only root .git/.tools/vendor/node_modules directories. There is an earlier uncommitted traversal-scope change in scripts/validate-docs.ps1; it was already present in both the recorded 520 and 524 runs and was not modified in this review.

The ADR-002/003 packet replacement accounts for the reported delta: the old two sections had two PRD local links; the replacement has six (two CHANGE_CONTROL references, one evidence record, two approval records and one task register), hence 520 - 2 + 6 = 524. The pre-edit shared packet was untracked and no Git blob snapshot is available, so this is a reconstruction from the recorded edit and current destinations, not a byte-for-byte historical diff. Current link validation is authoritative for the current tree. External official references added in this review do not increase the local-link count. Anchor existence is outside this validator's best-effort check.

Read-only coordinator Ready: 239 tasks, 35 done, 85 blocked, 119 planned, zero active leases, no pending intent, currently_ready=0. An independent evaluation using the coordinator's actual readiness functions also finds zero unfinished tasks with all task/decision prerequisites satisfied. No independent task can be claimed/executed now; no execution order is available. The five-task matrix above remains current. DECISION_TASK_MATRIX.md is an explicitly dated 2026-10-16 historical snapshot with an older source hash/counts; do not treat its old potential-unlock column as current eligibility or overwrite it silently.

Next safe human actions: (1) review the exact proposed Git file sets and inherited shared-packet content, then authorize staging/commits separately; (2) Engineering/IT decide the exact supported-target versus historical-local baseline, engine/isolation and identifier choice; (3) name role authorities and collect approve/reject/defer decisions with evidence/date/scope. Only after valid architecture approvals may the coordinator reassess the existing gates. No application work, migration, database connection, approval transition or task transition is performed here.

## Owner direction update — 2026-10-17

Ibrahim explicitly approved the Engineering design direction: PHP 8.4 + Laravel 13; MariaDB 11.4 LTS for qualification; InnoDB for transactional domain tables; initial REPEATABLE READ; textual ULID identifiers (26 characters). See the [dated owner decision evidence](../../architecture/adr/ADR-002.md#owner-engineering-design-direction--2026-10-17) and the ADR-002/003 approval records. This addendum supersedes the earlier request to choose those directions; it does not grant final architecture approval. Prior alternatives/recommendations remain historical review material, not competing current selections.

Do not request these same direction choices again. The next Engineering review must address exact 11.4 patch/build/platform/PDO qualification, ULID normalization/collation and reference compatibility, plus ADR-003 retry/lease/timeout, supervision, replay, authorization, event and recovery contracts. Local PHP 8.4.21/Laravel 13.36.0 observations and the 40/40 MariaDB 10.4.32 run remain historical technical evidence only; no result is transferred to 11.4 and no new test was run.

Next executable action: Engineering specifies the exact 11.4 qualification matrix and remaining contracts for role review, while Finance/Operations (ADR-002) and IT/independent Security/Legal/Operations (ADR-003) provide their still-pending scoped decisions. Any qualification execution needs separate authorization. Architecture Engineering approval, both ADR acceptance gates and all task statuses remain unchanged. This update neither closes OPEN policies nor authorizes staging/commits, implementation, deployment or production operations.

# ضبط التغيير

## GOV-AMEND-001 — Executive Decision Authority appointment (prepared)

The proposed appointment of Ibrahim as `Executive Decision Authority` is prepared in [GOV-AMEND-001](proposals/GOV-AMEND-001.md). It is **not adopted**. If formally adopted, it applies only to existing Executive-owned decisions and does not replace Product, Engineering, Finance, IT, Security, Legal, Operations, domain-owner, or independent-review authority. Ibrahim cannot independently review a decision he made or authorized. The formal adoption record is [GOV-AMEND-001 evidence](evidence/GOV-AMEND-001.md); its blank fields must be completed by an authorized human before any governance state changes.

Baseline هو PRD الأصلي؛ لا تعدله لترتيب التنفيذ. CR يتضمن requester وسبب/مرجع requirement والنطاق الحالي/المقترح والبدائل وأثر privacy/data/finance/operations والمهام/الاعتماديات والاختبارات والمالك والتاريخ والحالة.

دورة: Draft → Impact reviewed → Approved/Rejected → Scheduled → Verified. Product owner يعتمد النطاق؛ Finance/Security/Operations يعتمدون أثر اختصاصهم. ADR يسجل technical decision لا يحل محل business sign-off. Approved فقط بإثبات خارجي صريح؛ agent لا يعتمد لنفسه.

بعد الاعتماد حدّث القرارات وADR وtasks والتتبع وacceptance criteria تحت lock واحد؛ validate DAG وlinks ثم مراجعة diff. أضف deferred items إلى طلب منفصل لا إلى Phase A ضمنياً. لا تمس PRD verbatim؛ deviations موثقة مع المصدر.
## ADR-002/003 staged acceptance amendment — 2026-10-17

Authorization: Ibrahim, Product Owner and Engineering Decision Authority, explicitly authorized this amendment in the message “MY ARASYA — OWNER AUTHORIZATION: STAGED ACCEPTANCE”. This authorizes the governance separation, not missing role signoffs or ADR acceptance. Reason: remove circular implementation prerequisites while preserving application and operational acceptance. Alternative retained: full acceptance before implementation; rejected for this bounded local stage only. PRD requirements and unrelated decision gates are unchanged.

This section supersedes earlier undifferentiated “before acceptance” wording for ADR-002/003 only. Existing evidence: [40/40 synthetic MariaDB baseline](evidence/ADR-002-003-environment-check.md). These executed synthetic checks are technical baseline evidence only; incomplete criterion coverage remains NOT TESTED.

| Stage | ADR-002 requirements | ADR-003 requirements |
|---|---|---|
| Architecture Acceptance | Explicit target version/PDO/transaction engine/isolation and identifier choice, compatibility limits, Finance fixtures/contracts and domain-owner uniqueness review; Engineering + Finance approval and Operations recovery-boundary approval. | Database queue/cache/scoped SQL search contracts, non-authoritative cache, authorization/masking, bounded retry/replay/supervision/recovery boundaries; Engineering + IT + Security + Legal + Operations approval of the local architecture scope. |
| Application Integration Acceptance | Actual reserve/issue and competing period-close behavior; concurrent source/idempotency uniqueness; Finance-approved decimal/rounding and domain keys; model/route/serialization/relations; project forward migrations, backfill and recovery. | Application atomic outbox/inbox and audit, enqueue/publish/projection contention, duplicate/out-of-order handling, queued reauthorization, crash boundaries, retry/DLQ/replay, rebuild/reconciliation, synthetic provider rejection/timeout/lost response, search/count/export masking and revocation/deletion. |
| Production Readiness | Supported production engine/driver configuration, secure encrypted backups and permissions, operational restore/reconciliation and measured RPO/RTO. | Approved workload/capacity/performance, service/network/TLS/secrets/least privilege, outages, deployment drain/recovery, retention/legal deletion and operational replay/restore. |

Finance OPEN-03 and relevant domain-owner business decisions remain mandatory before accepting or activating their affected application behavior. Security review/signoff remains mandatory for affected application security and production configuration. IT and Operations operational approval, Legal/Operations retention/replay/restore signoff, OPEN-10 Legal + HR/Finance policy approval, and OPEN-11 IT + Engineering workload/SLO/RPO/RTO approval remain mandatory at their applicable activation/production stages. Architecture-stage reviews approve boundaries only and do not substitute for these later approvals. No policy is implicitly closed or waived.

Coordinator contract: only after legitimate architecture approvals, use `Status: Accepted` consistently in the ADR and index and `Decision: Accepted` in its approval record, with `Acceptance stage: Architecture` and `Implementation scope: Bounded local only`. Required named, dated role fields use `Architecture ROLE approval: Approved; authority name; yyyy-MM-dd`; Pending never passes. Existing evidence/date/option/review requirements still apply. Recorded authorities must be supported by explicit owner authorization; field validation cannot establish identity or fabricate a signoff. Production acceptance is never inferred from this local gate.

Direct task gates remain unchanged: A0-ARCH-902 needs both ADRs; A0-CORE-007 and A0-ARCH-003 need ADR-003; A0-CORE-008 and A0-ARCH-004 also need their implementation predecessors done. Reassess only through task-loop. Completing a local implementation task does not accept its application integration or production readiness. Deployment, real integrations, production/customer data and irreversible operations remain prohibited without separate explicit authorization.

Current amendment state: owner-authorized and applied; domain-role approvals remain pending as recorded. No application execution is authorized in this amendment run. Verification: focused staged-approval fixtures plus the existing coordinator lifecycle tests; no MariaDB rerun. Privacy/data/finance/operations impact: no real data or services changed; all domain gates retained. No task dependencies are removed and no additional scope is scheduled.

## GOV-AMEND-002 — Founder-Led Governance

GOV-AMEND-002 records Ibrahim's explicit founder-led authority for bounded DEVELOPMENT eligibility. It consolidates internal decision capacities without treating them as independent review. The coordinator reads the deterministic, task-scoped allowlist in `gate-policy.json`: DEVELOPMENT permits only the named synthetic/local slices; INTEGRATION requires feature tests, authorization checks, invariants and technical review; PRODUCTION retains security, legal, financial and operational readiness. OPEN-02, ADR-002 and ADR-003 remain unresolved as decisions, and the prior GOV-AMEND-001 record remains historical PREPARED evidence.

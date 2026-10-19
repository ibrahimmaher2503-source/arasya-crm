# OPEN decision gap matrix — all twelve unresolved

> `OPEN` remains the source status for all twelve decisions. This is an evidence/readiness aid only, not approval, scope change, gate amendment, or task-state update. Keep provisional `OWNER-SELECTED` values and `RECOMMENDED-DEFAULT` proposals distinct in [Decision Review Pack](../DECISION_REVIEW_PACK.md). Current availability means documented statements only; no evidence below is an executed acceptance test unless explicitly described as such. All requested decision evidence and tests remain outstanding.

Use [Decision-to-task matrix](DECISION_TASK_MATRIX.md) for exact direct IDs, remaining descendant IDs, current task dependencies, and potential immediate eligibility. Do not treat overlapping closures as unique unlock totals.

## OPEN-01 — Commission eligibility and allocation

- Owner/source: Executive + Finance; PRD `PAY-02/03`, [evidence brief](../evidence/OPEN-01.md). Required: collection trigger, attribution, eligible types/renewals, rate by type, splits, reversals/refunds, rounding stage/residual, legal/accounting review.
- Conflict assessment: PRD leaves the commission trigger/calculation open; collection-based net-of-tax is a provisional review direction, not a conflicting approved policy. Actual rates, rounding and eligibility must remain unresolved.
- Existing documented direction (not signed): collection-qualified net of tax; proportional installments; no invented actual rates/minimum/cap; EGP only, FX inactive/unresolved; manually closed monthly cycle, versioned/non-retroactive rates, no automatic payroll deduction; settlement/reversal and employee rights retained. Recommended-only items remain labeled so.
- Missing owner inputs/evidence: actual rates and eligible outcomes, split attribution examples, rounding fixture expected values, final authorized roles, legal/accountant review and signed decision/date.
- Future acceptance tests (not run): eligible/unallocated/advance/partial collection; mixed category/split and reallocation; refund after payout and closed statement; EGP precision/rounding only after signed fixture; FX rejected/inactive.
- Risks/rollback: duplicate or retroactive accrual/payroll harm. Use source-linked reversal/adjustment in an open period and preserve locked statement; never delete posted history or auto-deduct payroll.
- Direct task IDs: see matrix (`A4-PAY-001..004`); task descendants remain gated. **NOT READY FOR APPROVAL.**

## OPEN-02 — Approval thresholds, delegation and escalation

- Owner/source: Executive; PRD `SEC-03/04`, approval baseline; [evidence brief](../evidence/OPEN-02.md).
- Conflict assessment: the threshold amounts and escalation path are provisional review inputs, not approved exceptions to SoD or PRD approval requirements. Current equality and fail-closed constraints remain; exact operation scope/calendar/delegates are unresolved.
- Existing provisional input: manager-first; extra authority only strictly `>` EGP 5,000 expense / EGP 100,000 contract / 10% quote discount / EGP 10,000 stock adjustment. Equality remains `CURRENT`, never auto-approval; ordinary approval still applies. 24 business-hour SLA requires both escalation notice and reassignment to an authorized substitute; no unauthorized fallback. Delegation cannot break SoD/limits/nondelegable rules. Break-glass cannot bypass invariants; exact allowlist, calendar, amount basis, roles, material-change rules, reviewer remain `OPEN`.
- Missing: named role authority, operation catalog and valuation/discount denominator, business calendar/hours, approved delegate list/duration, nondelegable actions, deterministic material-change allowlist and exact break-glass actions/reviewer, signed policy/version.
- Future tests (not run): four boundary triplets with `CURRENT/CURRENT/ADDITIONAL`; unknown actor/entity/policy fails closed; self-approval/delegation/limit and independent break-glass review; policy version changes and reapproval; SLA calendar expiry + notice + authorized reassignment, no fallback.
- Risk/rollback: unauthorized approval or stuck request. Suspend activation and route to explicit review; preserve request/history. No autoapprove, permission bypass, or post hoc rewrite.
- Direct task IDs: matrix (`A0-SEC-005..008`, `A4-PAY-005..006`). **NOT READY FOR APPROVAL.**

## OPEN-03 — Chart, tax, fiscal periods and partner accounting

- Owner/source: Finance + accountant; PRD `FIN-01..08`, `PAY-04`; [evidence brief](../evidence/OPEN-03.md).
- Conflict assessment: configurable COA/tax and balanced posting are baseline requirements; no chart/tax/fiscal choice in this note supersedes them. Provisional EGP/manual-close direction does not resolve legal mappings or payment/commission policy.
- Documented only: configurable COA/fiscal calendar; EGP-only transactions; separate partner accounts; configurable tax engine; approved event posting; manual monthly close and assisted bank reconciliation. Tax-per-line, mappings, partner GL/subledger and other variants remain recommendations, not owner choices.
- Missing: signed COA/accounts/opening balances, actual tax classes/rates/exemptions, fiscal calendar, partner structure, mappings, revenue/COGS recognition, reconciliation examples, jurisdiction/accountant opinion and test fixtures.
- Tests not run: balanced journal and source-event mapping; tax calculation from owner fixtures; locked-period rejection and authorized reopen/reversal; partner settlement; TB/GL reconciliation. Never guess legal rates or accounts.
- Risk/rollback: misstatement or closed-period damage. Correct only with authorized source-linked reversal/adjustment in open period; preserve posted journals.
- Direct IDs: matrix (`A4-FIN-001..016`, `A4-PAY-003..008`). **NOT READY FOR APPROVAL.**

## OPEN-04 — Inventory units, valuation and negative stock

- Owner/source: Warehouse + Finance; PRD `INV-01..05`, `FIN-05`; [evidence brief](../evidence/OPEN-04.md).
- Conflict assessment: PRD requires stock traceability and valuation, but does not select FIFO or weighted-average here. `moving weighted average` is only a `RECOMMENDED-DEFAULT`; no conflict is resolved until owners select units/costing/negative-stock rules.
- Only `moving weighted average` is `RECOMMENDED-DEFAULT`; FIFO remains an alternative, not rejected. Proposed units/conversion versions, no routine negative stock, separate on-hand/reserved/available, reversal-based ledger, barcode/manual counts are not approved policy.
- Missing: SKU/unit list and conversion precision, opening valuation, costing/rounding, batch/lot/roll needs, negative-stock exceptions, count variance authorities, fixture expectations and owner sign-off.
- Tests not run: receipt/reserve/issue/return/damage/count; conversion-version preservation; concurrency and duplicate idempotency on exact authorized MariaDB target; no negative stock only if policy confirms. No guessed unit or valuation rule.
- Risk/rollback: ledger/valuation drift. Source-linked compensating movement and reconciliation; never delete or edit posted movements.
- Direct IDs: matrix (`A3-INV-001..010`, `A3-MFG-001..002`, `A4-FIN-009..010`). **NOT READY FOR APPROVAL.**

## OPEN-05 — Project revenue recognition and cost allocation

- Owner/source: Finance + Operations; PRD `PRJ-02` and §6.1; [evidence brief](../evidence/OPEN-05.md).
- Conflict assessment: the PRD explicitly requires actual/estimated margin distinction and recognition policy, not cash-as-revenue. No selected milestone formula exists; proposed scope must not silently treat cash or an unsigned formula as recognized revenue.
- Available only as requirement: cash is not recognized revenue; estimated and actual margin distinct; candidate direct costs include materials/labor/transport/subcontract/site. No recognition trigger, overhead, tax, WIP, close, or allocation formula is selected.
- Missing: accountant-signed recognition milestones and tax treatment; project-cost allocation examples; overhead/WIP/close rules; source/refund/amendment fixtures and review authority.
- Tests not run: contract/invoice/receipt and direct-cost scenarios; no actual margin/posting until fixtures and formula signed; reconcile changes/refunds without silent overwrite.
- Risk/rollback: false margin and financial reporting. Version formula; correct through source-linked open-period adjustment, not rewriting recognized/posted entries.
- Direct IDs: matrix (`A2-PRJ-003..004`, `A2-PRJ-011..012`). **NOT READY FOR APPROVAL.**

## OPEN-06 — Minimal production boundary

- Owner/source: Operations; PRD `MFG-01`; [evidence brief](../evidence/OPEN-06.md).
- Conflict assessment: PRD `MFG-01` requires a limited production traceability capability; output-only versus input/consumption/output changes the evidence boundary and is not yet accepted as satisfying it. No BOM/MRP expansion is in baseline; any scope reduction or split needs explicit Product/Operations change review.
- Alternatives remain output-only or minimal input/consumption/output trace if required for stock correctness; neither is selected. No BOM/MRP/routing/machine planning.
- Missing: explicitly scoped examples, units/transformations/waste/returns, cost/source rules, required traceability and owner acceptance; coordinate OPEN-04 unit/valuation decision.
- Tests not run: synthetic conversion and output/consumption reconciliation; no double movement or broad manufacturing; tie records to source IDs; verify chosen task scope before implementation.
- Risk/rollback: duplicate stock movement or unapproved scope growth. Reverse by linked compensating movement; never erase posted movement. No task-split or DAG change authorized here.
- Direct IDs: matrix (`A3-MFG-001..002`). **NOT READY FOR APPROVAL.**

## OPEN-07 — External communication channels

- Owner/source: Management + IT; Security/Legal/Finance as applicable; PRD `COM-03..09`; [evidence brief](../evidence/OPEN-07.md).
- Provisional owner direction in the review pack (not formal scope approval): employee internal conversations in Phase A; in-app notifications and Browser Push synthetic contracts; live corporate email/SMS/WhatsApp deferred; Telegram excluded from current delivery. Preserve these statuses as directions only.
- Conflict assessment: PRD `COM-03` email and `COM-04` SMS are operational `MUST`; fake-only/deferment conflicts with that delivery scope until explicit Change Control approval. WhatsApp is a channel decision/evaluation requirement, not an assumed low-cost live provider; Telegram is optional in PRD. The drafts do not downgrade MUST or amend DAG. `A1-AT-11` tests failure/retry semantics and does not itself require paid sends.
- Missing: corporate account/provider/ownership, provider terms and consent, costs, retention/data region, delivery/webhook behavior, WhatsApp compliant route, Telegram pilot decision, security/legal review and exact live-vs-synthetic scope. No provider fetch/account/egress authorized.
- Tests not run: fake-only failure/timeout/duplicate callbacks/revocation/masking/destination resolution; `accepted` must not mean delivered. Real delivery compatibility is separate and requires explicit external-call authorization.
- Risk/rollback: duplicate/undelivered or privacy exposure. Disable dispatch and reconcile `unknown` explicitly, no blind resend; keep customer records/provider IDs and audit history.
- Direct IDs: matrix (`A1-COM-005..014`, `A1-COM-017..018`). **NOT READY FOR APPROVAL.**

## OPEN-08 — Mobile/PWA/device support

- Owner/source: Engineering; PRD `NFR-05/06`, `AT-13`; [evidence brief](../evidence/OPEN-08.md).
- Conflict assessment: Web/PWA online-only is provisionally consistent only if PRD authorized-workflow parity and safe reconnect are met; it cannot be read as an approved waiver of mobile parity or device validation. Native/hybrid remains undecided.
- `Web + PWA`, RTL, online-only, safe reconnect is an `OWNER-SELECTED` direction in review, not verified device support. Native/hybrid alternatives remain open. No offline financial/stock/approval commit.
- Missing: supported device/browser matrix, camera/upload/signature support, accessibility/RTL criteria, session revoke behavior and conflict/retry fixtures/owner acceptance.
- Tests not run: representative authorized devices, orientation/RTL/upload/reconnect/idempotency/session revocation. No deployment or real records.
- Risk/rollback: unsupported devices or duplicate upload. Fall back to responsive web and explicit retry/review, preserving records and attachments.
- Direct IDs: matrix (`A5-NFR-009..010`). **NOT READY FOR APPROVAL.**

## OPEN-09 — AI provider, data processing, budget and thresholds

- Owner/source: Executive + Security; Legal as required; PRD `AI-02..09`; [evidence brief](../evidence/OPEN-09.md).
- Conflict assessment: PRD requires practical AI audit in Phase A; `OPENAI` evaluation-only and synthetic architecture do not satisfy that requirement or authorize external processing. SCOPE's deferred autonomous chat/voice and `AT-10` prohibition on AI post/delete remain intact.
- `OPENAI` is evaluation target only, not live authorization. AI remains advisory; tool execution/action assistant is future scope. No provider call or real data.
- Missing: provider/model/region, DPA and processing terms, payload classification/redaction, approved audit categories, budget/cost ceilings, labeled/sanitized cases and quality thresholds, human review, kill switch and legal/privacy approval.
- Tests not run: injection/invalid schema/stale input/outage and deterministic fail-closed; synthetic quality/latency/cost evaluation after criteria owner signs; zero-egress assertion. No actual external run in this sprint.
- Risk/rollback: PII exposure, false finding, cost or unauthorized mutation. Disable provider, retain advisory/audit trail; never post/delete/approve or reverse ledger via AI.
- Direct IDs: matrix (`A5-AI-003..004`, `A5-AI-009..012`, `A5-AI-017..018`). **NOT READY FOR APPROVAL.**

## OPEN-10 — Retention, consent and document access

- Owner/source: Legal + HR/Finance; PRD `DOC-01/02`, `NFR-08`; [evidence brief](../evidence/OPEN-10.md).
- Conflict assessment: PRD confidentiality, access and record-retention safeguards remain requirements; no proposed default duration/legal basis is asserted. `LOCAL` storage does not prove encrypted/private-object compliance; reconcile storage separately under ADR-004 without inventing retention law.
- Retention classification, legal hold, access grants, financial archive and deletion are requirements; any proposed default is not law or owner-approved duration. Do not invent legal basis or retention period.
- Missing: Egyptian-law/legal basis opinion by record category, retention schedule, consent/notice/access grants, employee document policy, legal-hold and deletion/backup semantics, role authority and sign-off.
- Tests not run: authorization/revocation/masking, retention lifecycle using synthetic records and owner-provided dates, legal hold/deletion, restore consistency. No actual deletion or production data.
- Risk/rollback: unlawful deletion or disclosure. Preserve originals/legal holds and use reversible access suspension until Legal decision; do not erase backups or posted records.
- Direct IDs: matrix (`A0-DOC-001..004`, `A1-COM-013..014`, `A5-AI-011..012`, `A5-NFR-015..016`). **NOT READY FOR APPROVAL.**

## OPEN-11 — Hosting, SLO/RPO/RTO and migration

- Owner/source: IT + Engineering; PRD `NFR-03/04`, A0 release; [evidence brief](../evidence/OPEN-11.md).
- Conflict assessment: PRD requires backup/recovery/capacity evidence but supplies no numeric target in this source; no hosting design or unverified VPS report satisfies it. Numeric SLO/RPO/RTO and restore proof remain owner inputs, not invented estimates.
- VPS/cPanel/WHM/root SSH is an unverified report, not proof. No provider, user count, load, resource, backup, restore or numeric SLO/RPO/RTO assumed.
- Missing: signed user/load profile, hosting/OS/database version, availability/capacity/monitoring target, backup schedule/key controls, numeric RPO/RTO, import/migration and recovery plan, restore drill, security/runbook review.
- Tests not run: disposable load/restore/queue recovery after IT authorization and fixed target; no VPS/production access in this sprint.
- Risk/rollback: data loss/outage or exposed access. Keep production untouched; restore only to isolated environment and preserve existing backups.
- Direct IDs: matrix (`A5-NFR-005..008`). **NOT READY FOR APPROVAL.**

## OPEN-12 — QA checklists, warranty and sign-off

- Owner/source: Operations + Quality; PRD `QA-01/02`, `PRJ-06`; [evidence brief](../evidence/OPEN-12.md).
- Conflict assessment: PRD requires acceptance evidence and authorized sign-off; draft partial-acceptance/default-warranty ideas cannot reduce that baseline. Checklist contents, warranty terms and sign-off authority remain open, not rejected or selected.
- QA/work-type checklists, acceptance evidence, warranty-ready records are requirements; proposed partial acceptance/customer portal/default warranty are not selected. Warranty duration/authority is not guessed.
- Missing: approved checklists by work type, measurements/evidence, partial acceptance/rework/exception criteria, warranty terms, sign-off roles/delegation and legal/commercial review.
- Tests not run: synthetic accepted/rejected/partial, missing evidence, corrective action, closure gate and warranty handoff with role review. No real customer signatures.
- Risk/rollback: premature closure or fabricated acceptance. Reopen only through authorized auditable workflow, preserve prior evidence and source; never delete/rewrite sign-off.
- Direct IDs: matrix (`A2-PRJ-011..012`, `A3-QA-001..004`). **NOT READY FOR APPROVAL.**

## لا توجد موافقات ضمن المصفوفة

تظل جميع الحالات الاثنتي عشرة `OPEN`. لا يثبت استكمال brief أو كتابة test plan صلاحية أو تنفيذ اختبار، ولا يغيّر `OPEN_DECISIONS.md` أو `tasks.json`. تأكد من حقول المالك/الخيار/التاريخ/evidence/tests/review حسب LOOP_OPERATIONS قبل أي اعتماد لاحق.

# GOV-AMEND-001 — Executive Decision Authority Appointment

**Version:** 1.0

**Status:** PREPARED — NOT ADOPTED

## Purpose

Prepare the formal appointment of Ibrahim as My Arasya's Executive Decision Authority for decisions whose existing governance owner is `Executive`.

This amendment does not accept ADR-002 or ADR-003, approve OPEN-02, change task states, or grant implementation, production, deployment, provider, or data access permission.

## Proposed authority boundary

- Ibrahim may act in the `Executive Decision Authority` role for Executive-owned governance decisions, including OPEN-02, once this amendment is formally adopted.
- Product Owner and Engineering Decision Authority records remain separate role-labeled decisions. One person holding multiple roles does not create independent approval or review.
- Finance, IT, Security, Legal, Operations, domain-owner, and independent-review requirements remain with their existing authorities and cannot be replaced by this appointment.
- Ibrahim cannot be the independent reviewer of a decision he made, authorized, or materially influenced. A separately authorized reviewer must be named for any required independent review.
- Adoption does not make any proposed threshold, delegation, escalation, calendar, exception, or break-glass rule effective. Those remain subject to the owning decision record and its evidence requirements.

## Existing-rule compatibility

The existing change-control rules permit a scoped governance amendment when the owner, reason, affected decisions, authority boundaries, evidence, and review requirements are explicit. Adoption must follow the existing `Draft → Impact reviewed → Approved/Rejected → Scheduled → Verified` lifecycle. The coordinator remains the only authority that may update decision/task state after valid evidence is supplied.

## Formal adoption requirements

Adoption requires an explicit record in [GOV-AMEND-001 adoption evidence](../evidence/GOV-AMEND-001.md) containing:

1. `Decision: Adopted`.
2. Ibrahim's authority name and the exact role `Executive Decision Authority`.
3. Adoption date and effective scope.
4. The decisions covered and the boundaries preserved.
5. Confirmation that Ibrahim is not an independent reviewer of his own decisions.
6. Impact review and any required Finance, IT, Security, Legal, Operations, domain-owner, or independent-review decisions.
7. Evidence references and a reviewer distinct from Ibrahim where independent review is required.

Until those fields are completed by an authorized human, the amendment remains PREPARED and has no governance effect.

## OPEN-02 consequence

After adoption, Ibrahim may supply the Executive decision for OPEN-02. OPEN-02 still requires a versioned policy decision covering its unresolved operation catalog, amount/valuation basis, risk classes, calendar/SLA, approver/delegate scope, material-change rules, and break-glass policy. This proposal does not supply or approve those values.

## References

- [Change control](../CHANGE_CONTROL.md)
- [OPEN-02 policy evidence](../evidence/OPEN-02.md)
- [GOV-AMEND-001 adoption record](../evidence/GOV-AMEND-001.md)


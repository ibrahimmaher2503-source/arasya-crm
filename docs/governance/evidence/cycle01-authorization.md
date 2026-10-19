# CR-CYCLE01-DEV-001 — Organization, users and access

Requester and scope owner: Ibrahim, explicit owner directive “CLOSE CYCLE 01:
ORGANIZATION, USERS & RBAC”, 2026-10-17. Status: Approved for bounded synthetic
DEVELOPMENT; scheduled as A1-CORE-011. No production or business-policy approval.

References: CORE-01, SEC-01, SEC-05, SEC-06, NFR-06;
[change control](../CHANGE_CONTROL.md), [task ledger](../../backlog/tasks.json).
Current scope consists of completed reference/scoped-permission diagnostics and
synthetic session contracts. Proposed scope is one operable administration cycle:
company configuration, branches/departments, users, explicit role/permission
assignment and revocation, real password/session login and Cycle01-local audit.
The owner explicitly prioritizes this cycle ahead of Tasks, Finance, Inventory
and AI. Alternative: wait for A0-VINT-101's broader approvals/AI scope; the owner
directed independently operable access administration instead. A0-VINT-101 and
all existing task states, acceptance contracts and handoffs remain unchanged.

Implementation is divided into exclusive organization, identity, UI, feature-test,
browser-test and independent-review ownership under one coordinated acceptance
task. Existing runtime files absent from HEAD prevent a clean linked worktree
from representing this checkout; workers use disjoint files here. Only the
coordinator runs schema/database/browser commands, serially. All dispatches
explicitly request gpt-6-luna with xhigh reasoning; dispatch acceptance is the
owner-approved operational evidence, not independently attested model identity.

Dependencies: completed A0-CORE-002, A0-SEC-002, A0-VINT-001 through 004 and
A0-DS-002. No unresolved decision dependency is removed or waived. OPEN-02
approval chains, thresholds, delegation and maker/checker policy remain blocked.
Synthetic bootstrap administrator is a private test fixture only; there is no
HTTP bootstrap, default password, real-business role matrix or provider activation.
The company ID maps to the existing entity scope. One branch and department per
user is the bounded initial assignment; records retain owner/assignee checks.

Privacy/data/finance/operations impact: dedicated Windows loopback MariaDB test
databases only, synthetic credentials/data, redacted audit, no external services,
real business records or unrelated database/service changes. Generic SEC-009
audit, approval audit and retention/tamper-proof operational policy are not
completed by this local audit slice; later integration must consolidate its
append service and retained evidence without deleting history.

Acceptance: actual administrator login, branch/department/user creation, a
restricted role assigned through UI, restricted real login, allowed operation,
403 denials, cross-branch/privilege-escalation negatives, immediate same-session
role revocation and administrator audit review. Verify persisted MariaDB rows,
password hashing, secure session rotation/logout/revocation, duplicate and stale
write rejection, Arabic RTL desktop/mobile Playwright, PHPUnit, PHPStan, scoped
Pint, the existing frontend asset checks, independent review and scoped cleanup.
Do not label the broader AI or production cycle verified.

Technical closure: A1-CORE-011 completed through coordinator Review/Complete;
see [actual acceptance](cycle01-acceptance.md). This records the executed
DEVELOPMENT verification and does not extend the original owner authorization.

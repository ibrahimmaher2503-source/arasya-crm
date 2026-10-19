# CR-TASK-DEV-001 — Tasks DEVELOPMENT vertical slice

Requester and scope owner: Ibrahim (owner directive, 2026-10-17).
Status: Approved for DEVELOPMENT; scheduled as A1-TASK-003. Not production approval.
Authority: attached “OWNER APPROVES TASKS VERTICAL SLICE” and subsequent
“OWNER DECISION: LUNA X HIGH DISPATCH EVIDENCE”. Successful dispatch with
`gpt-6-luna` / `xhigh` is sufficient operational evidence; independent runtime
model identity is not claimed.

References: [SCOPE](../../product/SCOPE.md), TASK-01, SEC-02, NFR-06;
[change control](../CHANGE_CONTROL.md), [task ledger](../../backlog/tasks.json).
Current implementation is TaskRecord/ManageTasks and diagnostic access only.
Approved delta: real create → start → private evidence → complete persistence,
HTTP and RTL UI, scoped authorization, audit, idempotency and deterministic
task-local transition preview/confirmation/reauthorization through the same
application service. Completion in this slice requires evidence. Assignment is
self-assignment until a trusted directory/delegation contract exists; arbitrary
caller-supplied employee identities must not grant access.

Alternative: wait for the entire shared AI/Finance/Inventory dependency chain.
Owner explicitly approved this independent bounded delivery path instead.
Effort: S, one bounded session/aggregate; existing A1-TASK-001/002 work is reused, not duplicated.
Dependencies: A1-TASK-002 and A0-DS-002, both completed. Their existing edges and
all historical task states remain intact. No OPEN-02/03/04, ADR, A5-AI-001,
A5-VINT-001 or A5-VINT-002 gate is waived or completed by this CR.

Impact: synthetic isolated Windows DEVELOPMENT databases and private test files
only; no business/Finance/stock data, live provider, production, external upload,
retention policy activation or new operational service. Existing identity session
contract is reused. AI gateway/central registry integration remains restricted
until its legitimate dependencies are deliverable; local action evidence does
not establish full AI-native acceptance.

Acceptance: independent PHPUnit lifecycle, MariaDB transaction/idempotency/audit,
RBAC and scope denials, stale/tampered/expired AI preview, explicit confirmation,
private upload/download, real Playwright desktop/mobile RTL persistence/reload,
synthetic cleanup, PHPStan, scoped Pint and independent technical review.
Coordinator owns registration, integration, all test execution and lifecycle.
Workers own disjoint files; shared DB/schema and compiled views are never reset
concurrently. Existing dirty baseline contains runtime files absent from HEAD;
linked worktrees would omit them, so exclusive-file workers share the current
checkout without lifecycle, package, router or runtime ownership.

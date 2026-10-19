# SEC-01 authorization slice

This is a pure, read-only evaluator over an explicit actor/grant snapshot and a scoped record reference. `AuthorizeScopedRecord` is the application seam; `ScopedPermissionGuard` is the domain rule. It performs no authentication, data lookup, persistence, business mutation, audit append, or outbox write.

Invariants: permission matching is exact and deny-by-default; an explicit employee `true`/`false` override takes precedence over inherited defaults; missing/malformed identity, grant, or scope data denies. Permission allowance never bypasses matching entity and department or the record owner/assignee check. An unassigned record and an actor matching neither owner nor assignee are denied. Role names—including administrator or executive labels—grant no implicit bypass.

Policy references: [PRD SEC-01](../../../../../docs/product/PRD.md#L113-L115) and [PERMISSION_MATRIX.md](../../../../../docs/security/PERMISSION_MATRIX.md#L1-L2) / [record-scope test baseline](../../../../../docs/security/PERMISSION_MATRIX.md#L16-L20). The matrix is Proposed pending its referenced OPEN decisions; this slice chooses no role defaults, policy cell, delegation, or OPEN option.

The guard is not a database/query/API/job adapter: callers must constrain the record set before exposing results (including counts, search, exports, and background work). This SEC-01 read path has no mutable business operation, so audit/outbox mutation obligations do not apply here; any later state-changing authorization or assignment flow needs its own transaction and audit/outbox design.

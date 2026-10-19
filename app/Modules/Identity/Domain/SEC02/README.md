# SEC-02 contact masking slice

`ContactMaskingProjector` emits one immutable, fixed-shape `ContactReadModel` for `ui`, `api`, `search`, `export`, `log`, `notification_preview`, and `file_download`. The result always contains `contact: null` and `contact_visibility: hidden`; neither channel nor supplied contact input is retained, inspected, or passed through. Unknown/malformed channels and arbitrary nested input produce that same hidden result. JSON serialization and debug output expose only that DTO shape.

This deliberately denies every contact reveal. It does not establish authentication, authorization, an audited grant, or a caller's current/revoked grant state; roles and channel names grant no bypass. There is no outbound-destination lookup, raw storage, database/network access, audit/outbox mutation, or migration. A future reveal path must be built on a genuinely authenticated, explicitly granted and audited authorization capability before raw contact data can be projected.

The guarantee is limited to this returned readmodel and its serialization/debug representation. It does not mask raw text, customer quotes, arbitrary logs, other read paths, API/query adapters, exports/download implementations, or outbound notification handling; integrating those callers remains necessary before claiming system-wide PRD coverage. This slice addresses the safe default only, not the entire PRD requirement.

Policy reference: [PRD SEC-02](../../../../../docs/product/PRD.md#L117-L119).

Focused check: `php artisan test --filter=SEC02DomainTest`.

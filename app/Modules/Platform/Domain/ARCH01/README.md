# ARCH01 module-boundary invariant

This is an internal trusted-code architecture decision, not HTTP/user authorization. The guard permits a same-module write only when entity scope matches and the target resource is present in its immutable local resource-owner policy fixture. That fixture currently maps `organization_references` to `Organization`; these are guard policy entries, not claims about persisted tables or schema. Unknown resources, mismatched module ownership/entity scope, malformed data, and every cross-module write intent fail closed.

No authorized cross-module write contract/event exists in this baseline. A future cross-module update must invoke a real owner Application service contract or reliable event; contract invocation is a distinct boundary route, never permission to write directly to the owner's table. Until such an implemented owner contract exists, a contract identifier in a write intent is denied.

Policy: [PRD ARCH-01](../../../../../docs/product/PRD.md#arch-01--module-boundaries) requires module-owned entities/invariants, explicit service contracts or reliable events for cross-module updates, and prohibits arbitrary writes to another module's tables.

This decision seam is read-only: persistence, mutation, audit, and outbox behavior are N/A. It does not globally intercept Laravel/database writes. AI/tool exposure is N/A because this is restricted internal architecture policy, not a business capability or user-facing assistant action.

`ProviderSdkDependencyGuard` token-checks PHP names while ignoring comments and string literals. It denies known AI SDK namespaces (OpenAI, Anthropic) and communication SDK namespaces (Twilio, Vonage, MessageBird, Infobip, SendGrid, Mailgun, Postmark), plus the entire Google, Aws, and Laravel namespace roots as a conservative Domain-only dependency policy. That intentionally broad root rule catches SDK subnamespaces despite partial aliases or grouped imports; malformed PHP fails closed. This is a focused dependency policy, not a general PHP static analyzer. Unit fixtures cover token forms and false positives, and tests recursively inspect all Platform Domain PHP sources.

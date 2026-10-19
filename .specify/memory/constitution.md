# My Arasya Engineering Constitution

## Core Principles

### I. Existing authority and bounded ownership

AGENTS.md, the execution/loop guides, the authoritative backlog and owner-approved
decision gates retain precedence. This constitution applies engineering guidance to
Spec Kit; it grants no business, ADR, branch, publication or production approval.
Only the coordinator changes lifecycle state through task-loop. Feature checkboxes
are supplemental. Missing registration support must be resolved with explicit owner
authorization, never an invented ready task or an implicit governance bypass.

### II. Preserve the implemented architecture

Use the existing modular monolith, Domain Records, Query Builder application services,
guards and shared Blade components. Discover actual callers, table ownership and
transaction boundaries before changing them. Domain rules must remain independently
testable. Introduce models, repositories, DTOs, packages or abstractions only for a
demonstrated requirement within approved scope. Diagnostic contracts and in-memory
outbox tests are not proof of durable production delivery.

### III. Risk-based RED → GREEN → REFACTOR

Suitable new business logic and reproducible bugs require a failing behavioral test
before implementation, the smallest passing change, then justified refactoring while
tests remain green. Finance, sales, inventory, authorization, calculations, transitions
and cross-module behavior require relevant negative/security/invariant tests.
Record RED and GREEN commands/results; a setup error is not a valid behavioral RED.
Documentation and non-behavioral changes may use focused checks with an explicit
test-design rationale. Never add mirror tests or coverage-only assertions.

### IV. Independent work and review

Before test authoring, assess independent files, fixtures and runtime state. Delegate
safe independent testing/review with explicit file ownership and isolated worktrees
where concurrent edits need isolation. Use gpt-6-luna / xhigh; report unavailability
without silent fallback. No recursive delegation, shared-file writers or concurrent
schema-mutating suites. The coordinator integrates and owns final checks/lifecycle.
Independent review must examine the resulting changes and unresolved failures.

### V. Evidence and security

Keep secrets, real customer data and production databases out of test fixtures and
reports. Preserve authorization, scope, validation, transactions and audit behavior.
Use the existing services for UI/API/tool parity; AI previews grant no execution.
Record PASS, FAIL, SKIPPED or NOT RUN with exact command, runtime, scope and evidence.
Local source tests, browser checks, hosted CI and production proof are distinct.

## Compatibility and Scope

The current PHP requirement is ^8.4, with Laravel ^13.17 in Composer; lockfiles govern
resolved versions. Presentation is Blade/custom CSS/plain JavaScript. Reuse the
design-system catalog and tokens, and verify RTL/LTR, mobile, themes and keyboard
behavior for UI changes. MariaDB qualification needs an isolated authorized database;
SQLite tests cannot establish row-locking or production compatibility.

## Development Workflow

Discovery → Specification → Clarification → Technical Plan → Test Design →
Implementation → Verification → Review → Integration.

The canonical phase contract, lightweight path and feature-to-backlog mapping are in
[Spec Kit integration](../../docs/agents/SPECKIT_INTEGRATION.md). Test Design is a
section of the technical plan, not a mandatory extra document. Apply the existing
[test strategy](../../docs/engineering/TEST_STRATEGY.md) by risk. Integration requires
the relevant passing gates; baseline failures must remain visible and cannot be
silently waived. DEVELOPMENT evidence never closes INTEGRATION/PRODUCTION gates.

## Governance

This version records the owner's 2026-10-18 hardening mission and approved bounded
registration exception, documented in
[A0-ARCH-903 evidence](../../docs/governance/evidence/A0-ARCH-903-hardening.md).
Future amendments follow existing change control and independent review. Use semantic
versions: major for incompatible principles, minor for additions, patch for clarifications.
Review template compatibility when amending or upgrading Spec Kit; do not rewrite
upstream skills or expand decision authority through this document.

**Version**: 1.0.0 | **Ratified**: 2026-10-18 | **Last Amended**: 2026-10-18

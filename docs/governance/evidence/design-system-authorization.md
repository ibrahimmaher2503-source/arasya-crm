# Design system DEVELOPMENT authorization — 2026-10-17

Source: the owner's attached request, “MY ARASYA — BUILD & ENFORCE THE PERMANENT ERP DESIGN SYSTEM”, marked OWNER DIRECTIVE — APPROVED. The request explicitly authorizes implementation, permanent agent reuse policy, synthetic showcase/reference screens, tests and independent Luna X High workers. This record preserves that instruction; it approves no OPEN decision, ADR, production deployment or AI activation.

Coordinator registration follows [CHANGE_CONTROL](../CHANGE_CONTROL.md): a CreateNew exclusive coordinator lock, no active leases/pending transaction, original-file hash comparison, validated JSON/reverse edges, and atomic replacement. Existing task statuses and audit history were preserved. The first replacement attempt failed because PowerShell supplied an empty backup path; the source was unchanged. The second used a dedicated local backup and succeeded. No new Register action was invented.

Bounded task chain:

- A0-DS-001: semantic tokens, themes, shell and reusable UI components; depends on completed A0-CORE-900.
- A0-DS-002: ERP/AI presentation components and protected synthetic showcase/reference screens; depends on DS-001.
- A0-DS-003: permanent reuse instructions, local checks, browser acceptance and practical catalog; depends on DS-002.

All lifecycle transitions use task-loop. Existing blocked ERP/AI tasks remain unchanged. The current checkout contains necessary untracked application/configuration files and extensive unrelated changes; workers therefore use exclusive file ownership rather than incomplete linked-worktree snapshots. The coordinator integrates shared route/config/catalog files and owns every lifecycle mutation. No concurrent database reset is needed for presentation tests.

Before any completion, the coordinator clarified the newly registered split under the same change-control lock/hash guard: DS-001 owns component/shell checks, DS-002 owns protected-route/presentation checks, and DS-003 owns integrated browser acceptance for the entire library. The initial generic verification lists assigned that final check to all three tasks and would have created a verification-order cycle. This clarification changes no historical completion evidence or pre-existing task criteria. DS-003 also owns the existing layout README asset-reference update and checker self-test.

Visual contract: forest green navigation and actions, neutral off-white surfaces, compact Arabic enterprise typography, semantic shared CSS tokens, logical layout properties, accessible native controls, light/dark themes and RTL/LTR. Existing Blade and direct local assets are retained; no second UI framework or asset bundler is introduced.

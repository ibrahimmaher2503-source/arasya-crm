# CR-CYCLE03-DEV-001 — CRM operational cycle

Requester: Ibrahim, explicit owner directive “EXECUTE CYCLE 03 CRM, THEN RUN
CONSOLIDATED ERP ACCEPTANCE”, 2026-10-17. Approved scope: bounded local synthetic
DEVELOPMENT implementation of CRM-01..06; no production, external provider,
OPEN/ADR approval or waiver of independent review. References: [PRD](../../product/PRD.md),
[scope](../../product/SCOPE.md), [change control](../CHANGE_CONTROL.md),
[tasks](../../backlog/tasks.json), [Cycle02 checkpoint](cycle02-ui.md).

Existing CRM001..012 are completed domain/diagnostic slices. Preserve history
and reuse their invariants. The owner now requests persisted lead library,
assigned-only access, explicit contact masking, versioned taxonomy, activity,
acknowledged transfer and duplicate-safe customer conversion. Downstream Sales
records must not be fabricated. Alternative of waiting for Employee UI was
explicitly rejected by the owner where independent work is safe.

Schedule CRM013 for durable lead library/assignment/masking and schema foundation;
CRM014 for lead activity, handover and conversion after core review; then a
separate controller/shared-component/route/browser integration task. This split
follows the independent governance review's bounded-aggregate recommendation.
Consolidated acceptance follows actual
integration, with one exclusive disposable MariaDB window. Dependencies retain
CRM012, CORE012, TASK003 and DS002. No existing status or decision is rewritten.
Registration uses the existing CreateNew coordinator lock, pending-transaction
guard, hash-checked replacement and validated reverse DAG edges.

Privacy/data/operations: synthetic records only. Persisted Identity session and
grant catalog remain authoritative. Managers are branch-scoped; ordinary sales
queries require current assignment, including customers. Lists never expose
contact values. Explicit detail reveal requires contact.reveal plus reason and
audit. Sensitive contact fields encrypted, duplicate keys hashed, audit has no
raw contacts. Free text can contain user-authored contact information; no claim
of universal semantic redaction. No outbound communications or provider calls.
Private attachment activation remains subject to Documents/ADR004/OPEN10; missing
contracts remain an explicit acceptance blocker, not a fake success.

The same coordinator explicitly released its own EMP006 lease at the preserved
partial checkpoint to hand off shared shell/coverage; no other owner's lease was
released. CRM013 was released, amended under lock and claimed again, with accurate
CRM + Identity module ownership and tool-coverage ownership. All CRM assistant capabilities remain
Restricted/disabled, no registry/handler/provider is enabled. UI integration may register
actual routes only after rechecking leases (DASH003 explicitly released its
router lease). Never create an alternate hidden registry. Employee-owned files
and other dirty work remain preserved.

Skills applied: repository Ponytail/Laravel best practices, installed frontend-design,
dispatching-parallel-agents, using-git-worktrees, test-driven-development and
requesting-code-review. Official Superpowers plugin/verification skill is not
currently discoverable; no duplicate installation. Worktrees inspected: current
untracked runtime (artisan/bootstrap/config) is absent from HEAD, so linked
historical worktrees omit dependencies. Disjoint files here are used instead;
only coordinator executes runtime/schema checks. All workers explicitly request
gpt-6-luna / xhigh, no recursive delegation or silent fallback.

Acceptance remains the owner's fifteen CRM steps plus consolidated cycle matrix.
Full PHPUnit/Playwright runs are reserved for final coordinated acceptance;
focused checks during development. Unimplemented Cycle02 documents/offboarding
must remain BLOCKED. No cycle becomes VERIFIED from diagnostic tests alone.

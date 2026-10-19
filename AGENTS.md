# My Arasya agent skill routing

The existing coordinator and lifecycle remain authoritative: follow
`docs/agents/AGENT_EXECUTION_GUIDE.md` and `docs/agents/LOOP_OPERATIONS.md`.
Skills do not authorize task-state, ADR, approval, branch, or scope changes.

The [Spec Kit integration contract](docs/agents/SPECKIT_INTEGRATION.md) defines
Discovery → Specification → Clarification → Technical Plan → Test Design →
Implementation → Verification → Review → Integration, including a lightweight path.
Its engineering constitution (`.specify/memory/constitution.md`) is subordinate to
this file and existing governance. Suitable new business logic and reproducible bugs
require RED → GREEN → REFACTOR; upstream optional-test defaults do not override this.

## Permanent skills-first execution policy

For every My Arasya task, discover and read the installed `ponytail` skill
(`.agents/skills/ponytail/SKILL.md`) before editing. For Laravel/PHP work, read
the installed Laravel-specific skill, including `laravel-best-practices` when
available. For frontend, Blade, Livewire, RTL, browser, or styling work,
discover and read an installed frontend-specific skill; if none exists, report
that limitation and use the repository workflow. Never invent a skill path.

Load Superpowers skills on demand, especially systematic-debugging,
dispatching-parallel-agents, using-git-worktrees, test-driven-development,
verification-before-completion, and requesting-code-review. Do not preload
unrelated skills or recursively delegate.

Before writing test code, explicitly assess whether independent test files or
review work can be delegated. Dispatch workers when scopes, files, fixtures,
and runtime state are genuinely independent; use isolated worktrees and exact
file ownership for concurrent edits. If delegation is unsafe or unavailable,
record the concrete conflict. The coordinator owns integration, lifecycle
transitions, final verification, and all task/ADR/approval state changes.

Every implementation handoff records skills actually used, exact paths,
workers and file ownership, tests/assertions executed, review findings,
integration verification, commits, newly ready tasks, and any reason work was
not delegated. Preserve Founder-Led DEVELOPMENT/INTEGRATION/PRODUCTION gates,
unrelated changes, and the existing coordinator as the source of truth.

Reuse the installed official Superpowers plugin; do not install duplicate skills.
Load only the relevant skill's instructions when its task arises:

- `superpowers:systematic-debugging`: investigate a bug, failing test, or unexpected behavior before fixing it.
- `superpowers:dispatching-parallel-agents`: only for explicitly authorized independent work with non-overlapping scopes permitted by the coordinator. No recursive delegation.
- `superpowers:test-driven-development`: relevant feature or bug-fix implementation; respect explicit user limits on tests and execution.
- `superpowers:verification-before-completion`: verify evidence before claiming completion, a fix, or passing checks.
- `superpowers:using-git-worktrees`: when authorized work needs isolation; reuse suitable existing worktrees and follow project branch restrictions.

Do not preload all five skills into sub-agents. Give each authorized worker only
its task, boundaries, and relevant skill references. Installation or skill use
does not trigger brainstorming, elaborate plans, or extra review rounds.
Delegation is required when the policy above identifies genuinely independent
work and otherwise must record its concrete safety conflict. Follow the user's
scope and the existing coordinator's required gates.

## Permanent Design System Reuse Policy

Before any frontend work, read Ponytail, the installed frontend skill and the
[component catalog](docs/design-system/README.md). Reuse `layouts.shell`,
`resources/views/components/{ui,erp,ai}` and the semantic tokens in
`resources/css/design-system.css`. Extend the existing component API; do not
duplicate a component, hardcode a competing palette/spacing/type system, or add
another UI framework without owner approval. Keep business logic and server
authorization outside presentation components; AI previews grant no execution.

Verify Arabic RTL and English LTR, mobile layouts, light/dark themes and keyboard
accessibility. Update behavior tests and the catalog when extending components;
run `npm.cmd run check:design-system` and relevant PHPUnit/Playwright checks.
Delegate independent frontend tests/review to **Luna X High** using explicit
`gpt-6-luna` / `xhigh` dispatch settings; report unavailability without fallback.
Coordinators must require workers to read this root file and pass exact file
ownership. These requirements apply to new sessions through the execution guide's
existing root-AGENTS-first instruction; they do not bypass lifecycle/decision gates.

Administration visual contract: follow the owner-approved October 17 references
documented in the catalog. Reuse `ui.page-header`, `ui.summary-strip`,
`ui.list-pagination`, `ui.option-browser` and `erp.permission-groups` where they
fit; use the existing dialog/drawer APIs for editing. Preserve one shell/header
and server-scoped counts, search, stable sorting and bounded result/option pages.
Never invent screenshot metrics or unsupported business actions. Preserve selected
assignments across option pages; incomplete authorization scope must remain read-only.

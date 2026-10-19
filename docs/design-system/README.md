# My Arasya design system

## Approved administration references and shared patterns

The owner approved these October 17 references: [access overview](references/access.png),
[users](references/users.png),
and [roles](references/roles.png). Reuse their forest-green navigation, aligned
page header, compact surfaces, status colors and table density. Actual records,
authorized actions and supported fields take precedence over screenshot sample
values; invitation, deletion and unsupported company fields are not fabricated.

The historical organization reference (`references/organization.png`) was removed
by the owner during consolidation on October 18; it is no longer an available visual baseline.

All access screens use `layouts.shell`, `erp.application-sidebar` and
`ui.page-header`; account detail pages use the same shell. `admin.css` extends the
existing semantic tokens and logical layout properties. Its desktop creation
panel becomes a stacked form on mobile. Tables scroll inside the existing table
region, never by breaking the page width. Long names remain bounded in cells.

| Shared tag | Contract |
| --- | --- |
| `ui.icon` | `name=building/users/shield/audit/search/plus/edit/arrow/mail/clock/help/bell/sun/grid`; decorative local SVG. Actions need their own accessible label. |
| `ui.page-header` | `title`, `subtitle`, `icon`, breadcrumb `items` via `breadcrumbs`, optional `actions` slot. `heading=h1` by default; `h2` for an embedded showcase. |
| `ui.summary-strip` | `items=[{label,value,icon,tone,description}]`, `variant=strip/cards`; default is one strip, `cards` is the overview's separate metric cards. Composes actual `ui.stat-card` elements. Caller supplies already authorized counts. |
| `ui.list-pagination` | `paginator` must be a Laravel length-aware paginator; bounded window, edge/previous/next links and localized range. Arrays render no pagination. |
| `ui.list-controls` | `locale`, `sorts` map; native named `per_page`, `sort`, `direction` controls inside the caller's GET form. Use once per page. |
| `ui.option-browser` | `sources=[{label,query,paginator}]`, `locale`; bounded server search and option-page links before filling a form. No full-directory browser array. |
| `erp.permission-groups` | `catalog`, `selected`, `labels`, unique `id`, `locale`; initially collapsed native disclosures, search opens matching groups, group-local selection/clear, live selected count. The decorative selection indicator reflects checked inputs; `data-permission-total` in the enclosing form mirrors their count, including native reset. Supply only the authorized catalog. |

`data-open-dialog` retains the existing native dialog/focus API. A shared edit
opener may add `data-edit-record`, HTML-escaped `data-record` JSON and a scoped
`data-form-action`. The dialog form uses `data-record-form`; controls retain
their real names, CSRF and optimistic version. A `data-preserved-role-values`
container retains authorized selected roles absent from an option page. Current
branch selection is retained even when absent from the displayed choices.
Incomplete role/override scope is read-only and rejected server-side under the
same company lock as the mutation. A UI preview grants no authority.

HTML directory data comes from `BrowseAccessDirectory`: authorization precedes
counts, filtering and pagination; stable ID tie ordering and page sizes 10/25/50
bound rows. `q`, status, branch/department/role and audit date/actor/action/record
filters persist in links. Organization tables have independent page parameters.
Selector queries use `branch_q`, `department_q`, `role_q` and separate option
page parameters. Existing JSON list shapes remain compatible; hidden assignment
metadata is omitted without its read permission. JSON lists retain their legacy
unbounded behavior, so large browser lists must use the bounded HTML contract.

The protected showcase catalog now renders these exact components, including a
10,000-record synthetic directory with only ten materialized rows per page. It
reads no business data and enables no save operation. Existing light/dark and
direction controls apply to the new components. See the
[administration implementation evidence](../governance/evidence/admin-redesign.md)
for executed checks, ownership and residual limits.

Use the real protected showcase at `/dev/design-system` in `local` or `testing`,
with a session carrying the existing application's runtime identity. It returns 404 in every other environment
and 401 without identity. It reads no business data. All five ERP screen examples
are synthetic; forms, attachments and AI previews execute no business actions.

Source: [root agent policy](../../AGENTS.md), [shell](../../resources/views/layouts/shell.blade.php),
[tokens](../../resources/css/design-system.css), [UI components](../../resources/views/components/ui),
[ERP components](../../resources/views/components/erp), [AI components](../../resources/views/components/ai),
and [shared interactions](../../resources/js/design-system.js).

## Foundation and composition

### Authentication presentation

The login page opts into `layouts.shell` with `authShell=true`, keeping its shared
tokens, controls, skip link and interaction script while omitting workspace
navigation. `ui.auth-layout` accepts `locale`, a `visual` slot, the default form
slot and an optional `footer` slot. `ui.brand-lockup` renders the reference-led
local wordmark treatment; it does not replace authenticated navigation branding.
`auth.css` owns this split layout and collapses it on mobile; the local generated
background contains no form or text. `ui.icon` now includes `lock` and `eye`.
The shared `data-password-toggle` API targets an input ID and accepts localized
`data-show-label`/`data-hide-label`; it updates type, label and `aria-pressed`.
Authentication, session lifetime and authorization remain server-owned. No SSO,
remember-device or password-reset capability is implied by this presentation.
See [login design evidence](../governance/evidence/login-design.md).

### CRM pages

The lead overview composes the shared entity header, detail panels, stat cards
and native dialogs in a reference-sized desktop board. Company/contact
information sits beside a six-entry activity preview. On October19 the owner
removed the recent-leads table and stage/source charts from the lead detail page;
those aggregate reads are no longer performed there. Full activity outcomes, notes, follow-up
dates and pagination remain in the full-activity dialog; history and existing
workflow forms use the same modal/focus API. Conversion has its own confirmation
dialog. Contacts remain masked until the existing reasoned, audited reveal.
The layout adapts to RTL/LTR, light/dark and mobile without adding a component API.
Counts reuse existing authorized reads; priority is not a fabricated
lead score. See the
October 19 slice in [CRM reference evidence](../governance/evidence/crm-reference-development.md).

The consolidated directory also composes `ui.directory-workspace` (main content
and optional `editor` slot), `ui.filter-bar` (a GET search form accepting normal
form attributes), and `ui.directory-toolbar` (controls plus an `actions` slot).
These reuse the existing semantic tokens and controls. `ui.drawer` accepts the
optional boolean `docked`: on wide screens it opens nonmodally beside the list;
on narrower screens it retains the existing modal API. Default drawers are
unchanged. Counts, owners and priorities come from the scoped application service;
protected contacts still require the existing audited reveal workflow.
See [local consolidation evidence](../governance/evidence/unified-local-product.md)
for verification and remaining acceptance limits.

The local authenticated CRM pages at `/crm` and `/crm/leads` reuse the same
`layouts.shell`, application sidebar and `ui.page-header` as Administration.
The shell accepts optional `breadcrumbs`, `pageSubtitle` and `pageIcon` values;
omitting them preserves existing page defaults. CRM list and option pagination
adapts the service's bounded data/meta to `ui.list-pagination` using a Laravel
length-aware paginator; no component fetches or authorizes business data.
Create/edit uses the existing form controls, detail uses `erp.entity-header`,
escaped `ui.tabs` and `erp.audit-history`. `resources/css/crm.css` only composes
existing semantic tokens. The owner reference guides layout and the forest-green
shared sidebar; unsupported financial or growth metrics are not fabricated.
See [Phase 3 evidence](../governance/evidence/cycle03-ui.md) for executed checks
and limitations. No new shared component API was added.

Keep the existing Blade/CSS/JavaScript stack. The shell embeds a fixed set of
local assets; there is no Vite manifest or frontend compilation step. No remote
font, icon service, provider or new UI framework is required. Arabic typography
uses local Noto Sans Arabic when available, then Tahoma/Segoe UI fallbacks.

Use `--color-primary`, `--color-primary-hover`, `--color-primary-soft`,
`--color-background`, `--color-surface`, `--color-surface-muted`, `--color-text`,
`--color-text-muted`, `--color-border`, `--color-on-primary`, `--color-focus`,
`--color-sidebar` and `--color-sidebar-text`. Feedback uses `success`, `warning`,
`danger`, `info` plus their `-soft` surfaces. Literal theme values belong only in
the token stylesheet. Do not add a second palette.

Spacing is `--space-1` through `--space-8`; corners `--radius-sm/md/lg`;
elevation `--shadow-sm/md/lg`; typography `--font-family-sans`, `--font-size-*`,
`--font-weight-*`, `--line-height-*`; motion `--motion-fast/normal/ease`;
layers `--z-base/sticky/dropdown/overlay/dialog/toast/skip-link`.
The shell breakpoint is 48rem; grids adapt intrinsically. CSS custom properties
cannot drive media-query breakpoints, so keep the existing 48rem contract in
shared styles. Reduced-motion overrides disable nonessential animation.

Use `.ui-stack`, `.ui-inline`, `.ui-grid`, `.ui-grid--wide`, `.ui-muted` and
`.ui-section` to compose pages. Table scrolling stays inside `.ui-table-region`.
Use logical properties (`padding-inline`, `inset-inline-start`, etc.). Isolate
formatted identifiers and numbers with `bdi`; callers own number/date formatting,
currency policy, timezone and precision. Components perform no calculations.

`$locale` defaults to `ar`; pass `en` for English/LTR. The showcase validates the
locale and sets Laravel translations. Its theme control toggles `html[data-theme]`
between `light` and `dark`; direction control previews `dir`/`lang`. For fully
translated content use `?locale=en` or `?locale=ar`. Preview choices are page-local,
not user preference persistence. No browser storage is required.

## Component API

All tags are anonymous Blade components. Normal props and text are escaped.
Pass rich markup through trusted Blade slots; never pass request HTML as raw output.
Additional attributes merge through the attribute bag. Use unique caller-owned IDs.

| Tags (prefix `x-ui.`) | Props / slots |
|---|---|
| `button` | `variant=primary/secondary/ghost/danger`, `type=button/submit/reset`; default slot. Native `disabled`. |
| `icon-button` | Required accessible `label`; icon slot; optional button variant. |
| `input`, `textarea` | `id`, `name`, `value`, `error`, `hint`; native attributes. Textarea also accepts a text slot. |
| `number-input`, `currency-input`, `date-input`, `search-input` | Same field props; native input types. Currency uses `step=any`; caller may set a policy-specific step. |
| `select`, `multi-select` | Field props, option slot; native `multiple` in multi-select. |
| `checkbox`, `radio`, `switch` | Native checked/disabled/name/value; switch may take `label`, and generates a linked ID if omitted. |
| `field` | `id`, `label`, `hint`, `error`, control slot. A label requires a nonblank ID; nested controls inherit it and connect help/error IDs. |
| `validation-message` | `id`, `type=error/hint`, text slot. |
| `form-section`, `form-actions` | Section `title`, `description`, control slot; actions accepts buttons. |
| `table` | Optional `caption`; standard `thead`/`tbody` in slot. Attributes label the scroll region. Use `th scope` yourself. |
| `table-toolbar` | Caller controls in slot. Filtering/sorting stay in the owning controller, not the component. |
| `pagination`, `breadcrumbs` | `items` for breadcrumbs / `links` for pagination: arrays of `label`, `url`, pagination `current`. Breadcrumb last item is current. Unsafe URL schemes are not links. |
| `status-badge` | `status=success/warning/danger/info/neutral`, translated text slot. |
| `stat-card` | `label`, formatted `value`, optional formatted `change`. |
| `description-list`, `detail-panel` | List `items` is label=>value; panel `title` and rich slot. |
| `tabs` | Unique `id`, `items=[{id,label,content}]`, optional aria-label. Content is plain escaped text. Extend this component for future rich panels. |
| `avatar`, `empty-state` | Avatar `name`; empty `title`, `description`, optional action slot. |
| `dropdown-menu` | `label`, optional `trigger` slot, action/link content slot; native details disclosure. |
| `modal`, `drawer` | Unique `id`, `title`, translated `close-label`, content slot. Native dialog focus trap and Escape. |
| `confirmation-dialog` | Dialog props, `cancel-label`, `confirm-label`; optional `confirm` slot or caller-owned `form` ID. Default hook executes nothing. |
| `toast`, `alert` | Toast `dismiss-label` and message slot; alert `variant=success/warning/danger/info` and message slot. |
| `tooltip` | Unique `id`, `text`, trigger slot. Do not nest interactive controls inside its focusable trigger. |
| `skeleton`, `loading-state`, `error-state` | Skeleton `variant=text/circle/block`; loading `label`; error `title`,`message`. |

| Tags (prefix `x-erp.`) | Props / slots |
|---|---|
| `entity-header` | `title`, `subtitle`, `identifier`; named `status`, `actions` slots. Uses h2 inside shell h1. |
| `entity-status` | `status` variant and translated text slot; wraps status badge. |
| `money-display`, `date-time-display` | Caller-formatted `value`; money `currency`; time optional machine `datetime`. |
| `approval-timeline` | `events=[{title,status,actor,date,note}]`; status is a display variant. |
| `audit-history` | `entries=[{action,actor,date,description}]`. |
| `workflow-stepper` | `steps=[{label,state}]`, state `complete/current/upcoming`. |
| `line-items-table` | `caption`, standard table markup slot; no calculations. |
| `totals-summary` | `lines=[{label,value}]`, `total-label`, `total-value`; precomputed strings. |
| `attachment-uploader` | `id`,`name`,`label`, optional `accept`,`multiple`; file selection only, no upload endpoint. |
| `permission-aware-action` | `:allowed="true"` shows slot; every other value hides it. This never grants server permission. |

| Tags (prefix `x-ai.`) | Props / slots |
|---|---|
| `assistant-panel`, `message` | Panel `title` and slot; message `role=user/assistant/system`, `name`,`time`, body slot. |
| `finding-card` | `severity=info/warning/danger`, `title`,`summary`, evidence in default slot. |
| `action-preview`, `confirmation` | `title`; preview optional description/slot; confirmation `confirm-label` and slot. Confirmation is always disabled. |
| `execution-receipt` | Required `state`,`label`, optional `details`; always labeled sample receipt. |
| `error-state` | `title`,`message`; no live retry. |

These AI components establish presentation only. They prove neither a gateway nor
an action, finding, authorization decision or successful execution. Wire governed
application contracts in a separate legitimately eligible task.

## Page template

```blade
@extends('layouts.shell')
@section('content')
    <x-ui.detail-panel title="Customer details">
        <form method="post" action="{{ route('an.existing.authorized.route') }}">
            @csrf
            <x-ui.field id="customer-name" label="Customer name"
                :error="$errors->first('name')">
                <x-ui.input name="name" :value="old('name')" required />
            </x-ui.field>
            <x-ui.form-actions>
                <x-ui.button type="submit">Save changes</x-ui.button>
            </x-ui.form-actions>
        </form>
    </x-ui.detail-panel>
@endsection
```

The sample route name is a placeholder: use a real authorized application route.
An existing form request/action owns validation, permissions and persistence.
For a modal opener use `<x-ui.button data-open-dialog="record-modal">…</x-ui.button>`
and `<x-ui.modal id="record-modal" title="…">…</x-ui.modal>`.
Use whitespace after closing named slots; compact `</x-slot:confirm>Text` can
confuse the installed Blade compiler's directive boundary.

## Extend and verify

Inspect the catalog before adding anything. Extend a matching component, document
new props and states here, and delegate independent tests with exact file ownership.
Keep data/business policy out of Blade. Reusable visual patterns belong in this
library, not a per-screen stylesheet. The local checker detects literal palette
values; it cannot decide semantic duplicate components or prove accessibility.

```powershell
npm.cmd run check:design-system
php vendor/bin/phpunit tests/Feature/DesignSystemComponentsTest.php tests/Feature/DesignSystemTest.php
npm.cmd run test:design-system
```

Browser checks use the existing synthetic session/server setup in
[the browser guide](../../tests/Browser/README.md), with bounded Playwright timeouts.
Never run simultaneous PHPUnit processes against the shared Blade compiled-view
cache on Windows; one can contend during atomic rename. Presentation checks need
no database reset. Review [current acceptance evidence](../governance/evidence/design-system-acceptance.md)
for exact executed results and limitations rather than inferring PASS from files.

### Unified sidebar navigation

`erp.application-sidebar` is the single production navigation component for all
workspace modules. The targeted view composer resolves current session identity
and effective navigation permissions consistently, independently of each module's
actor representation. Native `details` groups provide keyboard accessible
sections, exact/longest matching destinations identify one active child, and
unsupported standalone pages render disabled with Soon labels. Existing record
profile pages remain accessible through their directories. Search expands matching
groups and restores their previous open state when cleared. Desktop compact mode
and mobile drawer use the same component and shared dashboard interactions.
Module styles must not hide or restyle sidebar controls or unfinished entries.
See [sidebar implementation evidence](../governance/evidence/sidebar-design.md).

### CRM record workspaces

`crm._workspace-nav` shares record navigation between overview, activities, notes,
and linked follow-ups. `crm.workspace` reuses the shell, entity header, filter bar,
table, pagination, modal, and detail-panel components. Its scoped results and kind
counts come from `LeadDirectory::workspaceSection`; note selection is limited to
authorized rows on the current page. Linked tasks retain their existing service
authorization and open in the Tasks module; activity outcomes are not task statuses.
The two-column workspace stacks on small screens and uses semantic theme tokens.
Reference parity across all six requested screens is still pending.

CRM lead details and workspaces share `crm._activity-form`, built from the existing
`ui` fields, buttons, select and pagination components. Its bounded outcome lookup
and the pipeline lead lookup share `crm._outcome-picker-script`: searches and option
pages replace only authorized choices and pagination, preserving the open dialog,
current selection and transient form fields. Server validation and masking remain
authoritative; validation failures do not flash private notes. CRM styles own
content layouts only; shell/sidebar/header dimensions come from the shared shell.
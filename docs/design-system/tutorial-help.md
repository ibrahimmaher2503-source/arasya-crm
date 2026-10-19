# Tutorial and Help components

The standalone Help module uses `layouts.shell`, semantic design-system tokens,
`ui.button`, `ui.field` and `ui.search-input`. The shell owns the sole page header.
No second shell, navigation system, AI integration or business-action executor is introduced.

`ui.product-tour` accepts a server-authored `tour` with `tour_id`, positive `version`,
ordered stable `key`/`anchor`/localized `title`/`body` steps, `csrf_token` and localized
`messages`, plus `locale`. Targets use exact `data-tour` values. The component
includes its existing deterministic JavaScript/CSS once. It accepts no HTML,
external destination, executable action or record identifier from progress input.

The spotlight is a read-only explanation: it intentionally intercepts interaction
with the highlighted control. Users close/pause the tour before performing business
actions themselves. Tab stays inside the popover; unrelated ancestor branches are
inert only while the tour is visible and their previous inert state is restored.
Escape/Close pause, Previous/Next move, Skip records skipped progress, Restart
returns to the first step. Missing/hidden targets are skipped safely and do not
produce a falsely completed tour. The step body is described and announced.

Progress uses same-origin session/CSRF endpoints and is scoped to the authenticated
persisted company/user/tour version. No localStorage or cross-user browser progress
is used. The Help Center uses bounded server-side search, permission-filtered page
and workflow descriptions, FAQs and glossary. Guide text is escaped.

Integration status is explicit in HelpCatalog. A1-HELP-002 adds the shared Topbar
Help link, global provider registration, a first-login Dashboard autostart and
anchors for Help Center, Dashboard, Access (overview/users/organization/roles/audit),
Employees (directory/workers) and CRM (overview/lead list). The launcher stays
operable above the scrim while a tour is active; anchors remain targetable even when
the spotlight marks unrelated branches inert. Tasks list, CRM pipeline and quotations
remain unanchored (`integrated => false`) because their views are owned by concurrent
leases. Browser fixtures are synthetic; they do not prove application-wide coverage or
real HTTP session/CSRF behavior. See governance evidence for actual executed checks.

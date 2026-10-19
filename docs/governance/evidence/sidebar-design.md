# Shared sidebar reference implementation

Owner request 2026-10-18: one stable sidebar across modules, hierarchy from supplied
reference, and Soon next to undelivered standalone pages.

Changed: resources/views/components/erp/application-sidebar.blade.php,
resources/css/dashboard.css, resources/css/admin.css, resources/js/dashboard.js,
app/Providers/AppServiceProvider.php, docs/design-system/README.md.

Root causes: per-module actor representations produced different navigation
permission lists; admin.css hid sidebar search and unfinished items and changed
spacing, brand and typography. Removed those module-specific overrides. A targeted
view composer obtains current session identity through existing AccessControl;
backend endpoint authorization is unchanged. No invented metrics or business links.

Shared native disclosure groups, localized child links, one active destination,
search, compact desktop mode, mobile drawer, and disabled Soon items implemented.
Record-specific employee profiles are delivered through the existing directory;
the standalone profile index is not delivered. Navigation is permission-filtered.

Verification: .local/windows-qa/sidebar-check.cjs passed 24 live authenticated
page/locale/viewport combinations: dashboard, users, employees, tasks, CRM overview,
and leads; Arabic/English; 1440/390 widths. Asserted identical link sets, one active
child, no page overflow, search disclosure, compact toggle, and mobile open. Dark
theme applied in each combination; not independently visually reviewed. Screenshot:
.local/windows-qa/sidebar-final.png. Design-system checker/self-test and JavaScript
syntax checks passed. No database resets, migrations, push, deployment or commits.

Skills actually used: Ponytail, Laravel best practices (Blade/composers), Laravel
UI UX minimal, systematic debugging. Solo per owner's explicit no-subagent
instruction; no independent review claimed. Other login/demo work preserved.
No task completion or production/pixel parity approval is claimed.

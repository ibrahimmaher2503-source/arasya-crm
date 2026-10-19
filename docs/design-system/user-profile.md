# User detail and edit page

The user profile page follows the owner-provided account-edit reference: one shared shell and page header, a compact account summary, native in-page section links, paired basic and organization panels, and separate account/access forms. The physical use context is an office administrator maintaining accounts at a desktop in daytime; the reference is light, while the existing theme tokens also support dark mode.

The page reuses `layouts.shell`, `ui.page-header` props, `ui.option-browser`, `ui.form-section`, shared fields and controls, `ui.avatar`, `ui.status-badge`, buttons, and `ui.icon`. Its stylesheet is loaded from the child view because the shell has no page stylesheet stack. Every selector in `resources/css/user-profile.css` is scoped to `.user-profile`; it adds no palette or component API and uses logical properties for RTL/LTR.

The account form posts its existing name, email, optional new password, branch, department, active flag, CSRF token, and version to the existing user route. The independent access form keeps its own version token and posts paginated role selections plus direct permission values (`inherit`, `1`, or `0`) to the existing access route. Current branch/department options and selected roles outside the displayed option page remain retained. Existing authorization, self-access protection, and incomplete-assignment read-only behavior stay server-owned.

The layout intentionally omits unsupported phone, notes, job title, photo upload, account creation/last-login metadata, deletion, password-reset actions, and any new persistence or endpoint. Section links target real sections and work without JavaScript. The layout stacks at the existing 48rem shell breakpoint; shared keyboard focus and light/dark semantic tokens remain in effect.

The heading is neutral for read-only viewers. Missing location names display a
translated Unavailable label; internal identifiers remain in submitted values.
The account actions follow the same Save/Cancel sequence visually and in keyboard
focus. The access form carries `_access_assignment` so a validation redirect can
distinguish an empty role selection from an initial read. Flashed direct permission
choices use literal dotted keys and only `inherit`, `1`, or `0`; invalid choices
fall back to stored values. Submitted off-page roles survive validation without
restoring removed assignments.

Current acceptance is PARTIAL: account/access writes work, but successful saves
from English still redirect to Arabic through the shared AccessController. This
controller is outside the isolated profile task scope. See the current evidence.

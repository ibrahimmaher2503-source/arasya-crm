# Reference-led login presentation — 2026-10-18

Owner requested `/login` resemble the attached cream/green split-screen reference.
Implemented in the canonical working tree, with no route, controller, credential,
database or authentication-policy change. Uses existing `layouts.shell`, semantic
tokens, `ui.field`, `ui.input`, `ui.button` and `ui.icon`; adds reusable
`ui.auth-layout` and `ui.brand-lockup` plus scoped authentication composition CSS.
Only the guest login opts into this layout; authenticated pages retain their shell.

The original reference is
`C:/Users/N/AppData/Local/Temp/codex-clipboard-64a25685-8fec-4e16-ab8b-41f207253acf.png`.
A generated local material photograph is stored at `public/images/login-materials.png`.
The headline, brand treatment, benefits, labels and form are HTML/SVG, not baked
into a screenshot. The photograph and geometric brand treatment are similar to,
not pixel-identical reproductions of, the reference. Unsupported Microsoft SSO,
persistent remember-device and reset links are omitted; a real support message
is shown instead. Password visibility is keyboard-operable and localized.

## Ownership and workflow

Task A1-CORE-015 scope was extended under the existing coordinator lock/hash/DAG
guards for the owner's specific login request, then claimed by
`login-ui-coordinator`. No task/ADR/policy completion is inferred. Existing broader
Access acceptance remains partial. No workers: the owner explicitly required
solo work; independent test/review delegation was assessed and not dispatched
under that instruction. No independent review or production acceptance claimed.

Skills actually used: repository Ponytail, Laravel best practices/Blade rules,
installed Laravel UI/UX minimal skill and imagegen for the local photo asset.
Relevant paths: `.agents/skills/ponytail/SKILL.md`,
`.agents/skills/laravel-best-practices/SKILL.md`,
`.agents/skills/laravel-best-practices/rules/blade-views.md`,
`C:/Users/N/.codex/skills/laravel-ui-ux-minimal/SKILL.md`,
`C:/Users/N/.codex/skills/.system/imagegen/SKILL.md`.

## Verification

- Browser regression was first RED because the standalone authentication layout
  did not exist. Final Playwright matrix: desktop 1672x941, tablet 768x1024 and
  mobile 390x844; Arabic RTL and English LTR; light/dark screenshots, required
  validation, labels, show/hide password via mouse/keyboard and no page overflow.
- `npx playwright test --config=playwright.login.config.ts`: **3 PASS**;
  each scenario checks both locales and themes. The image response is verified
  as PNG rather than an HTML fallback.
- `npm run check:design-system`: checker, self-test and shared JS syntax **PASS**.
- Actual login with the existing isolated preview administrator and **30
  authenticated page/locale checks PASS** after the shell change. No database
  tests or schema resets required for this UI-only change. An initial smoke
  invocation from main lacked the QA credential fixture; rerun from its owning
  QA worktree passed, without moving credentials into source.
- Desktop and mobile screenshots inspected; dark visual panel contrast improved
  with a semantic dark overlay. Canonical ignored `.local/windows-qa/login-*`
  contains evidence. Source diff whitespace check passed for the UI files.

The owned ignored preview startup helper now uses Laravel's official development
server router with the public directory as cwd, so real static images are served.
No public application router or external server configuration was changed.
Canonical preview remains `http://127.0.0.1:8773/login` with existing credentials.

Concurrent unrelated demo bootstrap/config/command/tests appeared during this
work and were not modified or included. No deployment, push or feature expansion.
New files remain in the working tree; no UI commit was requested for this change.
Next: owner visual review at the reference viewport; product-wide Access/AI/tour
closure continues under its existing tasks rather than this login presentation.

## Evidence artifact incident

The initial login Playwright config inherited the default `test-results` output
directory. Playwright cleared that directory, removing twelve ignored historical
Employees screenshots referenced by `employees-completion.md`. This was an
execution mistake, not a source/database deletion. No matching backup was found
in the inspected employee worktree, QA worktree or local evidence directories.
The login config now explicitly uses `.local/windows-qa/login-test-results` so
subsequent runs cannot clear sibling module evidence. Documentation validation
currently FAILS on those twelve image links; it is not claimed green. Historical
images were not fabricated or replaced with screenshots from a different run.
Recovery requires a separately owned Employees screenshot rerun against its
existing isolated fixture, preserving the distinction from the original proof.

## Desktop readability correction (2026-10-18)
Owner screenshot showed feature captions overlapping dark product materials. In resources/css/auth.css the visual now places the photograph in a separate flexible lower region, with the entire message on a solid semantic surface. Mobile omits that decorative region; dark theme retains readable text. Desktop screenshot inspected at 1672x941. Existing desktop/tablet/mobile Playwright matrix passed (3 tests, Arabic/English/light/dark); design-system check passed. Solo per owner instruction; no auth/backend change or commit.


## Continuous visual refinement (2026-10-18)
Owner rejected hard split. Supersedes previous separate lower-region treatment: photograph now covers the continuous visual panel with a smooth top-to-bottom mask, preserving clear upper copy and visible lower materials. Feature captions have translucent semantic surfaces. Reduced upper spacing. Desktop screenshot visually inspected after final cover sizing; 3 viewport browser tests passed before final background sizing adjustment, final pristine capture and design-system checker passed after it. Authentication unchanged. Solo as requested.

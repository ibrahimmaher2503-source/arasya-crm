# A1-DASH-004 — full-height sidebar correction

Owner screenshot follow-up, 2026-10-17: sidebar must not appear cut off on a
document taller than the viewport. Existing A1-DASH-003 integration blockers and
historical evidence remain unchanged; this is a separately registered narrow fix.

Root cause: sticky sidebar paints only its own 100vh box; the grid row follows
the taller main content and previously exposed the light body below that box.
The shell now paints the existing sidebar token through the full document;
the main content retains its existing background token. Sidebar uses the same
solid token to avoid a seam, 100dvh and no 42rem minimum that could clip controls
on shorter screens. Its existing independently scrollable navigation and mobile
drawer behavior remain intact. No routes, database, permissions or shared shell
were changed. AI tool coverage N/A: visual styling only.

Skills actually read/applied: .agents/skills/ponytail/SKILL.md,
C:/Users/N/.codex/skills/frontend-design/SKILL.md, component catalog
docs/design-system/README.md; existing session debugging, parallel review and
verification skills applied. Only resources/css/dashboard.css was implemented.
No permanent test code was authored; existing browser checks and coordinator
manual browser geometry checks were sufficient for this focused style change.

Independent read-only worker sidebar_review explicitly requested gpt-6-luna /
xhigh; dispatch accepted. Worker confirmed the root cause and approach and
identified gradient seam and short-screen minimum risks; both addressed. No
runtime model attestation claimed. No new worktree: existing dirty runtime is
needed for browser integration, with exclusive CSS ownership and read-only review.

Executed on the existing Windows native synthetic environment:

* Real Playwright dashboard suite: 6/6 PASS, 14.374 seconds, desktop/mobile,
  Arabic/English, light/dark, navigation and keyboard regression.
* Additional real Chromium at 1280x720: document height 1099, sidebar height720,
  sidebar/shell background equality true; after scrolling to document end the
  sidebar remained at y=0 with height720. PASS. Full-page screenshot:
  .local/windows-qa/sidebar-full-height.png.
* npm run check:design-system PASS (checker and self-tests).

No PHPUnit/MariaDB rerun: no backend or persistence change. Browser used existing
authenticated synthetic fixtures; no reset, reinitialization or service change.
Other dirty work and existing coordinator leases preserved.

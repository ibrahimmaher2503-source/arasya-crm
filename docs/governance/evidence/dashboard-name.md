# A1-DASH-005 — short display name

Owner follow-up 2026-10-17: shorten the administrator name for a tidier layout.
Only resources/views/dashboard/home.blade.php changed: retain fullActorName and
format actorName with the installed Laravel Str::words (two words) and Str::limit
(24 display units). Both greeting and sidebar share the same value. Full name
remains escaped in title attributes and unchanged in persisted account data.
Native title is not a dependable full-name disclosure on touch; account records
remain authoritative. No authorization, session or backend data changes.

Skills applied from this session: .agents/skills/ponytail/SKILL.md,
.agents/skills/laravel-best-practices/SKILL.md (Blade guidance),
C:/Users/N/.codex/skills/frontend-design/SKILL.md; component catalog read.
Independent read-only admin_name_review worker requested gpt-6-luna/xhigh,
dispatch accepted, reviewed Unicode handling and escaping: no functional finding.
No independent runtime identity claim. No new test code authored; existing
Playwright coverage reused. No worktree necessary for the exclusively owned
view change; unrelated dirty runtime and other coordinator leases preserved.

Windows native real Playwright dashboard suite: 6/6 PASS, 16.720 seconds,
desktop/mobile, RTL/LTR, theme/navigation/keyboard. Log:
.local/windows-qa/dashboard-name-browser.json. Design system checker/self-tests
PASS. No PHPUnit/MariaDB rerun for presentation formatting only. No data reset.
AI tool coverage N/A, no business capability or assistant execution added.
Parent dashboard integration blockers remain unchanged.

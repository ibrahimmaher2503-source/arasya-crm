# Reopen: A1-EMP-007

- Event ID: ce28b3bfc5824ddf8fabf3dab8e2b781
- Recorded UTC: 2026-10-19T02:37:00.5861650Z
- Owner: closure04-coordinator
- Previous status: done
- New status: blocked
- Lease ID: 
- Scope paths: app\Http\Controllers\EmployeeWorkController.php, app\Modules\Employees\Application\EMP01\EmployeeWorkDirectory.php, resources\views\employees\work.blade.php, resources\css\employee-work.css, routes\employees.php, tests\Feature\Employees\EmployeeWorkTest.php, tests\Browser\employee-work.spec.ts, tests\Browser\employee-work-fixtures.php, playwright.employee-work.config.ts, docs\governance\evidence\employee-work.md
- Scope modules: EmployeeWorkPresentation
- Reviewer: 
- Blocker: EmployeeWorkDirectory reads task counts/list/evidence after authorization without the existing company read lock; native two-connection regression required.
- Blocker owner: closure04-coordinator
- Next action: Claim this exact non-overlapping EmployeeWork task; prove RED on isolated MariaDB, reuse AccessControl.withReadLock, then verify and independently review.

## Evidence / reason

Reopen reason: Owner Closure04 directs safe repairs to existing workflows; independent source review found employee-work protected task reads do not serialize with permission mutations.

User Closure04 steps3,4,6,7,8. Independent closure04_security review: EmployeeWorkDirectory.php32-116 and RequestAuthorizationPerformanceTest160-182 existing serialization contract. No policy or scope expansion. Current task paths have no overlap with recorded active leases; keep Identity,CRM,Tasks protected files read-only.

> This is supporting evidence, not proof of a committed transition by itself. The matching event_id and handoff_path in state.json are authoritative. This is not a business approval and does not change any OPEN decision or ADR.

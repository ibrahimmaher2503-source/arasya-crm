<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EmployeeWorkTest extends TestCase
{
    use DatabaseMigrations;

    public function test_company_lock_is_held_through_the_employee_work_task_count_query(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('Two-connection serialization requires native MariaDB.');
        }

        $org = $this->organization();
        $actor = $this->user($org, 'Employee work task reader', ['tasks.read']);
        $this->task($org['company'], $org['department'], $actor, $actor, 'Lock probe task', 'pending');

        config(['database.connections.employee_work_contender' => config('database.connections.'.config('database.default'))]);
        $contender = DB::connection('employee_work_contender');
        $contender->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $countQueryCount = 0;
        $companyLockWasHeld = false;

        DB::listen(function (QueryExecuted $query) use ($contender, $org, &$countQueryCount, &$companyLockWasHeld): void {
            $sql = strtolower($query->sql);
            if ($query->connectionName !== config('database.default')
                || ! str_contains($sql, 'from `tasks_cycle`')
                || ! str_contains($sql, 'count(case')) {
                return;
            }

            $countQueryCount++;
            if ($countQueryCount !== 1) {
                return;
            }

            try {
                $contender->transaction(fn () => $contender->table('access_companies')
                    ->where('id', $org['company'])
                    ->lockForUpdate()
                    ->first());
            } catch (QueryException $exception) {
                if (($exception->errorInfo[1] ?? null) !== 1205) {
                    throw $exception;
                }
                $companyLockWasHeld = true;
            }
        });

        try {
            $this->withSession($this->sessionFor($actor))
                ->get('/employees/work?locale=en')
                ->assertOk();

            self::assertSame(1, $countQueryCount, 'The task summary count query should run once.');
            self::assertTrue($companyLockWasHeld, 'A concurrent writer must wait while the task count is read.');
            self::assertNotNull($contender->transaction(fn () => $contender->table('access_companies')
                ->where('id', $org['company'])
                ->lockForUpdate()
                ->first()), 'The company lock must be released after the response completes.');
        } finally {
            DB::purge('employee_work_contender');
        }
    }

    public function test_work_route_requires_task_read_and_preserves_employee_json_routes(): void
    {
        $org = $this->organization();
        $reader = $this->user($org, 'Reader without task access', ['employees.read']);
        $admin = $this->user($org, 'Employee work administrator', [], true);

        $this->getJson('/employees/work')->assertUnauthorized();
        $this->withSession($this->sessionFor($reader))
            ->get('/employees/work?locale=en')
            ->assertForbidden();

        $page = $this->withSession($this->sessionFor($admin))
            ->get('/employees/work?locale=en')
            ->assertOk()
            ->assertSee('Employee work follow-up')
            ->assertSee('Follow only tasks authorized to your current identity');
        $document = new \DOMDocument;
        @$document->loadHTML($page->getContent());
        self::assertSame(1, $document->getElementsByTagName('h1')->length);
        $this->withSession($this->sessionFor($admin))
            ->getJson('/employees')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_page_shows_only_authorized_tasks_and_their_real_evidence(): void
    {
        $org = $this->organization();
        $actor = $this->user($org, 'Current employee', [], true);
        $otherOwner = $this->user($org, 'Other owner');
        $manager = $this->employee($org, 'MGR-001', 'Current supervisor');
        $employee = $this->employee($org, 'EMP-001', 'Current employee profile', $actor, $manager);

        $todayTask = $this->task($org['company'], $org['department'], $actor, $actor, 'Due today task', 'pending', now()->startOfDay()->addHours(12));
        $evidenceTask = $this->task($org['company'], $org['department'], $actor, $actor, 'Task with actual evidence', 'in_progress', now()->subDay());
        $completedTask = $this->task($org['company'], $org['department'], $actor, $actor, 'Completed task', 'completed', now()->addDay());
        $cancelledTask = $this->task($org['company'], $org['department'], $actor, $actor, 'Cancelled task', 'cancelled');
        $assignedTask = $this->task($org['company'], $org['department'], $otherOwner, $actor, 'Assigned to current employee', 'pending');
        $otherOwnerTask = $this->task($org['company'], $org['department'], $otherOwner, $otherOwner, 'Private other-owner task', 'pending');
        $otherDepartmentTask = $this->task($org['company'], $org['other_department'], $actor, $actor, 'Other department task', 'pending');
        $otherEntityTask = $this->task($org['other_company'], $org['other_company_department'], $actor, $actor, 'Other company task', 'pending');

        DB::table('tasks_cycle_evidence')->insert([
            'id' => (string) Str::ulid(), 'task_id' => $evidenceTask, 'path' => 'task-evidence/'.$evidenceTask.'/actual.txt',
            'name' => 'Actual task evidence.txt', 'mime' => 'text/plain', 'size' => 24, 'created_at' => now(),
        ]);
        DB::table('tasks_cycle_evidence')->insert([
            'id' => (string) Str::ulid(), 'task_id' => $otherOwnerTask, 'path' => 'task-evidence/'.$otherOwnerTask.'/private.txt',
            'name' => 'Private other-owner evidence.txt', 'mime' => 'text/plain', 'size' => 31, 'created_at' => now(),
        ]);
        DB::table('employee_evidence')->insert([
            'id' => (string) Str::ulid(), 'company_id' => $org['company'], 'employee_id' => $employee,
            'job_id' => $evidenceTask, 'actor_id' => $actor, 'note' => 'Recorded work evidence note', 'created_at' => now(),
        ]);
        DB::table('employee_evidence')->insert([
            'id' => (string) Str::ulid(), 'company_id' => $org['company'], 'employee_id' => $employee,
            'job_id' => $otherOwnerTask, 'actor_id' => $otherOwner, 'note' => 'Private other-owner note', 'created_at' => now(),
        ]);

        $response = $this->withSession($this->sessionFor($actor))
            ->get('/employees/work?locale=en&employee_id='.$employee)
            ->assertOk()
            ->assertSee('Current employee profile')
            ->assertSee('Current supervisor')
            ->assertSee('Due today task')
            ->assertSee('Task with actual evidence')
            ->assertSee('Assigned to current employee')
            ->assertSee('Actual task evidence.txt')
            ->assertSee('Recorded work evidence note')
            ->assertDontSee('Private other-owner task')
            ->assertDontSee('Private other-owner evidence.txt')
            ->assertDontSee('Private other-owner note')
            ->assertDontSee('Other department task')
            ->assertDontSee('Other company task')
            ->assertSee('data-summary-total="5"', false)
            ->assertSee('name="supervisor_id"', false)
            ->assertSee('disabled', false)
            ->assertDontSee('name="note"', false)
            ->assertDontSee('name="project"', false)
            ->assertDontSee('name="progress"', false);

        $response->assertSee('/tasks/'.$todayTask)
            ->assertSee('/tasks/'.$evidenceTask)
            ->assertSee('/tasks/'.$completedTask)
            ->assertSee('/tasks/'.$cancelledTask)
            ->assertSee('/tasks/'.$assignedTask)
            ->assertDontSee('/tasks/'.$otherOwnerTask)
            ->assertDontSee('/tasks/'.$otherDepartmentTask)
            ->assertDontSee('/tasks/'.$otherEntityTask);
    }

    public function test_whitespace_and_nul_identifiers_are_excluded_from_all_work_sections(): void
    {
        $org = $this->organization();
        $actor = $this->user($org, 'Identifier scope reader', ['tasks.read']);
        $other = $this->user($org, 'Identifier scope peer');
        $dueAt = now()->startOfDay()->addHours(12);
        $owned = $this->task($org['company'], $org['department'], $actor, $other, 'Valid owned task', 'pending', $dueAt);
        $assigned = $this->task($org['company'], $org['department'], $other, $actor, 'Valid assigned task', 'in_progress', $dueAt);
        $unassigned = $this->task($org['company'], $org['department'], $actor, null, 'Valid unassigned task', 'completed', $dueAt);
        $blank = " \t\n\r\0\v";
        $blankOwner = $this->task($org['company'], $org['department'], $blank, $actor, 'Malformed owner sentinel', 'pending', $dueAt);
        $blankAssignee = $this->task($org['company'], $org['department'], $actor, $blank, 'Malformed assignee sentinel', 'in_progress', $dueAt);
        $blankId = $this->task($org['company'], $org['department'], $actor, $actor, 'Malformed id sentinel', 'completed', $dueAt);
        DB::table('tasks_cycle')->where('id', $blankId)->update(['id' => $blank]);

        foreach ([$owned, $assigned, $unassigned, $blankOwner, $blankAssignee, $blank] as $index => $taskId) {
            DB::table('tasks_cycle_evidence')->insert([
                'id' => (string) Str::ulid(), 'task_id' => $taskId, 'path' => 'task-evidence/sentinel-'.$index.'.txt',
                'name' => 'Identifier evidence '.$index.'.txt', 'mime' => 'text/plain', 'size' => 24, 'created_at' => now(),
            ]);
        }

        $session = $this->sessionFor($actor);
        $response = $this->withSession($session)->get('/employees/work?locale=en&per_page=10')
            ->assertOk()
            ->assertSee('Valid owned task')->assertSee('Valid assigned task')->assertSee('Valid unassigned task')
            ->assertDontSee('Malformed owner sentinel')->assertDontSee('Malformed assignee sentinel')->assertDontSee('Malformed id sentinel')
            ->assertSee('data-summary-total="3"', false);
        self::assertSame(['total' => 3, 'pending' => 1, 'in_progress' => 1, 'completed' => 1], $response->viewData('summaryCounts'));
        $tasks = $response->viewData('tasks');
        self::assertSame(3, $tasks->total());
        self::assertSame(1, $tasks->lastPage());
        self::assertSame(10, $tasks->perPage());
        self::assertEqualsCanonicalizing([$owned, $assigned, $unassigned], $tasks->pluck('id')->all());
        self::assertEqualsCanonicalizing([$owned, $assigned, $unassigned], $response->viewData('todayTasks')->pluck('id')->all());
        self::assertEqualsCanonicalizing([$owned, $assigned, $unassigned], array_column($response->viewData('taskEvidence'), 'task_id'));
        foreach (range(0, 5) as $index) {
            if ($index < 3) {
                $response->assertSee('Identifier evidence '.$index.'.txt');
            } else {
                $response->assertDontSee('Identifier evidence '.$index.'.txt');
            }
        }

        $this->withSession($session)->get('/employees/work?locale=en&per_page=10&page=2')
            ->assertOk()->assertViewHas('tasks', fn ($tasks): bool => $tasks->total() === 3 && $tasks->isEmpty());
    }

    public function test_filters_are_bounded_and_pagination_is_stable(): void
    {
        $org = $this->organization();
        $actor = $this->user($org, 'Filter administrator', [], true);
        $this->employee($org, 'FILTER-EMP', 'Filter employee', $actor);
        $dates = [];
        $taskIds = [];

        foreach (range(1, 12) as $number) {
            $dates[$number] = now()->startOfDay()->addDays($number - 1);
            $taskIds[$number] = $this->task(
                $org['company'],
                $org['department'],
                $actor,
                $actor,
                sprintf('Browse task %02d', $number),
                $number % 2 === 0 ? 'pending' : 'in_progress',
                $dates[$number],
            );
        }

        $session = $this->sessionFor($actor);
        $firstPage = $this->withSession($session)
            ->get('/employees/work?locale=en&per_page=10&sort=title&direction=desc')
            ->assertOk()
            ->assertSee('data-summary-total="12"', false)
            ->assertSee('Browse task 12')
            ->assertSee('Browse task 03')
            ->assertDontSee('Browse task 02');
        self::assertSame(1, $firstPage->viewData('tasks')->currentPage());
        self::assertSame(12, $firstPage->viewData('tasks')->total());
        self::assertSame(10, $firstPage->viewData('tasks')->count());
        $this->assertStringContainsString('sort=title', (string) $firstPage->viewData('tasks')->nextPageUrl());

        $this->withSession($session)
            ->get('/employees/work?locale=en&per_page=10&sort=title&direction=desc&page=2')
            ->assertOk()
            ->assertSee('Browse task 02')
            ->assertSee('Browse task 01')
            ->assertDontSee('Browse task 03');

        $filtered = $this->withSession($session)
            ->get('/employees/work?locale=en&status=pending&date_from='.$dates[2]->toDateString().'&date_to='.$dates[6]->toDateString())
            ->assertOk()
            ->assertSee('Browse task 02')
            ->assertSee('Browse task 04')
            ->assertSee('Browse task 06')
            ->assertDontSee('Browse task 03')
            ->assertSee('data-summary-total="12"', false);
        foreach ([1, 3, 5, 7] as $excluded) {
            $filtered->assertDontSee('data-task-id="'.$taskIds[$excluded].'"', false);
        }
        foreach ([2, 4, 6] as $included) {
            $filtered->assertSee('data-task-id="'.$taskIds[$included].'"', false);
        }

        $this->withSession($session)
            ->get('/employees/work?locale=en&q=Browse%20task%2012')
            ->assertOk()
            ->assertSee('Browse task 12')
            ->assertDontSee('Browse task 11');
        $this->withSession($session)
            ->get('/employees/work?locale=en&branch_id='.$org['other_branch'])
            ->assertOk()
            ->assertSee('No tasks match');
        $this->withSession($session)
            ->get('/employees/work?locale=en&date_from='.$dates[6]->toDateString().'&date_to='.$dates[2]->toDateString())
            ->assertSessionHasErrors('date_to');
    }

    public function test_employee_selection_cannot_expand_task_scope_and_profile_is_hidden_without_employee_read(): void
    {
        $org = $this->organization();
        $actor = $this->user($org, 'Scoped reader', ['tasks.read', 'employees.read']);
        $other = $this->user($org, 'Peer reader', ['tasks.read', 'employees.read']);
        $currentProfile = $this->employee($org, 'SELF-001', 'Visible current profile', $actor);
        $peerProfile = $this->employee($org, 'PEER-001', 'Unavailable peer profile', $other);
        $this->task($org['company'], $org['department'], $actor, $actor, 'Current actor task', 'pending');
        $this->task($org['company'], $org['department'], $other, $other, 'Peer task must stay hidden', 'pending');

        $this->withSession($this->sessionFor($actor))
            ->get('/employees/work?locale=en&employee_id='.$currentProfile)
            ->assertOk()
            ->assertSee('Visible current profile')
            ->assertSee('Current actor task')
            ->assertDontSee('Peer task must stay hidden');
        $this->withSession($this->sessionFor($actor))
            ->get('/employees/work?locale=en&employee_id='.$peerProfile)
            ->assertNotFound()
            ->assertDontSee('Unavailable peer profile')
            ->assertDontSee('Peer task must stay hidden');

        $reader = $this->user($org, 'Task-only reader', ['tasks.read']);
        $readerProfile = $this->employee($org, 'TASK-ONLY-001', 'Unrevealed profile name', $reader);
        $this->task($org['company'], $org['department'], $reader, $reader, 'Task-only reader task', 'pending');
        $this->withSession($this->sessionFor($reader))
            ->get('/employees/work?locale=en')
            ->assertOk()
            ->assertSee('Task-only reader task')
            ->assertSee('Employee profile unavailable')
            ->assertDontSee('Unrevealed profile name')
            ->assertDontSee($readerProfile);
    }

    /** @return array<string, string> */
    private function organization(): array
    {
        $company = (string) Str::ulid();
        $otherCompany = (string) Str::ulid();
        $branch = (string) Str::ulid();
        $otherBranch = (string) Str::ulid();
        $otherCompanyBranch = (string) Str::ulid();
        $department = (string) Str::ulid();
        $otherDepartment = (string) Str::ulid();
        $otherCompanyDepartment = (string) Str::ulid();

        DB::table('access_companies')->insert([
            ['id' => $company, 'name' => 'Employee Work Synthetic Company', 'version' => 0],
            ['id' => $otherCompany, 'name' => 'Employee Work Other Company', 'version' => 0],
        ]);
        foreach ([[$branch, $company], [$otherBranch, $company], [$otherCompanyBranch, $otherCompany]] as [$branchId, $companyId]) {
            DB::table('access_branches')->insert([
                'id' => $branchId, 'company_id' => $companyId, 'name' => 'Synthetic branch', 'code' => 'branch-'.$branchId, 'active' => true, 'version' => 0,
            ]);
        }
        foreach ([[$department, $company, $branch], [$otherDepartment, $company, $branch], [$otherCompanyDepartment, $otherCompany, $otherCompanyBranch]] as [$departmentId, $companyId, $branchId]) {
            DB::table('access_departments')->insert([
                'id' => $departmentId, 'company_id' => $companyId, 'branch_id' => $branchId, 'name' => 'Synthetic department', 'code' => 'dept-'.$departmentId, 'active' => true, 'version' => 0,
            ]);
        }

        return [
            'company' => $company,
            'other_company' => $otherCompany,
            'branch' => $branch,
            'other_branch' => $otherBranch,
            'other_company_branch' => $otherCompanyBranch,
            'department' => $department,
            'other_department' => $otherDepartment,
            'other_company_department' => $otherCompanyDepartment,
        ];
    }

    /** @param list<string> $permissions */
    private function user(array $org, string $name, array $permissions = [], bool $isAdmin = false): string
    {
        $id = (string) Str::ulid();
        DB::table('access_users')->insert([
            'id' => $id, 'company_id' => $org['company'], 'branch_id' => $org['branch'], 'department_id' => $org['department'],
            'name' => $name, 'email' => strtolower(str_replace(' ', '-', $name)).'-'.$id.'@example.test',
            'password' => Hash::make('synthetic-employee-work-password'), 'active' => true, 'is_admin' => $isAdmin,
            'session_version' => 0, 'version' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($permissions !== []) {
            $role = (string) Str::ulid();
            DB::table('access_roles')->insert(['id' => $role, 'company_id' => $org['company'], 'name' => 'Employee work role '.$role, 'version' => 0]);
            DB::table('access_role_permissions')->insert(array_map(static fn (string $permission): array => ['role_id' => $role, 'permission' => $permission], $permissions));
            DB::table('access_user_roles')->insert(['user_id' => $id, 'role_id' => $role]);
        }

        return $id;
    }

    private function employee(array $org, string $code, string $name, ?string $userId = null, ?string $managerId = null): string
    {
        $id = (string) Str::ulid();
        DB::table('employee_profiles')->insert([
            'id' => $id, 'company_id' => $org['company'], 'branch_id' => $org['branch'], 'department_id' => $org['department'],
            'manager_id' => $managerId, 'user_id' => $userId, 'code' => $code, 'name' => $name, 'role' => 'Technician',
            'hire_date' => '2026-01-01', 'status' => 'active', 'phone' => null, 'email' => null, 'private_notes' => 'Private employee note',
            'version' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function task(string $entity, string $department, string $owner, ?string $assignee, string $title, string $status, mixed $dueAt = null): string
    {
        $id = (string) Str::ulid();
        DB::table('tasks_cycle')->insert([
            'id' => $id, 'entity_id' => $entity, 'department_id' => $department, 'owner_id' => $owner, 'assignee_id' => $assignee,
            'title' => $title, 'priority' => 'normal', 'due_at' => $dueAt, 'status' => $status, 'version' => 0,
            'create_key_hash' => hash('sha256', $id), 'create_payload_hash' => hash('sha256', $title), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** @return array{access_user_id: string, access_session_version: int} */
    private function sessionFor(string $userId): array
    {
        return ['access_user_id' => $userId, 'access_session_version' => 0];
    }
}

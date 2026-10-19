<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class EmployeeAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_routes_require_a_current_session_and_read_grant(): void
    {
        $org = $this->organization();
        $employee = $this->employee($org, 'AUTH-001', 'Access boundary worker');
        $readerWithoutGrant = $this->user($org, 'No employee grant');

        $this->getJson('/employees')->assertUnauthorized();
        $this->getJson('/employees/'.$employee)->assertUnauthorized();

        foreach ([
            ['GET', '/employees', []],
            ['GET', '/employees/workers', []],
            ['GET', '/employees/create', []],
            ['GET', '/employees/'.$employee.'/edit', []],
            ['GET', '/employees/'.$employee, []],
            ['POST', '/employees', $this->profilePayload($org, 'AUTH-002', 'Unauthorized create')],
            ['POST', '/employees/'.$employee, ['name' => 'Unauthorized edit', 'version' => 0]],
            ['POST', '/employees/'.$employee.'/evidence', []],
        ] as [$method, $uri, $payload]) {
            $this->withSession($this->sessionFor($readerWithoutGrant))
                ->json($method, $uri, $payload)
                ->assertForbidden();
        }
    }

    public function test_json_directory_searches_filters_and_paginates_large_result_sets_without_losing_query(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Directory administrator', isAdmin: true);
        $manager = $this->employee($org, 'FILTER-MGR', 'Filter manager');
        $matches = [
            $this->employee($org, 'NEEDLE-CODE', 'Code match', managerId: $manager),
            $this->employee($org, 'NAME-ROW', 'Needle in name', managerId: $manager),
            $this->employee($org, 'ROLE-ROW', 'Role match', managerId: $manager, role: 'Needle technician'),
        ];

        $secondManager = $this->employee($org, 'FILTER-MGR-2', 'Second manager');
        $wrongManager = $this->employee($org, 'WRONG-MANAGER', 'Wrong manager', managerId: $secondManager);
        $wrongStatus = $this->employee($org, 'WRONG-STATUS', 'Wrong status', managerId: $manager, status: 'on_leave');
        $wrongDepartment = $this->employee($org, 'WRONG-DEPARTMENT', 'Wrong department', department: 'department_a2', status: 'active');
        $wrongBranch = $this->employee($org, 'WRONG-BRANCH', 'Wrong branch', branch: 'branch_b', department: 'department_b');
        $wrongCompany = $this->employee($org, 'WRONG-COMPANY', 'Wrong company', branch: 'branch_c', department: 'department_c');

        $search = $this->sessionAs($admin)->getJson('/employees?q=Needle')
            ->assertOk()
            ->assertJsonStructure(['data' => ['data' => [['id', 'code', 'name', 'role']], 'total', 'per_page', 'current_page', 'last_page']])
            ->assertJsonPath('data.total', 3);
        $searchIds = array_column($search->json('data.data'), 'id');
        sort($matches, SORT_STRING);
        sort($searchIds, SORT_STRING);
        self::assertSame($matches, $searchIds);

        foreach ([
            [['branch_id' => $org['branch_a']], [$manager, ...$matches, $secondManager, $wrongManager, $wrongStatus, $wrongDepartment]],
            [['department_id' => $org['department_a']], [$manager, ...$matches, $secondManager, $wrongManager, $wrongStatus]],
            [['manager_id' => $manager], [...$matches, $wrongStatus]],
            [['status' => 'active'], [$manager, ...$matches, $secondManager, $wrongManager, $wrongDepartment, $wrongBranch]],
            [[
                'branch_id' => $org['branch_a'],
                'department_id' => $org['department_a'],
                'manager_id' => $manager,
                'status' => 'active',
            ], $matches],
        ] as [$filters, $expectedIds]) {
            $filtered = $this->sessionAs($admin)->getJson('/employees?'.http_build_query($filters))->assertOk();
            $actualIds = array_column($filtered->json('data.data'), 'id');
            sort($expectedIds, SORT_STRING);
            sort($actualIds, SORT_STRING);
            self::assertSame($expectedIds, $actualIds);
        }

        self::assertNotContains($wrongBranch, $matches);
        self::assertNotContains($wrongCompany, $matches);

        $bulkIds = [];
        foreach (range(1, 121) as $number) {
            $bulkIds[] = $this->employee($org, sprintf('BULK-%03d', $number), sprintf('Bulk worker %03d', $number));
        }

        $query = [
            'q' => 'Bulk worker',
            'status' => 'active',
            'sort' => 'code',
            'direction' => 'desc',
        ];
        $firstPage = $this->sessionAs($admin)->getJson('/employees?'.http_build_query($query))
            ->assertOk()
            ->assertJsonPath('data.total', 121)
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonPath('data.last_page', 13);
        $this->assertCount(10, $firstPage->json('data.data'));
        self::assertSame('BULK-121', $firstPage->json('data.data.0.code'));
        $this->assertPaginatorQuery($firstPage, $query + ['per_page' => '10']);

        $this->sessionAs($admin)->getJson('/employees?'.http_build_query($query + ['per_page' => 25]))
            ->assertOk()->assertJsonPath('data.per_page', 25)->assertJsonPath('data.total', 121);
        $lastPage = $this->sessionAs($admin)->getJson('/employees?'.http_build_query($query + ['per_page' => 50, 'page' => 3]))
            ->assertOk()->assertJsonPath('data.per_page', 50)->assertJsonPath('data.current_page', 3);
        $this->assertCount(21, $lastPage->json('data.data'));

        $this->sessionAs($admin)->getJson('/employees?per_page=20')->assertUnprocessable();
        $this->sessionAs($admin)->getJson('/employees?per_page=100')->assertUnprocessable();
    }

    public function test_json_directory_sorts_by_allowed_fields_and_breaks_ties_by_id(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Sort administrator', isAdmin: true);
        $ids = [
            '00000000000000000000000001',
            '00000000000000000000000002',
            '00000000000000000000000003',
            '00000000000000000000000004',
        ];
        $this->employee($org, 'C-003', 'Zulu', id: $ids[0], hireDate: '2025-01-01', status: 'active');
        $this->employee($org, 'A-001', 'Alpha', id: $ids[1], hireDate: '2024-01-01', status: 'active');
        $this->employee($org, 'B-002', 'Alpha', id: $ids[2], hireDate: '2026-01-01', status: 'on_leave');
        $this->employee($org, 'D-004', 'Delta', id: $ids[3], hireDate: '2023-01-01', status: 'on_leave');

        $this->assertSortedIds($admin, 'code', 'asc', [$ids[1], $ids[2], $ids[0], $ids[3]]);
        $this->assertSortedIds($admin, 'code', 'desc', [$ids[3], $ids[0], $ids[2], $ids[1]]);
        $this->assertSortedIds($admin, 'name', 'asc', [$ids[1], $ids[2], $ids[3], $ids[0]]);
        $this->assertSortedIds($admin, 'hire_date', 'desc', [$ids[2], $ids[0], $ids[1], $ids[3]]);
        $this->assertSortedIds($admin, 'status', 'asc', [$ids[0], $ids[1], $ids[2], $ids[3]]);
    }

    public function test_supervisor_directory_only_shows_direct_workers_and_omits_private_fields_in_json_and_html(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'HTTP supervisor', permissions: ['employees.read', 'employees.write']);
        $managerProfile = $this->employee($org, 'SUP-001', 'HTTP supervisor', userId: $supervisor);
        $worker = $this->employee($org, 'REPORT-001', 'Assigned worker', managerId: $managerProfile);
        $peerManagerProfile = $this->employee($org, 'SUP-002', 'Another supervisor');
        $peer = $this->employee($org, 'PEER-001', 'Peer worker', managerId: $peerManagerProfile);
        $otherBranch = $this->employee($org, 'BRANCH-001', 'Other branch worker', branch: 'branch_b', department: 'department_b');
        $otherCompany = $this->employee($org, 'COMPANY-001', 'Other company worker', branch: 'branch_c', department: 'department_c');

        $workers = $this->sessionAs($supervisor)->getJson('/employees/workers')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
        self::assertSame([$worker], array_column($workers->json('data.data'), 'id'));

        $this->sessionAs($supervisor)->getJson('/employees/'.$worker)
            ->assertOk()->assertJsonPath('data.id', $worker)
            ->assertJsonMissingPath('data.phone')->assertJsonMissingPath('data.email')->assertJsonMissingPath('data.private_notes')
            ->assertDontSee('PRIVATE-PHONE-REPORT-001')->assertDontSee('PRIVATE-EMAIL-REPORT-001')->assertDontSee('PRIVATE-NOTE-REPORT-001');

        $this->sessionAs($supervisor)->get('/employees/'.$worker.'?locale=en')
            ->assertOk()->assertSee('Assigned worker')
            ->assertDontSee('PRIVATE-PHONE-REPORT-001')->assertDontSee('PRIVATE-EMAIL-REPORT-001')->assertDontSee('PRIVATE-NOTE-REPORT-001');
        $this->sessionAs($supervisor)->get('/employees/create?locale=en')->assertOk();
        $this->sessionAs($supervisor)->get('/employees/'.$worker.'/edit?locale=en')->assertOk();

        foreach ([$peer, $otherBranch, $otherCompany] as $hiddenEmployee) {
            $this->sessionAs($supervisor)->getJson('/employees/'.$hiddenEmployee)->assertNotFound();
        }
        $this->sessionAs($supervisor)->postJson('/employees/'.$peer, ['name' => 'Forged peer edit', 'version' => 0])->assertForbidden();
    }

    public function test_supervisor_can_create_self_managed_non_login_workers_and_edit_only_name_and_role(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'Worker creator', permissions: ['employees.read', 'employees.write']);
        $managerProfile = $this->employee($org, 'CREATE-MGR', 'Worker creator', userId: $supervisor);
        $otherManager = $this->employee($org, 'CREATE-MGR-2', 'Other manager');
        $peer = $this->employee($org, 'CREATE-PEER', 'Other manager report', managerId: $otherManager);
        $otherBranch = $this->employee($org, 'CREATE-BRANCH', 'Other branch worker', branch: 'branch_b', department: 'department_b');
        $otherCompany = $this->employee($org, 'CREATE-COMPANY', 'Other company worker', branch: 'branch_c', department: 'department_c');
        $linkedAccount = $this->user($org, 'Must not link');

        $created = $this->sessionAs($supervisor)->postJson('/employees', $this->profilePayload(
            $org,
            'SUP-CREATED-001',
            'Created by supervisor',
            managerId: $managerProfile,
        ))->assertCreated()
            ->assertJsonPath('data.manager_id', $managerProfile)
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.version', 0);
        $worker = (string) $created->json('data.id');
        $this->assertDatabaseHas('employee_profiles', [
            'id' => $worker,
            'company_id' => $org['company_a'],
            'branch_id' => $org['branch_a'],
            'department_id' => $org['department_a'],
            'manager_id' => $managerProfile,
            'user_id' => null,
        ]);

        $this->sessionAs($supervisor)->postJson('/employees/'.$worker, [
            'name' => 'Updated by supervisor', 'role' => 'Senior technician', 'version' => 0,
        ])->assertOk()->assertJsonPath('data.name', 'Updated by supervisor')->assertJsonPath('data.role', 'Senior technician')->assertJsonPath('data.version', 1);

        $forbiddenChanges = [
            ['user_id' => $linkedAccount],
            ['status' => 'on_leave'],
            ['branch_id' => $org['branch_b'], 'department_id' => $org['department_b'], 'manager_id' => null],
            ['department_id' => $org['department_a2'], 'manager_id' => null],
            ['manager_id' => $otherManager],
        ];
        foreach ($forbiddenChanges as $change) {
            $this->sessionAs($supervisor)->postJson('/employees/'.$worker, $change + ['version' => 1])->assertForbidden();
        }
        $this->assertDatabaseHas('employee_profiles', [
            'id' => $worker,
            'name' => 'Updated by supervisor',
            'role' => 'Senior technician',
            'branch_id' => $org['branch_a'],
            'department_id' => $org['department_a'],
            'manager_id' => $managerProfile,
            'user_id' => null,
            'status' => 'active',
            'version' => 1,
        ]);

        $invalidCreates = [
            ['manager_id' => null],
            ['manager_id' => $otherManager],
            ['user_id' => $linkedAccount],
            ['status' => 'on_leave'],
            ['branch_id' => $org['branch_b'], 'department_id' => $org['department_b']],
            ['branch_id' => $org['branch_c'], 'department_id' => $org['department_c']],
        ];
        foreach ($invalidCreates as $index => $change) {
            $code = 'SUP-REJECTED-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $response = $this->sessionAs($supervisor)->postJson('/employees', array_replace(
                $this->profilePayload($org, $code, 'Must not be created', managerId: $managerProfile),
                $change,
            ));
            self::assertContains($response->getStatusCode(), [403, 422]);
            $this->assertDatabaseMissing('employee_profiles', ['code' => $code]);
        }

        foreach ([$peer, $otherBranch, $otherCompany] as $hiddenEmployee) {
            $response = $this->sessionAs($supervisor)->postJson('/employees/'.$hiddenEmployee, [
                'name' => 'Forged out-of-scope edit', 'version' => 0,
            ]);
            self::assertContains($response->getStatusCode(), [403, 404]);
        }
    }

    public function test_admin_can_create_and_change_versioned_account_links_and_identity_updates_revoke_old_sessions(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Account-link administrator', isAdmin: true);
        $linkedAccount = $this->user($org, 'Linked employee account', permissions: ['employees.read']);
        $replacementAccount = $this->user($org, 'Replacement employee account', permissions: ['employees.read']);
        $staleAccount = $this->user($org, 'Stale employee account');

        $created = $this->sessionAs($admin)->postJson('/employees', $this->profilePayload(
            $org,
            'ADMIN-LINK-001',
            'Linked employee',
            userId: $linkedAccount,
        ))->assertCreated()->assertJsonPath('data.user_id', $linkedAccount)->assertJsonPath('data.version', 0);
        $employee = (string) $created->json('data.id');

        $this->sessionAs($admin)->postJson('/employees/'.$employee, [
            'user_id' => $replacementAccount,
            'version' => 0,
        ])->assertOk()->assertJsonPath('data.user_id', $replacementAccount)->assertJsonPath('data.version', 1);

        $this->sessionAs($admin)->postJson('/employees/'.$employee, [
            'user_id' => $staleAccount,
            'version' => 0,
        ])->assertStatus(409);
        $this->assertDatabaseHas('employee_profiles', ['id' => $employee, 'user_id' => $replacementAccount, 'version' => 1]);

        $staleSession = $this->sessionFor($replacementAccount);
        $account = DB::table('access_users')->where('id', $replacementAccount)->first();
        $this->sessionAs($admin)->postJson('/access/users/'.$replacementAccount, [
            'name' => (string) $account->name,
            'email' => (string) $account->email,
            'password' => 'updated-password-for-session',
            'branch_id' => $org['branch_a'],
            'department_id' => $org['department_a'],
            'version' => 0,
        ])->assertOk();
        $this->assertDatabaseHas('access_users', ['id' => $replacementAccount, 'session_version' => 1]);
        $this->withSession($staleSession)->getJson('/employees/'.$employee)->assertUnauthorized();
    }

    public function test_admin_can_edit_historical_terminated_profile_without_allowing_termination_transitions(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Historical profile administrator', isAdmin: true);
        $terminated = $this->employee($org, 'HIST-TERM-001', 'Historical terminated employee', status: 'terminated');

        $this->sessionAs($admin)->postJson('/employees/'.$terminated, [
            'name' => 'Corrected historical employee',
            'status' => 'terminated',
            'version' => 0,
        ])->assertOk()->assertJsonPath('data.name', 'Corrected historical employee')
            ->assertJsonPath('data.status', 'terminated');
        $this->assertDatabaseHas('employee_profiles', [
            'id' => $terminated, 'name' => 'Corrected historical employee', 'status' => 'terminated',
        ]);

        $active = $this->employee($org, 'HIST-ACTIVE-001', 'Still active employee');
        $this->sessionAs($admin)->postJson('/employees/'.$active, [
            'status' => 'terminated', 'version' => 0,
        ])->assertUnprocessable();
        $this->sessionAs($admin)->postJson('/employees', array_replace(
            $this->profilePayload($org, 'HIST-CREATE-TERM-001', 'Must remain uncreated'),
            ['status' => 'terminated'],
        ))->assertUnprocessable();
        $this->assertDatabaseMissing('employee_profiles', ['code' => 'HIST-CREATE-TERM-001']);
    }

    public function test_writer_without_supervisor_profile_sees_no_add_worker_action_and_cannot_open_create(): void
    {
        $org = $this->organization();
        $writerWithoutProfile = $this->user($org, 'Writer without employee profile', permissions: ['employees.read', 'employees.write']);

        $this->sessionAs($writerWithoutProfile)->get('/employees/workers?locale=en')
            ->assertOk()->assertDontSee('Add worker');
        $this->sessionAs($writerWithoutProfile)->get('/employees/create?locale=en')->assertForbidden();
    }

    public function test_non_root_manager_cannot_unlink_or_replace_out_of_branch_account_from_employee_form(): void
    {
        $org = $this->organization();
        $manager = $this->user($org, 'Branch A employee manager', permissions: [
            'employees.read', 'employees.write', 'employees.manage_all', 'users.read',
        ]);
        $linkedAccount = $this->user($org, 'Current branch B account', branch: 'branch_b', department: 'department_b');
        $replacementAccount = $this->user($org, 'Other branch B account', branch: 'branch_b', department: 'department_b');
        $employee = $this->employee($org, 'BRANCH-B-LINKED', 'Branch B linked employee', branch: 'branch_b', department: 'department_b', userId: $linkedAccount);

        $this->sessionAs($manager)->get('/employees/'.$employee.'/edit?locale=en')
            ->assertOk()->assertDontSee('name="user_id"', false);
        $this->sessionAs($manager)->postJson('/employees/'.$employee, [
            'name' => 'Updated without changing account', 'version' => 0,
        ])->assertOk();
        $this->assertDatabaseHas('employee_profiles', ['id' => $employee, 'user_id' => $linkedAccount]);

        foreach ([null, $replacementAccount] as $attemptedLink) {
            $this->sessionAs($manager)->postJson('/employees/'.$employee, [
                'user_id' => $attemptedLink,
                'version' => 1,
            ])->assertForbidden();
            $this->assertDatabaseHas('employee_profiles', ['id' => $employee, 'user_id' => $linkedAccount]);
        }

        $root = $this->user($org, 'Root cross-branch account linker', isAdmin: true);
        $this->sessionAs($root)->get('/employees/'.$employee.'/edit?locale=en')
            ->assertOk()->assertSee('name="user_id"', false);
        $this->sessionAs($root)->postJson('/employees/'.$employee, [
            'user_id' => $replacementAccount,
            'version' => 1,
        ])->assertOk()->assertJsonPath('data.user_id', $replacementAccount);
        $this->assertDatabaseHas('employee_profiles', ['id' => $employee, 'user_id' => $replacementAccount]);
    }

    public function test_employee_form_options_exclude_inactive_or_linked_records_and_keep_selected_values_beyond_first_page(): void
    {
        $org = $this->organization();
        $manager = $this->user($org, 'Option administrator', permissions: [
            'employees.read', 'employees.write', 'employees.manage_all', 'users.read',
        ], isAdmin: true);

        $inactiveBranch = $this->id();
        DB::table('access_branches')->insert([
            'id' => $inactiveBranch, 'company_id' => $org['company_a'], 'name' => 'A00-INACTIVE-BRANCH-MARKER',
            'code' => 'INACTIVE-BRANCH', 'active' => false, 'version' => 0,
        ]);
        $inactiveDepartment = $this->id();
        DB::table('access_departments')->insert([
            'id' => $inactiveDepartment, 'company_id' => $org['company_a'], 'branch_id' => $org['branch_a'],
            'name' => 'A00-INACTIVE-DEPARTMENT-MARKER', 'code' => 'INACTIVE-DEPARTMENT', 'active' => false, 'version' => 0,
        ]);
        $inactiveAccount = $this->user($org, 'A00-INACTIVE-ACCOUNT-MARKER');
        DB::table('access_users')->where('id', $inactiveAccount)->update(['active' => false]);

        $linkedAccount = $this->user($org, 'A01-LINKED-ACCOUNT-MARKER');
        $this->employee($org, 'OPTION-LINKED-ROW', 'Already linked elsewhere', userId: $linkedAccount);

        for ($index = 0; $index < 26; $index++) {
            $suffix = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $branchId = $this->id();
            DB::table('access_branches')->insert([
                'id' => $branchId, 'company_id' => $org['company_a'], 'name' => 'AAA-OPTION-BRANCH-'.$suffix,
                'code' => 'OPTION-BR-'.$suffix, 'active' => true, 'version' => 0,
            ]);
            DB::table('access_departments')->insert([
                'id' => $this->id(), 'company_id' => $org['company_a'], 'branch_id' => $branchId,
                'name' => 'AAA-OPTION-DEPARTMENT-'.$suffix, 'code' => 'OPTION-DEPT-'.$suffix, 'active' => true, 'version' => 0,
            ]);
            $this->user($org, 'AAA-OPTION-ACCOUNT-'.$suffix);
        }

        $selectedBranch = $this->id();
        DB::table('access_branches')->insert([
            'id' => $selectedBranch, 'company_id' => $org['company_a'], 'name' => 'ZZZ-CURRENT-BRANCH-MARKER',
            'code' => 'CURRENT-BRANCH', 'active' => true, 'version' => 0,
        ]);
        $org['branch_selected'] = $selectedBranch;
        $selectedDepartment = $this->id();
        DB::table('access_departments')->insert([
            'id' => $selectedDepartment, 'company_id' => $org['company_a'], 'branch_id' => $selectedBranch,
            'name' => 'ZZZ-CURRENT-DEPARTMENT-MARKER', 'code' => 'CURRENT-DEPARTMENT', 'active' => true, 'version' => 0,
        ]);
        $org['department_selected'] = $selectedDepartment;
        $selectedAccount = $this->user($org, 'ZZZ-CURRENT-ACCOUNT-MARKER', branch: 'branch_selected', department: 'department_selected');
        $employee = $this->employee($org, 'OPTION-CURRENT-ROW', 'Current historical selection', branch: 'branch_selected', department: 'department_selected', userId: $selectedAccount);

        $create = $this->sessionAs($manager)->get('/employees/create?locale=en')->assertOk();
        foreach ([$inactiveBranch, $inactiveDepartment, $inactiveAccount, $linkedAccount, $selectedBranch, $selectedDepartment, $selectedAccount] as $unavailableId) {
            $create->assertDontSee($unavailableId);
        }
        $create->assertDontSee('A00-INACTIVE-BRANCH-MARKER')
            ->assertDontSee('A00-INACTIVE-DEPARTMENT-MARKER')
            ->assertDontSee('A00-INACTIVE-ACCOUNT-MARKER')
            ->assertDontSee('A01-LINKED-ACCOUNT-MARKER');

        $edit = $this->sessionAs($manager)->get('/employees/'.$employee.'/edit?locale=en')->assertOk();
        foreach ([$selectedBranch, $selectedDepartment, $selectedAccount] as $selectedId) {
            $edit->assertSee('value="'.$selectedId.'" selected', false);
        }
        $edit->assertSee('ZZZ-CURRENT-BRANCH-MARKER')->assertSee('ZZZ-CURRENT-DEPARTMENT-MARKER')->assertSee('ZZZ-CURRENT-ACCOUNT-MARKER');
    }

    public function test_employee_account_linkage_is_masked_without_users_read_and_worker_json_keeps_null_key(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'Link privacy supervisor', permissions: ['employees.read']);
        $managerProfile = $this->employee($org, 'LINK-PRIVACY-MGR', 'Link privacy supervisor', userId: $supervisor);
        $linkedAccount = $this->user($org, 'FOREIGN-ACCOUNT-ID-MUST-NOT-LEAK');
        $worker = $this->employee($org, 'LINK-PRIVACY-WORKER', 'Linked worker privacy check', managerId: $managerProfile, userId: $linkedAccount);

        $workers = $this->sessionAs($supervisor)->getJson('/employees/workers')->assertOk();
        self::assertArrayHasKey('user_id', $workers->json('data.data.0'));
        $workers->assertJsonPath('data.data.0.id', $worker)->assertJsonPath('data.data.0.user_id', null);
        $this->sessionAs($supervisor)->getJson('/employees/'.$worker)
            ->assertOk()->assertJsonPath('data.user_id', null)->assertJsonMissingPath('data.user_name')
            ->assertDontSee($linkedAccount);
        $this->sessionAs($supervisor)->get('/employees/'.$worker.'?locale=en')
            ->assertOk()->assertSee('Linked worker privacy check')
            ->assertDontSee('Login account linked')->assertDontSee($linkedAccount);
    }

    public function test_evidence_route_records_only_for_an_visible_worker_and_existing_task(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'Evidence supervisor', permissions: ['employees.read', 'employees.evidence.write', 'tasks.read']);
        $managerProfile = $this->employee($org, 'EVIDENCE-MGR', 'Evidence supervisor', userId: $supervisor);
        $worker = $this->employee($org, 'EVIDENCE-WORKER', 'Evidence worker', managerId: $managerProfile);
        $task = $this->task($org, $supervisor);

        $response = $this->sessionAs($supervisor)->postJson('/employees/'.$worker.'/evidence', [
            'job_id' => $task,
            'note' => 'Synthetic evidence note',
        ])->assertCreated()->assertJsonPath('data.employee_id', $worker)->assertJsonPath('data.actor_id', $supervisor);

        $this->assertDatabaseHas('employee_evidence', [
            'id' => $response->json('data.id'),
            'company_id' => $org['company_a'],
            'employee_id' => $worker,
            'job_id' => $task,
            'actor_id' => $supervisor,
        ]);
    }

    public function test_evidence_form_uses_only_an_exact_authorized_task_and_redirects_to_its_tab(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'Exact task selector', permissions: [
            'employees.read', 'employees.evidence.write', 'tasks.read',
        ]);
        $managerProfile = $this->employee($org, 'EXACT-TASK-MGR', 'Exact task selector', userId: $supervisor);
        $worker = $this->employee($org, 'EXACT-TASK-WORKER', 'Exact task worker', managerId: $managerProfile);
        $selectedTask = $this->task($org, $supervisor, 'Selected authorized task marker');
        $unrelatedTask = $this->task($org, $supervisor, 'Unrelated authorized task marker');
        $otherOwner = $this->user($org, 'Other task owner');
        $unavailableTask = $this->task($org, $otherOwner, 'Unavailable task marker');

        $this->sessionAs($supervisor)->get('/employees/'.$worker.'?locale=en')
            ->assertOk()->assertDontSee('Selected authorized task marker')->assertDontSee('Unrelated authorized task marker');
        $this->sessionAs($supervisor)->get('/employees/'.$worker.'?locale=en&tab=evidence&task_id='.$selectedTask)
            ->assertOk()->assertSee('Selected authorized task marker')
            ->assertDontSee('Unrelated authorized task marker')->assertDontSee('Unavailable task marker')
            ->assertSee('value="'.$selectedTask.'" selected', false);

        $unavailable = $this->sessionAs($supervisor)->get('/employees/'.$worker.'?locale=en&tab=evidence&task_id='.$unavailableTask);
        self::assertContains($unavailable->getStatusCode(), [403, 404]);

        $this->sessionAs($supervisor)->post('/employees/'.$worker.'/evidence?locale=en&tab=evidence', [
            'job_id' => $selectedTask,
            'note' => 'Evidence redirect marker',
        ])->assertRedirect('/employees/'.$worker.'?locale=en&tab=evidence')->assertSessionHas('status');
    }

    public function test_employee_overview_and_evidence_history_avoid_loading_task_detail_tables(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'Task history query observer', permissions: [
            'employees.read', 'employees.evidence.write', 'tasks.read',
        ]);
        $managerProfile = $this->employee($org, 'TASK-QUERY-MGR', 'Task history query observer', userId: $supervisor);
        $worker = $this->employee($org, 'TASK-QUERY-WORKER', 'Task history query worker', managerId: $managerProfile);
        $task = $this->task($org, $supervisor, 'Task detail query sentinel');
        $this->sessionAs($supervisor)->postJson('/employees/'.$worker.'/evidence', [
            'job_id' => $task, 'note' => 'Query observer evidence entry',
        ])->assertCreated();

        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->sessionAs($supervisor)->get('/employees/'.$worker.'?locale=en')
            ->assertOk()->assertDontSee('Task detail query sentinel');
        $this->assertNoTaskDetailQueries($queries);

        $queries = [];
        $this->sessionAs($supervisor)->get('/employees/'.$worker.'?locale=en&tab=evidence&task_id='.$task)
            ->assertOk()->assertSee('Task detail query sentinel')->assertSee('Query observer evidence entry');
        $this->assertNoTaskDetailQueries($queries);
    }

    public function test_employee_history_does_not_disclose_evidence_to_a_viewer_without_task_read(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'History supervisor', permissions: [
            'employees.read', 'employees.evidence.write', 'tasks.read',
        ]);
        $managerProfile = $this->employee($org, 'HISTORY-MGR', 'History supervisor', userId: $supervisor);
        $worker = $this->employee($org, 'HISTORY-WORKER', 'History worker', managerId: $managerProfile);
        $task = $this->task($org, $supervisor);
        $note = 'PRIVATE-EVIDENCE-NOTE-WITHOUT-TASK-GRANT';

        $this->sessionAs($supervisor)->postJson('/employees/'.$worker.'/evidence', [
            'job_id' => $task,
            'note' => $note,
        ])->assertCreated();
        $this->sessionAs($supervisor)->get('/employees/'.$worker.'?locale=en&tab=evidence')
            ->assertOk()->assertSee($note)->assertSee($task);
        $this->revokePermission($supervisor, 'tasks.read');

        $this->sessionAs($supervisor)->get('/employees/'.$worker.'?locale=en&tab=evidence')
            ->assertOk()->assertSee('History worker')
            ->assertDontSee($note)->assertDontSee($task);
    }

    public function test_employee_history_hides_evidence_for_a_task_outside_the_viewers_task_scope(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'Task owner supervisor', permissions: [
            'employees.read', 'employees.evidence.write', 'tasks.read',
        ]);
        $managerProfile = $this->employee($org, 'TASK-HISTORY-MGR', 'Task owner supervisor', userId: $supervisor);
        $worker = $this->employee($org, 'TASK-HISTORY-WORKER', 'Task history worker', managerId: $managerProfile);
        $task = $this->task($org, $supervisor);
        $note = 'PRIVATE-CROSS-TASK-EVIDENCE-NOTE';
        $this->sessionAs($supervisor)->postJson('/employees/'.$worker.'/evidence', [
            'job_id' => $task,
            'note' => $note,
        ])->assertCreated();
        $this->sessionAs($supervisor)->get('/employees/'.$worker.'?locale=en&tab=evidence')
            ->assertOk()->assertSee($note)->assertSee($task);

        $companyAdmin = $this->user($org, 'Task scope administrator', isAdmin: true);
        $this->sessionAs($companyAdmin)->getJson('/tasks/'.$task)->assertNotFound();
        $this->sessionAs($companyAdmin)->get('/employees/'.$worker.'?locale=en&tab=evidence')
            ->assertOk()->assertSee('Task history worker')
            ->assertDontSee($note)->assertDontSee($task);
    }

    private function assertSortedIds(string $admin, string $field, string $direction, array $expected): void
    {
        $response = $this->sessionAs($admin)->getJson('/employees?'.http_build_query([
            'sort' => $field,
            'direction' => $direction,
            'per_page' => 50,
        ]))->assertOk();

        self::assertSame($expected, array_column($response->json('data.data'), 'id'));
    }

    private function assertPaginatorQuery(TestResponse $response, array $expected): void
    {
        $next = (string) $response->json('data.next_page_url');
        parse_str((string) parse_url($next, PHP_URL_QUERY), $query);

        foreach ($expected as $key => $value) {
            self::assertSame((string) $value, (string) ($query[$key] ?? null), 'Paginator did not preserve '.$key.'.');
        }
    }

    /** @param list<string> $queries */
    private function assertNoTaskDetailQueries(array $queries): void
    {
        $detailQueries = array_values(array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'tasks_cycle_evidence') || str_contains($sql, 'tasks_cycle_audit')));

        self::assertSame([], $detailQueries, 'Employee pages should not load task evidence or audit details.');
    }

    /** @return array<string, string> */
    private function organization(): array
    {
        $companyA = $this->id();
        $companyB = $this->id();
        DB::table('access_companies')->insert([
            ['id' => $companyA, 'name' => 'Employee HTTP Company A', 'version' => 0],
            ['id' => $companyB, 'name' => 'Employee HTTP Company B', 'version' => 0],
        ]);

        $ids = [
            'branch_a' => $this->id(),
            'branch_b' => $this->id(),
            'branch_c' => $this->id(),
            'department_a' => $this->id(),
            'department_a2' => $this->id(),
            'department_b' => $this->id(),
            'department_c' => $this->id(),
        ];
        foreach ([
            [$ids['branch_a'], $companyA, 'HTTP-BR-A'],
            [$ids['branch_b'], $companyA, 'HTTP-BR-B'],
            [$ids['branch_c'], $companyB, 'HTTP-BR-C'],
        ] as [$branchId, $companyId, $code]) {
            DB::table('access_branches')->insert([
                'id' => $branchId,
                'company_id' => $companyId,
                'name' => $code,
                'code' => $code,
                'active' => true,
                'version' => 0,
            ]);
        }
        foreach ([
            [$ids['department_a'], $companyA, $ids['branch_a'], 'HTTP-DEPT-A'],
            [$ids['department_a2'], $companyA, $ids['branch_a'], 'HTTP-DEPT-A2'],
            [$ids['department_b'], $companyA, $ids['branch_b'], 'HTTP-DEPT-B'],
            [$ids['department_c'], $companyB, $ids['branch_c'], 'HTTP-DEPT-C'],
        ] as [$departmentId, $companyId, $branchId, $code]) {
            DB::table('access_departments')->insert([
                'id' => $departmentId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => $code,
                'code' => $code,
                'active' => true,
                'version' => 0,
            ]);
        }

        return ['company_a' => $companyA, 'company_b' => $companyB] + $ids;
    }

    /** @param list<string> $permissions */
    private function user(
        array $org,
        string $name,
        string $branch = 'branch_a',
        string $department = 'department_a',
        array $permissions = [],
        bool $isAdmin = false,
    ): string {
        $id = $this->id();
        $companyId = $branch === 'branch_c' ? $org['company_b'] : $org['company_a'];
        DB::table('access_users')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'branch_id' => $org[$branch],
            'department_id' => $org[$department],
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.$id.'@example.test',
            'password' => Hash::make('employee-http-test-password'),
            'active' => true,
            'is_admin' => $isAdmin,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($permissions !== []) {
            $roleId = $this->id();
            DB::table('access_roles')->insert([
                'id' => $roleId,
                'company_id' => $companyId,
                'name' => 'Employee HTTP role '.$roleId,
                'version' => 0,
            ]);
            foreach ($permissions as $permission) {
                DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
            }
            DB::table('access_user_roles')->insert(['user_id' => $id, 'role_id' => $roleId]);
        }

        return $id;
    }

    private function employee(
        array $org,
        string $code,
        string $name,
        string $branch = 'branch_a',
        string $department = 'department_a',
        ?string $managerId = null,
        ?string $userId = null,
        string $role = 'Technician',
        string $status = 'active',
        string $hireDate = '2026-01-01',
        ?string $id = null,
    ): string {
        $id ??= $this->id();
        DB::table('employee_profiles')->insert([
            'id' => $id,
            'company_id' => $branch === 'branch_c' ? $org['company_b'] : $org['company_a'],
            'branch_id' => $org[$branch],
            'department_id' => $org[$department],
            'manager_id' => $managerId,
            'user_id' => $userId,
            'code' => $code,
            'name' => $name,
            'role' => $role,
            'hire_date' => $hireDate,
            'status' => $status,
            'phone' => 'PRIVATE-PHONE-'.$code,
            'email' => 'PRIVATE-EMAIL-'.$code.'@example.test',
            'private_notes' => 'PRIVATE-NOTE-'.$code,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @return array<string, mixed> */
    private function profilePayload(
        array $org,
        string $code,
        string $name,
        ?string $managerId = null,
        ?string $userId = null,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'role' => 'Technician',
            'branch_id' => $org['branch_a'],
            'department_id' => $org['department_a'],
            'manager_id' => $managerId,
            'user_id' => $userId,
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ];
    }

    private function task(array $org, string $ownerId, string $title = 'Employee HTTP evidence task'): string
    {
        $id = $this->id();
        DB::table('tasks_cycle')->insert([
            'id' => $id,
            'entity_id' => $org['company_a'],
            'department_id' => $org['department_a'],
            'owner_id' => $ownerId,
            'assignee_id' => $ownerId,
            'title' => $title,
            'priority' => 'normal',
            'due_at' => null,
            'status' => 'pending',
            'version' => 0,
            'create_key_hash' => hash('sha256', $id),
            'create_payload_hash' => hash('sha256', $title),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function sessionAs(string $userId): self
    {
        return $this->withSession($this->sessionFor($userId));
    }

    private function revokePermission(string $userId, string $permission): void
    {
        $roleIds = DB::table('access_user_roles')->where('user_id', $userId)->pluck('role_id');
        DB::table('access_role_permissions')->whereIn('role_id', $roleIds)->where('permission', $permission)->delete();
    }

    /** @return array{access_user_id: string, access_session_version: int} */
    private function sessionFor(string $userId): array
    {
        return [
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ];
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }
}

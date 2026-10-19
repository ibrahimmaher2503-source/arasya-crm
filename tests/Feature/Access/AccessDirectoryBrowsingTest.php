<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AccessDirectoryBrowsingTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-92!';

    public function test_html_user_directory_paginates_filtered_rows_inside_the_actor_scope(): void
    {
        $world = $this->organization('Directory Company');
        $otherCompany = $this->organization('Foreign Directory Company');
        $this->user($world, 'Owner', 'owner@example.test', $world['branch_a'], $world['department_a'], true);
        $manager = $this->user($world, 'Branch Manager', 'manager@example.test', $world['branch_a'], $world['department_a']);
        $managerRole = $this->role($world, 'Branch Manager Role', ['users.read']);
        DB::table('access_user_roles')->insert(['user_id' => $manager['id'], 'role_id' => $managerRole]);

        for ($index = 1; $index <= 11; $index++) {
            $suffix = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $this->user($world, "Visible User {$suffix}", "visible-{$suffix}@example.test", $world['branch_a'], $world['department_a']);
        }
        $this->user($world, 'Inactive User', 'inactive@example.test', $world['branch_a'], $world['department_a'], active: false);
        $this->user($world, 'Visible User 99', 'other-branch@example.test', $world['branch_b'], $world['department_b']);
        $this->user($otherCompany, 'Visible User 98', 'foreign@example.test', $otherCompany['branch_a'], $otherCompany['department_a']);

        $this->loginAs($manager['email']);

        $response = $this->get('/access/users?'.http_build_query([
            'q' => 'Visible User',
            'status' => 'active',
            'branch_id' => $world['branch_a'],
            'department_id' => $world['department_a'],
            'per_page' => 10,
            'page' => 2,
            'sort' => 'name',
            'direction' => 'asc',
        ]))->assertOk();

        $users = $response->viewData('users');
        self::assertInstanceOf(LengthAwarePaginator::class, $users);
        self::assertSame(11, $users->total());
        self::assertSame(10, $users->perPage());
        self::assertSame(2, $users->currentPage());
        self::assertSame(['Visible User 11'], array_column($users->items(), 'name'));

        $summary = $response->viewData('summary');
        self::assertSame(14, $summary['total']);
        self::assertSame(13, $summary['active']);
        self::assertSame(1, $summary['inactive']);
    }

    public function test_html_user_directory_filters_and_sorts_rows_and_bounds_page_size(): void
    {
        $world = $this->organization('Filter Company');
        $admin = $this->user($world, 'Filter Administrator', 'filter-admin@example.test', $world['branch_a'], $world['department_a'], true);
        $roleId = $this->role($world, 'Filterable Role', ['users.read']);
        $otherDepartmentInBranchA = (string) Str::ulid();
        DB::table('access_departments')->insert([
            'id' => $otherDepartmentInBranchA,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_a'],
            'name' => 'Filter Other Department',
            'code' => 'filter-other-department',
            'active' => true,
            'version' => 0,
        ]);
        $matchingUsers = [
            $this->user($world, 'Filter Match Alpha', 'filter-alpha@example.test', $world['branch_a'], $world['department_a']),
            $this->user($world, 'Filter Match Bravo', 'filter-bravo@example.test', $world['branch_a'], $world['department_a']),
        ];
        $this->user($world, 'Filter Match Inactive', 'filter-inactive@example.test', $world['branch_a'], $world['department_a'], active: false);
        $this->user($world, 'Filter Match Other Branch', 'filter-other-branch@example.test', $world['branch_b'], $world['department_b']);
        $this->user($world, 'Filter Match Other Department', 'filter-other-department@example.test', $world['branch_a'], $otherDepartmentInBranchA);
        foreach ($matchingUsers as $matchingUser) {
            DB::table('access_user_roles')->insert(['user_id' => $matchingUser['id'], 'role_id' => $roleId]);
        }

        $this->loginAs($admin['email']);

        $response = $this->get('/access/users?'.http_build_query([
            'q' => 'Filter Match',
            'status' => 'active',
            'branch_id' => $world['branch_a'],
            'department_id' => $world['department_a'],
            'role_id' => $roleId,
            'sort' => 'name',
            'direction' => 'desc',
            'per_page' => 10,
        ]))->assertOk();
        $users = $response->viewData('users');
        self::assertInstanceOf(LengthAwarePaginator::class, $users);
        self::assertSame(2, $users->total());
        self::assertSame(['Filter Match Bravo', 'Filter Match Alpha'], array_column($users->items(), 'name'));

        foreach ([null => 10, 10 => 10, 25 => 25, 50 => 50] as $requested => $expected) {
            $query = $requested === '' ? '' : '?per_page='.$requested;
            $page = $this->get('/access/users'.$query)->assertOk()->viewData('users');
            self::assertInstanceOf(LengthAwarePaginator::class, $page);
            self::assertSame($expected, $page->perPage());
        }

        $invalid = $this->from('/access/users')->get('/access/users?per_page=11');
        self::assertContains($invalid->status(), [200, 302, 422]);
        if ($invalid->status() === 200) {
            $bounded = $invalid->viewData('users');
            self::assertInstanceOf(LengthAwarePaginator::class, $bounded);
            self::assertContains($bounded->perPage(), [10, 25, 50]);
        } elseif ($invalid->status() === 302) {
            $invalid->assertSessionHasErrors('per_page');
        }

        foreach ([['q[]' => 'Filter Match', 'field' => 'q'], ['sort' => 'name desc; DROP TABLE access_users', 'field' => 'sort']] as $invalidFilter) {
            $field = $invalidFilter['field'];
            unset($invalidFilter['field']);
            $validation = $this->from('/access/users')->get('/access/users?'.http_build_query($invalidFilter));
            self::assertSame(302, $validation->status());
            $validation->assertSessionHasErrors($field);
        }
    }

    public function test_large_user_directory_uses_a_bounded_stable_page_and_company_scoped_totals(): void
    {
        $world = $this->organization('Large Directory Company');
        $foreignCompany = $this->organization('Outside Large Directory Company');
        $admin = $this->user($world, 'Large Directory Administrator', 'large-directory-admin@example.test', $world['branch_a'], $world['department_a'], true);
        $this->user($foreignCompany, 'Synthetic User', 'outside-synthetic-user@example.test', $foreignCompany['branch_a'], $foreignCompany['department_a']);

        $password = Hash::make(self::PASSWORD);
        $timestamp = now()->toDateTimeString();
        $rows = [];
        $ids = [];
        for ($index = 0; $index < 1001; $index++) {
            $id = (string) Str::ulid();
            $ids[] = $id;
            $rows[] = [
                'id' => $id,
                'company_id' => $world['company_id'],
                'branch_id' => $world['branch_a'],
                'department_id' => $world['department_a'],
                'name' => 'Synthetic User',
                'email' => "synthetic-user-{$index}@example.test",
                'password' => $password,
                'active' => true,
                'is_admin' => false,
                'session_version' => 0,
                'version' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }
        foreach (array_chunk($rows, 200) as $batch) {
            DB::table('access_users')->insert($batch);
        }

        $this->loginAs($admin['email']);
        $selections = [];
        DB::listen(static function (QueryExecuted $query) use (&$selections): void {
            $sql = strtolower($query->sql);
            if (preg_match('/from\s+[`"]?access_users[`"]?\s+as\s+[`"]?users[`"]?/i', $sql) === 1
                && preg_match('/\blimit\s+\d+/i', $sql) === 1) {
                $selections[] = $query;
            }
        });

        $response = $this->get('/access/users?'.http_build_query([
            'q' => 'Synthetic User',
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 10,
            'page' => 51,
        ]))->assertOk();
        $users = $response->viewData('users');
        self::assertInstanceOf(LengthAwarePaginator::class, $users);
        self::assertSame(1001, $users->total());
        self::assertSame(10, $users->perPage());
        self::assertSame(51, $users->currentPage());
        self::assertCount(10, $users->items());

        sort($ids, SORT_STRING);
        self::assertSame(array_slice($ids, 500, 10), array_column($users->items(), 'id'));

        $summary = $response->viewData('summary');
        self::assertSame(1002, $summary['total']);
        self::assertSame(1002, $summary['active']);
        self::assertSame(0, $summary['inactive']);

        self::assertCount(1, $selections, 'The directory should issue one bounded row-selection query.');
        $selection = $selections[0];
        self::assertMatchesRegularExpression('/\blimit\s+10\b/i', $selection->sql);
        self::assertMatchesRegularExpression('/\boffset\s+500\b/i', $selection->sql);
    }

    public function test_audit_directory_recursively_redacts_sensitive_nested_metadata(): void
    {
        $world = $this->organization('Redacted Audit Company');
        $admin = $this->user($world, 'Redacted Audit Administrator', 'redacted-audit@example.test', $world['branch_a'], $world['department_a'], true);
        $metadata = [
            'safe_marker' => 'VISIBLE_AUDIT_MARKER',
            'password' => 'HIDDEN_PASSWORD_MARKER',
            'nested' => [
                'api_token' => 'HIDDEN_TOKEN_MARKER',
                'safe_marker' => 'VISIBLE_NESTED_MARKER',
                'deeper' => [
                    'client_secret' => 'HIDDEN_SECRET_MARKER',
                    'safe_marker' => 'VISIBLE_DEEP_MARKER',
                ],
            ],
            'items' => [[
                'authorization' => 'HIDDEN_AUTHORIZATION_MARKER',
                'safe_marker' => 'VISIBLE_LIST_MARKER',
            ]],
        ];
        DB::table('access_audit')->insert([
            'id' => (string) Str::ulid(),
            'company_id' => $world['company_id'],
            'actor_id' => $admin['id'],
            'action' => 'nested.audit.redaction',
            'subject_id' => $admin['id'],
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now()->toDateTimeString(),
        ]);
        $this->loginAs($admin['email']);

        $response = $this->get('/access/audit?action=nested.audit.redaction')->assertOk();
        $entries = $response->viewData('entries');
        self::assertInstanceOf(LengthAwarePaginator::class, $entries);
        self::assertSame(1, $entries->total());
        self::assertSame([
            'safe_marker' => 'VISIBLE_AUDIT_MARKER',
            'nested' => [
                'safe_marker' => 'VISIBLE_NESTED_MARKER',
                'deeper' => ['safe_marker' => 'VISIBLE_DEEP_MARKER'],
            ],
            'items' => [['safe_marker' => 'VISIBLE_LIST_MARKER']],
        ], $entries->items()[0]['metadata']);
        foreach (['VISIBLE_AUDIT_MARKER', 'VISIBLE_NESTED_MARKER', 'VISIBLE_DEEP_MARKER', 'VISIBLE_LIST_MARKER'] as $marker) {
            $response->assertSee($marker, false);
        }
        foreach (['HIDDEN_PASSWORD_MARKER', 'HIDDEN_TOKEN_MARKER', 'HIDDEN_SECRET_MARKER', 'HIDDEN_AUTHORIZATION_MARKER'] as $marker) {
            $response->assertDontSee($marker);
        }
    }

    public function test_branch_scoped_role_counts_exclude_other_branch_direct_and_inherited_assignments(): void
    {
        $world = $this->organization('Branch Role Scope Company');
        $manager = $this->user($world, 'Branch Role Manager', 'branch-role-manager@example.test', $world['branch_a'], $world['department_a']);
        $grantRole = $this->role($world, 'Access Grant Role', ['roles.read', 'users.read']);
        DB::table('access_user_roles')->insert(['user_id' => $manager['id'], 'role_id' => $grantRole]);

        for ($index = 1; $index <= 12; $index++) {
            $suffix = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $this->role($world, "Visible Role {$suffix}", ['users.read']);
        }

        $inheritedRole = $this->role($world, 'Inherited Department Role', ['users.read']);
        DB::table('access_department_roles')->insert([
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_a'],
            'department_id' => $world['department_a'],
            'role_id' => $inheritedRole,
        ]);

        $otherBranchUser = $this->user($world, 'Other Branch User', 'other-branch-role-user@example.test', $world['branch_b'], $world['department_b']);
        $hiddenDirectRole = $this->role($world, 'ZZ Hidden Direct Role', ['users.read']);
        DB::table('access_user_roles')->insert(['user_id' => $otherBranchUser['id'], 'role_id' => $hiddenDirectRole]);

        $hiddenDepartmentRole = $this->role($world, 'ZZ Hidden Department Role', ['users.read']);
        DB::table('access_department_roles')->insert([
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_b'],
            'department_id' => $world['department_b'],
            'role_id' => $hiddenDepartmentRole,
        ]);

        $this->loginAs($manager['email']);
        $response = $this->get('/access/roles?'.http_build_query([
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 10,
            'page' => 2,
        ]))->assertOk();
        $roles = $response->viewData('roles');
        self::assertInstanceOf(LengthAwarePaginator::class, $roles);
        self::assertSame(14, $roles->total());
        self::assertSame(14, $response->viewData('summary')['total']);
        self::assertSame(['Visible Role 09', 'Visible Role 10', 'Visible Role 11', 'Visible Role 12'], array_column($roles->items(), 'name'));

        $inheritedResponse = $this->get('/access/roles?role_id='.$inheritedRole)->assertOk();
        $inherited = $inheritedResponse->viewData('roles');
        self::assertSame(1, $inherited->total());
        self::assertSame('Inherited Department Role', $inherited->items()[0]['name']);
        self::assertSame(14, $inheritedResponse->viewData('summary')['total']);

        foreach ([$hiddenDirectRole, $hiddenDepartmentRole] as $hiddenRoleId) {
            $hiddenResponse = $this->get('/access/roles?role_id='.$hiddenRoleId)->assertOk();
            $hidden = $hiddenResponse->viewData('roles');
            self::assertSame(0, $hidden->total());
            self::assertSame(14, $hiddenResponse->viewData('summary')['total']);
        }
    }

    public function test_user_access_detail_hides_out_of_scope_assignments_and_rejects_edits_without_changes(): void
    {
        $world = $this->organization('User Assignment Scope Company');
        $manager = $this->user($world, 'Assignment Scope Manager', 'assignment-scope-manager@example.test', $world['branch_a'], $world['department_a']);
        $target = $this->user($world, 'Assignment Scope Target', 'assignment-scope-target@example.test', $world['branch_a'], $world['department_a']);
        $otherBranchUser = $this->user($world, 'Assignment Scope Other Branch', 'assignment-scope-other@example.test', $world['branch_b'], $world['department_b']);
        $managerRole = $this->role($world, 'Assignment Scope Manager Role', ['users.read', 'users.write', 'roles.read', 'roles.write']);
        DB::table('access_user_roles')->insert(['user_id' => $manager['id'], 'role_id' => $managerRole]);
        $outOfScopeRole = $this->role($world, 'Shared Out of Scope Role', ['users.read']);
        DB::table('access_user_roles')->insert([
            ['user_id' => $target['id'], 'role_id' => $outOfScopeRole],
            ['user_id' => $otherBranchUser['id'], 'role_id' => $outOfScopeRole],
        ]);
        DB::table('access_user_permissions')->insert([
            'user_id' => $target['id'],
            'permission' => 'organization.write',
            'allowed' => false,
        ]);

        $this->loginAs($manager['email']);

        $html = $this->get('/access/users/'.$target['id'])->assertOk();
        $htmlUser = $html->viewData('user');
        self::assertFalse($htmlUser['assignments_editable']);
        self::assertSame([], $htmlUser['role_ids']);
        self::assertArrayNotHasKey('organization.write', $htmlUser['permissions']);
        $html->assertDontSee($outOfScopeRole)->assertDontSee('organization.write');

        $jsonUser = $this->getJson('/access/users/'.$target['id'])->assertOk()->json('data');
        self::assertFalse($jsonUser['assignments_editable']);
        self::assertSame([], $jsonUser['role_ids']);
        self::assertArrayNotHasKey('organization.write', $jsonUser['permissions']);

        $rolesBefore = DB::table('access_user_roles')->where('user_id', $target['id'])->orderBy('role_id')->pluck('role_id')->all();
        $overridesBefore = DB::table('access_user_permissions')->where('user_id', $target['id'])->pluck('allowed', 'permission')
            ->map(static fn ($allowed): bool => (bool) $allowed)->all();
        $versionBefore = (int) DB::table('access_users')->where('id', $target['id'])->value('version');

        $this->postJson('/access/users/'.$target['id'].'/access', [
            'role_ids' => [],
            'permissions' => [],
            'version' => $versionBefore,
        ])->assertForbidden();

        self::assertSame($rolesBefore, DB::table('access_user_roles')->where('user_id', $target['id'])->orderBy('role_id')->pluck('role_id')->all());
        self::assertSame($overridesBefore, DB::table('access_user_permissions')->where('user_id', $target['id'])->pluck('allowed', 'permission')
            ->map(static fn ($allowed): bool => (bool) $allowed)->all());
        self::assertSame($versionBefore, (int) DB::table('access_users')->where('id', $target['id'])->value('version'));
    }

    public function test_department_role_details_and_writes_fail_closed_for_hidden_role_assignments(): void
    {
        $world = $this->organization('Department Role Scope Company');
        $manager = $this->user($world, 'Department Role Scope Manager', 'department-role-scope-manager@example.test', $world['branch_a'], $world['department_a']);
        $managerRole = $this->role($world, 'Department Role Scope Manager Role', [
            'organization.read', 'organization.write', 'roles.read', 'roles.write', 'users.read', 'users.write',
        ]);
        DB::table('access_user_roles')->insert(['user_id' => $manager['id'], 'role_id' => $managerRole]);

        $targetDepartmentId = (string) Str::ulid();
        DB::table('access_departments')->insert([
            'id' => $targetDepartmentId,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_a'],
            'name' => 'Department Role Scope Target',
            'code' => 'department-role-scope-target',
            'active' => true,
            'version' => 0,
        ]);
        self::assertNotSame($world['department_a'], $targetDepartmentId);

        $otherBranchUser = $this->user($world, 'Department Role Scope Other Branch', 'department-role-scope-other@example.test', $world['branch_b'], $world['department_b']);
        $outOfScopeRole = $this->role($world, 'Department Role Scope Hidden Role', ['users.read']);
        DB::table('access_user_roles')->insert(['user_id' => $otherBranchUser['id'], 'role_id' => $outOfScopeRole]);
        DB::table('access_department_roles')->insert([
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_a'],
            'department_id' => $targetDepartmentId,
            'role_id' => $outOfScopeRole,
        ]);
        $this->loginAs($manager['email']);

        $response = $this->get('/access/organization')->assertOk();
        $departments = $response->viewData('data')['departments'];
        $targetDepartment = collect($departments->items())->firstWhere('id', $targetDepartmentId);
        self::assertIsArray($targetDepartment);
        self::assertFalse($targetDepartment['roles_editable']);

        $rolesBefore = DB::table('access_department_roles')->where('department_id', $targetDepartmentId)->orderBy('role_id')->pluck('role_id')->all();
        $versionBefore = (int) DB::table('access_departments')->where('id', $targetDepartmentId)->value('version');
        $this->postJson('/access/departments/'.$targetDepartmentId.'/roles', [
            'role_ids' => [],
            'version' => $versionBefore,
        ])->assertForbidden();
        self::assertSame($rolesBefore, DB::table('access_department_roles')->where('department_id', $targetDepartmentId)->orderBy('role_id')->pluck('role_id')->all());
        self::assertSame($versionBefore, (int) DB::table('access_departments')->where('id', $targetDepartmentId)->value('version'));
    }

    public function test_organization_paginates_branches_and_departments_independently_and_keeps_json_lists(): void
    {
        $world = $this->organization('Organization Pages Company');
        $admin = $this->user($world, 'Organization Pages Administrator', 'organization-pages@example.test', $world['branch_a'], $world['department_a'], true);

        for ($index = 1; $index <= 10; $index++) {
            $branchId = (string) Str::ulid();
            $departmentId = (string) Str::ulid();
            DB::table('access_branches')->insert([
                'id' => $branchId,
                'company_id' => $world['company_id'],
                'name' => "Organization Branch {$index}",
                'code' => "organization-{$index}",
                'active' => true,
                'version' => 0,
            ]);
            DB::table('access_departments')->insert([
                'id' => $departmentId,
                'company_id' => $world['company_id'],
                'branch_id' => $branchId,
                'name' => "Organization Department {$index}",
                'code' => "organization-dept-{$index}",
                'active' => true,
                'version' => 0,
            ]);
        }

        $this->loginAs($admin['email']);
        $response = $this->get('/access/organization?branches_page=2&departments_page=1&per_page=10')->assertOk();
        $data = $response->viewData('data');
        self::assertInstanceOf(LengthAwarePaginator::class, $data['branches']);
        self::assertInstanceOf(LengthAwarePaginator::class, $data['departments']);
        self::assertSame(12, $data['branches']->total());
        self::assertSame(12, $data['departments']->total());
        self::assertSame(2, $data['branches']->currentPage());
        self::assertSame(1, $data['departments']->currentPage());
        self::assertSame(12, $response->viewData('summary')['branches']);
        self::assertSame(12, $response->viewData('summary')['departments']);
        self::assertIsArray($data['branches']->items()[0]);
        self::assertIsArray($data['departments']->items()[0]);

        $this->getJson('/access/organization')->assertOk()
            ->assertJsonPath('data.company.id', $world['company_id']);
        self::assertIsArray($this->getJson('/access/organization')->json('data.branches'));
        self::assertIsArray($this->getJson('/access/organization')->json('data.departments'));
    }

    public function test_role_and_audit_directories_filter_and_paginate_array_rows(): void
    {
        $world = $this->organization('Role and Audit Company');
        $admin = $this->user($world, 'Role and Audit Administrator', 'role-audit-admin@example.test', $world['branch_a'], $world['department_a'], true);

        for ($index = 1; $index <= 11; $index++) {
            $suffix = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $this->role($world, "Browse Role {$suffix}", []);
        }

        $subjectId = (string) Str::ulid();
        $today = now()->toDateString();
        for ($index = 1; $index <= 11; $index++) {
            DB::table('access_audit')->insert([
                'id' => (string) Str::ulid(),
                'company_id' => $world['company_id'],
                'actor_id' => $admin['id'],
                'action' => $index === 1 ? 'role.updated' : 'role.created',
                'subject_id' => $index === 1 ? $subjectId : (string) Str::ulid(),
                'metadata' => '{}',
                'created_at' => now()->subDays($index - 1)->toDateTimeString(),
            ]);
        }

        $this->loginAs($admin['email']);

        $rolesResponse = $this->get('/access/roles?'.http_build_query([
            'q' => 'Browse Role',
            'sort' => 'name',
            'direction' => 'asc',
            'per_page' => 10,
            'page' => 2,
        ]))->assertOk();
        $roles = $rolesResponse->viewData('roles');
        self::assertInstanceOf(LengthAwarePaginator::class, $roles);
        self::assertSame(11, $roles->total());
        self::assertSame(['Browse Role 11'], array_column($roles->items(), 'name'));

        $auditResponse = $this->get('/access/audit?'.http_build_query([
            'action' => 'role.updated',
            'actor_id' => $admin['id'],
            'subject_id' => $subjectId,
            'from' => $today,
            'to' => $today,
            'sort' => 'created_at',
            'direction' => 'desc',
            'per_page' => 10,
        ]))->assertOk();
        $entries = $auditResponse->viewData('entries');
        self::assertInstanceOf(LengthAwarePaginator::class, $entries);
        self::assertSame(1, $entries->total());
        self::assertSame('role.updated', $entries->items()[0]['action']);
        self::assertSame($admin['id'], $entries->items()[0]['actor_id']);
        self::assertSame($subjectId, $entries->items()[0]['subject_id']);
    }

    public function test_json_directory_responses_remain_unpaginated_lists(): void
    {
        $world = $this->organization('JSON Compatibility Company');
        $admin = $this->user($world, 'JSON Compatibility Administrator', 'json-compatibility@example.test', $world['branch_a'], $world['department_a'], true);
        $this->user($world, 'JSON Compatibility User', 'json-compatibility-user@example.test', $world['branch_a'], $world['department_a']);
        $this->role($world, 'JSON Compatibility Role', ['users.read']);
        DB::table('access_audit')->insert([
            'id' => (string) Str::ulid(),
            'company_id' => $world['company_id'],
            'actor_id' => $admin['id'],
            'action' => 'compatibility.checked',
            'subject_id' => $admin['id'],
            'metadata' => '{}',
            'created_at' => now()->toDateTimeString(),
        ]);
        $this->loginAs($admin['email']);

        $users = $this->getJson('/access/users?per_page=10')->assertOk()->json('data');
        $roles = $this->getJson('/access/roles?per_page=10')->assertOk()->json('data');
        $audit = $this->getJson('/access/audit?per_page=10')->assertOk()->json('data');
        self::assertIsArray($users);
        self::assertIsArray($roles);
        self::assertIsArray($audit);
        self::assertCount(2, $users);
        self::assertCount(1, $roles);
        self::assertContains('JSON Compatibility User', array_column($users, 'name'));
        self::assertContains('JSON Compatibility Role', array_column($roles, 'name'));
        self::assertContains('compatibility.checked', array_column($audit, 'action'));
    }

    public function test_directory_permissions_are_checked_before_any_summary_is_rendered(): void
    {
        $world = $this->organization('No Access Company');
        $reader = $this->user($world, 'No Access Reader', 'no-access-reader@example.test', $world['branch_a'], $world['department_a']);
        $this->loginAs($reader['email']);

        $this->get('/access/users?status=active&per_page=10')->assertForbidden();
        $this->get('/access/organization?branches_page=1&departments_page=1')->assertForbidden();
        $this->get('/access/audit?actor_id='.$reader['id'])->assertForbidden();
    }

    /** @return array{company_id: string, branch_a: string, branch_b: string, department_a: string, department_b: string} */
    private function organization(string $name): array
    {
        $companyId = (string) Str::ulid();
        $branchA = (string) Str::ulid();
        $branchB = (string) Str::ulid();
        $departmentA = (string) Str::ulid();
        $departmentB = (string) Str::ulid();

        DB::table('access_companies')->insert(['id' => $companyId, 'name' => $name, 'version' => 0]);
        foreach ([[$branchA, 'Main', $departmentA], [$branchB, 'Other', $departmentB]] as [$branchId, $branchName, $departmentId]) {
            DB::table('access_branches')->insert([
                'id' => $branchId,
                'company_id' => $companyId,
                'name' => $branchName,
                'code' => strtolower($branchName),
                'active' => true,
                'version' => 0,
            ]);
            DB::table('access_departments')->insert([
                'id' => $departmentId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => $branchName.' Department',
                'code' => strtolower($branchName).'-dept',
                'active' => true,
                'version' => 0,
            ]);
        }

        return [
            'company_id' => $companyId,
            'branch_a' => $branchA,
            'branch_b' => $branchB,
            'department_a' => $departmentA,
            'department_b' => $departmentB,
        ];
    }

    /** @param array{company_id: string, branch_a: string, branch_b: string, department_a: string, department_b: string} $world
     * @return array{id: string, email: string}
     */
    private function user(
        array $world,
        string $name,
        string $email,
        string $branchId,
        string $departmentId,
        bool $admin = false,
        bool $active = true,
    ): array {
        $id = (string) Str::ulid();
        $now = now()->toDateTimeString();
        DB::table('access_users')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'active' => $active,
            'is_admin' => $admin,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $id, 'email' => $email];
    }

    /** @param array{company_id: string} $world
     * @param  list<string>  $permissions
     */
    private function role(array $world, string $name, array $permissions): string
    {
        $id = (string) Str::ulid();
        DB::table('access_roles')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'name' => $name,
            'version' => 0,
        ]);
        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $id, 'permission' => $permission]);
        }

        return $id;
    }

    private function loginAs(string $email): void
    {
        $this->postJson('/login', ['email' => $email, 'password' => self::PASSWORD])->assertSuccessful();
    }
}

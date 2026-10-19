<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Identity\Application\SEC04\AccessControl;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

final class AccessScopeCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery';

    public function test_central_admin_can_read_users_from_another_branch_in_the_company(): void
    {
        $fixture = $this->fixture();
        $otherBranchUser = $this->user(
            $fixture,
            'Other Branch User',
            'other-branch@example.test',
            $fixture['branch_b'],
            $fixture['department_b'],
        );

        $this->loginAs($fixture['root_email']);

        $users = $this->getJson('/access/users')->assertOk()->json('data');

        self::assertContains($otherBranchUser['id'], array_column($users, 'id'));
    }

    public function test_same_company_role_assigned_outside_branch_cannot_be_reassigned_by_branch_manager(): void
    {
        $fixture = $this->fixture();
        $manager = $this->user($fixture, 'Branch Manager', 'manager@example.test');
        $localTarget = $this->user($fixture, 'Local Target', 'local-target@example.test');
        $outsideAssignee = $this->user(
            $fixture,
            'Outside Assignee',
            'outside-assignee@example.test',
            $fixture['branch_b'],
            $fixture['department_b'],
        );
        $managerRole = $this->role($fixture, 'Branch Manager Role', ['users.read', 'users.write', 'roles.write']);
        $sharedRole = $this->role($fixture, 'Outside Branch Role', ['users.read']);
        $this->assignRole($manager['id'], $managerRole);
        $this->assignRole($outsideAssignee['id'], $sharedRole);
        $this->loginAs($manager['email']);

        $this->postJson('/access/users/'.$localTarget['id'].'/access', [
            'role_ids' => [$sharedRole],
            'permissions' => [],
            'version' => 0,
        ])->assertForbidden();

        $this->assertDatabaseMissing('access_user_roles', [
            'user_id' => $localTarget['id'],
            'role_id' => $sharedRole,
        ]);
        $this->assertDatabaseHas('access_users', ['id' => $localTarget['id'], 'version' => 0]);
    }

    public function test_branch_manager_cannot_set_a_department_default_to_a_role_used_in_another_branch(): void
    {
        $fixture = $this->fixture();
        $manager = $this->user($fixture, 'Branch Manager', 'manager@example.test');
        $outsideAssignee = $this->user(
            $fixture,
            'Outside Assignee',
            'outside-assignee@example.test',
            $fixture['branch_b'],
            $fixture['department_b'],
        );
        $managerRole = $this->role($fixture, 'Branch Manager Role', [
            'organization.write', 'roles.write', 'users.read', 'users.write', 'tasks.read',
        ]);
        $sharedRole = $this->role($fixture, 'Outside Branch Role', ['tasks.read']);
        $this->assignRole($manager['id'], $managerRole);
        $this->assignRole($outsideAssignee['id'], $sharedRole);

        try {
            app(AccessControl::class)->assignDepartmentRoles(
                $this->actor($manager['email']),
                $fixture['department_a2'],
                ['role_ids' => [$sharedRole], 'version' => 0],
            );
            self::fail('A branch manager must not apply an outside-branch role as a department default.');
        } catch (AccessDeniedHttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseMissing('access_department_roles', [
            'department_id' => $fixture['department_a2'],
            'role_id' => $sharedRole,
        ]);
        $this->assertDatabaseHas('access_departments', ['id' => $fixture['department_a2'], 'version' => 0]);
    }

    public function test_department_role_composite_foreign_keys_reject_a_foreign_company_role(): void
    {
        $fixture = $this->fixture();
        $foreignFixture = $this->fixture('-foreign');
        $foreignRole = $this->role($foreignFixture, 'Foreign Company Role', ['tasks.read']);
        self::assertTrue(Schema::hasTable('access_department_roles'));

        $rejected = false;
        try {
            DB::table('access_department_roles')->insert([
                'company_id' => $fixture['company_id'],
                'branch_id' => $fixture['branch_a'],
                'department_id' => $fixture['department_a'],
                'role_id' => $foreignRole,
            ]);
        } catch (QueryException) {
            $rejected = true;
        }

        self::assertTrue($rejected, 'The composite company foreign key must reject a role from another company.');
        $this->assertDatabaseMissing('access_department_roles', [
            'department_id' => $fixture['department_a'],
            'role_id' => $foreignRole,
        ]);
    }

    public function test_primary_navigation_shows_only_the_access_links_allowed_by_effective_permissions(): void
    {
        $fixture = $this->fixture();
        $allowedLinks = [
            'organization.read' => '/access/organization',
            'users.read' => '/access/users',
            'roles.read' => '/access/roles',
            'audit.read' => '/access/audit',
        ];

        foreach ($allowedLinks as $permission => $expectedLink) {
            $user = $this->user($fixture, $permission, str_replace('.', '-', $permission).'@example.test');
            $this->assignRole($user['id'], $this->role($fixture, 'Role '.$permission, [$permission]));
            $this->loginAs($user['email']);

            $page = $this->get('/access')->assertOk();
            $accessLinks = array_values(array_filter(
                $this->primaryNavigationLinks($page->getContent()),
                static fn (string $href): bool => str_starts_with($href, '/access/'),
            ));

            self::assertSame([$expectedLink.'?locale=ar'], $accessLinks, 'Only the effective permission should expose its matching localized navigation link.');
            $this->post('/logout')->assertRedirect();
        }
    }

    public function test_audit_page_renders_actual_before_and_after_metadata_without_credentials(): void
    {
        $fixture = $this->fixture();
        $target = $this->user($fixture, 'Target', 'target@example.test');
        $beforeRole = $this->role($fixture, 'Before Role', ['tasks.read']);
        $afterRole = $this->role($fixture, 'After Role', ['tasks.read', 'users.read']);
        $this->assignRole($target['id'], $beforeRole);
        $this->loginAs($fixture['root_email']);

        $this->postJson('/access/users/'.$target['id'].'/access', [
            'role_ids' => [$afterRole],
            'permissions' => [],
            'version' => 0,
        ])->assertOk();

        $audit = DB::table('access_audit')->where('action', 'access.assigned')->first();
        self::assertNotNull($audit);
        $metadata = json_decode((string) $audit->metadata, true, flags: JSON_THROW_ON_ERROR);
        $metadata['review_fixture'] = ['api_token' => 'audit-secret-value'];
        DB::table('access_audit')->where('id', $audit->id)->update([
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);

        $page = $this->get('/access/audit')->assertOk();
        $html = html_entity_decode($page->getContent(), ENT_QUOTES | ENT_HTML5);

        self::assertStringContainsString('"roles_before":["'.$beforeRole.'"]', $html);
        self::assertStringContainsString('"roles_after":["'.$afterRole.'"]', $html);
        self::assertStringContainsString('effective_permissions_before', $html);
        self::assertStringContainsString('effective_permissions_after', $html);
        self::assertStringContainsString('tasks.read', $html);
        self::assertStringContainsString('users.read', $html);
        self::assertStringNotContainsString('audit-secret-value', $html);
    }

    public function test_access_assignment_audit_records_the_non_admin_actors_effective_role_ids(): void
    {
        $fixture = $this->fixture();
        $manager = $this->user($fixture, 'Manager', 'manager@example.test');
        $target = $this->user($fixture, 'Target', 'target@example.test');
        $managerRole = $this->role($fixture, 'Manager Role', ['users.read', 'users.write', 'roles.write']);
        $targetRole = $this->role($fixture, 'Target Role', ['users.read']);
        $this->assignRole($manager['id'], $managerRole);
        $this->loginAs($manager['email']);

        $this->postJson('/access/users/'.$target['id'].'/access', [
            'role_ids' => [$targetRole],
            'permissions' => [],
            'version' => 0,
        ])->assertOk();

        $audit = DB::table('access_audit')
            ->where('actor_id', $manager['id'])
            ->where('action', 'access.assigned')
            ->first();
        self::assertNotNull($audit);
        $metadata = json_decode((string) $audit->metadata, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame([$managerRole], $metadata['effective_role_ids'] ?? null);
    }

    public function test_authorized_audit_page_view_records_the_actor_and_audit_viewed_action(): void
    {
        $fixture = $this->fixture();
        $viewer = $this->user($fixture, 'Audit Viewer', 'audit-viewer@example.test');
        $viewerRole = $this->role($fixture, 'Audit Viewer Role', ['audit.read']);
        $this->assignRole($viewer['id'], $viewerRole);
        $this->loginAs($viewer['email']);

        $this->get('/access/audit')->assertOk();

        $this->assertDatabaseHas('access_audit', [
            'company_id' => $fixture['company_id'],
            'actor_id' => $viewer['id'],
            'action' => 'audit.viewed',
        ]);
    }

    public function test_denied_audit_page_view_creates_no_audit_event(): void
    {
        $fixture = $this->fixture();
        $viewer = $this->user($fixture, 'Restricted Viewer', 'restricted-viewer@example.test');
        $viewerRole = $this->role($fixture, 'Restricted Viewer Role', ['users.read']);
        $this->assignRole($viewer['id'], $viewerRole);
        $this->loginAs($viewer['email']);

        $this->get('/access/audit')->assertForbidden();

        $this->assertDatabaseMissing('access_audit', [
            'company_id' => $fixture['company_id'],
            'actor_id' => $viewer['id'],
            'action' => 'audit.viewed',
        ]);
    }

    public function test_audit_page_displays_a_dash_when_an_entry_has_no_metadata(): void
    {
        $fixture = $this->fixture();
        $this->loginAs($fixture['root_email']);

        DB::table('access_audit')->insert([
            'id' => $this->id(),
            'company_id' => $fixture['company_id'],
            'actor_id' => $fixture['root_id'],
            'action' => 'metadata.missing',
            'subject_id' => $fixture['root_id'],
            'metadata' => '{}',
            'created_at' => now()->toDateTimeString(),
        ]);

        $html = html_entity_decode($this->get('/access/audit')->assertOk()->getContent(), ENT_QUOTES | ENT_HTML5);

        self::assertMatchesRegularExpression('/<tr>[^<]*(?:(?!<\/tr>).)*metadata\.missing(?:(?!<\/tr>).)*<span data-empty-metadata>—<\/span>/s', $html);
    }

    public function test_department_role_permissions_join_user_roles_and_direct_denies_still_win(): void
    {
        $fixture = $this->fixture();
        $member = $this->user($fixture, 'Department Member', 'department-member@example.test');
        $memberRole = $this->role($fixture, 'Member Role', ['roles.read']);
        $departmentRole = $this->role($fixture, 'Department Role', ['users.read', 'tasks.read']);
        $this->assignRole($member['id'], $memberRole);
        DB::table('access_user_permissions')->insert([
            'user_id' => $member['id'],
            'permission' => 'tasks.read',
            'allowed' => false,
        ]);

        app(AccessControl::class)->assignDepartmentRoles(
            $this->actor($fixture['root_email']),
            $fixture['department_a'],
            ['role_ids' => [$departmentRole], 'version' => 0],
        );
        $this->loginAs($member['email']);

        $this->getJson('/access')
            ->assertOk()
            ->assertJsonPath('permissions', ['users.read', 'roles.read']);
    }

    public function test_department_role_removal_revokes_access_in_an_existing_session(): void
    {
        $fixture = $this->fixture();
        $member = $this->user($fixture, 'Department Member', 'department-member@example.test');
        $departmentRole = $this->role($fixture, 'Task Reader', ['tasks.read']);
        $root = $this->actor($fixture['root_email']);

        app(AccessControl::class)->assignDepartmentRoles(
            $root,
            $fixture['department_a'],
            ['role_ids' => [$departmentRole], 'version' => 0],
        );
        $this->loginAs($member['email']);
        $this->getJson('/tasks')->assertOk();

        app(AccessControl::class)->assignDepartmentRoles(
            $root,
            $fixture['department_a'],
            ['role_ids' => [], 'version' => 1],
        );

        $this->getJson('/tasks')->assertForbidden();
    }

    public function test_non_admin_cannot_assign_roles_to_their_own_department(): void
    {
        $fixture = $this->fixture();
        $manager = $this->user($fixture, 'Department Manager', 'department-manager@example.test');
        $managerRole = $this->role($fixture, 'Department Manager Role', [
            'organization.write', 'roles.write', 'users.read', 'users.write', 'tasks.read',
        ]);
        $departmentRole = $this->role($fixture, 'Department Task Reader', ['tasks.read']);
        $this->assignRole($manager['id'], $managerRole);
        $managerActor = $this->actor($manager['email']);

        try {
            app(AccessControl::class)->assignDepartmentRoles(
                $managerActor,
                $fixture['department_a'],
                ['role_ids' => [$departmentRole], 'version' => 0],
            );
            self::fail('A non-admin actor must not grant permissions through their own department.');
        } catch (AccessDeniedHttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseHas('access_departments', ['id' => $fixture['department_a'], 'version' => 0]);
    }

    public function test_department_role_assignment_rejects_a_department_from_another_company(): void
    {
        $fixture = $this->fixture();
        $foreignFixture = $this->fixture('-foreign');
        $departmentRole = $this->role($fixture, 'Department Role', ['tasks.read']);

        try {
            app(AccessControl::class)->assignDepartmentRoles(
                $this->actor($fixture['root_email']),
                $foreignFixture['department_a'],
                ['role_ids' => [$departmentRole], 'version' => 0],
            );
            self::fail('A department from another company must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('department_id', $exception->errors());
        }
    }

    public function test_department_role_assignment_rejects_a_stale_department_version_with_409(): void
    {
        $fixture = $this->fixture();
        $departmentRole = $this->role($fixture, 'Department Role', ['tasks.read']);
        $root = $this->actor($fixture['root_email']);

        app(AccessControl::class)->assignDepartmentRoles(
            $root,
            $fixture['department_a'],
            ['role_ids' => [$departmentRole], 'version' => 0],
        );

        try {
            app(AccessControl::class)->assignDepartmentRoles(
                $root,
                $fixture['department_a'],
                ['role_ids' => [], 'version' => 0],
            );
            self::fail('A stale department version must be rejected.');
        } catch (ConflictHttpException $exception) {
            self::assertSame(409, $exception->getStatusCode());
        }
    }

    /** @return array<string, string> */
    private function fixture(string $suffix = ''): array
    {
        $companyId = $this->id();
        $branchA = $this->id();
        $branchB = $this->id();
        $departmentA = $this->id();
        $departmentA2 = $this->id();
        $departmentB = $this->id();

        DB::table('access_companies')->insert([
            'id' => $companyId,
            'name' => 'Scope Test Company'.$suffix,
            'version' => 0,
        ]);

        foreach ([[$branchA, 'BR-A'.$suffix], [$branchB, 'BR-B'.$suffix]] as [$branchId, $code]) {
            DB::table('access_branches')->insert([
                'id' => $branchId,
                'company_id' => $companyId,
                'name' => $code,
                'code' => $code,
                'active' => true,
                'version' => 0,
            ]);
        }

        foreach ([[$departmentA, $branchA, 'DEPT-A'.$suffix], [$departmentA2, $branchA, 'DEPT-A2'.$suffix], [$departmentB, $branchB, 'DEPT-B'.$suffix]] as [$departmentId, $branchId, $code]) {
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

        $root = $this->user(
            ['company_id' => $companyId, 'branch_a' => $branchA, 'department_a' => $departmentA],
            'Root',
            'root'.$suffix.'@example.test',
            isAdmin: true,
        );

        return [
            'company_id' => $companyId,
            'branch_a' => $branchA,
            'branch_b' => $branchB,
            'department_a' => $departmentA,
            'department_a2' => $departmentA2,
            'department_b' => $departmentB,
            'root_id' => $root['id'],
            'root_email' => $root['email'],
        ];
    }

    /** @param array<string, string> $fixture
     * @return array{id: string, email: string}
     */
    private function user(
        array $fixture,
        string $name,
        string $email,
        ?string $branchId = null,
        ?string $departmentId = null,
        bool $isAdmin = false,
    ): array {
        $id = $this->id();
        $now = now();

        DB::table('access_users')->insert([
            'id' => $id,
            'company_id' => $fixture['company_id'],
            'branch_id' => $branchId ?? $fixture['branch_a'],
            'department_id' => $departmentId ?? $fixture['department_a'],
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'active' => true,
            'is_admin' => $isAdmin,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $id, 'email' => $email];
    }

    /** @param array<string, string> $fixture
     * @param  list<string>  $permissions
     */
    private function role(array $fixture, string $name, array $permissions): string
    {
        $id = $this->id();
        DB::table('access_roles')->insert([
            'id' => $id,
            'company_id' => $fixture['company_id'],
            'name' => $name,
            'version' => 0,
        ]);

        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert([
                'role_id' => $id,
                'permission' => $permission,
            ]);
        }

        return $id;
    }

    private function assignRole(string $userId, string $roleId): void
    {
        DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);
    }

    /** @return array<string, mixed> */
    private function actor(string $email): array
    {
        return app(AccessControl::class)->authenticate($email, self::PASSWORD);
    }

    private function loginAs(string $email): void
    {
        $this->postJson('/login', ['email' => $email, 'password' => self::PASSWORD])->assertSuccessful();
    }

    /** @return list<string> */
    private function primaryNavigationLinks(string $html): array
    {
        self::assertSame(1, preg_match('/<nav\b(?=[^>]*\bid="primary-navigation")[^>]*>(.*?)<\/nav>/s', $html, $navigation));
        preg_match_all('/<a\b[^>]*\bhref="([^"]+)"/i', $navigation[1], $links);

        return $links[1];
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }
}

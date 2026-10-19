<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Identity\Application\SEC04\AccessControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

final class AccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery';

    public function test_access_requires_login_and_a_persisted_grant(): void
    {
        $this->getJson('/access')->assertUnauthorized();

        $fixture = $this->fixture();
        $user = $this->user($fixture, 'Unprivileged', 'unprivileged@example.test');

        $this->loginAs($user['email']);
        $this->getJson('/access')->assertForbidden();
        $this->getJson('/access/audit')->assertForbidden();
    }

    public function test_login_uses_the_password_hash_and_reload_grants_on_every_request(): void
    {
        $fixture = $this->fixture();
        $user = $this->user($fixture, 'Reader', 'reader@example.test');
        $roleId = $this->role($fixture, 'Reader', ['users.read']);
        $this->assignRole($user['id'], $roleId);

        $this->loginAs($user['email']);
        $this->getJson('/access')
            ->assertOk()
            ->assertJsonStructure(['user', 'permissions'])
            ->assertJsonPath('permissions', ['users.read']);

        DB::table('access_role_permissions')->where('role_id', $roleId)->delete();

        $this->getJson('/access')->assertForbidden();
    }

    public function test_explicit_user_permission_deny_overrides_a_role_grant_after_login(): void
    {
        $fixture = $this->fixture();
        $user = $this->user($fixture, 'Reader', 'reader@example.test');
        $roleId = $this->role($fixture, 'Reader', ['users.read']);
        $this->assignRole($user['id'], $roleId);
        DB::table('access_user_permissions')->insert([
            'user_id' => $user['id'],
            'permission' => 'users.read',
            'allowed' => false,
        ]);

        $this->loginAs($user['email']);

        $this->getJson('/access')->assertForbidden();
    }

    public function test_runtime_tasks_access_uses_current_database_grants_after_real_login(): void
    {
        $fixture = $this->fixture();
        $user = $this->user($fixture, 'Reader', 'reader@example.test');
        $roleId = $this->role($fixture, 'Reader', ['users.read']);
        $this->assignRole($user['id'], $roleId);

        $this->loginAs($user['email']);
        $this->getJson('/tasks')->assertForbidden();

        DB::table('access_role_permissions')->insert([
            'role_id' => $roleId,
            'permission' => 'tasks.read',
        ]);
        $this->getJson('/tasks')->assertOk();

        DB::table('access_role_permissions')
            ->where('role_id', $roleId)
            ->where('permission', 'tasks.read')
            ->delete();
        $this->getJson('/tasks')->assertForbidden();
    }

    public function test_legacy_session_identity_is_rejected_outside_testing(): void
    {
        app()->detectEnvironment(static fn (): string => 'local');

        $this->withSession([
            'actor_id' => 'legacy-actor',
            'entity_id' => 'legacy-entity',
            'department_id' => 'legacy-department',
            'permissions' => ['tasks.read'],
        ])->getJson('/tasks')->assertUnauthorized();
    }

    public function test_bad_password_is_rejected_without_creating_an_identity(): void
    {
        $fixture = $this->fixture();
        $user = $this->user($fixture, 'Member', 'member@example.test');

        $this->postJson('/login', [
            'email' => $user['email'],
            'password' => 'incorrect-password',
        ])->assertUnprocessable();

        $this->getJson('/access')->assertUnauthorized();
    }

    public function test_repeated_failed_logins_are_rate_limited(): void
    {
        $fixture = $this->fixture();
        $user = $this->user($fixture, 'Member', 'member@example.test');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $response = $this->postJson('/login', [
                'email' => $user['email'],
                'password' => 'incorrect-password',
            ]);

            if ($response->status() === 429) {
                return;
            }

            $response->assertUnprocessable();
        }

        self::fail('Repeated failed logins were not rate limited.');
    }

    public function test_logout_inactive_users_and_revoked_sessions_cannot_reuse_identity(): void
    {
        $fixture = $this->fixture();
        $logoutUser = $this->user($fixture, 'Logout', 'logout@example.test');
        $this->loginAs($logoutUser['email']);
        $this->postJson('/logout')->assertSuccessful();
        $this->getJson('/access')->assertUnauthorized();

        $inactiveUser = $this->user($fixture, 'Inactive', 'inactive@example.test');
        $this->loginAs($inactiveUser['email']);
        DB::table('access_users')->where('id', $inactiveUser['id'])->update(['active' => false]);
        $this->getJson('/access')->assertUnauthorized();

        $revokedUser = $this->user($fixture, 'Revoked', 'revoked@example.test');
        $this->loginAs($revokedUser['email']);
        DB::table('access_users')->where('id', $revokedUser['id'])->increment('session_version');
        $this->getJson('/access')->assertUnauthorized();
    }

    public function test_other_branch_users_are_hidden_and_wrong_branch_departments_are_rejected(): void
    {
        $fixture = $this->fixture();
        $manager = $this->user($fixture, 'Branch Manager', 'manager@example.test');
        $roleId = $this->role($fixture, 'User Manager', ['users.write']);
        $this->assignRole($manager['id'], $roleId);
        $otherBranchUser = $this->user(
            $fixture,
            'Other Branch',
            'other-branch@example.test',
            $fixture['branch_b'],
            $fixture['department_b'],
        );

        $this->loginAs($manager['email']);

        $this->postJson('/access/users/'.$otherBranchUser['id'], [
            'name' => 'Changed from another branch',
            'email' => $otherBranchUser['email'],
            'branch_id' => $fixture['branch_b'],
            'department_id' => $fixture['department_b'],
            'active' => false,
            'version' => 0,
        ])->assertNotFound();

        $this->assertDatabaseHas('access_users', [
            'id' => $otherBranchUser['id'],
            'name' => 'Other Branch',
            'active' => true,
        ]);

        $this->postJson('/access/users', [
            'name' => 'Mismatched Department',
            'email' => 'mismatched-department@example.test',
            'password' => self::PASSWORD,
            'branch_id' => $fixture['branch_a'],
            'department_id' => $fixture['department_b'],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('access_users', ['email' => 'mismatched-department@example.test']);
    }

    public function test_user_reads_and_access_changes_cannot_cross_company_scope(): void
    {
        $fixture = $this->fixture();
        $foreignFixture = $this->fixture('-foreign');
        $manager = $this->user($fixture, 'Company Manager', 'company-manager@example.test');
        $localTarget = $this->user($fixture, 'Local Target', 'local-target@example.test');
        $foreignTarget = $this->user($foreignFixture, 'Foreign Target', 'foreign-target@example.test');
        $managerRoleId = $this->role($fixture, 'Company Manager Role', ['users.read', 'users.write', 'roles.write']);
        $foreignRoleId = $this->role($foreignFixture, 'Foreign Reader Role', ['users.read']);
        $this->assignRole($manager['id'], $managerRoleId);

        $this->loginAs($manager['email']);

        $this->getJson('/access/users/'.$foreignTarget['id'])->assertNotFound();

        $this->postJson('/access/users/'.$foreignTarget['id'], [
            'name' => 'Changed across company',
            'email' => $foreignTarget['email'],
            'branch_id' => $foreignFixture['branch_a'],
            'department_id' => $foreignFixture['department_a'],
            'active' => false,
            'version' => 0,
        ])->assertNotFound();

        $this->postJson('/access/users/'.$localTarget['id'].'/access', [
            'role_ids' => [$foreignRoleId],
            'permissions' => [],
            'version' => 0,
        ])->assertUnprocessable();

        $this->postJson('/access/users', [
            'name' => 'Forged Company User',
            'email' => 'forged-company@example.test',
            'password' => self::PASSWORD,
            'branch_id' => $fixture['branch_a'],
            'department_id' => $fixture['department_a'],
            'company_id' => $foreignFixture['company_id'],
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('access_users', ['email' => 'forged-company@example.test']);
    }

    public function test_users_cannot_assign_access_to_themselves_or_edit_an_assigned_role(): void
    {
        $fixture = $this->fixture();
        $manager = $this->user($fixture, 'Manager', 'manager@example.test');
        $managerRoleId = $this->role($fixture, 'Manager Role', ['users.write', 'roles.write']);
        $readerRoleId = $this->role($fixture, 'Reader Role', ['users.read']);
        $this->assignRole($manager['id'], $managerRoleId);

        $this->loginAs($manager['email']);

        $this->postJson('/access/users/'.$manager['id'].'/access', [
            'role_ids' => [$readerRoleId],
            'permissions' => [],
            'version' => 0,
        ])->assertForbidden();

        $this->postJson('/access/roles/'.$managerRoleId, [
            'name' => 'Escalated Manager Role',
            'permissions' => ['audit.read'],
            'version' => 0,
        ])->assertForbidden();

        $this->assertDatabaseMissing('access_user_roles', [
            'user_id' => $manager['id'],
            'role_id' => $readerRoleId,
        ]);
        $this->assertDatabaseHas('access_roles', [
            'id' => $managerRoleId,
            'name' => 'Manager Role',
            'version' => 0,
        ]);
    }

    public function test_users_cannot_grant_permissions_they_do_not_hold(): void
    {
        $fixture = $this->fixture();
        $manager = $this->user($fixture, 'Manager', 'manager@example.test');
        $target = $this->user($fixture, 'Target', 'target@example.test');
        $roleId = $this->role($fixture, 'User Manager', ['users.write', 'roles.write']);
        $this->assignRole($manager['id'], $roleId);

        $this->loginAs($manager['email']);

        $this->postJson('/access/users/'.$target['id'].'/access', [
            'role_ids' => [],
            'permissions' => ['organization.write' => true],
            'version' => 0,
        ])->assertForbidden();

        $this->assertDatabaseMissing('access_user_permissions', [
            'user_id' => $target['id'],
            'permission' => 'organization.write',
        ]);
    }

    public function test_access_service_requires_roles_write_even_when_called_without_the_controller(): void
    {
        $fixture = $this->fixture();
        $manager = $this->user($fixture, 'Manager', 'manager@example.test');
        $target = $this->user($fixture, 'Target', 'target@example.test');
        $roleId = $this->role($fixture, 'User Manager', ['users.write']);
        $this->assignRole($manager['id'], $roleId);
        $this->loginAs($manager['email']);

        $actor = [
            'id' => $manager['id'],
            'company_id' => $fixture['company_id'],
            'branch_id' => $fixture['branch_a'],
            'department_id' => $fixture['department_a'],
            'session_version' => 0,
        ];
        $denied = false;
        $auditCount = DB::table('access_audit')->count();

        try {
            app(AccessControl::class)->assignAccess($actor, $target['id'], [
                'role_ids' => [],
                'permissions' => [],
                'version' => 0,
            ]);
        } catch (AccessDeniedHttpException $exception) {
            $denied = true;
            self::assertSame(403, $exception->getStatusCode());
        }

        self::assertTrue($denied, 'The access service must require roles.write.');
        self::assertSame(0, DB::table('access_user_roles')->where('user_id', $target['id'])->count());
        self::assertSame(0, DB::table('access_user_permissions')->where('user_id', $target['id'])->count());
        self::assertDatabaseHas('access_users', ['id' => $target['id'], 'version' => 0]);
        self::assertSame($auditCount, DB::table('access_audit')->count());
    }

    public function test_users_read_does_not_expose_another_users_role_or_permission_details(): void
    {
        $fixture = $this->fixture();
        $reader = $this->user($fixture, 'Reader', 'reader@example.test');
        $target = $this->user($fixture, 'Target', 'target@example.test');
        $readerRoleId = $this->role($fixture, 'User Reader', ['users.read', 'users.write', 'roles.write']);
        $targetRoleId = $this->role($fixture, 'Sensitive Role', ['audit.read']);
        $this->assignRole($reader['id'], $readerRoleId);
        $this->assignRole($target['id'], $targetRoleId);
        DB::table('access_user_permissions')->insert([
            'user_id' => $target['id'],
            'permission' => 'organization.write',
            'allowed' => true,
        ]);

        $this->loginAs($reader['email']);

        $response = $this->getJson('/access/users/'.$target['id'])
            ->assertOk()
            ->assertJsonPath('data.id', $target['id'])
            ->assertJsonPath('data.name', 'Target');

        self::assertArrayNotHasKey('role_ids', $response->json('data'));
        self::assertArrayNotHasKey('permissions', $response->json('data'));

        $this->get('/access/users/'.$target['id'])
            ->assertOk()
            ->assertDontSee('data-testid="user-access-form"', false)
            ->assertDontSee('Sensitive Role')
            ->assertDontSee('audit.read')
            ->assertDontSee('organization.write');
    }

    public function test_non_root_cannot_change_root_and_http_admin_flags_cannot_escalate(): void
    {
        $fixture = $this->fixture();
        $manager = $this->user($fixture, 'Manager', 'manager@example.test');
        $roleId = $this->role($fixture, 'User Manager', ['users.write']);
        $this->assignRole($manager['id'], $roleId);

        $this->loginAs($manager['email']);

        $this->postJson('/access/users/'.$fixture['root_id'], [
            'name' => 'Root Disabled',
            'email' => 'root@example.test',
            'branch_id' => $fixture['branch_a'],
            'department_id' => $fixture['department_a'],
            'active' => false,
            'version' => 0,
        ])->assertForbidden();

        $this->assertDatabaseHas('access_users', [
            'id' => $fixture['root_id'],
            'active' => true,
            'is_admin' => true,
        ]);

        $response = $this->postJson('/access/users', [
            'name' => 'Attempted Admin',
            'email' => 'attempted-admin@example.test',
            'password' => self::PASSWORD,
            'branch_id' => $fixture['branch_a'],
            'department_id' => $fixture['department_a'],
            'is_admin' => true,
        ]);

        if ($response->status() === 422) {
            $this->assertDatabaseMissing('access_users', ['email' => 'attempted-admin@example.test']);

            return;
        }

        $response->assertSuccessful();
        $this->assertDatabaseHas('access_users', [
            'email' => 'attempted-admin@example.test',
            'is_admin' => false,
        ]);
    }

    public function test_successful_access_changes_are_audited_with_the_authenticated_actor(): void
    {
        $fixture = $this->fixture();
        $manager = $this->user($fixture, 'Manager', 'manager@example.test');
        $target = $this->user($fixture, 'Target', 'target@example.test');
        $managerRoleId = $this->role($fixture, 'Manager Role', ['users.read', 'users.write', 'roles.write', 'audit.read']);
        $readerRoleId = $this->role($fixture, 'Reader Role', ['users.read']);
        $this->assignRole($manager['id'], $managerRoleId);

        $this->loginAs($manager['email']);

        $this->postJson('/access/users/'.$target['id'].'/access', [
            'role_ids' => [$readerRoleId],
            'permissions' => [],
            'version' => 0,
        ])->assertSuccessful();

        $this->assertDatabaseHas('access_user_roles', [
            'user_id' => $target['id'],
            'role_id' => $readerRoleId,
        ]);
        $this->assertDatabaseHas('access_audit', [
            'company_id' => $fixture['company_id'],
            'actor_id' => $manager['id'],
            'subject_id' => $target['id'],
        ]);

        $this->getJson('/access/audit')->assertOk();
    }

    /** @return array<string, string> */
    private function fixture(string $suffix = ''): array
    {
        $companyId = $this->id();
        $branchA = $this->id();
        $branchB = $this->id();
        $departmentA = $this->id();
        $departmentB = $this->id();

        DB::table('access_companies')->insert([
            'id' => $companyId,
            'name' => 'Security Test Company'.$suffix,
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

        foreach ([[$departmentA, $branchA, 'DEPT-A'.$suffix], [$departmentB, $branchB, 'DEPT-B'.$suffix]] as [$departmentId, $branchId, $code]) {
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
            'department_b' => $departmentB,
            'root_id' => $root['id'],
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

    private function loginAs(string $email): void
    {
        $this->postJson('/login', ['email' => $email, 'password' => self::PASSWORD])->assertSuccessful();
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }
}

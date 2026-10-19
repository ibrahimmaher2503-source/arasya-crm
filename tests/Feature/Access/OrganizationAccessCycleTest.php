<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OrganizationAccessCycleTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-92!';

    public function test_owner_can_manage_the_organization_access_cycle_and_audit_changes(): void
    {
        $world = $this->seedWorld();
        $this->login($world['owner_email']);

        $this->getJson('/access')
            ->assertOk()
            ->assertJsonPath('user.id', $world['owner_id']);

        $this->getJson('/access/organization')
            ->assertOk()
            ->assertJsonStructure(['data' => ['company', 'branches', 'departments']]);

        $this->postJson('/access/organization', [
            'name' => 'Arasya HQ Renamed',
            'version' => 0,
        ])->assertOk()->assertJsonPath('data.version', 1);

        $branch = $this->postJson('/access/branches', [
            'name' => 'West Branch',
            'code' => 'west',
        ])->assertCreated()->assertJsonPath('data.version', 0);
        $branchId = (string) $branch->json('data.id');
        self::assertSame(26, strlen($branchId));

        $this->postJson("/access/branches/{$branchId}", [
            'name' => 'West Branch Edited',
            'code' => 'west-edited',
            'active' => false,
            'version' => 0,
        ])->assertOk()->assertJsonPath('data.version', 1);
        $this->postJson("/access/branches/{$branchId}", [
            'name' => 'West Branch Active',
            'code' => 'west-edited',
            'active' => true,
            'version' => 1,
        ])->assertOk()->assertJsonPath('data.version', 2);

        $department = $this->postJson('/access/departments', [
            'name' => 'Customer Care',
            'code' => 'care',
            'branch_id' => $branchId,
        ])->assertCreated()->assertJsonPath('data.version', 0);
        $departmentId = (string) $department->json('data.id');

        $this->postJson("/access/departments/{$departmentId}", [
            'name' => 'Customer Care Edited',
            'code' => 'care-edited',
            'branch_id' => $branchId,
            'active' => false,
            'version' => 0,
        ])->assertOk()->assertJsonPath('data.version', 1);
        $this->postJson("/access/departments/{$departmentId}", [
            'name' => 'Customer Care Active',
            'code' => 'care-edited',
            'branch_id' => $branchId,
            'active' => true,
            'version' => 1,
        ])->assertOk()->assertJsonPath('data.version', 2);

        $user = $this->postJson('/access/users', [
            'name' => 'Branch User',
            'email' => 'branch.user@example.test',
            'password' => self::PASSWORD,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
        ])->assertCreated()->assertJsonPath('data.version', 0);
        $userId = (string) $user->json('data.id');
        self::assertTrue(Hash::check(
            self::PASSWORD,
            (string) DB::table('access_users')->where('id', $userId)->value('password'),
        ));

        $this->postJson("/access/users/{$userId}", [
            'name' => 'Branch User Edited',
            'email' => 'branch.user@example.test',
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'active' => false,
            'version' => 0,
        ])->assertOk()->assertJsonPath('data.version', 1);
        $this->postJson("/access/users/{$userId}", [
            'name' => 'Branch User Active',
            'email' => 'branch.user@example.test',
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'active' => true,
            'version' => 1,
        ])->assertOk()->assertJsonPath('data.version', 2);

        $role = $this->postJson('/access/roles', [
            'name' => 'Branch Reader',
            'permissions' => ['organization.read'],
        ])->assertCreated()->assertJsonPath('data.version', 0);
        $roleId = (string) $role->json('data.id');

        $this->postJson("/access/roles/{$roleId}", [
            'name' => 'Branch Reader Updated',
            'permissions' => ['organization.read', 'users.read'],
            'version' => 0,
        ])->assertOk()->assertJsonPath('data.version', 1);

        $this->postJson("/access/users/{$userId}/access", [
            'role_ids' => [$roleId],
            'permissions' => ['users.read' => true],
            'version' => 2,
        ])->assertOk();

        $this->getJson('/access/audit')->assertOk();
        $this->assertDatabaseHas('access_audit', [
            'company_id' => $world['company_id'],
            'actor_id' => $world['owner_id'],
        ]);
        $this->assertDatabaseHas('access_role_permissions', [
            'role_id' => $roleId,
            'permission' => 'users.read',
        ]);
        $this->assertDatabaseHas('access_user_roles', ['user_id' => $userId, 'role_id' => $roleId]);
        $this->assertDatabaseHas('access_user_permissions', [
            'user_id' => $userId,
            'permission' => 'users.read',
            'allowed' => true,
        ]);

        $this->post('/logout')->assertRedirect();
        $this->login('branch.user@example.test');
        $this->getJson('/access')->assertOk()->assertJsonPath('user.id', $userId);
    }

    public function test_persisted_permissions_scope_reads_and_are_rechecked_after_revocation(): void
    {
        $world = $this->seedWorld();
        $sibling = $this->seedBranch($world['company_id'], 'East Branch', 'east');
        $restricted = $this->seedUser('limited@example.test', $world['branch_id'], $world['department_id']);
        $siblingUser = $this->seedUser('east@example.test', $sibling['branch_id'], $sibling['department_id']);
        $unassigned = $this->seedUser('unassigned@example.test', $world['branch_id'], $world['department_id']);

        $this->login($world['owner_email']);
        $role = $this->postJson('/access/roles', [
            'name' => 'Scoped Reader',
            'permissions' => ['organization.read', 'users.read'],
        ])->assertCreated();
        $roleId = (string) $role->json('data.id');
        $grant = $this->postJson("/access/users/{$restricted['id']}/access", [
            'role_ids' => [$roleId],
            'permissions' => [],
            'version' => 0,
        ])->assertOk();

        $this->post('/logout')->assertRedirect();
        $this->login('limited@example.test');

        $organization = $this->getJson('/access/organization')->assertOk();
        self::assertSame([$world['branch_id']], array_column($organization->json('data.branches'), 'id'));
        self::assertSame([$world['department_id']], array_column($organization->json('data.departments'), 'id'));

        $users = $this->getJson('/access/users')->assertOk()->json('data');
        self::assertContains($restricted['id'], array_column($users, 'id'));
        self::assertNotContains($siblingUser['id'], array_column($users, 'id'));
        self::assertSame([$world['branch_id']], array_values(array_unique(array_column($users, 'branch_id'))));

        $restrictedSession = app('session.store')->all();
        $this->post('/logout')->assertRedirect();
        $this->login($world['owner_email']);
        $this->postJson("/access/users/{$restricted['id']}/access", [
            'role_ids' => [],
            'permissions' => [],
            'version' => $grant->json('data.version'),
        ])->assertOk();

        $this->post('/logout')->assertRedirect();
        $this->withSession($restrictedSession)->getJson('/access')->assertForbidden();

        $this->post('/logout')->assertRedirect();
        $this->login('unassigned@example.test');
        $this->getJson('/access')->assertForbidden();
    }

    public function test_duplicates_and_stale_versions_do_not_change_persisted_data(): void
    {
        $world = $this->seedWorld();
        $this->login($world['owner_email']);

        $this->postJson('/access/branches', ['name' => 'Duplicate Branch', 'code' => 'main'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/access/users', [
            'name' => 'Duplicate Owner',
            'email' => $world['owner_email'],
            'password' => self::PASSWORD,
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/access/users', [
            'name' => 'Short Password',
            'email' => 'short.password@example.test',
            'password' => 'short',
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/access/organization', [
            'name' => 'Arasya HQ Current',
            'version' => 0,
        ])->assertOk()->assertJsonPath('data.version', 1);
        $this->postJson('/access/organization', [
            'name' => 'Stale Organization Name',
            'version' => 0,
        ])->assertConflict();

        $this->assertDatabaseHas('access_companies', [
            'id' => $world['company_id'],
            'name' => 'Arasya HQ Current',
            'version' => 1,
        ]);
        $this->assertSame(1, DB::table('access_branches')->where('company_id', $world['company_id'])->count());
        $this->assertSame(1, DB::table('access_users')->where('email', $world['owner_email'])->count());
        $this->assertSame(0, DB::table('access_users')->where('email', 'short.password@example.test')->count());
    }

    public function test_valid_html_form_posts_redirect_after_saving(): void
    {
        $world = $this->seedWorld();
        $this->login($world['owner_email']);

        $this->post('/access/branches', ['name' => 'Form Branch', 'code' => 'form'])->assertRedirect();
        $this->assertDatabaseHas('access_branches', [
            'company_id' => $world['company_id'],
            'name' => 'Form Branch',
            'code' => 'form',
        ]);
    }

    public function test_html_edits_accept_hidden_version_strings_and_stale_versions_conflict(): void
    {
        $world = $this->seedWorld();
        $this->login($world['owner_email']);

        $this->post('/access/organization', ['name' => 'Arasya HTML Edit', 'version' => '0'])
            ->assertRedirect('/access/organization');
        $this->assertDatabaseHas('access_companies', [
            'id' => $world['company_id'],
            'name' => 'Arasya HTML Edit',
            'version' => 1,
        ]);

        $this->post("/access/branches/{$world['branch_id']}", [
            'name' => 'Main Branch HTML Edit',
            'code' => 'main-html',
            'active' => '1',
            'version' => '0',
        ])->assertRedirect('/access/organization');
        $this->assertDatabaseHas('access_branches', [
            'id' => $world['branch_id'],
            'name' => 'Main Branch HTML Edit',
            'version' => 1,
        ]);

        $this->post("/access/departments/{$world['department_id']}", [
            'name' => 'Operations HTML Edit',
            'code' => 'ops-html',
            'branch_id' => $world['branch_id'],
            'active' => '1',
            'version' => '0',
        ])->assertRedirect('/access/organization');
        $this->assertDatabaseHas('access_departments', [
            'id' => $world['department_id'],
            'name' => 'Operations HTML Edit',
            'version' => 1,
        ]);

        $this->post('/access/organization', ['name' => 'Stale HTML Edit', 'version' => '0'])
            ->assertConflict();
        $this->assertDatabaseHas('access_companies', [
            'id' => $world['company_id'],
            'name' => 'Arasya HTML Edit',
            'version' => 1,
        ]);
    }

    /** @return array<string, string> */
    private function seedWorld(): array
    {
        $companyId = (string) Str::ulid();
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        $ownerId = (string) Str::ulid();
        $email = 'owner@example.test';
        $this->seedOrganization($companyId, $branchId, $departmentId);
        $this->insertUser($ownerId, $companyId, $branchId, $departmentId, 'Owner', $email, true);

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'owner_id' => $ownerId,
            'owner_email' => $email,
        ];
    }

    private function seedOrganization(string $companyId, string $branchId, string $departmentId): void
    {
        DB::table('access_companies')->insert([
            'id' => $companyId,
            'name' => 'Arasya HQ',
            'version' => 0,
        ]);
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Main Branch',
            'code' => 'main',
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Operations',
            'code' => 'ops',
            'active' => true,
            'version' => 0,
        ]);
    }

    /** @return array{branch_id: string, department_id: string} */
    private function seedBranch(string $companyId, string $name, string $code): array
    {
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => $name,
            'code' => $code,
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => $name.' Operations',
            'code' => $code.'-ops',
            'active' => true,
            'version' => 0,
        ]);

        return ['branch_id' => $branchId, 'department_id' => $departmentId];
    }

    /** @return array{id: string, email: string} */
    private function seedUser(string $email, string $branchId, string $departmentId): array
    {
        $userId = (string) Str::ulid();
        $this->insertUser(
            $userId,
            (string) DB::table('access_branches')->where('id', $branchId)->value('company_id'),
            $branchId,
            $departmentId,
            'Fixture User',
            $email,
            false,
        );

        return ['id' => $userId, 'email' => $email];
    }

    private function insertUser(
        string $id,
        string $companyId,
        string $branchId,
        string $departmentId,
        string $name,
        string $email,
        bool $admin,
    ): void {
        $now = now()->toDateTimeString();
        DB::table('access_users')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'active' => true,
            'is_admin' => $admin,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function login(string $email): void
    {
        $this->post('/login', ['email' => $email, 'password' => self::PASSWORD])->assertRedirect();
    }
}

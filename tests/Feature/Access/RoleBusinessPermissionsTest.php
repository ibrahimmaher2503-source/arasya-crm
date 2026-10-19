<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RoleBusinessPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-92!';

    public function test_role_crud_grants_and_revokes_sensitive_permissions_and_sessions_reload_them(): void
    {
        $world = $this->world();
        $target = $this->user($world, 'Role recipient', 'role-recipient@example.test');
        $this->loginAs($world['admin_email']);

        $adminPermissions = $this->getJson('/access')->assertOk()->json('permissions');
        self::assertNotContains('contact.reveal', $adminPermissions);
        self::assertNotContains('tasks.assign', $adminPermissions);

        $role = $this->postJson('/access/roles', [
            'name' => 'Contact and task delegation',
            'permissions' => ['tasks.read', 'contact.reveal', 'tasks.assign'],
        ])->assertCreated();
        $roleId = (string) $role->json('data.id');
        $this->postJson('/access/users/'.$target['id'].'/access', [
            'role_ids' => [$roleId],
            'permissions' => [],
            'version' => 0,
        ])->assertOk();

        $this->get('/access/roles?locale=ar')->assertOk()
            ->assertSee('عرض بيانات تواصل العملاء')
            ->assertSee('إسناد المهام للآخرين');
        $this->get('/access/roles?locale=en')->assertOk()
            ->assertSee('View customer contact details')
            ->assertSee('Assign tasks to others');

        $this->post('/logout')->assertRedirect();
        $this->loginAs($target['email']);
        $this->getJson('/access')->assertOk()
            ->assertJsonPath('permissions', ['contact.reveal', 'tasks.read', 'tasks.assign']);
        $targetSession = app('session.store')->all();

        $this->post('/logout')->assertRedirect();
        $this->loginAs($world['admin_email']);
        $this->postJson('/access/roles/'.$roleId, [
            'name' => 'Contact and task delegation',
            'permissions' => ['tasks.read'],
            'version' => 0,
        ])->assertOk()->assertJsonPath('data.version', 1);

        $this->post('/logout')->assertRedirect();
        $this->withSession($targetSession)->getJson('/access')->assertOk()
            ->assertJsonPath('permissions', ['tasks.read']);
    }

    public function test_explicit_user_denials_override_contact_and_assignment_role_grants(): void
    {
        $world = $this->world();
        $target = $this->user($world, 'Denied role recipient', 'denied-role-recipient@example.test');
        $this->loginAs($world['admin_email']);

        $role = $this->postJson('/access/roles', [
            'name' => 'Explicitly denied role',
            'permissions' => ['tasks.read', 'contact.reveal', 'tasks.assign'],
        ])->assertCreated();
        $roleId = (string) $role->json('data.id');
        $this->postJson('/access/users/'.$target['id'].'/access', [
            'role_ids' => [$roleId],
            'permissions' => ['contact.reveal' => false, 'tasks.assign' => false],
            'version' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('access_user_roles', ['user_id' => $target['id'], 'role_id' => $roleId]);
        $this->assertDatabaseHas('access_role_permissions', ['role_id' => $roleId, 'permission' => 'contact.reveal']);
        $this->assertDatabaseHas('access_role_permissions', ['role_id' => $roleId, 'permission' => 'tasks.assign']);
        $this->assertDatabaseHas('access_user_permissions', [
            'user_id' => $target['id'],
            'permission' => 'contact.reveal',
            'allowed' => false,
        ]);
        $this->assertDatabaseHas('access_user_permissions', [
            'user_id' => $target['id'],
            'permission' => 'tasks.assign',
            'allowed' => false,
        ]);

        $this->post('/logout')->assertRedirect();
        $this->loginAs($target['email']);
        $this->getJson('/access')->assertOk()->assertJsonPath('permissions', ['tasks.read']);
    }

    /** @return array{company_id: string, branch_id: string, department_id: string, admin_id: string, admin_email: string} */
    private function world(): array
    {
        $world = [
            'company_id' => $this->id(),
            'branch_id' => $this->id(),
            'department_id' => $this->id(),
            'admin_id' => $this->id(),
            'admin_email' => 'role-admin@example.test',
        ];
        DB::table('access_companies')->insert([
            'id' => $world['company_id'],
            'name' => 'Role permissions company',
            'version' => 0,
        ]);
        DB::table('access_branches')->insert([
            'id' => $world['branch_id'],
            'company_id' => $world['company_id'],
            'name' => 'Role permissions branch',
            'code' => 'role-permissions',
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $world['department_id'],
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'name' => 'Role permissions department',
            'code' => 'role-permissions',
            'active' => true,
            'version' => 0,
        ]);
        $this->insertUser($world, $world['admin_id'], 'Root administrator', $world['admin_email'], true);

        return $world;
    }

    /** @param array{company_id: string, branch_id: string, department_id: string} $world
     * @return array{id: string, email: string}
     */
    private function user(array $world, string $name, string $email): array
    {
        $id = $this->id();
        $this->insertUser($world, $id, $name, $email, false);

        return ['id' => $id, 'email' => $email];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string} $world */
    private function insertUser(array $world, string $id, string $name, string $email, bool $isAdmin): void
    {
        DB::table('access_users')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'active' => true,
            'is_admin' => $isAdmin,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

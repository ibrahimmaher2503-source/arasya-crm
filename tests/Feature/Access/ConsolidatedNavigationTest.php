<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ConsolidatedNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-92!';

    public function test_english_login_preserves_locale_on_the_dashboard_redirect(): void
    {
        $actor = $this->seedActor();
        $this->grant($actor, ['organization.read']);

        $response = $this->post('/login?locale=en', [
            'email' => $actor['email'],
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect('/dashboard?locale=en');
        $this->get('/dashboard?locale=en')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false);
    }

    public function test_employee_create_navigation_requires_read_and_write_permissions(): void
    {
        $actor = $this->seedActor();
        $roleId = $this->grant($actor, ['organization.read', 'employees.write']);
        $this->login($actor['email']);

        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('href="/employees/create?locale=ar"', false);

        DB::table('access_role_permissions')->insert([
            'role_id' => $roleId,
            'permission' => 'employees.read',
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('href="/employees/create?locale=ar"', false);
    }

    /** @return array{company_id: string, branch_id: string, department_id: string, id: string, email: string} */
    private function seedActor(): array
    {
        $companyId = (string) Str::ulid();
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        $id = (string) Str::ulid();
        $email = 'navigation-user@example.test';
        $now = now()->toDateTimeString();

        DB::table('access_companies')->insert([
            'id' => $companyId,
            'name' => 'Arasya Test Company',
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
        DB::table('access_users')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'name' => 'Navigation Test User',
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'id' => $id,
            'email' => $email,
        ];
    }

    /** @param array{company_id: string, id: string} $actor
     * @param  list<string>  $permissions
     */
    private function grant(array $actor, array $permissions): string
    {
        $roleId = (string) Str::ulid();
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $actor['company_id'],
            'name' => 'Navigation Test Role',
            'version' => 0,
        ]);
        DB::table('access_user_roles')->insert(['user_id' => $actor['id'], 'role_id' => $roleId]);
        DB::table('access_role_permissions')->insert(array_map(
            static fn (string $permission): array => ['role_id' => $roleId, 'permission' => $permission],
            $permissions,
        ));

        return $roleId;
    }

    private function login(string $email): void
    {
        $this->post('/login', ['email' => $email, 'password' => self::PASSWORD])->assertRedirect();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DashboardHomeTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-92!';

    public function test_root_and_dashboard_require_login_and_authenticated_root_opens_dashboard(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');

        $actor = $this->seedActor();
        $this->grant($actor, ['organization.read']);
        $this->login($actor['email']);

        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_dashboard_renders_rtl_demo_metrics_and_filters_sidebar_permissions(): void
    {
        $actor = $this->seedActor();
        $this->grant($actor, ['organization.read', 'users.read']);
        $this->seedOrganization('OTHER_COMPANY_REAL_DATA_742');
        $this->login($actor['email']);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('بيانات تجريبية')
            ->assertSee('إجمالي المبيعات')
            ->assertSee('1,248,500')
            ->assertSee('إجمالي المشتريات')
            ->assertSee('892,300')
            ->assertSee('المخزون الحالي')
            ->assertSee('3,420')
            ->assertSee('المستحقات')
            ->assertSee('684,200')
            ->assertSee('href="/access/organization?locale=ar"', false)
            ->assertSee('href="/access/users?locale=ar"', false)
            ->assertDontSee('href="/access/roles?locale=ar"', false)
            ->assertDontSee('href="/access/audit?locale=ar"', false)
            ->assertDontSee('OTHER_COMPANY_REAL_DATA_742');
    }

    public function test_dashboard_rechecks_effective_permissions_after_login(): void
    {
        $actor = $this->seedActor();
        $this->login($actor['email']);

        $this->get('/dashboard')->assertForbidden();

        $roleId = $this->grant($actor, ['organization.read']);
        $this->get('/dashboard')->assertOk();

        DB::table('access_role_permissions')->where('role_id', $roleId)->delete();
        $this->get('/dashboard')->assertForbidden();
    }

    /** @return array{company_id: string, branch_id: string, department_id: string} */
    private function seedOrganization(string $name = 'Arasya Test Company'): array
    {
        $companyId = (string) Str::ulid();
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();

        DB::table('access_companies')->insert([
            'id' => $companyId,
            'name' => $name,
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

        return ['company_id' => $companyId, 'branch_id' => $branchId, 'department_id' => $departmentId];
    }

    /** @return array{company_id: string, branch_id: string, department_id: string, id: string, email: string} */
    private function seedActor(): array
    {
        $organization = $this->seedOrganization();
        $id = (string) Str::ulid();
        $email = 'dashboard.reader@example.test';
        $now = now()->toDateTimeString();

        DB::table('access_users')->insert([
            'id' => $id,
            'company_id' => $organization['company_id'],
            'branch_id' => $organization['branch_id'],
            'department_id' => $organization['department_id'],
            'name' => 'Dashboard Reader',
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $organization + ['id' => $id, 'email' => $email];
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
            'name' => 'Dashboard Reader',
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

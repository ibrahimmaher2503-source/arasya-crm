<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AccessPresentationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-92!';

    public function test_access_screens_render_the_requested_arabic_and_english_directions(): void
    {
        $world = $this->world();
        $this->loginAs($world['email']);
        $screens = [
            ['/access', 'إدارة الوصول', 'Access management'],
            ['/access/organization', 'المؤسسة', 'Organization'],
            ['/access/users', 'المستخدمون', 'Users'],
            ['/access/roles', 'الأدوار', 'Roles'],
            ['/access/audit', 'سجل التدقيق', 'Audit log'],
        ];

        foreach ($screens as [$path, $arabicTitle, $englishTitle]) {
            $arabic = $this->get($path.'?locale=ar')->assertOk();
            $arabic->assertSee('<html lang="ar" dir="rtl">', false)
                ->assertSee('<main id="main-content"', false)
                ->assertSee('<h1', false)
                ->assertSeeText($arabicTitle);
            self::assertSame(1, preg_match_all('/<h1\b/i', $arabic->getContent()));

            $english = $this->get($path.'?locale=en')->assertOk();
            $english->assertSee('<html lang="en" dir="ltr">', false)
                ->assertSee('<main id="main-content"', false)
                ->assertSee('<h1', false)
                ->assertSeeText($englishTitle);
            self::assertSame(1, preg_match_all('/<h1\b/i', $english->getContent()));
        }
    }

    public function test_scoped_summaries_and_role_permissions_use_shared_accessible_components(): void
    {
        $world = $this->world();
        $this->seedDirectoryRows($world);
        $this->loginAs($world['email']);

        $users = $this->get('/access/users?locale=en')->assertOk();
        $users->assertSee('ui-stat-card', false)
            ->assertSeeText('Total users')
            ->assertSeeText('Active')
            ->assertSeeText('Inactive')
            ->assertSeeText('12')
            ->assertSeeText('0');

        $organization = $this->get('/access/organization?locale=en')->assertOk();
        $organization->assertSee('ui-stat-card', false)
            ->assertSeeText('Branches')
            ->assertSeeText('Departments');

        $roles = $this->get('/access/roles?locale=en')->assertOk();
        $roles->assertSee('<fieldset', false)
            ->assertSee('<legend', false)
            ->assertSeeText('Organization')
            ->assertSeeText('Users')
            ->assertSeeText('Roles')
            ->assertSeeText('Audit')
            ->assertSee('value="organization.read"', false)
            ->assertSee('value="users.write"', false)
            ->assertSee('value="roles.read"', false)
            ->assertSee('value="audit.read"', false);
    }

    public function test_directory_filters_and_paginated_tables_keep_accessible_labels_and_headers(): void
    {
        $world = $this->world();
        $this->seedDirectoryRows($world);
        $this->loginAs($world['email']);

        $users = $this->get('/access/users')->assertOk();
        foreach (['q', 'status', 'branch_id', 'department_id', 'role_id', 'per_page', 'sort', 'direction'] as $name) {
            $users->assertSee('name="'.$name.'"', false);
        }

        $roles = $this->get('/access/roles')->assertOk();
        foreach (['q', 'per_page', 'sort', 'direction'] as $name) {
            $roles->assertSee('name="'.$name.'"', false);
        }

        $audit = $this->get('/access/audit')->assertOk();
        foreach (['action', 'actor_id', 'subject_id', 'from', 'to', 'per_page', 'sort', 'direction'] as $name) {
            $audit->assertSee('name="'.$name.'"', false);
        }

        $organization = $this->get('/access/organization')->assertOk();
        $organization->assertSee('branches_page=2', false)->assertSee('departments_page=2', false);

        foreach ([$users, $roles, $audit, $organization] as $page) {
            $page->assertSee('role="region"', false)
                ->assertSee('<caption', false)
                ->assertSee('<th scope="col"', false);
            self::assertSame(
                1,
                preg_match('/aria-label="(?:Pagination|صفحات النتائج)"/u', (string) $page->getContent()),
                'Pagination needs a localized accessible label.',
            );
        }
    }

    /** @return array{company_id: string, branch_id: string, department_id: string, id: string, email: string} */
    private function world(): array
    {
        $companyId = (string) Str::ulid();
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        $userId = (string) Str::ulid();
        $email = 'access-presentation@example.test';
        $now = now()->toDateTimeString();

        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Presentation Company', 'version' => 0]);
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Presentation Branch',
            'code' => 'presentation',
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Presentation Department',
            'code' => 'presentation-dept',
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'name' => 'Presentation Administrator',
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'active' => true,
            'is_admin' => true,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'id' => $userId,
            'email' => $email,
        ];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, id: string, email: string} $world */
    private function seedDirectoryRows(array $world): void
    {
        for ($index = 1; $index <= 11; $index++) {
            $this->insertUser($world, "Directory User {$index}", "directory-{$index}@example.test");

            $roleId = (string) Str::ulid();
            DB::table('access_roles')->insert([
                'id' => $roleId,
                'company_id' => $world['company_id'],
                'name' => "Directory Role {$index}",
                'version' => 0,
            ]);
            DB::table('access_audit')->insert([
                'id' => (string) Str::ulid(),
                'company_id' => $world['company_id'],
                'actor_id' => $world['id'],
                'action' => 'directory.viewed',
                'subject_id' => $roleId,
                'metadata' => '{}',
                'created_at' => now()->subDays($index)->toDateTimeString(),
            ]);

            $branchId = (string) Str::ulid();
            $departmentId = (string) Str::ulid();
            DB::table('access_branches')->insert([
                'id' => $branchId,
                'company_id' => $world['company_id'],
                'name' => "Directory Branch {$index}",
                'code' => "directory-{$index}",
                'active' => true,
                'version' => 0,
            ]);
            DB::table('access_departments')->insert([
                'id' => $departmentId,
                'company_id' => $world['company_id'],
                'branch_id' => $branchId,
                'name' => "Directory Department {$index}",
                'code' => "directory-dept-{$index}",
                'active' => true,
                'version' => 0,
            ]);
        }
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, id: string, email: string} $world */
    private function insertUser(array $world, string $name, string $email): void
    {
        $now = now()->toDateTimeString();
        DB::table('access_users')->insert([
            'id' => (string) Str::ulid(),
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function loginAs(string $email): void
    {
        $this->postJson('/login', ['email' => $email, 'password' => self::PASSWORD])->assertSuccessful();
    }
}

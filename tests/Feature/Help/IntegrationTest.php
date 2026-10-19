<?php

declare(strict_types=1);

namespace Tests\Feature\Help;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_routes_are_registered_without_manual_provider_bootstrapping(): void
    {
        $this->assertTrue(Route::has('help.index'), 'The Help index route should be registered by the application provider list.');
        $this->assertTrue(Route::has('help.articles.show'), 'The Help article route should be registered by the application provider list.');
        $this->assertTrue(Route::has('help.progress.show'), 'The Help progress read route should be registered.');
        $this->assertTrue(Route::has('help.progress.update'), 'The Help progress write route should be registered.');
    }

    public function test_authorized_pages_expose_matching_anchors_and_tour_config(): void
    {
        $actor = $this->seedActor($this->seedOrganization());
        $session = $this->sessionFor($actor);

        $pages = [
            ['/access/users', 'access-users', 'access.users.filters'],
            ['/access/organization', 'access-organization', 'access.organization.filters'],
            ['/access/roles', 'access-roles', 'access.roles.filters'],
            ['/access/audit', 'access-audit', 'access.audit.filters'],
            ['/employees', 'employees-directory', 'employees.filters'],
            ['/employees/workers', 'employees-workers', 'employees.workers.filters'],
            ['/crm', 'crm-overview', 'crm.overview.nav'],
            ['/crm/leads', 'crm-leads-list', 'crm.leads.filters'],
        ];

        foreach ($pages as [$path, $tourId, $anchor]) {
            $response = $this->withSession($session)->withHeaders(['Accept' => 'text/html'])->get($path.'?locale=en');
            $this->assertSame(200, $response->getStatusCode(), 'Expected a rendered page for '.$path.'.');
            $response->assertSee('data-tour="'.$anchor.'"', false);
            $response->assertSee('data-tour-id="'.$tourId.'"', false);
            $response->assertSee('data-product-tour-config', false);
        }
    }

    public function test_dashboard_tour_is_integrated_and_autostarts(): void
    {
        $actor = $this->seedActor($this->seedOrganization());
        $response = $this->withSession($this->sessionFor($actor))->get('/dashboard?locale=en')->assertOk();

        $response->assertSee('data-tour="dashboard.heading"', false)
            ->assertSee('data-tour-id="dashboard"', false)
            ->assertSee('data-tour-autostart', false);
    }

    public function test_pages_without_the_permission_expose_no_tour_for_that_screen(): void
    {
        $organization = $this->seedOrganization();
        $actor = $this->seedActor($organization, admin: false);
        DB::table('access_user_permissions')->insert([
            'user_id' => $actor['id'],
            'permission' => 'organization.read',
            'allowed' => true,
        ]);

        $response = $this->withSession($this->sessionFor($actor))->get('/access/organization?locale=en')->assertOk();
        $response->assertSee('data-tour-id="access-organization"', false)
            ->assertDontSee('data-tour-id="access-users"', false)
            ->assertDontSee('data-tour-id="access-roles"', false);

        $this->withSession($this->sessionFor($actor))->get('/access/users?locale=en')->assertForbidden();
    }

    /** @return array{company_id: string, branch_id: string, department_id: string} */
    private function seedOrganization(): array
    {
        $companyId = (string) Str::ulid();
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();

        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Integrated Help Company', 'version' => 0]);
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Integrated Help Branch',
            'code' => 'int-'.Str::lower(substr($companyId, 0, 8)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Integrated Help Department',
            'code' => 'int-'.Str::lower(substr($departmentId, 0, 8)),
            'active' => true,
            'version' => 0,
        ]);

        return ['company_id' => $companyId, 'branch_id' => $branchId, 'department_id' => $departmentId];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string} $organization
     * @return array{id: string, company_id: string, branch_id: string, department_id: string}
     */
    private function seedActor(array $organization, bool $admin = true): array
    {
        $id = (string) Str::ulid();
        $now = now()->toDateTimeString();
        DB::table('access_users')->insert([
            'id' => $id,
            'company_id' => $organization['company_id'],
            'branch_id' => $organization['branch_id'],
            'department_id' => $organization['department_id'],
            'name' => 'Integrated Help Actor',
            'email' => 'int-'.Str::lower($id).'@example.test',
            'password' => Hash::make('help-test-only'),
            'active' => true,
            'is_admin' => $admin,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $id] + $organization;
    }

    /** @param array{id: string, company_id: string, branch_id: string, department_id: string} $actor
     * @return array{access_user_id: string, access_session_version: int}
     */
    private function sessionFor(array $actor): array
    {
        return ['access_user_id' => $actor['id'], 'access_session_version' => 0];
    }
}

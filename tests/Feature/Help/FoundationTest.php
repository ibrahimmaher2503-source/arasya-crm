<?php

declare(strict_types=1);

namespace Tests\Feature\Help;

use App\Providers\HelpServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(HelpServiceProvider::class);
    }

    public function test_progress_requires_a_verified_persisted_access_session(): void
    {
        $this->getJson('/help/api/progress/help-center')
            ->assertUnauthorized()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->withSession(['access_user_id' => (string) Str::ulid(), 'access_session_version' => 0])
            ->getJson('/help/api/progress/help-center')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_save_and_read_only_their_tour_progress(): void
    {
        $actor = $this->seedAdmin();
        $session = ['access_user_id' => $actor['id'], 'access_session_version' => 0];

        $this->withSession($session)->patchJson('/help/api/progress/tasks-list', [
            'version' => 1,
            'step_key' => 'filters',
            'status' => 'in_progress',
        ])->assertOk()
            ->assertJsonPath('data.tour_id', 'tasks-list')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.step_key', 'filters')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->withSession($session)->getJson('/help/api/progress/tasks-list')
            ->assertOk()
            ->assertJsonPath('data.step_key', 'filters')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.company_id')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertDatabaseCount('tasks_cycle', 0);
        $this->assertDatabaseCount('crm_leads', 0);
    }

    public function test_progress_is_scoped_to_the_current_persisted_user_and_company(): void
    {
        $first = $this->seedAdmin();
        $second = $this->seedAdmin();
        $firstSession = ['access_user_id' => $first['id'], 'access_session_version' => 0];
        $secondSession = ['access_user_id' => $second['id'], 'access_session_version' => 0];

        $this->withSession($firstSession)->patchJson('/help/api/progress/tasks-list', [
            'version' => 1,
            'step_key' => 'filters',
            'status' => 'paused',
        ])->assertOk();

        $this->withSession($secondSession)->getJson('/help/api/progress/tasks-list')
            ->assertOk()
            ->assertJsonPath('data.step_key', null)
            ->assertJsonPath('data.status', null);
    }

    public function test_unknown_tours_and_extra_payload_keys_are_rejected_without_persistence(): void
    {
        $actor = $this->seedAdmin();
        $session = ['access_user_id' => $actor['id'], 'access_session_version' => 0];

        $this->withSession($session)->getJson('/help/api/progress/not-registered')
            ->assertNotFound()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->withSession($session)->patchJson('/help/api/progress/tasks-list', [
            'version' => 1,
            'step_key' => 'filters',
            'status' => 'in_progress',
            'user_id' => (string) Str::ulid(),
        ])->assertUnprocessable()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertDatabaseCount('tutorial_progress', 0);
    }

    public function test_invalid_step_and_stale_tour_version_are_rejected(): void
    {
        $actor = $this->seedAdmin();
        $session = ['access_user_id' => $actor['id'], 'access_session_version' => 0];

        $this->withSession($session)->patchJson('/help/api/progress/tasks-list', [
            'version' => 1,
            'step_key' => 'raw-selector',
            'status' => 'in_progress',
        ])->assertUnprocessable();

        $this->withSession($session)->patchJson('/help/api/progress/tasks-list', [
            'version' => 2,
            'step_key' => 'filters',
            'status' => 'in_progress',
        ])->assertConflict()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertDatabaseCount('tutorial_progress', 0);
    }

    public function test_permission_revocation_blocks_progress_reads_and_updates(): void
    {
        $actor = $this->seedAdmin();
        $session = ['access_user_id' => $actor['id'], 'access_session_version' => 0];
        $payload = ['version' => 1, 'step_key' => 'filters', 'status' => 'in_progress'];

        $this->withSession($session)->patchJson('/help/api/progress/tasks-list', $payload)->assertOk();
        DB::table('access_user_permissions')->insert([
            'user_id' => $actor['id'],
            'permission' => 'tasks.read',
            'allowed' => false,
        ]);

        $this->withSession($session)->getJson('/help/api/progress/tasks-list')
            ->assertForbidden()
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->withSession($session)->patchJson('/help/api/progress/tasks-list', $payload)
            ->assertForbidden()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_old_version_progress_resets_to_the_current_tour_defaults(): void
    {
        $actor = $this->seedAdmin();
        $session = ['access_user_id' => $actor['id'], 'access_session_version' => 0];
        $this->withSession($session)->patchJson('/help/api/progress/tasks-list', [
            'version' => 1,
            'step_key' => 'filters',
            'status' => 'paused',
        ])->assertOk();

        DB::table('tutorial_progress')->where('user_id', $actor['id'])->update(['version' => 0]);

        $this->withSession($session)->getJson('/help/api/progress/tasks-list')
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.step_key', null)
            ->assertJsonPath('data.status', null);
    }

    /** @return array{id: string, company_id: string} */
    private function seedAdmin(): array
    {
        $companyId = (string) Str::ulid();
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        $userId = (string) Str::ulid();
        $now = now()->toDateTimeString();

        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Help Test Company', 'version' => 0]);
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Help Test Branch',
            'code' => 'help-test',
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Help Test Department',
            'code' => 'help-test',
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'name' => 'Help Test Admin',
            'email' => 'help-'.Str::lower($userId).'@example.test',
            'password' => Hash::make('help-test-only'),
            'active' => true,
            'is_admin' => true,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $userId, 'company_id' => $companyId];
    }
}

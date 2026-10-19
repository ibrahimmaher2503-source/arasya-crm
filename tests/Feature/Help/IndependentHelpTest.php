<?php

declare(strict_types=1);

namespace Tests\Feature\Help;

use App\Providers\HelpServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class IndependentHelpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(HelpServiceProvider::class);
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    public function test_help_index_uses_requested_arabic_rtl_and_english_ltr_locales(): void
    {
        $actor = $this->seedActor($this->seedOrganization());

        $arabic = $this->withSession($this->sessionFor($actor))->get('/help?locale=ar')->assertOk();
        $arabic->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('مركز المساعدة');

        $english = $this->withSession($this->sessionFor($actor))->get('/help?locale=en')->assertOk();
        $english->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Help Center');
    }

    public function test_help_index_has_only_one_primary_heading(): void
    {
        $actor = $this->seedActor($this->seedOrganization());
        $response = $this->withSession($this->sessionFor($actor))->get('/help?locale=en')->assertOk();

        preg_match_all('/<h1\b/i', (string) $response->getContent(), $headings);
        $this->assertCount(1, $headings[0], 'The shell and Help content should expose one page-level heading.');
    }

    public function test_every_authorized_help_entry_has_a_successful_guide_route(): void
    {
        $actor = $this->seedActor($this->seedOrganization());
        $index = $this->withSession($this->sessionFor($actor))->get('/help?locale=en')->assertOk();
        $html = (string) $index->getContent();
        preg_match_all('/data-help-page="([^"]+)"/', $html, $pageMatches);
        preg_match_all('/data-help-article="([^"]+)"/', $html, $articleMatches);
        $ids = array_values(array_unique(array_merge($pageMatches[1], $articleMatches[1])));
        $this->assertNotEmpty($ids, 'The authorized Help index should expose guide links.');

        $statuses = [];
        foreach ($ids as $id) {
            $response = $this->withSession($this->sessionFor($actor))
                ->get('/help/articles/'.rawurlencode($id).'?locale=en');
            $statuses[$id] = $response->getStatusCode();
        }

        $this->assertSame(array_fill_keys($ids, 200), $statuses, 'Each currently authorized Help entry should render its guide.');
    }

    public function test_search_escapes_markup_enforces_its_length_and_filters_denied_content(): void
    {
        $organization = $this->seedOrganization();
        $actor = $this->seedActor($organization);
        $session = $this->sessionFor($actor);
        $hostile = '"><img src=x onerror=alert(1)>';

        $this->withSession($session)->get('/help?locale=en&q='.rawurlencode($hostile))
            ->assertOk()
            ->assertSee(e($hostile), false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);

        $this->withSession($session)->get('/help?locale=en&q='.rawurlencode(str_repeat('x', 120)))
            ->assertOk()
            ->assertSee('maxlength="120"', false);

        $this->withSession($session)->get('/help?locale=en&q='.rawurlencode(str_repeat('x', 121)))
            ->assertRedirect()
            ->assertSessionHasErrors('q');

        DB::table('access_user_permissions')->insert([
            'user_id' => $actor['id'],
            'permission' => 'crm.read',
            'allowed' => false,
        ]);

        $this->withSession($session)->get('/help?locale=en&q='.rawurlencode('CRM transfer inbox'))
            ->assertOk()
            ->assertDontSee('data-help-article="transfer-inbox"', false)
            ->assertDontSee('data-help-page="crm-leads-list"', false);
        $this->withSession($session)->get('/help/articles/transfer-inbox?locale=en')->assertNotFound();
    }

    public function test_action_guidance_is_omitted_when_the_effective_write_permission_is_missing(): void
    {
        $organization = $this->seedOrganization();
        $actor = $this->seedActor($organization, admin: false);
        DB::table('access_user_permissions')->insert([
            'user_id' => $actor['id'],
            'permission' => 'organization.read',
            'allowed' => true,
        ]);

        $response = $this->withSession($this->sessionFor($actor))->get('/help?locale=en')->assertOk();
        $response->assertSee('data-help-page="access-organization"', false)
            ->assertDontSee('data-help-action="access-organization.edit"', false)
            ->assertDontSee('Editing company, branch, or department data requires organization.write.');
    }

    public function test_tour_progress_is_idempotent_and_never_mutates_business_or_audit_tables(): void
    {
        $actor = $this->seedActor($this->seedOrganization());
        $session = $this->sessionFor($actor);
        $businessTables = [
            'access_audit',
            'tasks_cycle',
            'tasks_cycle_audit',
            'tasks_cycle_evidence',
            'crm_customers',
            'crm_leads',
            'crm_activities',
            'crm_transfers',
            'crm_audit',
        ];
        $before = $this->tableCounts($businessTables);

        $this->withSession($session)->get('/help?locale=en')->assertOk();
        $payload = ['version' => 1, 'step_key' => 'welcome', 'status' => 'paused'];
        $this->withSession($session)->patchJson('/help/api/progress/help-center', $payload)
            ->assertOk()
            ->assertJsonPath('data.tour_id', 'help-center')
            ->assertJsonPath('data.step_key', 'welcome')
            ->assertJsonPath('data.status', 'paused');
        $this->withSession($session)->patchJson('/help/api/progress/help-center', $payload)->assertOk();

        $this->assertDatabaseCount('tutorial_progress', 1);
        $this->assertSame($before, $this->tableCounts($businessTables));
        $this->withSession($session)->getJson('/help/api/progress/help-center')
            ->assertOk()
            ->assertJsonPath('data.step_key', 'welcome')
            ->assertJsonPath('data.status', 'paused')
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.company_id');
    }

    public function test_progress_isolated_between_users_in_one_company_and_across_companies(): void
    {
        $company = $this->seedOrganization();
        $first = $this->seedActor($company);
        $sameCompany = $this->seedActor($company);
        $foreign = $this->seedActor($this->seedOrganization());
        $payload = ['version' => 1, 'step_key' => 'search', 'status' => 'in_progress'];

        $this->withSession($this->sessionFor($first))->patchJson('/help/api/progress/help-center', $payload)->assertOk();
        foreach ([$sameCompany, $foreign] as $other) {
            $this->withSession($this->sessionFor($other))->getJson('/help/api/progress/help-center')
                ->assertOk()
                ->assertJsonPath('data.step_key', null)
                ->assertJsonPath('data.status', null);
        }
        $this->withSession($this->sessionFor($first))->getJson('/help/api/progress/help-center')
            ->assertOk()
            ->assertJsonPath('data.step_key', 'search')
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseCount('tutorial_progress', 1);
    }

    public function test_revoked_persisted_session_cannot_read_or_change_tour_progress(): void
    {
        $actor = $this->seedActor($this->seedOrganization());
        $session = $this->sessionFor($actor);
        $payload = ['version' => 1, 'step_key' => 'welcome', 'status' => 'paused'];
        $this->withSession($session)->patchJson('/help/api/progress/help-center', $payload)->assertOk();

        DB::table('access_users')->where('id', $actor['id'])->update(['session_version' => 1]);

        $this->withSession($session)->getJson('/help/api/progress/help-center')->assertUnauthorized();
        $this->withSession($session)->patchJson('/help/api/progress/help-center', $payload)->assertUnauthorized();
        $this->assertDatabaseCount('tutorial_progress', 1);
    }

    public function test_unknown_stale_invalid_oversize_and_nonfinal_completion_requests_are_rejected(): void
    {
        $actor = $this->seedActor($this->seedOrganization());
        $session = $this->sessionFor($actor);
        $base = ['version' => 1, 'step_key' => 'welcome', 'status' => 'paused'];

        $this->withSession($session)->getJson('/help/api/progress/not-registered')->assertNotFound();
        $this->withSession($session)->patchJson('/help/api/progress/not-registered', $base)->assertNotFound();
        $this->withSession($session)->patchJson('/help/api/progress/help-center', $base + ['user_id' => (string) Str::ulid()])->assertUnprocessable();
        $this->withSession($session)->patchJson('/help/api/progress/help-center', ['version' => 2, 'step_key' => 'welcome', 'status' => 'paused'])->assertConflict();
        $this->withSession($session)->patchJson('/help/api/progress/help-center', ['version' => 1, 'step_key' => 'unknown-step', 'status' => 'paused'])->assertUnprocessable();
        $this->withSession($session)->patchJson('/help/api/progress/help-center', ['version' => 1, 'step_key' => 'welcome', 'status' => 'unknown-status'])->assertUnprocessable();
        $this->withSession($session)->patchJson('/help/api/progress/help-center', ['version' => 1, 'step_key' => 'search', 'status' => 'completed'])->assertUnprocessable();

        $this->withSession($session)->call(
            'PATCH',
            '/help/api/progress/help-center',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            str_repeat('x', 513),
        )->assertStatus(413);

        $this->assertDatabaseCount('tutorial_progress', 0);
    }

    /** @return array{company_id: string, branch_id: string, department_id: string} */
    private function seedOrganization(): array
    {
        $companyId = (string) Str::ulid();
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();

        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Independent Help Company', 'version' => 0]);
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Independent Help Branch',
            'code' => 'help-'.Str::lower(substr($companyId, 0, 8)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Independent Help Department',
            'code' => 'help-'.Str::lower(substr($departmentId, 0, 8)),
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
            'name' => 'Independent Help Actor',
            'email' => 'help-'.Str::lower($id).'@example.test',
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

    /** @param list<string> $tables
     * @return array<string, int>
     */
    private function tableCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}

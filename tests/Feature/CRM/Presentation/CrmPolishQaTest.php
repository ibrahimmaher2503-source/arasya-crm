<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use App\Modules\CRM\Application\Runtime\LeadDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class CrmPolishQaTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_workspace_has_scoped_metrics_metadata_and_persisted_priority_without_private_contacts(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->createLead($world, 'Workspace prospect');
        $request = Request::create('/crm/leads', 'PATCH');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->replace($this->sessionFor($world['manager_id']));
        app(LeadDirectory::class)->save($request, ['priority' => 'high', 'version' => 0], $leadId);
        $this->assertDatabaseHas('crm_leads', ['id' => $leadId, 'priority' => 'high', 'version' => 1]);
        $this->html($world['manager_id'], '/crm/leads?locale=en&priority=high')
            ->assertOk()->assertSee('data-directory-workspace', false)
            ->assertSee('Total leads')->assertSee('New this month')->assertSee('Conversion rate')
            ->assertSee('Next follow-up')->assertSee('Workspace prospect')->assertSee('High')
            ->assertSee('crm-create-drawer', false)->assertDontSee('valid-contact@example.test');
        $this->html($world['manager_id'], '/crm/leads?locale=en&priority=low')
            ->assertOk()->assertDontSee('Workspace prospect');
        $this->html($world['manager_id'], '/crm/leads?locale=en&priority=invalid')->assertStatus(422);
    }

    public function test_edit_form_keeps_the_company_scoped_current_stage_label_when_it_is_off_the_option_page(): void
    {
        $world = $this->seedWorld();
        foreach (range(1, 25) as $number) {
            $this->insertStage($world['company_id'], sprintf('Stage %03d', $number));
        }
        $selectedStageId = $this->insertStage($world['company_id'], 'ZZZ Current Company Stage');

        $foreignCompanyId = $this->id();
        DB::table('access_companies')->insert([
            'id' => $foreignCompanyId,
            'name' => 'Synthetic Foreign CRM Company',
            'version' => 0,
        ]);
        $this->insertStage($foreignCompanyId, 'ZZZ Foreign Company Stage');

        $leadId = $this->createLead($world, 'Stage Label Prospect', $selectedStageId);
        $response = $this->html($world['manager_id'], '/crm/leads/'.$leadId.'/edit?locale=en&stage_page=1')
            ->assertOk();

        $response->assertSee('<option value="'.$selectedStageId.'" selected>ZZZ Current Company Stage</option>', false);
        $response->assertDontSee('ZZZ Foreign Company Stage', false);
    }

    public function test_lead_pages_resolve_off_page_current_and_replaced_stage_labels_without_changing_history(): void
    {
        $world = $this->seedWorld();
        foreach (range(1, 25) as $number) {
            $this->insertStage($world['company_id'], sprintf('Stage %03d', $number));
        }

        $historicalLabel = 'Historical Replaced Stage';
        $historicalStageId = $this->insertStage($world['company_id'], $historicalLabel);
        $historicalLeadId = $this->createLead($world, 'Historical Stage Prospect', $historicalStageId);
        $replacementLabel = 'ZZZ Replacement Stage';
        $replacementStageId = $this->insertStage($world['company_id'], $replacementLabel);
        DB::table('crm_taxonomy')->where('id', $replacementStageId)->update(['replaces_id' => $historicalStageId]);

        $currentLabel = 'ZZZ Current Company Stage';
        $currentStageId = $this->insertStage($world['company_id'], $currentLabel);
        $currentLeadId = $this->createLead($world, 'Current Stage Prospect', $currentStageId);

        $pages = [
            ['/crm/leads?locale=en&per_page=25&stage_page=1', [$currentLabel, $historicalLabel]],
            ['/crm/leads/'.$currentLeadId.'?locale=en&stage_page=1', [$currentLabel]],
            ['/crm/leads/'.$historicalLeadId.'?locale=en&stage_page=1', [$historicalLabel]],
        ];

        foreach ($pages as [$uri, $expectedLabels]) {
            $response = $this->html($world['manager_id'], $uri)->assertOk();
            foreach ($expectedLabels as $label) {
                $response->assertSee($label, false);
            }
            $response->assertDontSee($replacementLabel, false);
        }

        $this->assertDatabaseHas('crm_taxonomy', [
            'id' => $replacementStageId,
            'company_id' => $world['company_id'],
            'replaces_id' => $historicalStageId,
        ]);
        $this->assertDatabaseHas('crm_leads', ['id' => $historicalLeadId, 'stage_id' => $historicalStageId]);
    }

    public function test_html_detail_does_not_render_after_the_persisted_session_is_revoked(): void
    {
        $world = $this->seedWorld();
        $leadName = 'Revoked Session CRM Prospect';
        $leadId = $this->createLead($world, $leadName);
        $staleSession = $this->sessionFor($world['manager_id']);
        DB::table('access_users')->where('id', $world['manager_id'])->update(['session_version' => 1]);

        $response = $this->withSession($staleSession)
            ->withHeaders(['Accept' => 'text/html'])
            ->get('/crm/leads/'.$leadId.'?locale=en')
            ->assertUnauthorized()
            ->assertDontSee($leadName);

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function test_json_create_rejects_an_unknown_field_name_without_echoing_its_sensitive_literal(): void
    {
        $world = $this->seedWorld();
        $privateLiteral = 'private-contact@example.test';
        $leadCount = DB::table('crm_leads')->count();
        $auditCount = DB::table('crm_audit')->count();

        $response = $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/leads', $this->leadPayload('Unknown Field Probe') + [$privateLiteral => 'unsupported field'])
            ->assertUnprocessable()
            ->assertDontSee($privateLiteral);

        self::assertSame($leadCount, DB::table('crm_leads')->count());
        self::assertSame($auditCount, DB::table('crm_audit')->count());
        $this->assertDatabaseMissing('crm_leads', ['name' => 'Unknown Field Probe']);
    }

    public function test_html_detail_does_not_copy_unknown_query_values_into_option_search_forms(): void
    {
        $world = $this->seedWorld();
        DB::table('access_user_permissions')->insert([
            'user_id' => $world['manager_id'],
            'permission' => 'crm.assign',
            'allowed' => true,
        ]);
        $leadId = $this->createLead($world, 'Query Reflection Prospect');
        $privateLiteral = 'synthetic-contact@example.test';

        $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/leads/'.$leadId.'?private='.$privateLiteral)
            ->assertUnprocessable()
            ->assertDontSee($privateLiteral);

        $response = $this->html($world['manager_id'], '/crm/leads/'.$leadId.'?locale=en&private='.$privateLiteral)
            ->assertOk()
            ->assertSee('class="crm-option-search"', false)
            ->assertDontSee('name="private"', false)
            ->assertDontSee($privateLiteral);
    }

    /** @return array{company_id: string, branch_id: string, department_id: string, manager_id: string} */
    private function seedWorld(): array
    {
        $companyId = $this->id();
        $branchId = $this->id();
        $departmentId = $this->id();
        $roleId = $this->id();
        $userId = $this->id();

        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Synthetic CRM QA Company', 'version' => 0]);
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Synthetic CRM QA Branch',
            'code' => 'crm-'.strtolower(substr($branchId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Synthetic CRM QA Department',
            'code' => 'crm-'.strtolower(substr($departmentId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $companyId,
            'name' => 'Synthetic CRM QA Manager',
            'version' => 0,
        ]);
        foreach (['crm.read', 'crm.manage'] as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'name' => 'Synthetic CRM QA Manager',
            'email' => 'crm-qa-'.$userId.'@example.test',
            'password' => Hash::make('unused-crm-qa-password'),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'manager_id' => $userId,
        ];
    }

    private function insertStage(string $companyId, string $label): string
    {
        $id = $this->id();
        DB::table('crm_taxonomy')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'kind' => 'stage',
            'label' => $label,
            'replaces_id' => null,
            'created_at' => now(),
        ]);

        return $id;
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id: string} $world */
    private function createLead(array $world, string $name, ?string $stageId = null): string
    {
        $request = Request::create('/crm/leads', 'POST');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->replace($this->sessionFor($world['manager_id']));

        return (string) app(LeadDirectory::class)->save($request, [
            'name' => $name,
            'organization' => 'Synthetic CRM QA Organization',
            'source' => 'website',
            'stage_id' => $stageId,
        ])['id'];
    }

    /** @return array<string, mixed> */
    private function leadPayload(string $name): array
    {
        return [
            'name' => $name,
            'organization' => 'Synthetic CRM QA Organization',
            'source' => 'website',
            'contacts' => ['email' => 'valid-contact@example.test'],
        ];
    }

    /** @return array{access_user_id: string, access_session_version: int} */
    private function sessionFor(string $userId): array
    {
        return [
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ];
    }

    private function html(string $userId, string $uri): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))
            ->withHeaders(['Accept' => 'text/html'])
            ->get($uri);
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }
}

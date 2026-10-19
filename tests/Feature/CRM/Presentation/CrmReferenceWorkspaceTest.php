<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use App\Modules\CRM\Application\Runtime\LeadDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class CrmReferenceWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_workspace_read_keeps_the_company_serialization_lock(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('FOR UPDATE serialization is verified on native MariaDB.');
        }
        $world = $this->seedWorld();
        $lead = $this->fixtureId(999);
        $this->seedLead($world, $lead, 'Serialized workspace read');
        DB::enableQueryLog();
        $this->workspaceData($world, $lead, 'activities', []);
        $locks = array_filter(DB::getQueryLog(), static fn ($query): bool => str_contains($query['query'], 'access_companies') && str_contains(strtolower($query['query']), 'for update'));
        self::assertNotEmpty($locks, 'A protected CRM read must still serialize with authorization writes.');
    }

    public function test_lead_overview_has_a_bounded_timeline_without_unrelated_widgets_or_private_contacts(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(501);
        $this->seedLead($world, $leadId, 'Overview prospect');
        $outcomeId = $this->seedOutcome($world);
        foreach (range(1, 8) as $number) {
            $this->seedActivity($world, $leadId, $outcomeId, [
                'id' => $this->fixtureId(800 + $number),
                'kind' => $number <= 4 ? 'note' : 'call',
                'next_step' => 'Overview next step '.$number,
            ]);
        }
        $foreign = $this->seedScope($world['company_id'], 'Foreign overview branch');
        $this->seedLead($world, $this->fixtureId(502), 'Foreign overview prospect');
        DB::table('crm_leads')->where('id', $this->fixtureId(502))->update($foreign);

        foreach (['ar', 'en'] as $locale) {
            $response = $this->html($world['manager_id'], '/crm/leads/'.$leadId.'?locale='.$locale)
                ->assertOk()->assertSee('data-lead-overview', false)
                ->assertDontSeeText('Foreign overview prospect')
                ->assertViewHas('activitySummary', fn (array $summary): bool => ($summary['kind_counts']['note'] ?? 0) === 4)
                ->assertViewHas('activities', fn ($rows): bool => $rows->total() === 8)
                ->assertDontSee('crm-overview-stage-chart', false)
                ->assertDontSee('crm-overview-source-chart', false)
                ->assertDontSee('crm-overview-recent', false)
                ->assertViewMissing('overview')->assertViewMissing('recentLeads');
            self::assertSame(6, substr_count((string) $response->getContent(), 'class="crm-overview-event '));
            $response->assertDontSee('PRIVATE-WORKSPACE-PHONE', false)
                ->assertDontSee('private-workspace@example.test', false);
        }
    }

    public function test_workspace_sections_render_shared_shell_lead_header_and_navigation_in_both_locales(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(1);
        $this->seedLead($world, $leadId, 'Synthetic Workspace Lead');
        $outcomeId = $this->seedOutcome($world);
        $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(101),
            'kind' => 'call',
            'next_step' => 'WORKSPACE_CALL_ACTIVITY',
        ]);
        $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(102),
            'kind' => 'note',
            'note' => 'WORKSPACE_NOTE_ACTIVITY',
            'next_step' => 'WORKSPACE_NOTE_NEXT_STEP',
        ]);
        $taskId = $this->seedTask($world, 'CRM follow-up task title', 'in_progress');
        $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(103),
            'kind' => 'meeting',
            'next_step' => 'WORKSPACE_TASK_ACTIVITY',
            'task_id' => $taskId,
        ]);

        $rowsBySection = [
            'activities' => ['WORKSPACE_CALL_ACTIVITY', 'WORKSPACE_NOTE_NEXT_STEP', 'WORKSPACE_TASK_ACTIVITY'],
            'notes' => ['WORKSPACE_NOTE_NEXT_STEP'],
            'tasks' => ['WORKSPACE_TASK_ACTIVITY'],
        ];

        foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction) {
            foreach ($rowsBySection as $section => $visibleRows) {
                $response = $this->html(
                    $world['manager_id'],
                    '/crm/leads/'.$leadId.'/workspace/'.$section.'?locale='.$locale,
                )->assertOk()
                    ->assertSee('<html lang="'.$locale.'" dir="'.$direction.'">', false)
                    ->assertSee('<main id="main-content"', false)
                    ->assertSee('<table', false)
                    ->assertSeeText('Synthetic Workspace Lead');

                $html = (string) $response->getContent();
                self::assertSame(1, preg_match_all('/<h1\b/i', $html));

                foreach (['activities', 'notes', 'tasks'] as $navigationSection) {
                    $response->assertSee('/workspace/'.$navigationSection, false);
                }
                foreach ($visibleRows as $marker) {
                    $response->assertSeeText($marker);
                }
                foreach (array_diff(
                    ['WORKSPACE_CALL_ACTIVITY', 'WORKSPACE_NOTE_NEXT_STEP', 'WORKSPACE_TASK_ACTIVITY'],
                    $visibleRows,
                ) as $marker) {
                    $response->assertDontSeeText($marker);
                }
            }
        }
    }

    public function test_workspace_service_counts_all_lead_activities_and_paginates_stably_without_contacts(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(2);
        $this->seedLead($world, $leadId, 'Synthetic Pagination Lead');
        $outcomeId = $this->seedOutcome($world);

        foreach (range(1, 27) as $number) {
            $this->seedActivity($world, $leadId, $outcomeId, [
                'id' => $this->fixtureId(100 + $number),
                'kind' => 'call',
                'next_step' => sprintf('PAGINATION-ROW-%02d', $number),
                'occurred_at' => '2026-10-17 12:00:00',
                'created_at' => '2026-10-17 12:00:00',
            ]);
        }
        $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(150),
            'kind' => 'note',
            'note' => 'PAGINATION-NOTE-ROW',
            'next_step' => 'PAGINATION-NOTE-NEXT',
        ]);

        $otherScope = $this->seedScope($world['company_id'], 'Workspace hidden branch');
        $hiddenLeadId = $this->fixtureId(3);
        $this->seedLead($world, $hiddenLeadId, 'Hidden Workspace Lead', $otherScope);
        $this->seedActivity($world, $hiddenLeadId, $outcomeId, [
            'id' => $this->fixtureId(151),
            'kind' => 'note',
            'note' => 'HIDDEN-WORKSPACE-ACTIVITY',
        ]);

        $firstPage = $this->workspaceData($world, $leadId, 'activities', ['page' => 1, 'per_page' => 25]);
        $secondPage = $this->workspaceData($world, $leadId, 'activities', ['page' => 2, 'per_page' => 25]);
        $repeatedFirstPage = $this->workspaceData($world, $leadId, 'activities', ['page' => 1, 'per_page' => 25]);

        self::assertSame(28, $firstPage['meta']['total']);
        self::assertSame(1, $firstPage['meta']['page']);
        self::assertSame(25, $firstPage['meta']['per_page']);
        self::assertSame(25, count($firstPage['data']));
        self::assertSame(3, count($secondPage['data']));
        self::assertSame(27, $firstPage['kind_counts']['call']);
        self::assertSame(1, $firstPage['kind_counts']['note']);

        $firstIds = array_column($firstPage['data'], 'id');
        $secondIds = array_column($secondPage['data'], 'id');
        self::assertSame($firstIds, array_column($repeatedFirstPage['data'], 'id'));
        self::assertSame(28, count(array_unique(array_merge($firstIds, $secondIds))));
        self::assertSame([], array_values(array_intersect($firstIds, $secondIds)));

        foreach ($firstPage['data'] as $row) {
            self::assertSame('Synthetic CRM Workspace Manager', $row['actor_name']);
            self::assertArrayNotHasKey('contacts', $row);
            self::assertArrayNotHasKey('lead_contacts', $row);
        }

        self::assertStringNotContainsString(
            'PRIVATE-WORKSPACE-PHONE',
            json_encode($firstPage['data'], JSON_THROW_ON_ERROR),
        );
    }

    public function test_workspace_search_kind_notes_and_task_filters_use_only_the_lead_activity_contract(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(4);
        $this->seedLead($world, $leadId, 'Synthetic Filter Lead');
        $outcomeId = $this->seedOutcome($world);
        $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(401),
            'kind' => 'call',
            'note' => 'SEARCH-NOTE-MATCH',
            'next_step' => 'FILTER-CALL-NOTE',
        ]);
        $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(402),
            'kind' => 'call',
            'outcome_label' => 'SEARCH-OUTCOME-MATCH',
            'next_step' => 'FILTER-CALL-OUTCOME',
        ]);
        $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(403),
            'kind' => 'meeting',
            'next_step' => 'SEARCH-NEXT-STEP-MATCH',
        ]);
        $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(404),
            'kind' => 'note',
            'note' => 'NOTE-SECTION-ROW',
            'next_step' => 'NOTE-SECTION-NEXT',
        ]);

        $linkedTaskId = $this->seedTask($world, 'Linked synthetic CRM task', 'in_progress');
        $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(405),
            'kind' => 'meeting',
            'next_step' => 'TASK-SECTION-ROW',
            'task_id' => $linkedTaskId,
        ]);
        $this->seedTask($world, 'UNRELATED_TASK_MUST_NOT_APPEAR', 'pending');

        foreach ([
            'SEARCH-NOTE-MATCH' => 'FILTER-CALL-NOTE',
            'SEARCH-OUTCOME-MATCH' => 'FILTER-CALL-OUTCOME',
            'SEARCH-NEXT-STEP-MATCH' => 'SEARCH-NEXT-STEP-MATCH',
        ] as $query => $expectedRow) {
            $this->html(
                $world['manager_id'],
                '/crm/leads/'.$leadId.'/workspace/activities?'.http_build_query(['locale' => 'en', 'q' => $query]),
            )->assertOk()
                ->assertSeeText($expectedRow)
                ->assertDontSeeText('NOTE-SECTION-ROW')
                ->assertDontSeeText('TASK-SECTION-ROW');
        }

        $this->html($world['manager_id'], '/crm/leads/'.$leadId.'/workspace/activities?locale=en&kind=call')
            ->assertOk()
            ->assertSeeText('FILTER-CALL-NOTE')
            ->assertSeeText('FILTER-CALL-OUTCOME')
            ->assertDontSeeText('SEARCH-NEXT-STEP-MATCH')
            ->assertDontSeeText('NOTE-SECTION-ROW');

        $this->html($world['manager_id'], '/crm/leads/'.$leadId.'/workspace/notes?locale=en&kind=call')
            ->assertOk()
            ->assertSeeText('NOTE-SECTION-ROW')
            ->assertDontSeeText('FILTER-CALL-NOTE')
            ->assertDontSeeText('TASK-SECTION-ROW');

        $this->html($world['manager_id'], '/crm/leads/'.$leadId.'/workspace/tasks?locale=en')
            ->assertOk()
            ->assertSeeText('TASK-SECTION-ROW')
            ->assertDontSeeText('FILTER-CALL-NOTE')
            ->assertDontSeeText('UNRELATED_TASK_MUST_NOT_APPEAR')
            ->assertDontSeeText('in_progress');
    }

    public function test_workspace_task_summary_uses_task_cycle_scope_even_for_a_crm_manager(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(10);
        $this->seedLead($world, $leadId, 'Synthetic Scoped Task Lead');
        $outcomeId = $this->seedOutcome($world);
        $crmManagerWithTaskRead = $this->seedUser($world, 'Synthetic CRM Manager With Task Read', ['crm.read', 'crm.manage', 'tasks.read']);

        $ownTaskId = $this->seedTask($world, 'OWN_TASK_SUMMARY', 'in_progress');
        DB::table('tasks_cycle')->where('id', $ownTaskId)->update([
            'owner_id' => $crmManagerWithTaskRead,
            'assignee_id' => $crmManagerWithTaskRead,
            'priority' => 'high',
            'due_at' => '2026-10-20 14:30:00',
        ]);
        $ownActivityId = $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(1001),
            'next_step' => 'OWN_TASK_LINKED_ACTIVITY',
            'task_id' => $ownTaskId,
        ]);

        $otherOwnerId = $this->seedUser($world, 'Synthetic Different Task Owner', ['tasks.read']);
        $otherOwnerTaskId = $this->seedTask($world, 'OTHER_OWNER_TASK_SUMMARY', 'pending');
        DB::table('tasks_cycle')->where('id', $otherOwnerTaskId)->update([
            'owner_id' => $otherOwnerId,
            'assignee_id' => $otherOwnerId,
        ]);
        $otherOwnerActivityId = $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(1002),
            'next_step' => 'OTHER_OWNER_LINKED_ACTIVITY',
            'task_id' => $otherOwnerTaskId,
        ]);

        $foreignWorld = $this->seedWorld();
        $foreignTaskId = $this->seedTask($foreignWorld, 'FOREIGN_TASK_SUMMARY', 'pending');
        $foreignActivityId = $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(1003),
            'next_step' => 'FOREIGN_TASK_LINKED_ACTIVITY',
            'task_id' => $foreignTaskId,
        ]);

        $result = $this->workspaceData($world, $leadId, 'activities', [], $crmManagerWithTaskRead);
        $rows = collect($result['data'])->keyBy('id');

        self::assertSame('in_progress', $rows[$ownActivityId]['task']['status']);
        self::assertSame('high', $rows[$ownActivityId]['task']['priority']);
        self::assertSame('2026-10-20T14:30:00+00:00', $rows[$ownActivityId]['task']['due_at']);
        self::assertSame('OWN_TASK_SUMMARY', $rows[$ownActivityId]['task']['title']);
        self::assertNull($rows[$otherOwnerActivityId]['task']);
        self::assertNull($rows[$foreignActivityId]['task']);
    }

    public function test_workspace_omits_linked_task_metadata_without_tasks_read(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(11);
        $this->seedLead($world, $leadId, 'Synthetic CRM-Only Task Lead');
        $outcomeId = $this->seedOutcome($world);
        $taskId = $this->seedTask($world, 'CRM_ONLY_TASK_MUST_NOT_LEAK', 'in_progress');
        $activityId = $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $this->fixtureId(1101),
            'next_step' => 'CRM_ONLY_LINKED_ACTIVITY',
            'task_id' => $taskId,
        ]);

        $result = $this->workspaceData($world, $leadId, 'activities', []);
        $rows = collect($result['data'])->keyBy('id');

        self::assertArrayHasKey('task', $rows[$activityId]);
        self::assertNull($rows[$activityId]['task']);
        self::assertArrayNotHasKey('task_details', $rows[$activityId]);
    }

    public function test_notes_selection_is_limited_to_authorized_rows_on_the_current_page(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(5);
        $this->seedLead($world, $leadId, 'Synthetic Selected Note Lead');
        $outcomeId = $this->seedOutcome($world);
        $noteBodies = [];

        foreach (range(1, 26) as $number) {
            $id = $this->fixtureId(500 + $number);
            $body = sprintf('PAGE-LIMITED-NOTE-%02d', $number);
            $noteBodies[$id] = $body;
            $this->seedActivity($world, $leadId, $outcomeId, [
                'id' => $id,
                'kind' => 'note',
                'note' => $body,
                'next_step' => 'PAGE-LIMITED-NEXT-'.$number,
                'occurred_at' => '2026-10-17 12:00:00',
                'created_at' => '2026-10-17 12:00:00',
            ]);
        }

        $callId = $this->fixtureId(531);
        $this->seedActivity($world, $leadId, $outcomeId, [
            'id' => $callId,
            'kind' => 'call',
            'note' => 'NON-NOTE-SELECTED-ROW',
        ]);
        $otherScope = $this->seedScope($world['company_id'], 'Selected note hidden branch');
        $hiddenLeadId = $this->fixtureId(6);
        $this->seedLead($world, $hiddenLeadId, 'Hidden Selected Note Lead', $otherScope);
        $hiddenNoteId = $this->fixtureId(532);
        $this->seedActivity($world, $hiddenLeadId, $outcomeId, [
            'id' => $hiddenNoteId,
            'kind' => 'note',
            'note' => 'PRIVATE-HIDDEN-SELECTED-NOTE',
        ]);

        $pageTwo = $this->workspaceData($world, $leadId, 'notes', ['page' => 2, 'per_page' => 25]);
        self::assertNotEmpty($pageTwo['data']);
        $pageTwoId = (string) $pageTwo['data'][0]['id'];
        $pageTwoBody = $noteBodies[$pageTwoId];

        $pageOneWithOffPageSelection = $this->html(
            $world['manager_id'],
            '/crm/leads/'.$leadId.'/workspace/notes?'.http_build_query([
                'locale' => 'en',
                'page' => 1,
                'per_page' => 25,
                'selected' => $pageTwoId,
            ]),
        )->assertOk()
            ->assertDontSee('aria-label="Selected note"', false)
            ->assertDontSeeText($pageTwoBody);

        $this->html(
            $world['manager_id'],
            '/crm/leads/'.$leadId.'/workspace/notes?'.http_build_query([
                'locale' => 'en',
                'page' => 2,
                'per_page' => 25,
                'selected' => $pageTwoId,
            ]),
        )->assertOk()
            ->assertSee('aria-label="Selected note"', false)
            ->assertSeeText($pageTwoBody);

        $this->html(
            $world['manager_id'],
            '/crm/leads/'.$leadId.'/workspace/notes?'.http_build_query([
                'locale' => 'en',
                'selected' => $callId,
            ]),
        )->assertOk()
            ->assertDontSee('aria-label="Selected note"', false)
            ->assertDontSeeText('NON-NOTE-SELECTED-ROW');

        $hiddenSelection = $this->html(
            $world['manager_id'],
            '/crm/leads/'.$leadId.'/workspace/notes?'.http_build_query([
                'locale' => 'en',
                'selected' => $hiddenNoteId,
            ]),
        )->assertOk()
            ->assertDontSee('aria-label="Selected note"', false)
            ->assertDontSeeText('PRIVATE-HIDDEN-SELECTED-NOTE');

        self::assertStringNotContainsString('PRIVATE-HIDDEN-SELECTED-NOTE', (string) $hiddenSelection->getContent());
    }

    public function test_workspace_requires_read_scope_and_preserves_the_existing_json_boundary(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(7);
        $this->seedLead($world, $leadId, 'Synthetic Authorization Lead');
        $otherScope = $this->seedScope($world['company_id'], 'Workspace unauthorized branch');
        $hiddenLeadId = $this->fixtureId(8);
        $this->seedLead($world, $hiddenLeadId, 'Out of Scope Workspace Lead', $otherScope);

        $route = '/crm/leads/'.$leadId.'/workspace/activities?locale=en';
        $this->html($world['manager_id'], $route)->assertOk();

        $assignedReaderId = $this->seedUser($world, 'Synthetic Assigned CRM Reader', ['crm.read']);
        DB::table('crm_leads')->where('id', $leadId)->update(['assignee_id' => $assignedReaderId]);
        $this->html($assignedReaderId, $route)->assertOk();

        $unassignedLeadId = $this->fixtureId(9);
        $this->seedLead($world, $unassignedLeadId, 'Unassigned Workspace Lead');
        $this->html(
            $assignedReaderId,
            '/crm/leads/'.$unassignedLeadId.'/workspace/activities?locale=en',
        )->assertNotFound();

        $readerWithoutCrmPermission = $this->seedUser($world, 'Synthetic No CRM Reader', []);
        $this->html($readerWithoutCrmPermission, $route)->assertForbidden();
        $this->html($world['manager_id'], '/crm/leads/'.$hiddenLeadId.'/workspace/activities?locale=en')->assertNotFound();

        $this->flushSession()
            ->withHeaders(['Accept' => 'text/html'])
            ->get($route)
            ->assertUnauthorized();

        $this->withSession($this->sessionFor($world['manager_id']))
            ->withHeaders(['Accept' => '*/*'])
            ->get('/crm/leads/'.$leadId.'/workspace/activities')
            ->assertNotFound()
            ->assertExactJson(['code' => 'not_found', 'message' => 'Resource not found.']);

        $this->withSession($this->sessionFor($world['manager_id']))
            ->withHeaders(['Accept' => '*/*'])
            ->get('/crm/leads')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['data', 'meta']);
    }

    /** @return array{company_id: string, branch_id: string, department_id: string, manager_id: string} */
    private function seedWorld(): array
    {
        $companyId = (string) Str::ulid();
        DB::table('access_companies')->insert([
            'id' => $companyId,
            'name' => 'Synthetic CRM Workspace Company',
            'version' => 0,
        ]);
        $scope = $this->seedScope($companyId, 'Workspace CRM Branch');
        $world = [
            'company_id' => $companyId,
            'branch_id' => $scope['branch_id'],
            'department_id' => $scope['department_id'],
        ];
        $managerId = $this->seedUser($world, 'Synthetic CRM Workspace Manager', ['crm.read', 'crm.manage']);

        return $world + ['manager_id' => $managerId];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string} $world
     * @param  array{branch_id: string, department_id: string}|null  $scope
     */
    private function seedLead(array $world, string $id, string $name, ?array $scope = null): void
    {
        $scope ??= ['branch_id' => $world['branch_id'], 'department_id' => $world['department_id']];
        DB::table('crm_leads')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'branch_id' => $scope['branch_id'],
            'department_id' => $scope['department_id'],
            'name' => $name,
            'organization' => 'Synthetic Organization '.$name,
            'source' => 'website',
            'contacts' => Crypt::encryptString(json_encode([
                'phone' => 'PRIVATE-WORKSPACE-PHONE',
                'email' => 'private-workspace@example.test',
                'whatsapp' => 'PRIVATE-WORKSPACE-WHATSAPP',
            ], JSON_THROW_ON_ERROR)),
            'duplicate_key' => null,
            'consent' => false,
            'preferences' => '{}',
            'notes' => null,
            'stage_id' => null,
            'assignee_id' => null,
            'customer_id' => null,
            'archived' => false,
            'version' => 0,
            'created_at' => '2026-10-17 10:00:00',
            'updated_at' => '2026-10-17 10:00:00',
        ]);
    }

    /** @param array{company_id: string} $world */
    private function seedOutcome(array $world): string
    {
        $id = $this->fixtureId(7001);
        DB::table('crm_taxonomy')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'kind' => 'outcome',
            'label' => 'Synthetic workspace outcome',
            'replaces_id' => null,
            'created_at' => '2026-10-17 10:00:00',
        ]);

        return $id;
    }

    /** @param array{company_id: string, manager_id: string} $world
     * @param  array<string, mixed>  $overrides
     */
    private function seedActivity(array $world, string $leadId, string $outcomeId, array $overrides = []): string
    {
        $id = (string) ($overrides['id'] ?? Str::ulid());
        DB::table('crm_activities')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'lead_id' => $leadId,
            'actor_id' => (string) ($overrides['actor_id'] ?? $world['manager_id']),
            'kind' => (string) ($overrides['kind'] ?? 'call'),
            'outcome_id' => $outcomeId,
            'outcome_label' => (string) ($overrides['outcome_label'] ?? 'Synthetic workspace outcome'),
            'note' => $overrides['note'] ?? 'Synthetic workspace activity note',
            'next_step' => (string) ($overrides['next_step'] ?? 'Synthetic workspace next step'),
            'occurred_at' => (string) ($overrides['occurred_at'] ?? '2026-10-17 12:00:00'),
            'next_follow_up' => null,
            'task_id' => $overrides['task_id'] ?? null,
            'created_at' => (string) ($overrides['created_at'] ?? '2026-10-17 12:00:00'),
        ]);

        return $id;
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id?: string} $world */
    private function seedTask(array $world, string $title, string $status): string
    {
        $id = (string) Str::ulid();
        DB::table('tasks_cycle')->insert([
            'id' => $id,
            'entity_id' => $world['company_id'],
            'department_id' => $world['department_id'],
            'owner_id' => $world['manager_id'],
            'assignee_id' => $world['manager_id'],
            'title' => $title,
            'priority' => 'normal',
            'due_at' => null,
            'status' => $status,
            'version' => 0,
            'create_key_hash' => hash('sha256', $id),
            'create_payload_hash' => hash('sha256', $title),
            'created_at' => '2026-10-17 12:00:00',
            'updated_at' => '2026-10-17 12:00:00',
        ]);

        return $id;
    }

    /** @return array{branch_id: string, department_id: string} */
    private function seedScope(string $companyId, string $name): array
    {
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => $name,
            'code' => 'crm-'.strtolower(substr($branchId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => $name.' Department',
            'code' => 'crm-'.strtolower(substr($departmentId, -6)),
            'active' => true,
            'version' => 0,
        ]);

        return ['branch_id' => $branchId, 'department_id' => $departmentId];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string} $world
     * @param  list<string>  $permissions
     */
    private function seedUser(array $world, string $name, array $permissions): string
    {
        $userId = (string) Str::ulid();
        $roleId = (string) Str::ulid();
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $world['company_id'],
            'name' => $name.' Role '.$roleId,
            'version' => 0,
        ]);
        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => $name,
            'email' => 'crm-workspace-'.$userId.'@example.test',
            'password' => Hash::make('unused-workspace-password'),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);

        return $userId;
    }

    /** @param array{company_id: string, manager_id: string} $world
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function workspaceData(array $world, string $leadId, string $section, array $filters, ?string $actorId = null): array
    {
        $request = Request::create('/crm/leads/'.$leadId.'/workspace/'.$section, 'GET', $filters);
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->replace($this->sessionFor($actorId ?? $world['manager_id']));

        return app(LeadDirectory::class)->workspaceSection($request, $leadId, $section, $filters);
    }

    /** @return array{access_user_id: string, access_session_version: int} */
    private function sessionFor(string $userId): array
    {
        return [
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ];
    }

    private function fixtureId(int $number): string
    {
        return sprintf('01ARASYA%018d', $number);
    }

    private function html(string $userId, string $uri): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))
            ->withHeaders(['Accept' => 'text/html'])
            ->get($uri);
    }
}

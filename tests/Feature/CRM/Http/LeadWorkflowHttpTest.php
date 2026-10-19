<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Http;

use App\Modules\CRM\Application\Runtime\LeadDirectory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class LeadWorkflowHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_http_endpoint_creates_a_task_cycle_follow_up_and_audit(): void
    {
        $world = $this->seedWorld();
        $lead = $this->assignedLead($world, 'Activity Follow Up Lead');
        $outcome = app(LeadDirectory::class)->saveTaxonomy(
            $this->serviceRequest($world['manager_id']),
            ['kind' => 'outcome', 'label' => 'Call completed'],
        );

        $response = $this->withSession($this->sessionFor($world['owner_id']))
            ->postJson('/crm/leads/'.$lead['id'].'/activities', [
                'kind' => 'call',
                'outcome_id' => $outcome['id'],
                'next_step' => 'Confirm installation requirements',
                'note' => 'Synthetic follow-up note',
                'occurred_at' => '2026-10-17T12:00:00+00:00',
                'next_follow_up' => '2026-10-19T09:00:00+00:00',
                'version' => $lead['version'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', $lead['id'])
            ->assertJsonCount(1, 'data.activities');

        $activity = DB::table('crm_activities')->where('lead_id', $lead['id'])->first();
        self::assertNotNull($activity);
        self::assertSame($world['owner_id'], (string) $activity->actor_id);
        self::assertNotNull($activity->task_id);

        $task = DB::table('tasks_cycle')->where('id', $activity->task_id)->first();
        self::assertNotNull($task);
        self::assertSame($world['company_id'], (string) $task->entity_id);
        self::assertSame($world['owner_id'], (string) $task->owner_id);
        self::assertSame($world['owner_id'], (string) $task->assignee_id);
        self::assertSame('CRM follow-up', (string) $task->title);
        $taskAudit = DB::table('tasks_cycle_audit')->where('task_id', $task->id)->first();
        self::assertNotNull($taskAudit);
        self::assertSame($world['owner_id'], (string) $taskAudit->actor_id);
        self::assertSame('create', (string) $taskAudit->action);
        self::assertSame(1, DB::table('crm_audit')->where('lead_id', $lead['id'])->where('action', 'lead.activity.recorded')->count());
        self::assertSame(1, DB::table('access_audit')->where('subject_id', $lead['id'])->where('action', 'lead.activity.recorded')->count());
        self::assertSame($lead['id'], $response->json('data.id'));
    }

    public function test_activity_http_denial_for_tasks_write_rolls_back_the_activity_task_and_audit(): void
    {
        $world = $this->seedWorld();
        $lead = $this->assignedLead($world, 'Denied Follow Up Lead');
        $outcome = app(LeadDirectory::class)->saveTaxonomy(
            $this->serviceRequest($world['manager_id']),
            ['kind' => 'outcome', 'label' => 'Call completed'],
        );
        DB::table('access_user_permissions')->insert([
            'user_id' => $world['owner_id'],
            'permission' => 'tasks.write',
            'allowed' => false,
        ]);
        $auditCount = DB::table('crm_audit')->count();
        $accessAuditCount = DB::table('access_audit')->count();

        $this->withSession($this->sessionFor($world['owner_id']))
            ->postJson('/crm/leads/'.$lead['id'].'/activities', [
                'kind' => 'call',
                'outcome_id' => $outcome['id'],
                'next_step' => 'Call again',
                'occurred_at' => '2026-10-17T12:00:00Z',
                'next_follow_up' => '2026-10-19T12:00:00Z',
                'version' => $lead['version'],
            ])
            ->assertForbidden();

        self::assertSame(0, DB::table('crm_activities')->where('lead_id', $lead['id'])->count());
        self::assertSame(0, DB::table('tasks_cycle')->count());
        self::assertSame(0, DB::table('tasks_cycle_audit')->count());
        self::assertSame($auditCount, DB::table('crm_audit')->count());
        self::assertSame($accessAuditCount, DB::table('access_audit')->count());
        self::assertSame((int) $lead['version'], (int) DB::table('crm_leads')->where('id', $lead['id'])->value('version'));
    }

    public function test_late_activity_insert_failure_rolls_back_the_follow_up_task_and_all_audits(): void
    {
        $world = $this->seedWorld();
        $lead = $this->assignedLead($world, 'Late Failure Follow Up Lead');
        $outcome = app(LeadDirectory::class)->saveTaxonomy(
            $this->serviceRequest($world['manager_id']),
            ['kind' => 'outcome', 'label' => 'Call completed'],
        );
        $activitiesBefore = DB::table('crm_activities')->count();
        $tasksBefore = DB::table('tasks_cycle')->count();
        $taskAuditsBefore = DB::table('tasks_cycle_audit')->count();
        $crmAuditsBefore = DB::table('crm_audit')->count();
        $accessAuditsBefore = DB::table('access_audit')->count();
        $versionBefore = (int) DB::table('crm_leads')->where('id', $lead['id'])->value('version');
        $armed = true;
        $injected = false;

        DB::listen(static function (QueryExecuted $query) use (&$armed, &$injected): void {
            $sql = strtolower(ltrim($query->sql));
            if (! $armed || $injected || ! str_starts_with($sql, 'insert') || ! str_contains($sql, 'crm_activities')) {
                return;
            }

            $injected = true;
            $armed = false;
            throw new RuntimeException('Synthetic failure after CRM activity insert.');
        });

        try {
            $response = $this->withSession($this->sessionFor($world['owner_id']))
                ->postJson('/crm/leads/'.$lead['id'].'/activities', [
                    'kind' => 'call',
                    'outcome_id' => $outcome['id'],
                    'next_step' => 'Confirm installation requirements',
                    'note' => 'Synthetic late-failure note',
                    'occurred_at' => '2026-10-17T12:00:00+00:00',
                    'next_follow_up' => '2026-10-19T09:00:00+00:00',
                    'version' => $lead['version'],
                ]);
        } finally {
            $armed = false;
        }

        $response->assertStatus(500);
        self::assertTrue($injected, 'The failure must occur after the CRM activity INSERT executes.');
        self::assertSame($activitiesBefore, DB::table('crm_activities')->count());
        self::assertSame($tasksBefore, DB::table('tasks_cycle')->count());
        self::assertSame($taskAuditsBefore, DB::table('tasks_cycle_audit')->count());
        self::assertSame($crmAuditsBefore, DB::table('crm_audit')->count());
        self::assertSame($accessAuditsBefore, DB::table('access_audit')->count());
        self::assertSame($versionBefore, (int) DB::table('crm_leads')->where('id', $lead['id'])->value('version'));
    }

    public function test_transfer_http_endpoints_keep_the_owner_until_recipient_accepts_once(): void
    {
        $world = $this->seedWorld();
        $lead = $this->assignedLead($world, 'Accept Transfer Lead');

        $transferResponse = $this->withSession($this->sessionFor($world['owner_id']))
            ->postJson('/crm/leads/'.$lead['id'].'/transfers', [
                'recipient_id' => $world['recipient_id'],
                'context' => 'Synthetic coverage handoff',
                'version' => $lead['version'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.state', 'pending')
            ->assertJsonPath('data.version', (int) $lead['version'] + 1);

        $transferId = (string) $transferResponse->json('data.id');
        $pendingVersion = (int) $transferResponse->json('data.version');
        self::assertSame($world['owner_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame('pending', (string) DB::table('crm_transfers')->where('id', $transferId)->value('state'));

        $inbox = $this->withSession($this->sessionFor($world['recipient_id']))
            ->getJson('/crm/transfers/inbox')
            ->assertOk();
        self::assertSame($transferId, (string) $inbox->json('data.0.id'));
        self::assertStringNotContainsString($this->contactEmail('Accept Transfer Lead'), json_encode($inbox->json(), JSON_THROW_ON_ERROR));

        $this->withSession($this->sessionFor($world['outsider_id']))
            ->postJson('/crm/transfers/'.$transferId.'/response', [
                'decision' => 'accept',
                'version' => $pendingVersion,
            ])
            ->assertNotFound();
        self::assertSame('pending', (string) DB::table('crm_transfers')->where('id', $transferId)->value('state'));
        self::assertSame($world['owner_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));

        $accepted = $this->withSession($this->sessionFor($world['recipient_id']))
            ->postJson('/crm/transfers/'.$transferId.'/response', [
                'decision' => 'accept',
                'version' => $pendingVersion,
            ])
            ->assertOk()
            ->assertJsonPath('data.state', 'accepted');

        self::assertSame($world['recipient_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame(1, DB::table('crm_audit')->where('lead_id', $lead['id'])->where('action', 'lead.transfer.accepted')->count());
        $auditCount = DB::table('crm_audit')->where('lead_id', $lead['id'])->count();

        $this->withSession($this->sessionFor($world['recipient_id']))
            ->postJson('/crm/transfers/'.$transferId.'/response', [
                'decision' => 'accept',
                'version' => $accepted->json('data.version'),
            ])
            ->assertConflict();

        self::assertSame('accepted', (string) DB::table('crm_transfers')->where('id', $transferId)->value('state'));
        self::assertSame($world['recipient_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame($auditCount, DB::table('crm_audit')->where('lead_id', $lead['id'])->count());
    }

    public function test_duplicate_transfer_and_stale_accept_conflict_while_rejection_preserves_the_owner(): void
    {
        $world = $this->seedWorld();
        $lead = $this->assignedLead($world, 'Reject Transfer Lead');
        $transfer = $this->withSession($this->sessionFor($world['owner_id']))
            ->postJson('/crm/leads/'.$lead['id'].'/transfers', [
                'recipient_id' => $world['recipient_id'],
                'context' => 'Original context',
                'version' => $lead['version'],
            ])
            ->assertCreated();
        $transferId = (string) $transfer->json('data.id');
        $requestVersion = (int) $transfer->json('data.version');

        $this->withSession($this->sessionFor($world['recipient_id']))
            ->postJson('/crm/transfers/'.$transferId.'/response', [
                'decision' => 'accept',
                'version' => $requestVersion - 1,
            ])
            ->assertConflict();
        self::assertSame('pending', (string) DB::table('crm_transfers')->where('id', $transferId)->value('state'));
        self::assertSame($world['owner_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));

        $this->withSession($this->sessionFor($world['owner_id']))
            ->postJson('/crm/leads/'.$lead['id'].'/transfers', [
                'recipient_id' => $world['outsider_id'],
                'context' => 'Competing context',
                'version' => $requestVersion,
            ])
            ->assertConflict();
        self::assertSame(1, DB::table('crm_transfers')->where('lead_id', $lead['id'])->count());
        self::assertSame(1, DB::table('crm_audit')->where('lead_id', $lead['id'])->where('action', 'lead.transfer.requested')->count());

        $changed = app(LeadDirectory::class)->save($this->serviceRequest($world['manager_id']), [
            'notes' => 'Updated while transfer was pending',
            'version' => $requestVersion,
        ], $lead['id']);
        $auditCount = DB::table('crm_audit')->where('lead_id', $lead['id'])->count();

        $this->withSession($this->sessionFor($world['recipient_id']))
            ->postJson('/crm/transfers/'.$transferId.'/response', [
                'decision' => 'accept',
                'version' => $changed['version'],
            ])
            ->assertConflict();
        self::assertSame('pending', (string) DB::table('crm_transfers')->where('id', $transferId)->value('state'));
        self::assertSame($world['owner_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame($auditCount, DB::table('crm_audit')->where('lead_id', $lead['id'])->count());

        $this->withSession($this->sessionFor($world['recipient_id']))
            ->postJson('/crm/transfers/'.$transferId.'/response', [
                'decision' => 'reject',
                'version' => $changed['version'],
            ])
            ->assertOk()
            ->assertJsonPath('data.state', 'rejected');
        self::assertSame('rejected', (string) DB::table('crm_transfers')->where('id', $transferId)->value('state'));
        self::assertSame($world['owner_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame(1, DB::table('crm_audit')->where('lead_id', $lead['id'])->where('action', 'lead.transfer.rejected')->count());
    }

    public function test_transfer_response_rechecks_recipient_scope_after_the_request(): void
    {
        $world = $this->seedWorld();
        $lead = $this->assignedLead($world, 'Moved Recipient Lead');
        $transfer = $this->requestTransferHttp($world, $lead);
        $auditCount = DB::table('crm_audit')->count();
        DB::table('access_users')->where('id', $world['recipient_id'])->update([
            'branch_id' => $world['branch_b'],
            'department_id' => $world['department_b'],
        ]);

        $this->withSession($this->sessionFor($world['recipient_id']))
            ->postJson('/crm/transfers/'.$transfer['id'].'/response', [
                'decision' => 'accept',
                'version' => $transfer['version'],
            ])
            ->assertNotFound();

        self::assertSame('pending', (string) DB::table('crm_transfers')->where('id', $transfer['id'])->value('state'));
        self::assertSame($world['owner_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame($auditCount, DB::table('crm_audit')->count());
    }

    public function test_transfer_response_rechecks_recipient_grants_after_the_request(): void
    {
        $world = $this->seedWorld();
        $lead = $this->assignedLead($world, 'Revoked Recipient Lead');
        $transfer = $this->requestTransferHttp($world, $lead);
        $auditCount = DB::table('crm_audit')->count();
        DB::table('access_user_permissions')->insert([
            'user_id' => $world['recipient_id'],
            'permission' => 'crm.write',
            'allowed' => false,
        ]);

        $this->withSession($this->sessionFor($world['recipient_id']))
            ->postJson('/crm/transfers/'.$transfer['id'].'/response', [
                'decision' => 'accept',
                'version' => $transfer['version'],
            ])
            ->assertForbidden();

        self::assertSame('pending', (string) DB::table('crm_transfers')->where('id', $transfer['id'])->value('state'));
        self::assertSame($world['owner_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame($auditCount, DB::table('crm_audit')->count());
    }

    public function test_transfer_inbox_is_masked_scoped_and_stably_paginated_with_a_hundred_item_maximum(): void
    {
        $this->travelTo(Carbon::parse('2026-10-17T12:00:00Z'));
        $world = $this->seedWorld();
        $expectedIds = [];

        for ($index = 1; $index <= 27; $index++) {
            $lead = $this->assignedLead($world, sprintf('Inbox Lead %02d', $index));
            $expectedIds[] = $this->requestTransferForSetup($world['owner_id'], $world['recipient_id'], $lead)['id'];
        }

        $otherLead = $this->assignedLead(
            $world,
            'Other Branch Inbox Lead',
            $world['other_owner_id'],
            $world['other_manager_id'],
        );
        $outOfScopeTransfer = $this->requestTransferForSetup(
            $world['other_owner_id'],
            $world['other_recipient_id'],
            $otherLead,
        );
        sort($expectedIds, SORT_STRING);

        $firstPage = $this->withSession($this->sessionFor($world['recipient_id']))
            ->getJson('/crm/transfers/inbox')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'lead_id', 'sender_id', 'context', 'state', 'created_at', 'name', 'version',
                ]],
                'meta' => ['page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 27)
            ->assertJsonPath('meta.last_page', 2);
        self::assertCount(25, $firstPage->json('data'));

        $secondPage = $this->withSession($this->sessionFor($world['recipient_id']))
            ->getJson('/crm/transfers/inbox?page=2')
            ->assertOk()
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 27)
            ->assertJsonPath('meta.last_page', 2);
        self::assertCount(2, $secondPage->json('data'));
        self::assertSame(
            $expectedIds,
            array_column([...$firstPage->json('data'), ...$secondPage->json('data')], 'id'),
        );
        self::assertNotContains($outOfScopeTransfer['id'], array_column([...$firstPage->json('data'), ...$secondPage->json('data')], 'id'));

        $allItems = $this->withSession($this->sessionFor($world['recipient_id']))
            ->getJson('/crm/transfers/inbox?per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('meta.total', 27)
            ->assertJsonPath('meta.last_page', 1);
        self::assertCount(27, $allItems->json('data'));
        self::assertSame($expectedIds, array_column($allItems->json('data'), 'id'));
        self::assertStringNotContainsString(
            $this->contactEmail('Inbox Lead 01'),
            json_encode($allItems->json(), JSON_THROW_ON_ERROR),
        );
        $this->withSession($this->sessionFor($world['recipient_id']))
            ->getJson('/crm/transfers/inbox?per_page=101')
            ->assertUnprocessable();
    }

    public function test_lead_detail_activity_history_is_scoped_and_stably_paginated(): void
    {
        $world = $this->seedWorld();
        $lead = $this->assignedLead($world, 'Paged Detail Lead');
        $outcome = app(LeadDirectory::class)->saveTaxonomy(
            $this->serviceRequest($world['manager_id']),
            ['kind' => 'outcome', 'label' => 'Call completed'],
        );
        $occurredAt = '2026-10-17 12:00:00';
        $createdAt = now()->toDateTimeString();
        $rows = [];
        $expectedIds = [];

        for ($index = 0; $index < 27; $index++) {
            $activityId = $this->id();
            $expectedIds[] = $activityId;
            $rows[] = [
                'id' => $activityId,
                'company_id' => $world['company_id'],
                'lead_id' => $lead['id'],
                'actor_id' => $world['owner_id'],
                'kind' => 'call',
                'outcome_id' => $outcome['id'],
                'outcome_label' => 'Call completed',
                'note' => null,
                'next_step' => 'Synthetic detail activity',
                'occurred_at' => $occurredAt,
                'next_follow_up' => null,
                'task_id' => null,
                'created_at' => $createdAt,
            ];
        }
        DB::table('crm_activities')->insert($rows);
        rsort($expectedIds, SORT_STRING);

        $firstPage = $this->withSession($this->sessionFor($world['owner_id']))
            ->getJson('/crm/leads/'.$lead['id'].'?page=1')
            ->assertOk()
            ->assertJsonPath('data.id', $lead['id'])
            ->assertJsonPath('data.activities_meta.page', 1)
            ->assertJsonPath('data.activities_meta.per_page', 25)
            ->assertJsonPath('data.activities_meta.total', 27)
            ->assertJsonPath('data.activities_meta.last_page', 2);
        self::assertCount(25, $firstPage->json('data.activities'));
        self::assertSame(array_slice($expectedIds, 0, 25), array_column($firstPage->json('data.activities'), 'id'));

        $secondPage = $this->withSession($this->sessionFor($world['owner_id']))
            ->getJson('/crm/leads/'.$lead['id'].'?page=2')
            ->assertOk()
            ->assertJsonPath('data.activities_meta.page', 2)
            ->assertJsonPath('data.activities_meta.per_page', 25)
            ->assertJsonPath('data.activities_meta.total', 27)
            ->assertJsonPath('data.activities_meta.last_page', 2);
        self::assertCount(2, $secondPage->json('data.activities'));
        self::assertSame(array_slice($expectedIds, 25), array_column($secondPage->json('data.activities'), 'id'));

        $allActivities = $this->withSession($this->sessionFor($world['owner_id']))
            ->getJson('/crm/leads/'.$lead['id'].'?per_page=100')
            ->assertOk()
            ->assertJsonPath('data.activities_meta.page', 1)
            ->assertJsonPath('data.activities_meta.per_page', 100)
            ->assertJsonPath('data.activities_meta.total', 27)
            ->assertJsonPath('data.activities_meta.last_page', 1);
        self::assertCount(27, $allActivities->json('data.activities'));
        self::assertSame($expectedIds, array_column($allActivities->json('data.activities'), 'id'));

        $this->withSession($this->sessionFor($world['owner_id']))
            ->getJson('/crm/leads/'.$lead['id'].'?page=0')
            ->assertUnprocessable();
        $this->withSession($this->sessionFor($world['owner_id']))
            ->getJson('/crm/leads/'.$lead['id'].'?page=1&unknown_filter=value')
            ->assertUnprocessable();
        $this->withSession($this->sessionFor($world['owner_id']))
            ->getJson('/crm/leads/'.$lead['id'].'?per_page=101')
            ->assertUnprocessable();

        $hiddenLead = $this->assignedLead($world, 'Hidden Detail Lead', $world['manager_id']);
        $this->withSession($this->sessionFor($world['owner_id']))
            ->getJson('/crm/leads/'.$hiddenLead['id'].'?page=1&per_page=100')
            ->assertNotFound();
    }

    public function test_conversion_http_retry_is_idempotent_and_denied_or_stale_first_attempt_has_no_effect(): void
    {
        $world = $this->seedWorld();
        $lead = $this->assignedLead($world, 'Convert Lead');
        $customersBefore = DB::table('crm_customers')->where('company_id', $world['company_id'])->count();
        $crmAuditBefore = DB::table('crm_audit')->where('lead_id', $lead['id'])->count();
        $accessAuditBefore = DB::table('access_audit')->count();
        $verticalRecordsBefore = DB::table('vertical_cycle_records')->count();

        $this->withSession($this->sessionFor($world['owner_id']))
            ->postJson('/crm/leads/'.$lead['id'].'/convert', ['version' => $lead['version']])
            ->assertForbidden();
        $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/leads/'.$lead['id'].'/convert', ['version' => (int) $lead['version'] + 99])
            ->assertConflict();

        self::assertSame($customersBefore, DB::table('crm_customers')->where('company_id', $world['company_id'])->count());
        self::assertSame($crmAuditBefore, DB::table('crm_audit')->where('lead_id', $lead['id'])->count());
        self::assertSame($accessAuditBefore, DB::table('access_audit')->count());
        self::assertNull(DB::table('crm_leads')->where('id', $lead['id'])->value('customer_id'));

        $first = $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/leads/'.$lead['id'].'/convert', ['version' => $lead['version']])
            ->assertOk();
        $retry = $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/leads/'.$lead['id'].'/convert', ['version' => $lead['version']])
            ->assertOk();

        self::assertSame($first->json('data.customer_id'), $retry->json('data.customer_id'));
        self::assertSame(1, DB::table('crm_customers')->where('company_id', $world['company_id'])->count());
        self::assertSame($first->json('data.customer_id'), DB::table('crm_leads')->where('id', $lead['id'])->value('customer_id'));
        self::assertSame(1, DB::table('crm_audit')->where('lead_id', $lead['id'])->where('action', 'lead.converted')->count());
        self::assertSame(1, DB::table('access_audit')->where('subject_id', $lead['id'])->where('action', 'lead.converted')->count());
        self::assertSame($verticalRecordsBefore, DB::table('vertical_cycle_records')->count());
    }

    /** @return array<string, string> */
    private function seedWorld(): array
    {
        $companyId = $this->id();
        $branchA = $this->id();
        $branchB = $this->id();
        $departmentA = $this->id();
        $departmentAOther = $this->id();
        $departmentB = $this->id();
        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Synthetic CRM HTTP Company', 'version' => 0]);

        foreach ([[$branchA, 'BR-HTTP-A'], [$branchB, 'BR-HTTP-B']] as [$id, $code]) {
            DB::table('access_branches')->insert([
                'id' => $id,
                'company_id' => $companyId,
                'name' => $code,
                'code' => strtolower($code).'-'.strtolower(substr($id, -4)),
                'active' => true,
                'version' => 0,
            ]);
        }
        foreach ([
            [$departmentA, $branchA, 'DEPT-HTTP-A'],
            [$departmentAOther, $branchA, 'DEPT-HTTP-A2'],
            [$departmentB, $branchB, 'DEPT-HTTP-B'],
        ] as [$id, $branchId, $code]) {
            DB::table('access_departments')->insert([
                'id' => $id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => $code,
                'code' => strtolower($code).'-'.strtolower(substr($id, -4)),
                'active' => true,
                'version' => 0,
            ]);
        }

        $managerPermissions = [
            'crm.read', 'crm.manage', 'crm.write', 'crm.assign', 'crm.transfer',
            'crm.convert', 'crm.taxonomy', 'tasks.read', 'tasks.write',
        ];

        return [
            'company_id' => $companyId,
            'branch_a' => $branchA,
            'branch_b' => $branchB,
            'department_a' => $departmentA,
            'department_a_other' => $departmentAOther,
            'department_b' => $departmentB,
            'manager_id' => $this->seedUser($companyId, $branchA, $departmentA, 'manager', $managerPermissions),
            'owner_id' => $this->seedUser($companyId, $branchA, $departmentA, 'owner', ['crm.read', 'crm.write', 'crm.transfer', 'tasks.read', 'tasks.write']),
            'recipient_id' => $this->seedUser($companyId, $branchA, $departmentA, 'recipient', ['crm.read', 'crm.write', 'tasks.read', 'tasks.write']),
            'outsider_id' => $this->seedUser($companyId, $branchA, $departmentA, 'outsider', ['crm.read', 'crm.write', 'tasks.read', 'tasks.write']),
            'other_manager_id' => $this->seedUser($companyId, $branchB, $departmentB, 'other-manager', $managerPermissions),
            'other_owner_id' => $this->seedUser($companyId, $branchB, $departmentB, 'other-owner', ['crm.read', 'crm.write', 'crm.transfer', 'tasks.read', 'tasks.write']),
            'other_recipient_id' => $this->seedUser($companyId, $branchB, $departmentB, 'other-recipient', ['crm.read', 'crm.write', 'tasks.read', 'tasks.write']),
        ];
    }

    /** @param list<string> $permissions */
    private function seedUser(string $companyId, string $branchId, string $departmentId, string $suffix, array $permissions): string
    {
        $userId = $this->id();
        $roleId = $this->id();
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $companyId,
            'name' => 'Synthetic CRM HTTP '.$suffix.' role '.$roleId,
            'version' => 0,
        ]);
        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'name' => 'Synthetic CRM HTTP '.$suffix,
            'email' => 'crm-http-'.$suffix.'-'.strtolower(substr($userId, -6)).'@example.test',
            'password' => Hash::make('unused-synthetic-password'),
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

    /** @return array<string, mixed> */
    private function assignedLead(array $world, string $name, ?string $ownerId = null, ?string $managerId = null): array
    {
        $managerId ??= $world['manager_id'];
        $ownerId ??= $world['owner_id'];
        $directory = app(LeadDirectory::class);
        $request = $this->serviceRequest($managerId);
        $lead = $directory->save($request, [
            'name' => $name,
            'organization' => 'Synthetic Organization '.$name,
            'source' => 'website',
            'contacts' => [
                'email' => $this->contactEmail($name),
                'phone' => '+201155500111',
                'whatsapp' => '+201155500112',
            ],
            'consent' => true,
            'preferences' => ['email' => true],
            'notes' => 'Synthetic lead note',
            'stage_id' => null,
        ]);

        return $directory->assign($request, (string) $lead['id'], [
            'assignee_id' => $ownerId,
            'version' => $lead['version'],
        ]);
    }

    /** @return array<string, mixed> */
    private function requestTransferHttp(array $world, array $lead): array
    {
        $response = $this->withSession($this->sessionFor($world['owner_id']))
            ->postJson('/crm/leads/'.$lead['id'].'/transfers', [
                'recipient_id' => $world['recipient_id'],
                'context' => 'Synthetic coverage handoff',
                'version' => $lead['version'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.state', 'pending');

        return [
            'id' => (string) $response->json('data.id'),
            'version' => (int) $response->json('data.version'),
        ];
    }

    /** @return array<string, mixed> */
    private function requestTransferForSetup(string $ownerId, string $recipientId, array $lead): array
    {
        return app(LeadDirectory::class)->requestTransfer($this->serviceRequest($ownerId), (string) $lead['id'], [
            'recipient_id' => $recipientId,
            'context' => 'Synthetic inbox coverage',
            'version' => $lead['version'],
        ]);
    }

    /** @return array<string, mixed> */
    private function sessionFor(string $userId): array
    {
        return [
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ];
    }

    private function serviceRequest(string $userId): Request
    {
        $session = new Store('crm-http-test-'.$userId.'-'.Str::ulid(), new ArraySessionHandler(120));
        $session->start();
        $session->replace($this->sessionFor($userId));
        $request = Request::create('/crm/leads', 'POST');
        $request->setLaravelSession($session);

        return $request;
    }

    private function contactEmail(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));

        return 'private-'.$slug.'@example.test';
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }
}

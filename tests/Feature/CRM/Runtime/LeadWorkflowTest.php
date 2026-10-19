<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Runtime;

use App\Modules\CRM\Application\Runtime\LeadDirectory;
use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use App\Modules\Tasks\Application\TASK01\TaskCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class LeadWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_directory_application_service_is_available(): void
    {
        self::assertTrue(class_exists(LeadDirectory::class), 'The persisted CRM lead workflow service must be available.');
    }

    public function test_manager_can_create_read_edit_and_archive_a_versioned_lead_without_exposing_contacts(): void
    {
        $world = $this->seedWorld();
        $request = $this->requestFor($world['manager_id']);
        $directory = app(LeadDirectory::class);
        $contacts = $this->contacts();

        $created = $directory->save($request, $this->leadData($contacts));
        $id = (string) $created['id'];
        $row = DB::table('crm_leads')->where('id', $id)->first();
        self::assertNotNull($row);
        self::assertSame($world['company_id'], (string) $row->company_id);
        self::assertSame(0, (int) $row->version);
        self::assertStringNotContainsString($contacts['email'], (string) $row->contacts);
        self::assertStringNotContainsString($contacts['phone'], (string) $row->contacts);

        $list = $directory->listing($request);
        $listed = collect($list)->firstWhere('id', $id);
        $detail = $directory->find($request, $id);
        self::assertNotNull($listed);
        self::assertSame($id, $detail['id']);
        $this->assertContactsMasked([$listed, $detail], $contacts);

        $updated = $directory->save($request, [
            ...$this->leadData($contacts),
            'name' => 'Updated Synthetic Prospect',
            'version' => $created['version'],
        ], $id);
        self::assertSame('Updated Synthetic Prospect', (string) DB::table('crm_leads')->where('id', $id)->value('name'));
        self::assertGreaterThan((int) $created['version'], (int) $updated['version']);
        self::assertSame((int) $updated['version'], (int) DB::table('crm_leads')->where('id', $id)->value('version'));

        $archived = $directory->archive($request, $id, ['version' => $updated['version']]);
        self::assertTrue((bool) DB::table('crm_leads')->where('id', $id)->value('archived'));
        self::assertSame((int) $archived['version'], (int) DB::table('crm_leads')->where('id', $id)->value('version'));
        self::assertNull(collect($directory->listing($request))->firstWhere('id', $id));
        self::assertNotNull(collect($directory->listing($request, ['archived' => true]))->firstWhere('id', $id));

        $history = $directory->find($request, $id);
        self::assertStringContainsString('archiv', strtolower($this->encoded($history)));
        self::assertCount(3, DB::table('crm_audit')->where('lead_id', $id)->get());
        $archiveAudit = DB::table('crm_audit')->where('lead_id', $id)->where('action', 'lead.archived')->first();
        self::assertNotNull($archiveAudit);
        self::assertSame($world['manager_id'], (string) $archiveAudit->actor_id);
        self::assertSame($world['company_id'], (string) $archiveAudit->company_id);
        $archiveMetadata = json_decode((string) $archiveAudit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($archiveMetadata);
        self::assertArrayHasKey('outbox_intent', $archiveMetadata);
        $intent = $archiveMetadata['outbox_intent'];
        self::assertIsArray($intent);
        self::assertSame('lead.archived', $intent['event_type']);
        self::assertSame(1, $intent['event_version']);
        self::assertSame('Lead', $intent['aggregate_type']);
        self::assertSame($id, $intent['aggregate_id']);
        self::assertSame((int) DB::table('crm_leads')->where('id', $id)->value('version'), $intent['aggregate_version']);
        self::assertSame([
            'entity_id' => $world['company_id'],
            'lead_id' => $id,
            'action' => 'archived',
            'actor_id' => $world['manager_id'],
        ], $intent['payload']);

        $canonicalAudit = DB::table('access_audit')->where('subject_id', $id)->where('action', 'lead.archived')->first();
        self::assertNotNull($canonicalAudit);
        self::assertSame($world['manager_id'], (string) $canonicalAudit->actor_id);
        $canonicalMetadata = json_decode((string) $canonicalAudit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($canonicalMetadata);
        self::assertSame($archiveMetadata['correlation_id'], $canonicalMetadata['correlation_id']);
        $this->assertContactsMasked([
            $history,
            DB::table('crm_audit')->where('lead_id', $id)->pluck('metadata')->all(),
            $intent,
            $canonicalMetadata,
        ], $contacts);
    }

    public function test_manager_can_make_an_initial_assignment_and_assignee_can_read_only_that_lead(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        $lead = $directory->save($this->requestFor($world['manager_id']), $this->leadData($this->contacts()));
        $assigned = $directory->assign($this->requestFor($world['manager_id']), $lead['id'], [
            'assignee_id' => $world['owner_id'],
            'version' => $lead['version'],
        ]);

        self::assertSame($world['owner_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame((int) $assigned['version'], (int) DB::table('crm_leads')->where('id', $lead['id'])->value('version'));
        $this->assertDenied(fn () => $directory->assign($this->requestFor($world['owner_id']), $lead['id'], [
            'assignee_id' => $world['recipient_id'],
            'version' => $assigned['version'],
        ]));
        self::assertSame($lead['id'], $directory->find($this->requestFor($world['owner_id']), $lead['id'])['id']);
        self::assertSame([$lead['id']], array_column($directory->listing($this->requestFor($world['owner_id'])), 'id'));
    }

    public function test_pending_transfer_keeps_old_owner_access_and_only_recipient_can_accept_once(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        $manager = $this->requestFor($world['manager_id']);
        $owner = $this->requestFor($world['owner_id']);
        $recipient = $this->requestFor($world['recipient_id']);
        $outsider = $this->requestFor($world['outsider_id']);
        $contacts = $this->contacts();
        $lead = $directory->save($manager, $this->leadData($contacts));
        $assigned = $directory->assign($manager, $lead['id'], [
            'assignee_id' => $world['owner_id'],
            'version' => $lead['version'],
        ]);
        $directory->requestTransfer($owner, $lead['id'], [
            'recipient_id' => $world['recipient_id'],
            'context' => 'Synthetic coverage handoff',
            'version' => $assigned['version'],
        ]);
        $transferId = (string) DB::table('crm_transfers')->where('lead_id', $lead['id'])->value('id');

        self::assertSame('pending', (string) DB::table('crm_transfers')->where('id', $transferId)->value('state'));
        self::assertSame($world['owner_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame($lead['id'], $directory->find($owner, $lead['id'])['id']);
        $inbox = $directory->inbox($recipient);
        self::assertNotEmpty($inbox);
        $this->assertContactsMasked($inbox, $contacts);
        $this->assertDenied(fn () => $directory->find($recipient, $lead['id']), $contacts);
        $this->assertDenied(fn () => $directory->respondTransfer($outsider, $transferId, [
            'decision' => 'accept',
            'version' => DB::table('crm_leads')->where('id', $lead['id'])->value('version'),
        ]));
        self::assertSame('pending', (string) DB::table('crm_transfers')->where('id', $transferId)->value('state'));

        $directory->respondTransfer($recipient, $transferId, [
            'decision' => 'accept',
            'version' => DB::table('crm_leads')->where('id', $lead['id'])->value('version'),
        ]);
        $acceptedLead = DB::table('crm_leads')->where('id', $lead['id'])->first();
        self::assertSame('accepted', (string) DB::table('crm_transfers')->where('id', $transferId)->value('state'));
        self::assertSame($world['recipient_id'], (string) $acceptedLead->assignee_id);
        $auditCount = DB::table('crm_audit')->where('lead_id', $lead['id'])->count();
        $this->assertDenied(fn () => $directory->find($owner, $lead['id']), $contacts);
        self::assertSame($lead['id'], $directory->find($recipient, $lead['id'])['id']);

        try {
            $directory->respondTransfer($recipient, $transferId, [
                'decision' => 'accept',
                'version' => $acceptedLead->version,
            ]);
            self::fail('Accepting an already accepted transfer must conflict.');
        } catch (ConflictHttpException) {
        }

        self::assertSame('accepted', (string) DB::table('crm_transfers')->where('id', $transferId)->value('state'));
        self::assertSame($world['recipient_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame((int) $acceptedLead->version, (int) DB::table('crm_leads')->where('id', $lead['id'])->value('version'));
        self::assertSame($auditCount, DB::table('crm_audit')->where('lead_id', $lead['id'])->count());
    }

    public function test_only_transfer_recipient_can_reject_and_rejection_leaves_owner_unchanged(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        $manager = $this->requestFor($world['manager_id']);
        $owner = $this->requestFor($world['owner_id']);
        $recipient = $this->requestFor($world['recipient_id']);
        $outsider = $this->requestFor($world['outsider_id']);
        $lead = $directory->save($manager, $this->leadData($this->contacts()));
        $assigned = $directory->assign($manager, $lead['id'], [
            'assignee_id' => $world['owner_id'],
            'version' => $lead['version'],
        ]);
        $directory->requestTransfer($owner, $lead['id'], [
            'recipient_id' => $world['recipient_id'],
            'context' => 'Synthetic coverage handoff',
            'version' => $assigned['version'],
        ]);
        $transferId = (string) DB::table('crm_transfers')->where('lead_id', $lead['id'])->value('id');
        $version = DB::table('crm_leads')->where('id', $lead['id'])->value('version');

        $this->assertDenied(fn () => $directory->respondTransfer($outsider, $transferId, [
            'decision' => 'reject',
            'version' => $version,
        ]));
        self::assertSame('pending', (string) DB::table('crm_transfers')->where('id', $transferId)->value('state'));

        $directory->respondTransfer($recipient, $transferId, ['decision' => 'reject', 'version' => $version]);
        self::assertSame('rejected', (string) DB::table('crm_transfers')->where('id', $transferId)->value('state'));
        self::assertSame($world['owner_id'], (string) DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame($lead['id'], $directory->find($owner, $lead['id'])['id']);
        $this->assertDenied(fn () => $directory->find($recipient, $lead['id']));
    }

    public function test_taxonomy_replacement_preserves_the_outcome_label_on_existing_activity(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        $request = $this->requestFor($world['manager_id']);
        $lead = $directory->save($request, $this->leadData($this->contacts()));
        $assigned = $directory->assign($request, $lead['id'], [
            'assignee_id' => $world['manager_id'],
            'version' => $lead['version'],
        ]);
        $directory->saveTaxonomy($request, ['kind' => 'outcome', 'label' => 'Interested']);
        $outcomeId = (string) DB::table('crm_taxonomy')->where('company_id', $world['company_id'])->where('kind', 'outcome')->where('label', 'Interested')->value('id');

        $directory->activity($request, $lead['id'], [
            'kind' => 'call',
            'outcome_id' => $outcomeId,
            'next_step' => 'Send the approved brochure',
            'note' => 'Synthetic call note',
            'occurred_at' => '2026-10-17T12:00:00+00:00',
            'next_follow_up' => null,
            'version' => $assigned['version'],
        ]);
        $directory->saveTaxonomy($request, [
            'kind' => 'outcome',
            'label' => 'Interested after review',
            'replaces_id' => $outcomeId,
        ]);

        $activity = DB::table('crm_activities')->where('lead_id', $lead['id'])->first();
        self::assertNotNull($activity);
        self::assertSame($outcomeId, (string) $activity->outcome_id);
        self::assertSame('Interested', (string) $activity->outcome_label);
        self::assertSame('Interested', (string) DB::table('crm_taxonomy')->where('id', $outcomeId)->value('label'));
        self::assertSame($outcomeId, (string) DB::table('crm_taxonomy')->where('label', 'Interested after review')->value('replaces_id'));
    }

    public function test_activity_follow_up_uses_task_cycle_with_a_generic_title_and_the_session_actor(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        $request = $this->requestFor($world['manager_id']);
        $contacts = $this->contacts();
        $lead = $directory->save($request, $this->leadData($contacts));
        $assigned = $directory->assign($request, $lead['id'], [
            'assignee_id' => $world['manager_id'],
            'version' => $lead['version'],
        ]);
        $directory->saveTaxonomy($request, ['kind' => 'outcome', 'label' => 'Call completed']);
        $outcomeId = (string) DB::table('crm_taxonomy')->where('company_id', $world['company_id'])->where('kind', 'outcome')->where('label', 'Call completed')->value('id');

        $directory->activity($request, $lead['id'], [
            'kind' => 'call',
            'outcome_id' => $outcomeId,
            'next_step' => 'Confirm installation requirements',
            'note' => 'Synthetic follow-up note',
            'occurred_at' => '2026-10-17T12:00:00+00:00',
            'next_follow_up' => '2026-10-19T09:00:00+00:00',
            'version' => $assigned['version'],
        ]);

        $activity = DB::table('crm_activities')->where('lead_id', $lead['id'])->first();
        self::assertNotNull($activity);
        self::assertSame($world['manager_id'], (string) $activity->actor_id);
        self::assertNotNull($activity->task_id);
        $task = DB::table('tasks_cycle')->where('id', $activity->task_id)->first();
        self::assertNotNull($task);
        self::assertSame($world['company_id'], (string) $task->entity_id);
        self::assertSame($world['manager_id'], (string) $task->owner_id);
        self::assertSame($world['manager_id'], (string) $task->assignee_id);
        self::assertNotNull($task->due_at);
        $this->assertContactsMasked([(string) $task->title, (string) $activity->note, (string) $activity->next_step], $contacts);
        self::assertStringNotContainsString('Synthetic Prospect', (string) $task->title);
        self::assertSame((string) $task->title, app(TaskCycle::class)
            ->find(app(RuntimeIdentity::class)->fromRequest($request), (string) $task->id)['title']);
    }

    public function test_repeated_conversion_returns_the_same_customer_without_creating_downstream_records(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        $request = $this->requestFor($world['manager_id']);
        $lead = $directory->save($request, $this->leadData($this->contacts()));
        $beforeVerticalRecords = DB::table('vertical_cycle_records')->count();

        $first = $directory->convert($request, $lead['id'], ['version' => $lead['version']]);
        $retry = $directory->convert($request, $lead['id'], ['version' => $lead['version']]);

        self::assertSame($first['customer_id'], $retry['customer_id']);
        self::assertSame(1, DB::table('crm_customers')->where('company_id', $world['company_id'])->count());
        $this->assertDatabaseHas('crm_customers', ['id' => $first['customer_id'], 'company_id' => $world['company_id']]);
        self::assertSame($first['customer_id'], DB::table('crm_leads')->where('id', $lead['id'])->value('customer_id'));
        self::assertSame($beforeVerticalRecords, DB::table('vertical_cycle_records')->count());
        foreach (['opportunity_id', 'site_id', 'quote_id', 'order_id', 'project_id', 'invoice_id'] as $downstreamId) {
            self::assertArrayNotHasKey($downstreamId, $first);
            self::assertArrayNotHasKey($downstreamId, $retry);
        }
    }

    public function test_pending_transfer_cannot_be_duplicated_or_accept_a_changed_lead_version(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        $manager = $this->requestFor($world['manager_id']);
        $owner = $this->requestFor($world['owner_id']);
        $recipient = $this->requestFor($world['recipient_id']);
        $lead = $directory->save($manager, $this->leadData($this->contacts()));
        $lead = $directory->assign($manager, $lead['id'], ['assignee_id' => $world['owner_id'], 'version' => $lead['version']]);
        $transfer = $directory->requestTransfer($owner, $lead['id'], ['recipient_id' => $world['recipient_id'], 'context' => 'Original context', 'version' => $lead['version']]);
        $this->assertWorkflowConflict(fn () => $directory->requestTransfer($owner, $lead['id'], ['recipient_id' => $world['outsider_id'], 'context' => 'Competing context', 'version' => $transfer['version']]));
        $changed = $directory->save($manager, ['notes' => 'Changed after request', 'version' => $transfer['version']], $lead['id']);
        $this->assertWorkflowConflict(fn () => $directory->respondTransfer($recipient, $transfer['id'], ['decision' => 'accept', 'version' => $changed['version']]));
        self::assertSame(1, DB::table('crm_transfers')->count());
        self::assertSame($world['owner_id'], DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame('pending', DB::table('crm_transfers')->where('id', $transfer['id'])->value('state'));
        $directory->respondTransfer($recipient, $transfer['id'], ['decision' => 'reject', 'version' => $changed['version']]);
        self::assertSame('rejected', DB::table('crm_transfers')->where('id', $transfer['id'])->value('state'));
    }

    public function test_revoked_recipient_cannot_accept_using_the_existing_session(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        $manager = $this->requestFor($world['manager_id']);
        $lead = $directory->save($manager, $this->leadData($this->contacts()));
        $lead = $directory->assign($manager, $lead['id'], ['assignee_id' => $world['owner_id'], 'version' => $lead['version']]);
        $transfer = $directory->requestTransfer($this->requestFor($world['owner_id']), $lead['id'], ['recipient_id' => $world['recipient_id'], 'context' => 'Coverage', 'version' => $lead['version']]);
        $recipient = $this->requestFor($world['recipient_id']);
        DB::table('access_user_permissions')->insert(['user_id' => $world['recipient_id'], 'permission' => 'crm.write', 'allowed' => false]);
        $beforeAudit = DB::table('crm_audit')->count();
        $this->assertDenied(fn () => $directory->respondTransfer($recipient, $transfer['id'], ['decision' => 'accept', 'version' => $transfer['version']]));
        self::assertSame($world['owner_id'], DB::table('crm_leads')->where('id', $lead['id'])->value('assignee_id'));
        self::assertSame('pending', DB::table('crm_transfers')->where('id', $transfer['id'])->value('state'));
        self::assertSame($beforeAudit, DB::table('crm_audit')->count());
    }

    public function test_failed_task_authorization_rolls_back_activity_and_archived_leads_reject_workflows(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        $manager = $this->requestFor($world['manager_id']);
        $lead = $directory->save($manager, $this->leadData($this->contacts()));
        $outcome = $directory->saveTaxonomy($manager, ['kind' => 'outcome', 'label' => 'Completed call']);
        $data = ['kind' => 'call', 'outcome_id' => $outcome['id'], 'next_step' => 'Call again', 'occurred_at' => '2026-10-17T12:00:00Z', 'next_follow_up' => '2026-10-19T12:00:00Z', 'version' => $lead['version']];
        DB::table('access_user_permissions')->insert(['user_id' => $world['manager_id'], 'permission' => 'tasks.write', 'allowed' => false]);
        $beforeAudit = DB::table('crm_audit')->count();
        $this->assertDenied(fn () => $directory->activity($manager, $lead['id'], $data));
        self::assertSame(0, DB::table('crm_activities')->count());
        self::assertSame(0, DB::table('tasks_cycle')->count());
        self::assertSame($beforeAudit, DB::table('crm_audit')->count());
        $archived = $directory->archive($manager, $lead['id'], ['version' => $lead['version']]);
        $data['version'] = $archived['version'];
        $this->assertWorkflowConflict(fn () => $directory->activity($manager, $lead['id'], $data));
        $this->assertWorkflowConflict(fn () => $directory->requestTransfer($manager, $lead['id'], ['recipient_id' => $world['recipient_id'], 'context' => 'Archived', 'version' => $archived['version']]));
        $this->assertWorkflowConflict(fn () => $directory->convert($manager, $lead['id'], ['version' => $archived['version']]));
        self::assertSame(0, DB::table('crm_customers')->count());
    }

    public function test_converted_lead_keeps_identity_when_manager_edits_notes(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        $manager = $this->requestFor($world['manager_id']);
        $lead = $directory->save($manager, $this->leadData($this->contacts()));
        $converted = $directory->convert($manager, $lead['id'], ['version' => $lead['version']]);
        $updated = $directory->save($manager, ['notes' => 'Reviewed customer history', 'version' => $converted['version']], $lead['id']);
        self::assertSame($converted['customer_id'], $updated['customer_id']);
        self::assertSame('Reviewed customer history', $directory->find($manager, $lead['id'])['notes']);
        self::assertSame(1, DB::table('crm_customers')->count());
        $this->assertWorkflowConflict(fn () => $directory->save($manager, ['contacts' => ['email' => 'different@example.test'], 'version' => $updated['version']], $lead['id']));
        self::assertSame($this->contacts()['email'], $directory->reveal($manager, $lead['id'], ['reason' => 'Check retained customer identity'])['contacts']['email']);
    }

    public function test_activity_rejects_next_step_longer_than_storage_without_mutation(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        $manager = $this->requestFor($world['manager_id']);
        $lead = $directory->save($manager, $this->leadData($this->contacts()));
        $outcome = $directory->saveTaxonomy($manager, ['kind' => 'outcome', 'label' => 'Call complete']);
        $audit = DB::table('crm_audit')->count();
        $canonicalAudit = DB::table('access_audit')->count();
        try {
            $directory->activity($manager, $lead['id'], ['kind' => 'call', 'outcome_id' => $outcome['id'], 'next_step' => str_repeat('x', 201), 'occurred_at' => '2026-10-17T12:00:00Z', 'next_follow_up' => '2026-10-19T12:00:00Z', 'version' => $lead['version']]);
            self::fail('Oversized next step must fail validation.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('next_step', $exception->errors());
        }
        self::assertSame(0, DB::table('crm_activities')->count());
        self::assertSame(0, DB::table('tasks_cycle')->count());
        self::assertSame($lead['version'], (int) DB::table('crm_leads')->where('id', $lead['id'])->value('version'));
        self::assertSame($audit, DB::table('crm_audit')->count());
        self::assertSame($canonicalAudit, DB::table('access_audit')->count());
    }

    public function test_pending_transfer_prevents_archival_until_recipient_rejects(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        $manager = $this->requestFor($world['manager_id']);
        $recipient = $this->requestFor($world['recipient_id']);
        $lead = $directory->save($manager, $this->leadData($this->contacts()));
        $lead = $directory->assign($manager, $lead['id'], ['assignee_id' => $world['owner_id'], 'version' => $lead['version']]);
        $transfer = $directory->requestTransfer($this->requestFor($world['owner_id']), $lead['id'], ['recipient_id' => $world['recipient_id'], 'context' => 'Coverage', 'version' => $lead['version']]);
        $audit = DB::table('crm_audit')->count();
        $canonicalAudit = DB::table('access_audit')->count();
        $this->assertWorkflowConflict(fn () => $directory->archive($manager, $lead['id'], ['version' => $transfer['version']]));
        self::assertFalse((bool) DB::table('crm_leads')->where('id', $lead['id'])->value('archived'));
        self::assertSame($transfer['version'], (int) DB::table('crm_leads')->where('id', $lead['id'])->value('version'));
        self::assertSame($audit, DB::table('crm_audit')->count());
        self::assertSame($canonicalAudit, DB::table('access_audit')->count());
        self::assertSame($transfer['id'], $directory->inbox($recipient)[0]['id']);
        $rejected = $directory->respondTransfer($recipient, $transfer['id'], ['decision' => 'reject', 'version' => $transfer['version']]);
        $archived = $directory->archive($manager, $lead['id'], ['version' => $rejected['version']]);
        self::assertTrue($archived['archived']);
        self::assertSame('rejected', DB::table('crm_transfers')->where('id', $transfer['id'])->value('state'));
    }

    private function assertWorkflowConflict(callable $operation): void
    {
        try {
            $operation();
            self::fail('The stale or unavailable workflow must be rejected.');
        } catch (ConflictHttpException $exception) {
            self::assertSame(409, $exception->getStatusCode());
        }
    }

    /** @return array<string, string> */
    private function seedWorld(): array
    {
        $companyId = $this->id();
        $branchId = $this->id();
        $departmentId = $this->id();
        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Synthetic CRM Company', 'version' => 0]);
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Synthetic CRM Branch',
            'code' => 'crm-'.strtolower(substr($branchId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Synthetic CRM Department',
            'code' => 'crm-'.strtolower(substr($departmentId, -6)),
            'active' => true,
            'version' => 0,
        ]);

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'manager_id' => $this->seedUser($companyId, $branchId, $departmentId, 'manager', [
                'crm.read', 'crm.manage', 'crm.write', 'crm.assign', 'crm.transfer',
                'crm.convert', 'crm.taxonomy', 'contact.reveal', 'tasks.read', 'tasks.write',
            ]),
            'owner_id' => $this->seedUser($companyId, $branchId, $departmentId, 'owner', [
                'crm.read', 'crm.write', 'crm.transfer', 'tasks.read', 'tasks.write',
            ]),
            'recipient_id' => $this->seedUser($companyId, $branchId, $departmentId, 'recipient', [
                'crm.read', 'crm.write', 'tasks.read', 'tasks.write',
            ]),
            'outsider_id' => $this->seedUser($companyId, $branchId, $departmentId, 'outsider', [
                'crm.read', 'crm.write', 'crm.transfer', 'tasks.read', 'tasks.write',
            ]),
        ];
    }

    /** @param list<string> $permissions */
    private function seedUser(string $companyId, string $branchId, string $departmentId, string $suffix, array $permissions): string
    {
        $userId = $this->id();
        $roleId = $this->id();
        $now = now()->toDateTimeString();
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $companyId,
            'name' => 'Synthetic CRM '.$suffix.' role',
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
            'name' => 'Synthetic CRM '.$suffix,
            'email' => 'crm.'.$suffix.'.'.strtolower(substr($userId, -6)).'@example.test',
            'password' => Hash::make('unused-synthetic-password'),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);

        return $userId;
    }

    /** @return array<string, string> */
    private function contacts(): array
    {
        return [
            'phone' => '+201155500111',
            'email' => 'private.crm.contact@example.test',
            'whatsapp' => '+201155500112',
        ];
    }

    /** @param array<string, string> $contacts
     * @return array<string, mixed>
     */
    private function leadData(array $contacts): array
    {
        return [
            'name' => 'Synthetic Prospect',
            'organization' => 'Synthetic Organization',
            'source' => 'website',
            'contacts' => $contacts,
            'consent' => true,
            'preferences' => ['email' => true, 'whatsapp' => false],
            'notes' => 'Synthetic lead note',
            'stage_id' => null,
        ];
    }

    private function requestFor(string $userId): Request
    {
        $session = new Store('crm-test-'.$userId.'-'.Str::ulid(), new ArraySessionHandler(120));
        $session->start();
        $session->replace([
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ]);
        $request = Request::create('/crm/leads', 'POST');
        $request->setLaravelSession($session);

        return $request;
    }

    /** @param array<mixed> $values
     * @param  array<string, string>  $contacts
     */
    private function assertContactsMasked(array $values, array $contacts): void
    {
        $encoded = $this->encoded($values);
        foreach ($contacts as $contact) {
            self::assertStringNotContainsString($contact, $encoded);
        }
    }

    /** @param array<string, string>|null $contacts */
    private function assertDenied(callable $operation, ?array $contacts = null): void
    {
        try {
            $operation();
            self::fail('The operation must be denied outside the current lead assignment or transfer recipient scope.');
        } catch (AccessDeniedHttpException|NotFoundHttpException $exception) {
            foreach ($contacts ?? [] as $contact) {
                self::assertStringNotContainsString($contact, $exception->getMessage());
            }
        }
    }

    private function encoded(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }
}

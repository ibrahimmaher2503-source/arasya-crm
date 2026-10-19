<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class OpportunitySubmissionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_key_replay_is_private_and_creates_one_opportunity_history_and_audit(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(1);
        $this->seedLead($world, $leadId);

        $form = $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en')->assertOk();
        preg_match('/<input\b(?=[^>]*\bname="submission_key")(?=[^>]*\bvalue="([^"]+)")[^>]*>/i', (string) $form->getContent(), $matches);
        self::assertSame(1, preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $matches[1] ?? ''));

        $submissionKey = $matches[1];
        $payload = array_replace($this->opportunityPayload($leadId), [
            'amount' => '10.250000',
            'submission_key' => $submissionKey,
        ]);
        $created = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $payload)->assertCreated();
        $replayed = $this->postJsonAs($world['manager_id'], '/crm/pipeline', array_replace($payload, ['amount' => '10.25']))->assertCreated();
        $id = (string) $created->json('data.id');

        self::assertSame($id, $replayed->json('data.id'));
        self::assertSame(1, DB::table('crm_opportunities')->where('id', $id)->count());
        self::assertSame(1, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());
        $hash = (string) DB::table('crm_opportunities')->where('id', $id)->value('submission_hash');
        self::assertNotSame('', $hash);

        foreach ([$created, $replayed] as $response) {
            self::assertArrayNotHasKey('submission_key', $response->json('data'));
            self::assertArrayNotHasKey('submission_hash', $response->json('data'));
            self::assertStringNotContainsString($submissionKey, (string) $response->getContent());
            self::assertStringNotContainsString($hash, (string) $response->getContent());
        }

        $this->htmlAs($world['manager_id'], '/crm/pipeline?locale=en&selected='.$id)->assertOk()
            ->assertDontSee($submissionKey, false)
            ->assertDontSee($hash, false);
    }

    public function test_reusing_a_key_with_a_changed_payload_conflicts_without_mutation(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(2);
        $this->seedLead($world, $leadId);
        $payload = array_replace($this->opportunityPayload($leadId), ['submission_key' => (string) Str::uuid()]);
        $created = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $payload)->assertCreated();
        $id = (string) $created->json('data.id');

        $this->postJsonAs($world['manager_id'], '/crm/pipeline', array_replace($payload, ['title' => 'Changed payload']))
            ->assertStatus(409);

        self::assertSame(1, DB::table('crm_opportunities')->where('company_id', $world['company_id'])->count());
        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'title' => 'Synthetic pipeline opportunity', 'version' => 0]);
        self::assertSame(1, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());
    }

    public function test_malformed_key_is_rejected_while_legacy_create_without_key_remains_supported(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(3);
        $this->seedLead($world, $leadId);
        $payload = $this->opportunityPayload($leadId);

        $this->postJsonAs($world['manager_id'], '/crm/pipeline', array_replace($payload, ['submission_key' => 'not-a-uuid']))
            ->assertUnprocessable();
        self::assertSame(0, DB::table('crm_opportunities')->count());
        self::assertSame(0, DB::table('crm_opportunity_history')->count());
        self::assertSame(0, DB::table('crm_audit')->count());

        $created = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $payload)->assertCreated();
        $id = (string) $created->json('data.id');
        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'submission_key' => null, 'submission_hash' => null]);
        self::assertSame(1, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
    }

    public function test_submission_keys_are_scoped_to_the_actor_within_one_company(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(4);
        $this->seedLead($world, $leadId);
        $assignedWriter = $this->seedUser($world, 'Assigned pipeline writer', ['crm.read', 'crm.write']);
        DB::table('crm_leads')->where('id', $leadId)->update(['assignee_id' => $assignedWriter]);
        $payload = array_replace($this->opportunityPayload($leadId), ['submission_key' => (string) Str::uuid()]);

        $managerCreated = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $payload)->assertCreated();
        $writerCreated = $this->postJsonAs($assignedWriter, '/crm/pipeline', $payload)->assertCreated();

        self::assertNotSame($managerCreated->json('data.id'), $writerCreated->json('data.id'));
        self::assertSame(2, DB::table('crm_opportunities')->where('company_id', $world['company_id'])->count());
        self::assertSame(2, DB::table('crm_opportunity_history')->where('company_id', $world['company_id'])->count());
        self::assertSame(2, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());
    }

    public function test_submission_key_from_another_company_does_not_replay_its_opportunity(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $firstWorld = $this->seedWorld();
        $secondWorld = $this->seedWorld();
        $firstLeadId = $this->fixtureId(11);
        $secondLeadId = $this->fixtureId(12);
        $this->seedLead($firstWorld, $firstLeadId);
        $this->seedLead($secondWorld, $secondLeadId);
        $key = (string) Str::uuid();

        $first = $this->postJsonAs($firstWorld['manager_id'], '/crm/pipeline', array_replace(
            $this->opportunityPayload($firstLeadId),
            ['submission_key' => $key],
        ))->assertCreated();
        $second = $this->postJsonAs($secondWorld['manager_id'], '/crm/pipeline', array_replace(
            $this->opportunityPayload($secondLeadId),
            ['submission_key' => $key],
        ))->assertCreated();

        $firstId = (string) $first->json('data.id');
        $secondId = (string) $second->json('data.id');
        self::assertNotSame($firstId, $secondId);
        $this->assertDatabaseHas('crm_opportunities', ['id' => $firstId, 'company_id' => $firstWorld['company_id']]);
        $this->assertDatabaseHas('crm_opportunities', ['id' => $secondId, 'company_id' => $secondWorld['company_id']]);
        self::assertSame(1, DB::table('crm_opportunity_history')->where('company_id', $firstWorld['company_id'])->count());
        self::assertSame(1, DB::table('crm_opportunity_history')->where('company_id', $secondWorld['company_id'])->count());
    }

    public function test_replaying_after_lead_archival_conflicts_without_creating_history_or_audit(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(5);
        $this->seedLead($world, $leadId);
        $payload = array_replace($this->opportunityPayload($leadId), ['submission_key' => (string) Str::uuid()]);
        $created = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $payload)->assertCreated();
        $id = (string) $created->json('data.id');
        DB::table('crm_leads')->where('id', $leadId)->update(['archived' => true]);

        $this->postJsonAs($world['manager_id'], '/crm/pipeline', $payload)->assertStatus(409);

        self::assertSame(1, DB::table('crm_opportunities')->where('id', $id)->count());
        self::assertSame(1, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());
    }

    public function test_replay_rechecks_current_write_permission_and_lead_assignment(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(6);
        $this->seedLead($world, $leadId);
        $writerId = $this->seedUser($world, 'Scoped pipeline writer', ['crm.read', 'crm.write']);
        DB::table('crm_leads')->where('id', $leadId)->update(['assignee_id' => $writerId]);
        $payload = array_replace($this->opportunityPayload($leadId), ['submission_key' => (string) Str::uuid()]);
        $created = $this->postJsonAs($writerId, '/crm/pipeline', $payload)->assertCreated();
        $id = (string) $created->json('data.id');
        $roleId = (string) DB::table('access_user_roles')->where('user_id', $writerId)->value('role_id');

        DB::table('access_role_permissions')->where('role_id', $roleId)->where('permission', 'crm.write')->delete();
        $this->postJsonAs($writerId, '/crm/pipeline', $payload)->assertForbidden();

        DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => 'crm.write']);
        $replacementWriter = $this->seedUser($world, 'Replacement pipeline writer', ['crm.read', 'crm.write']);
        DB::table('crm_leads')->where('id', $leadId)->update(['assignee_id' => $replacementWriter]);
        $this->postJsonAs($writerId, '/crm/pipeline', $payload)->assertNotFound();

        self::assertSame(1, DB::table('crm_opportunities')->where('id', $id)->count());
        self::assertSame(1, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());
    }

    public function test_create_and_edit_append_immutable_safe_opportunity_snapshots(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(7);
        $this->seedLead($world, $leadId);
        $submissionPayload = array_replace(
            $this->opportunityPayload($leadId),
            ['submission_key' => (string) Str::uuid()],
        );
        $created = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $submissionPayload)->assertCreated();
        $id = (string) $created->json('data.id');
        $initialRow = DB::table('crm_opportunity_history')->where('opportunity_id', $id)->where('version', 0)->first();
        self::assertNotNull($initialRow);
        self::assertSame($world['company_id'], $initialRow->company_id);
        self::assertSame($world['manager_id'], $initialRow->actor_id);
        $initialSnapshotJson = (string) $initialRow->snapshot;
        $initialSnapshot = json_decode($initialSnapshotJson, true, 512, JSON_THROW_ON_ERROR);
        $snapshotKeys = array_keys($initialSnapshot);
        sort($snapshotKeys);
        self::assertSame(['amount', 'currency', 'expected_close', 'id', 'lead_id', 'probability', 'stage', 'title', 'updated_at', 'version', 'work_type'], $snapshotKeys);
        self::assertSame($id, $initialSnapshot['id']);
        self::assertSame($leadId, $initialSnapshot['lead_id']);
        self::assertSame('Synthetic pipeline opportunity', $initialSnapshot['title']);
        self::assertSame(0, $initialSnapshot['version']);

        $update = array_replace(array_diff_key($this->opportunityPayload($leadId), ['lead_id' => true]), [
            'title' => 'Updated synthetic pipeline opportunity',
            'amount' => '77.500000',
            'version' => 0,
        ]);
        $this->patchJsonAs($world['manager_id'], '/crm/pipeline/'.$id, $update)
            ->assertOk()
            ->assertJsonPath('data.version', 1);

        $replayed = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $submissionPayload)
            ->assertCreated()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.title', 'Updated synthetic pipeline opportunity')
            ->assertJsonPath('data.version', 1);

        $history = DB::table('crm_opportunity_history')->where('opportunity_id', $id)->orderBy('version')->get();
        self::assertSame([0, 1], $history->pluck('version')->map(static fn ($version): int => (int) $version)->all());
        self::assertSame($initialSnapshotJson, (string) $history->first()->snapshot);
        $updatedSnapshot = json_decode((string) $history->last()->snapshot, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Updated synthetic pipeline opportunity', $updatedSnapshot['title']);
        self::assertSame('77.500000', $updatedSnapshot['amount']);
        self::assertSame(1, $updatedSnapshot['version']);
        foreach (['submission_key', 'submission_hash', 'contacts'] as $forbiddenField) {
            self::assertArrayNotHasKey($forbiddenField, $initialSnapshot);
            self::assertArrayNotHasKey($forbiddenField, $updatedSnapshot);
        }
        self::assertSame($id, $replayed->json('data.id'));
        self::assertSame(2, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(2, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());

        $this->htmlAs($world['manager_id'], '/crm/pipeline?'.http_build_query(['locale' => 'en', 'selected' => $id]))
            ->assertOk()
            ->assertSeeText('Opportunity history')
            ->assertSeeText('Synthetic pipeline opportunity');
    }

    public function test_invalid_stale_denied_and_keyed_edits_leave_history_audit_and_opportunity_unchanged(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(8);
        $this->seedLead($world, $leadId);
        $created = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $this->opportunityPayload($leadId))->assertCreated();
        $id = (string) $created->json('data.id');
        $base = array_diff_key($this->opportunityPayload($leadId), ['lead_id' => true]);

        $this->patchJsonAs($world['manager_id'], '/crm/pipeline/'.$id, array_replace($base, ['version' => 0, 'amount' => '1.1234567']))
            ->assertUnprocessable();
        $this->patchJsonAs($world['manager_id'], '/crm/pipeline/'.$id, array_replace($base, ['version' => 9, 'title' => 'Stale edit']))
            ->assertStatus(409);
        $this->patchJsonAs($world['manager_id'], '/crm/pipeline/'.$id, array_replace($base, ['version' => 0, 'submission_key' => (string) Str::uuid()]))
            ->assertUnprocessable();

        $readerId = $this->seedUser($world, 'Read-only pipeline user', ['crm.read']);
        DB::table('crm_leads')->where('id', $leadId)->update(['assignee_id' => $readerId]);
        $this->patchJsonAs($readerId, '/crm/pipeline/'.$id, array_replace($base, ['version' => 0, 'title' => 'Denied edit']))
            ->assertForbidden();

        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'title' => 'Synthetic pipeline opportunity', 'version' => 0]);
        self::assertSame(1, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());
    }

    public function test_selected_history_is_authorized_and_paginates_newest_version_first(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $visibleLead = $this->fixtureId(9);
        $hiddenLead = $this->fixtureId(10);
        $this->seedLead($world, $visibleLead);
        $this->seedLead($world, $hiddenLead, 'Hidden pipeline lead');
        $reader = $this->seedUser($world, 'History reader', ['crm.read', 'crm.write']);
        $otherWriter = $this->seedUser($world, 'Other scoped writer', ['crm.read', 'crm.write']);
        DB::table('crm_leads')->where('id', $visibleLead)->update(['assignee_id' => $reader]);
        DB::table('crm_leads')->where('id', $hiddenLead)->update(['assignee_id' => $otherWriter]);
        $visible = $this->postJsonAs($reader, '/crm/pipeline', $this->opportunityPayload($visibleLead))->assertCreated();
        $visibleId = (string) $visible->json('data.id');
        $hidden = $this->postJsonAs($otherWriter, '/crm/pipeline', $this->opportunityPayload($hiddenLead))->assertCreated();
        $hiddenId = (string) $hidden->json('data.id');

        for ($version = 0; $version < 25; $version++) {
            $update = array_replace(array_diff_key($this->opportunityPayload($visibleLead), ['lead_id' => true]), [
                'title' => 'History revision '.($version + 1),
                'version' => $version,
            ]);
            $this->patchJsonAs($reader, '/crm/pipeline/'.$visibleId, $update)->assertOk();
        }

        $this->htmlAs($reader, '/crm/pipeline?'.http_build_query(['locale' => 'en', 'selected' => $visibleId]))
            ->assertOk()
            ->assertViewHas('history', static function (mixed $history): bool {
                if (! $history instanceof LengthAwarePaginator || $history->perPage() !== 25 || $history->total() !== 26 || $history->currentPage() !== 1) {
                    return false;
                }

                return collect($history->items())->pluck('version')->map(static fn ($version): int => (int) $version)->all() === range(25, 1, -1);
            });
        $this->htmlAs($reader, '/crm/pipeline?'.http_build_query(['locale' => 'en', 'selected' => $visibleId, 'history_page' => 2]))
            ->assertOk()
            ->assertViewHas('history', static fn (mixed $history): bool => $history instanceof LengthAwarePaginator
                && $history->perPage() === 25
                && $history->total() === 26
                && $history->currentPage() === 2
                && collect($history->items())->pluck('version')->map(static fn ($version): int => (int) $version)->all() === [0]);
        $this->htmlAs($reader, '/crm/pipeline?locale=en')->assertOk()->assertViewHas('history', null);
        $this->htmlAs($reader, '/crm/pipeline?'.http_build_query(['locale' => 'en', 'selected' => $hiddenId]))
            ->assertOk()
            ->assertViewHas('selected', null)
            ->assertViewHas('history', null)
            ->assertDontSee('Hidden pipeline lead', false);
    }

    public function test_create_rolls_back_opportunity_and_history_when_audit_insert_fails_then_retries_same_key(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $trigger = $this->beginAuditInsertFailureScenario();

        try {
            $world = $this->seedWorld();
            $leadId = $this->fixtureId(13);
            $this->seedLead($world, $leadId);
            $payload = array_replace($this->opportunityPayload($leadId), ['submission_key' => (string) Str::uuid()]);

            $this->failOpportunityAuditInsert();
            $this->postJsonAs($world['manager_id'], '/crm/pipeline', $payload)->assertStatus(500);

            self::assertSame(0, DB::table('crm_opportunities')->where('company_id', $world['company_id'])->count());
            self::assertSame(0, DB::table('crm_opportunity_history')->where('company_id', $world['company_id'])->count());
            self::assertSame(0, DB::table('crm_audit')->where('company_id', $world['company_id'])->where('action', 'opportunity.saved')->count());

            $this->allowOpportunityAuditInsert();
            $retried = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $payload)->assertCreated();
            $id = (string) $retried->json('data.id');

            self::assertSame(1, DB::table('crm_opportunities')->where('company_id', $world['company_id'])->where('submission_key', $payload['submission_key'])->count());
            self::assertSame(1, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
            self::assertSame(1, DB::table('crm_audit')->where('company_id', $world['company_id'])->where('action', 'opportunity.saved')->count());
        } finally {
            $this->finishAuditInsertFailureScenario($trigger);
        }
    }

    public function test_edit_rolls_back_opportunity_and_history_when_audit_insert_fails_then_retries(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $trigger = $this->beginAuditInsertFailureScenario();

        try {
            $world = $this->seedWorld();
            $leadId = $this->fixtureId(14);
            $this->seedLead($world, $leadId);
            $created = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $this->opportunityPayload($leadId))->assertCreated();
            $id = (string) $created->json('data.id');
            $update = array_replace(array_diff_key($this->opportunityPayload($leadId), ['lead_id' => true]), [
                'title' => 'Updated synthetic pipeline opportunity',
                'version' => 0,
            ]);

            $this->failOpportunityAuditInsert();
            $this->patchJsonAs($world['manager_id'], '/crm/pipeline/'.$id, $update)->assertStatus(500);

            $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'title' => 'Synthetic pipeline opportunity', 'version' => 0]);
            self::assertSame(1, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
            self::assertSame(1, DB::table('crm_audit')->where('company_id', $world['company_id'])->where('action', 'opportunity.saved')->count());

            $this->allowOpportunityAuditInsert();
            $this->patchJsonAs($world['manager_id'], '/crm/pipeline/'.$id, $update)->assertOk()->assertJsonPath('data.version', 1);

            $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'title' => 'Updated synthetic pipeline opportunity', 'version' => 1]);
            self::assertSame(2, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
            self::assertSame(2, DB::table('crm_audit')->where('company_id', $world['company_id'])->where('action', 'opportunity.saved')->count());
        } finally {
            $this->finishAuditInsertFailureScenario($trigger);
        }
    }

    public function test_transition_rolls_back_stage_and_history_when_audit_insert_fails_then_retries_same_key(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $trigger = $this->beginAuditInsertFailureScenario();

        try {
            $world = $this->seedWorld();
            $leadId = $this->fixtureId(15);
            $this->seedLead($world, $leadId);
            $created = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $this->opportunityPayload($leadId))->assertCreated();
            $id = (string) $created->json('data.id');
            $payload = [
                'target_stage' => 'contact',
                'version' => 0,
                'submission_key' => (string) Str::uuid(),
                'reason' => null,
                'outcome' => null,
            ];

            $this->failOpportunityAuditInsert();
            $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $payload)->assertStatus(500);

            $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'stage' => 'lead', 'version' => 0]);
            self::assertSame(1, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
            self::assertSame(0, DB::table('crm_audit')->where('company_id', $world['company_id'])->where('action', 'opportunity.transitioned')->count());

            $this->allowOpportunityAuditInsert();
            $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $payload)->assertOk()->assertJsonPath('data.version', 1);

            $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'stage' => 'contact', 'version' => 1]);
            self::assertSame(2, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
            self::assertSame(1, DB::table('crm_audit')->where('company_id', $world['company_id'])->where('action', 'opportunity.transitioned')->count());
        } finally {
            $this->finishAuditInsertFailureScenario($trigger);
        }
    }

    /** @return array{lead_id: string, title: string, amount: string, currency: string, stage: string, probability: int, expected_close: string} */
    private function opportunityPayload(string $leadId): array
    {
        return [
            'lead_id' => $leadId,
            'title' => 'Synthetic pipeline opportunity',
            'amount' => '12345.670001',
            'currency' => 'SAR',
            'stage' => 'lead',
            'probability' => 40,
            'expected_close' => '2026-10-31',
        ];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id: string} $world */
    private function seedLead(array $world, string $id, string $name = 'Synthetic Pipeline Lead'): void
    {
        DB::table('crm_leads')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => $name,
            'organization' => 'Synthetic Organization '.$name,
            'source' => 'website',
            'contacts' => Crypt::encryptString(json_encode(['phone' => null, 'email' => null, 'whatsapp' => null], JSON_THROW_ON_ERROR)),
            'duplicate_key' => null,
            'consent' => false,
            'preferences' => '{}',
            'notes' => null,
            'stage_id' => null,
            'assignee_id' => null,
            'customer_id' => null,
            'archived' => false,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{company_id: string, branch_id: string, department_id: string, manager_id: string} */
    private function seedWorld(): array
    {
        $companyId = (string) Str::ulid();
        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Synthetic CRM Pipeline Company', 'version' => 0]);
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Synthetic CRM Pipeline Branch',
            'code' => 'pipeline-'.strtolower(substr($branchId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Synthetic CRM Pipeline Department',
            'code' => 'pipeline-'.strtolower(substr($departmentId, -6)),
            'version' => 0,
        ]);
        $world = ['company_id' => $companyId, 'branch_id' => $branchId, 'department_id' => $departmentId];

        return $world + ['manager_id' => $this->seedUser($world, 'Synthetic CRM Pipeline Manager', ['crm.read', 'crm.manage', 'crm.write'])];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string} $world
     * @param  list<string>  $permissions
     */
    private function seedUser(array $world, string $name, array $permissions): string
    {
        $userId = (string) Str::ulid();
        $roleId = (string) Str::ulid();
        DB::table('access_roles')->insert(['id' => $roleId, 'company_id' => $world['company_id'], 'name' => 'Synthetic CRM Pipeline '.$name.' Role', 'version' => 0]);
        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => $name,
            'email' => 'crm-pipeline-'.strtolower(substr($userId, -8)).'@example.test',
            'password' => Hash::make('unused-synthetic-pipeline-password'),
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

    private function htmlAs(string $userId, string $uri): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))
            ->withHeaders(['Accept' => 'text/html'])
            ->get($uri);
    }

    /** @param array<string, mixed> $payload */
    private function postJsonAs(string $userId, string $uri, array $payload): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))->postJson($uri, $payload);
    }

    /** @param array<string, mixed> $payload */
    private function patchJsonAs(string $userId, string $uri, array $payload): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))->patchJson($uri, $payload);
    }

    private function beginAuditInsertFailureScenario(): string
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Audit trigger rollback coverage requires MariaDB.');
        }
        $version = strtolower((string) $connection->selectOne('select version() as version')->version);
        if (! str_contains($version, 'mariadb')) {
            $this->markTestSkipped('Audit trigger rollback coverage requires MariaDB.');
        }

        // MariaDB trigger DDL commits, so start the fixture transaction after installing it.
        while ($connection->transactionLevel() > 0) {
            $connection->commit();
        }
        $trigger = 'crm_s1_fail_audit_'.Str::lower(Str::random(8));
        try {
            $connection->unprepared("CREATE TRIGGER `{$trigger}` BEFORE INSERT ON `crm_audit` FOR EACH ROW BEGIN IF COALESCE(@crm_s1_fail_audit, 0) = 1 AND NEW.action IN ('opportunity.saved', 'opportunity.transitioned') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic audit insert failure'; END IF; END");
            $connection->beginTransaction();
            $connection->unprepared('SET @crm_s1_fail_audit = 0');
        } catch (\Throwable $exception) {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            $connection->unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");

            throw $exception;
        }

        return $trigger;
    }

    private function failOpportunityAuditInsert(): void
    {
        DB::connection()->unprepared('SET @crm_s1_fail_audit = 1');
    }

    private function allowOpportunityAuditInsert(): void
    {
        DB::connection()->unprepared('SET @crm_s1_fail_audit = 0');
    }

    private function finishAuditInsertFailureScenario(string $trigger): void
    {
        $connection = DB::connection();
        $connection->unprepared('SET @crm_s1_fail_audit = 0');
        while ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
        $connection->unprepared("DROP TRIGGER IF EXISTS `{$trigger}`");
    }
}

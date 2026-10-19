<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class OpportunityTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_opportunities_start_in_lead_and_cannot_be_created_in_a_later_stage(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $firstLead = $this->fixtureId(1);
        $secondLead = $this->fixtureId(2);
        $this->seedLead($world, $firstLead);
        $this->seedLead($world, $secondLead);

        $created = $this->postJsonAs($world['manager_id'], '/crm/pipeline', $this->opportunityPayload($firstLead))->assertCreated();
        $id = (string) $created->json('data.id');
        $this->postJsonAs($world['manager_id'], '/crm/pipeline', array_replace($this->opportunityPayload($secondLead), ['stage' => 'proposal']))
            ->assertUnprocessable();

        $this->assertDatabaseHas('crm_opportunities', [
            'id' => $id,
            'stage' => 'lead',
            'version' => 0,
            'closing_outcome' => null,
        ]);
        self::assertSame(1, DB::table('crm_opportunities')->where('company_id', $world['company_id'])->count());
        self::assertSame(1, DB::table('crm_opportunity_history')->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());
    }

    public function test_adjacent_forward_transition_advances_version_and_appends_safe_history_and_audit(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(3);
        $this->seedLead($world, $leadId);
        $id = (string) $this->createOpportunity($world, $leadId)->json('data.id');
        $payload = $this->transitionPayload('contact', 0);

        $response = $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $payload)->assertOk();
        $response->assertJsonPath('data.id', $id)->assertJsonPath('data.version', 1);
        $data = (array) $response->json('data');
        self::assertArrayNotHasKey('transition_key', $data);
        self::assertArrayNotHasKey('transition_hash', $data);
        self::assertStringNotContainsString($payload['submission_key'], (string) $response->getContent());

        $this->assertDatabaseHas('crm_opportunities', [
            'id' => $id,
            'stage' => 'contact',
            'version' => 1,
            'closing_outcome' => null,
        ]);
        $history = DB::table('crm_opportunity_history')->where('opportunity_id', $id)->where('version', 1)->first();
        self::assertNotNull($history);
        self::assertSame('transitioned', $history->action);
        self::assertSame('lead', $history->from_stage);
        self::assertFalse((bool) $history->reason_provided);
        self::assertSame(strtolower($payload['submission_key']), $history->transition_key);
        self::assertNotSame('', (string) $history->transition_hash);
        $snapshot = json_decode((string) $history->snapshot, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('contact', $snapshot['stage']);
        self::assertSame(1, $snapshot['version']);
        foreach (['transition_key', 'transition_hash', 'reason', 'encrypted_reason', 'closing_outcome'] as $forbiddenField) {
            self::assertArrayNotHasKey($forbiddenField, $snapshot);
        }
        self::assertSame(2, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.transitioned')->count());
    }

    public function test_noop_jump_unknown_stage_outcome_on_open_stage_and_missing_key_are_rejected_without_mutation(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(4);
        $this->seedLead($world, $leadId);
        $id = (string) $this->createOpportunity($world, $leadId)->json('data.id');

        foreach ([
            ['target_stage' => 'lead'],
            ['target_stage' => 'proposal'],
            ['target_stage' => 'unknown'],
            ['target_stage' => 'contact', 'outcome' => 'won'],
            ['target_stage' => 'contact', 'outcome' => 'undecided'],
        ] as $invalid) {
            $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $this->transitionPayload(
                $invalid['target_stage'],
                0,
                array_diff_key($invalid, ['target_stage' => true]),
            ))->assertUnprocessable();
        }
        $missingKey = $this->transitionPayload('contact', 0, ['submission_key' => null]);
        $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $missingKey)->assertUnprocessable();

        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'stage' => 'lead', 'version' => 0, 'closing_outcome' => null]);
        self::assertSame(1, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(0, DB::table('crm_audit')->where('action', 'opportunity.transitioned')->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());
    }

    public function test_backward_transition_requires_a_reason_and_encrypts_it_in_audit_only(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(5);
        $this->seedLead($world, $leadId);
        $id = (string) $this->createOpportunity($world, $leadId)->json('data.id');
        $forward = $this->transitionPayload('contact', 0);
        $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $forward)->assertOk();

        $blankReason = $this->transitionPayload('lead', 1, ['reason' => '   ']);
        $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $blankReason)->assertUnprocessable();
        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'stage' => 'contact', 'version' => 1]);
        self::assertSame(2, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());

        $reason = 'Customer requested reassessment';
        $backward = $this->transitionPayload('lead', 1, ['reason' => $reason]);
        $response = $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $backward)->assertOk();
        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'stage' => 'lead', 'version' => 2]);
        $history = DB::table('crm_opportunity_history')->where('opportunity_id', $id)->where('version', 2)->first();
        self::assertNotNull($history);
        self::assertSame('contact', $history->from_stage);
        self::assertTrue((bool) $history->reason_provided);
        $snapshot = json_decode((string) $history->snapshot, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('lead', $snapshot['stage']);
        self::assertSame(2, $snapshot['version']);
        foreach (['reason', 'encrypted_reason', 'transition_key', 'transition_hash'] as $forbiddenField) {
            self::assertArrayNotHasKey($forbiddenField, $snapshot);
        }
        self::assertStringNotContainsString($reason, (string) $history->snapshot);

        $audit = DB::table('crm_audit')->where('action', 'opportunity.transitioned')->where('metadata->version', 2)->first();
        self::assertNotNull($audit);
        $metadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('encrypted_reason', $metadata);
        self::assertArrayNotHasKey('reason', $metadata);
        self::assertSame($reason, Crypt::decryptString((string) $metadata['encrypted_reason']));
        self::assertStringNotContainsString($reason, (string) $response->getContent());
        self::assertStringNotContainsString($backward['submission_key'], (string) $response->getContent());

        $this->htmlAs($world['manager_id'], '/crm/pipeline?'.http_build_query(['locale' => 'en', 'selected' => $id]))
            ->assertOk()
            ->assertDontSee($reason, false);
        self::assertSame(3, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(2, DB::table('crm_audit')->where('action', 'opportunity.transitioned')->count());
    }

    public function test_long_unicode_backward_reason_is_encrypted_in_available_audit_ledgers(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(16);
        $this->seedLead($world, $leadId);
        $id = (string) $this->createOpportunity($world, $leadId)->json('data.id');
        $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $this->transitionPayload('contact', 0))->assertOk();
        $reason = str_repeat('😀', 1000);
        self::assertGreaterThan(4096, strlen(Crypt::encryptString($reason)));

        $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $this->transitionPayload('lead', 1, ['reason' => $reason]))
            ->assertOk();

        $audit = DB::table('crm_audit')->where('action', 'opportunity.transitioned')->where('metadata->version', 2)->first();
        self::assertNotNull($audit);
        $metadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('encrypted_reason', $metadata);
        self::assertSame($reason, Crypt::decryptString((string) $metadata['encrypted_reason']));

        if (Schema::hasTable('access_audit')) {
            $accessAudit = DB::table('access_audit')->where('action', 'opportunity.transitioned')->where('subject_id', $id)->where('metadata->version', 2)->first();
            self::assertNotNull($accessAudit);
            $accessMetadata = json_decode((string) $accessAudit->metadata, true, 512, JSON_THROW_ON_ERROR);
            self::assertArrayHasKey('encrypted_reason', $accessMetadata);
            self::assertSame($reason, Crypt::decryptString((string) $accessMetadata['encrypted_reason']));
        }
    }

    public function test_generic_edits_cannot_change_stage_or_modify_a_closed_opportunity(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(6);
        $this->seedLead($world, $leadId);
        $id = (string) $this->createOpportunity($world, $leadId)->json('data.id');
        $edit = array_diff_key($this->opportunityPayload($leadId), ['lead_id' => true]);
        $this->patchJsonAs($world['manager_id'], '/crm/pipeline/'.$id, array_replace($edit, ['stage' => 'contact', 'version' => 0]))
            ->assertUnprocessable();
        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'stage' => 'lead', 'version' => 0]);

        DB::table('crm_opportunities')->where('id', $id)->update(['stage' => 'closed', 'closing_outcome' => 'won', 'version' => 1]);
        $this->patchJsonAs($world['manager_id'], '/crm/pipeline/'.$id, array_replace($edit, [
            'stage' => 'closed',
            'version' => 1,
            'title' => 'Must remain closed',
        ]))->assertStatus(409);

        $this->assertDatabaseHas('crm_opportunities', [
            'id' => $id,
            'stage' => 'closed',
            'closing_outcome' => 'won',
            'version' => 1,
            'title' => 'Synthetic pipeline opportunity',
        ]);
        self::assertSame(1, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());
    }

    public function test_transition_replay_is_idempotent_and_changed_or_stale_requests_conflict(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(7);
        $this->seedLead($world, $leadId);
        $id = (string) $this->createOpportunity($world, $leadId)->json('data.id');
        $request = $this->transitionPayload('contact', 0);

        $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $request)->assertOk();
        $replayed = $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $request)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.version', 1);
        self::assertSame($id, $replayed->json('data.id'));

        $changed = array_replace($request, ['target_stage' => 'proposal']);
        $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $changed)->assertStatus(409);
        $stale = $this->transitionPayload('contact', 0);
        $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $stale)->assertStatus(409);

        $edit = array_diff_key($this->opportunityPayload($leadId), ['lead_id' => true]);
        $this->patchJsonAs($world['manager_id'], '/crm/pipeline/'.$id, array_replace($edit, [
            'stage' => 'contact',
            'version' => 1,
            'title' => 'Edited after transition',
        ]))->assertOk()->assertJsonPath('data.version', 2);
        $replayedAfterEdit = $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $request)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.title', 'Edited after transition');
        self::assertSame($id, $replayedAfterEdit->json('data.id'));

        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'stage' => 'contact', 'version' => 2, 'title' => 'Edited after transition']);
        self::assertSame(3, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.transitioned')->count());
        self::assertSame(2, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());
    }

    public function test_transition_replay_rechecks_write_permission_lead_scope_and_archival(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(8);
        $this->seedLead($world, $leadId);
        $writerId = $this->seedUser($world, 'Scoped transition writer', ['crm.read', 'crm.write']);
        DB::table('crm_leads')->where('id', $leadId)->update(['assignee_id' => $writerId]);
        $id = (string) $this->createOpportunity($world, $leadId, $writerId)->json('data.id');
        $request = $this->transitionPayload('contact', 0);
        $this->postJsonAs($writerId, '/crm/pipeline/'.$id.'/transition', $request)->assertOk();
        $roleId = (string) DB::table('access_user_roles')->where('user_id', $writerId)->value('role_id');

        DB::table('access_role_permissions')->where('role_id', $roleId)->where('permission', 'crm.write')->delete();
        $this->postJsonAs($writerId, '/crm/pipeline/'.$id.'/transition', $request)->assertForbidden();

        DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => 'crm.write']);
        $replacementWriter = $this->seedUser($world, 'Replacement transition writer', ['crm.read', 'crm.write']);
        DB::table('crm_leads')->where('id', $leadId)->update(['assignee_id' => $replacementWriter]);
        $this->postJsonAs($writerId, '/crm/pipeline/'.$id.'/transition', $request)->assertNotFound();

        DB::table('crm_leads')->where('id', $leadId)->update(['assignee_id' => $writerId, 'archived' => true]);
        $this->postJsonAs($writerId, '/crm/pipeline/'.$id.'/transition', $request)->assertStatus(409);

        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'stage' => 'contact', 'version' => 1]);
        self::assertSame(2, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.transitioned')->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());
    }

    public function test_even_an_admin_cannot_close_or_reopen_and_no_close_outcome_is_recorded(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $adminId = $world['manager_id'];
        DB::table('access_users')->where('id', $adminId)->update(['is_admin' => true]);
        $leadId = $this->fixtureId(9);
        $this->seedLead($world, $leadId);
        $id = (string) $this->createOpportunity($world, $leadId, $adminId)->json('data.id');

        $version = 0;
        foreach (['contact', 'proposal', 'negotiation', 'review'] as $target) {
            $this->postJsonAs($adminId, '/crm/pipeline/'.$id.'/transition', $this->transitionPayload($target, $version))->assertOk();
            $version++;
        }
        foreach (['won', 'lost'] as $outcome) {
            $this->postJsonAs($adminId, '/crm/pipeline/'.$id.'/transition', $this->transitionPayload('closed', $version, ['outcome' => $outcome]))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'stage' => 'review', 'version' => 4, 'closing_outcome' => null]);
        self::assertSame(5, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(4, DB::table('crm_audit')->where('action', 'opportunity.transitioned')->count());

        DB::table('crm_opportunities')->where('id', $id)->update(['stage' => 'closed', 'closing_outcome' => 'won', 'version' => 5]);
        $this->postJsonAs($adminId, '/crm/pipeline/'.$id.'/reopen', [
            'version' => 5,
            'submission_key' => (string) Str::uuid(),
            'reason' => 'Synthetic forbidden reopen attempt',
        ])->assertForbidden();

        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'stage' => 'closed', 'version' => 5, 'closing_outcome' => 'won']);
        self::assertSame(5, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(4, DB::table('crm_audit')->where('action', 'opportunity.transitioned')->count());
    }

    public function test_transition_and_reopen_endpoints_are_hidden_when_synthetic_sales_is_disabled(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(10);
        $this->seedLead($world, $leadId);
        $id = (string) $this->createOpportunity($world, $leadId)->json('data.id');
        config(['sales.synthetic_drafts' => false]);

        $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/transition', $this->transitionPayload('contact', 0))->assertNotFound();
        $this->postJsonAs($world['manager_id'], '/crm/pipeline/'.$id.'/reopen', [
            'version' => 0,
            'submission_key' => (string) Str::uuid(),
        ])->assertNotFound();

        $this->assertDatabaseHas('crm_opportunities', ['id' => $id, 'stage' => 'lead', 'version' => 0]);
        self::assertSame(1, DB::table('crm_opportunity_history')->where('opportunity_id', $id)->count());
        self::assertSame(1, DB::table('crm_audit')->where('action', 'opportunity.saved')->count());
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

    /** @return array{target_stage: string, version: int, submission_key: string, reason: string|null, outcome: string|null} */
    private function transitionPayload(string $targetStage, int $version, array $overrides = []): array
    {
        return array_replace([
            'target_stage' => $targetStage,
            'version' => $version,
            'submission_key' => strtolower((string) Str::uuid()),
            'reason' => null,
            'outcome' => null,
        ], $overrides);
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id: string} $world */
    private function createOpportunity(array $world, string $leadId, ?string $actorId = null): TestResponse
    {
        return $this->postJsonAs($actorId ?? $world['manager_id'], '/crm/pipeline', $this->opportunityPayload($leadId))->assertCreated();
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id: string} $world */
    private function seedLead(array $world, string $id): void
    {
        DB::table('crm_leads')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => 'Synthetic Transition Lead',
            'organization' => 'Synthetic Transition Organization',
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
        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Synthetic Opportunity Transition Company', 'version' => 0]);
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Synthetic Opportunity Transition Branch',
            'code' => 'transition-'.strtolower(substr($branchId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Synthetic Opportunity Transition Department',
            'code' => 'transition-'.strtolower(substr($departmentId, -6)),
            'version' => 0,
        ]);
        $world = ['company_id' => $companyId, 'branch_id' => $branchId, 'department_id' => $departmentId];

        return $world + ['manager_id' => $this->seedUser($world, 'Synthetic Transition Manager', ['crm.read', 'crm.manage', 'crm.write'])];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string} $world
     * @param  list<string>  $permissions
     */
    private function seedUser(array $world, string $name, array $permissions): string
    {
        $userId = (string) Str::ulid();
        $roleId = (string) Str::ulid();
        DB::table('access_roles')->insert(['id' => $roleId, 'company_id' => $world['company_id'], 'name' => 'Synthetic Transition '.$name.' Role', 'version' => 0]);
        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => $name,
            'email' => 'crm-transition-'.strtolower(substr($userId, -8)).'@example.test',
            'password' => Hash::make('unused-synthetic-transition-password'),
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

    /** @param array<string, mixed> $world */
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
}

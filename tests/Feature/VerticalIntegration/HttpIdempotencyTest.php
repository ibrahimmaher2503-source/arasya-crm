<?php

namespace Tests\Feature\VerticalIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class HttpIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaying_same_authenticated_request_with_idempotency_key_reuses_result(): void
    {
        $session = [
            'actor_id' => 'synthetic-actor',
            'entity_id' => 'synthetic-entity',
            'department_id' => 'synthetic-branch',
            'permissions' => ['cycles.write'],
        ];
        $payload = [
            'record_type' => 'synthetic-cycle',
            'payload' => ['state' => 'created'],
        ];

        $first = $this->withSession($session)
            ->withHeader('Idempotency-Key', 'synthetic-request-1')
            ->postJson('/vertical/cycle-records', $payload)
            ->assertCreated();
        $second = $this->withSession($session)
            ->withHeader('Idempotency-Key', 'synthetic-request-1')
            ->postJson('/vertical/cycle-records', $payload)
            ->assertCreated();

        $this->assertSame($first->json('data.record_id'), $second->json('data.record_id'));
        $this->assertSame(1, DB::table('vertical_cycle_records')->count());
    }

    public function test_same_key_is_isolated_by_entity_and_branch(): void
    {
        $ids = [];
        foreach ([['entity-a', 'branch-a'], ['entity-b', 'branch-a'], ['entity-a', 'branch-b']] as [$entity, $branch]) {
            $ids[] = $this->withSession($this->actorSession($entity, $branch))->withHeader('Idempotency-Key', 'shared-key')
                ->postJson('/vertical/cycle-records', ['record_type' => 'cycle', 'payload' => ['state' => 'created']])
                ->assertCreated()->json('data.record_id');
        }
        $this->assertCount(3, array_unique($ids));
        $this->assertSame(3, DB::table('vertical_cycle_records')->count());
    }

    public function test_reusing_key_for_changed_type_or_payload_returns_conflict(): void
    {
        $this->withSession($this->actorSession())->withHeader('Idempotency-Key', 'conflict-key')
            ->postJson('/vertical/cycle-records', ['record_type' => 'cycle', 'payload' => ['state' => 'created']])->assertCreated();
        foreach ([['record_type' => 'other', 'payload' => ['state' => 'created']], ['record_type' => 'cycle', 'payload' => ['state' => 'changed']]] as $body) {
            $this->postJson('/vertical/cycle-records', $body)->assertConflict();
        }
        $this->assertSame(1, DB::table('vertical_cycle_records')->count());
    }

    public function test_oversized_idempotency_header_is_rejected_before_persistence(): void
    {
        $this->withSession($this->actorSession())->withHeader('Idempotency-Key', str_repeat('x', 129))
            ->postJson('/vertical/cycle-records', ['record_type' => 'cycle', 'payload' => ['state' => 'created']])
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertSame(0, DB::table('vertical_cycle_records')->count());
    }

    public function test_duplicate_insert_race_returns_the_winning_record(): void
    {
        $injected = false;
        DB::listen(function ($query) use (&$injected): void {
            if ($injected || ! str_starts_with($query->sql, 'select') || ! str_contains($query->sql, 'vertical_cycle_records')) {
                return;
            }
            $injected = true;
            DB::table('vertical_cycle_records')->insert([
                'record_id' => '01WINNER000000000000000000', 'entity_id' => 'entity-a', 'branch_id' => 'branch-a',
                'record_type' => 'cycle', 'payload' => json_encode(['state' => 'created']),
                'idempotency_key' => hash('sha256', json_encode(['entity-a', 'branch-a', 'race-key'])),
                'audit_reference' => 'audit-winner', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        $this->withSession($this->actorSession())->withHeader('Idempotency-Key', 'race-key')
            ->postJson('/vertical/cycle-records', ['record_type' => 'cycle', 'payload' => ['state' => 'created']])
            ->assertCreated()->assertJsonPath('data.record_id', '01WINNER000000000000000000');
        $this->assertSame(1, DB::table('vertical_cycle_records')->count());
    }

    public function test_reordered_object_keys_replay_but_changed_scalar_types_conflict(): void
    {
        $this->withSession($this->actorSession())->withHeader('Idempotency-Key', str_repeat('k', 128));
        $first = $this->postJson('/vertical/cycle-records', ['record_type' => 'cycle', 'payload' => ['outer' => ['z' => 1, 'a' => 'value']]])->assertCreated();
        $this->postJson('/vertical/cycle-records', ['record_type' => 'cycle', 'payload' => ['outer' => ['a' => 'value', 'z' => 1]]])
            ->assertCreated()->assertJsonPath('data.record_id', $first->json('data.record_id'));
        $this->postJson('/vertical/cycle-records', ['record_type' => 'cycle', 'payload' => ['outer' => ['a' => 'value', 'z' => '1']]])->assertConflict();
        $this->assertLessThanOrEqual(128, strlen(DB::table('vertical_cycle_records')->value('idempotency_key')));
    }

    private function actorSession(string $entity = 'entity-a', string $branch = 'branch-a'): array
    {
        return ['actor_id' => 'synthetic-actor', 'entity_id' => $entity, 'department_id' => $branch, 'permissions' => ['cycles.write']];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\VerticalIntegration;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PersistenceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MariaDB qualification connection is unavailable; no fallback is treated as PASS.');
        }
    }

    public function test_synthetic_cycle_record_persists_json_scope_and_audit_linkage(): void
    {
        $recordId = (string) Str::ulid();

        DB::table('vertical_cycle_records')->insert([
            'record_id' => $recordId,
            'entity_id' => 'acceptance-entity',
            'branch_id' => 'acceptance-branch',
            'record_type' => 'acceptance-cycle',
            'payload' => json_encode(['synthetic' => true, 'state' => 'created'], JSON_THROW_ON_ERROR),
            'idempotency_key' => 'acceptance-'.$recordId,
            'audit_reference' => 'audit-'.$recordId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = DB::table('vertical_cycle_records')->where('record_id', $recordId)->first();

        $this->assertNotNull($record);
        $this->assertSame('acceptance-entity', $record->entity_id);
        $this->assertSame('acceptance-branch', $record->branch_id);
        $this->assertSame(['synthetic' => true, 'state' => 'created'], json_decode($record->payload, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('audit-'.$recordId, $record->audit_reference);
    }

    public function test_duplicate_idempotency_key_is_rejected_by_mariadb_constraint(): void
    {
        $idempotencyKey = 'acceptance-idempotency-'.Str::random(12);
        $timestamps = ['created_at' => now(), 'updated_at' => now()];

        DB::table('vertical_cycle_records')->insert(array_merge([
            'record_id' => (string) Str::ulid(),
            'entity_id' => 'acceptance-entity',
            'branch_id' => 'acceptance-branch',
            'record_type' => 'acceptance-cycle',
            'payload' => '{}',
            'idempotency_key' => $idempotencyKey,
            'audit_reference' => 'audit-first',
        ], $timestamps));

        $this->expectException(QueryException::class);
        DB::table('vertical_cycle_records')->insert(array_merge([
            'record_id' => (string) Str::ulid(),
            'entity_id' => 'acceptance-entity',
            'branch_id' => 'acceptance-branch',
            'record_type' => 'acceptance-cycle',
            'payload' => '{}',
            'idempotency_key' => $idempotencyKey,
            'audit_reference' => 'audit-replay',
        ], $timestamps));
    }

    public function test_cleanup_removes_only_synthetic_acceptance_rows(): void
    {
        $ownedId = (string) Str::ulid();
        $otherId = (string) Str::ulid();
        $timestamps = ['created_at' => now(), 'updated_at' => now()];

        foreach ([[$ownedId, 'acceptance-entity'], [$otherId, 'other-entity']] as [$recordId, $entityId]) {
            DB::table('vertical_cycle_records')->insert(array_merge([
                'record_id' => $recordId,
                'entity_id' => $entityId,
                'branch_id' => 'acceptance-branch',
                'record_type' => 'acceptance-cycle',
                'payload' => '{}',
                'idempotency_key' => 'cleanup-'.$recordId,
                'audit_reference' => 'audit-'.$recordId,
            ], $timestamps));
        }

        $deleted = DB::table('vertical_cycle_records')->whereIn('record_id', [$ownedId])->delete();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('vertical_cycle_records', ['record_id' => $ownedId]);
        $this->assertDatabaseHas('vertical_cycle_records', ['record_id' => $otherId]);
    }
}

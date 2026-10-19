<?php

namespace Tests\Feature\VerticalIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PersistenceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_records_persist_with_scope_and_idempotency(): void
    {
        $id = (string) Str::ulid();

        DB::table('vertical_cycle_records')->insert([
            'record_id' => $id,
            'entity_id' => 'entity-demo',
            'branch_id' => 'branch-demo',
            'record_type' => 'synthetic-cycle',
            'payload' => json_encode(['state' => 'created'], JSON_THROW_ON_ERROR),
            'idempotency_key' => 'cycle-'.$id,
            'audit_reference' => 'audit-'.$id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('entity-demo', DB::table('vertical_cycle_records')->where('record_id', $id)->value('entity_id'));
        $this->assertSame(1, DB::table('vertical_cycle_records')->where('idempotency_key', 'cycle-'.$id)->count());
    }
}

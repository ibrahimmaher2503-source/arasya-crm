<?php

namespace Tests\Feature\VerticalIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class HttpContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_cycle_record_request_persists(): void
    {
        $response = $this->withSession([
            'actor_id' => 'actor-1',
            'entity_id' => 'entity-1',
            'department_id' => 'branch-1',
            'permissions' => ['cycles.write'],
        ])->postJson('/vertical/cycle-records', [
            'record_type' => 'synthetic-cycle',
            'payload' => ['state' => 'created'],
        ]);

        $response->assertCreated()->assertJsonPath('data.entity_id', 'entity-1');
        $this->assertDatabaseHas('vertical_cycle_records', ['entity_id' => 'entity-1', 'record_type' => 'synthetic-cycle']);
    }

    public function test_missing_session_identity_is_denied_and_invalid_input_is_rejected(): void
    {
        $this->postJson('/vertical/cycle-records', ['record_type' => ''])->assertUnauthorized();

        $this->withSession([
            'actor_id' => 'actor-1',
            'entity_id' => 'entity-1',
            'department_id' => 'branch-1',
            'permissions' => ['cycles.write'],
        ])->postJson('/vertical/cycle-records', ['record_type' => ''])->assertUnprocessable();

        $this->assertSame(0, DB::table('vertical_cycle_records')->count());
    }
}

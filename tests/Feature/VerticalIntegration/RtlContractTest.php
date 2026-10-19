<?php

namespace Tests\Feature\VerticalIntegration;

use Tests\TestCase;

final class RtlContractTest extends TestCase
{
    public function test_authenticated_cycle_entry_point_renders_rtl_shell(): void
    {
        $response = $this->withSession([
            'actor_id' => 'actor-1',
            'entity_id' => 'entity-1',
            'department_id' => 'branch-1',
            'permissions' => ['cycles.write'],
        ])->get('/vertical/cycle-records');

        $response->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('cycle-record-form', false)
            ->assertSee('record_type', false);
    }

    public function test_cycle_entry_point_denies_missing_identity(): void
    {
        $this->get('/vertical/cycle-records')->assertUnauthorized();
    }
}

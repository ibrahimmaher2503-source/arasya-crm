<?php

namespace Tests\Feature\VerticalIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AuthorizationNegativePathTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_actor_without_cycle_write_permission_is_denied_without_persistence(): void
    {
        $this->withSession([
            'actor_id' => 'actor-no-cycle-write',
            'entity_id' => 'entity-negative',
            'department_id' => 'branch-negative',
            'permissions' => ['cycles.read'],
        ])->postJson('/vertical/cycle-records', [
            'record_type' => 'unauthorized-cycle',
            'payload' => ['synthetic' => true],
        ])->assertUnauthorized();

        $this->assertSame(0, DB::table('vertical_cycle_records')->count());
    }
}

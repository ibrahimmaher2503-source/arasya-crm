<?php

namespace Tests\Feature\VerticalIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class A5VINT002VerticalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mariadb_acceptance_path_persists_and_denies_without_identity(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MariaDB qualification connection is unavailable; no fallback is treated as PASS.');
        }

        $this->withSession([
            'actor_id' => 'qa-actor',
            'entity_id' => 'qa-entity',
            'department_id' => 'qa-branch',
            'permissions' => ['cycles.write'],
        ])->postJson('/vertical/cycle-records', [
            'record_type' => 'qualification-cycle',
            'payload' => ['synthetic' => true],
        ])->assertCreated();

        $this->withSession([
            'actor_id' => null,
            'entity_id' => null,
            'department_id' => null,
            'permissions' => [],
        ])->postJson('/vertical/cycle-records', [
            'record_type' => 'qualification-cycle',
            'payload' => ['synthetic' => true],
        ])->assertUnauthorized();
    }
}

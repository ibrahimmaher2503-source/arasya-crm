<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Modules\CRM\Domain\CRM01\LeadRecord;
use App\Modules\CRM\Infrastructure\CRM01\LeadAccessDiagnosticAdapter;
use Tests\TestCase;

final class CRM01AccessTest extends TestCase
{
    public function test_preview_is_entity_and_assignment_scoped(): void
    {
        $lead = new LeadRecord('entity-1', 'lead-1', 'A', 'Org', 'import', 'a@example.test', true);
        $result = (new LeadAccessDiagnosticAdapter)->preview('actor-1', 'entity-1', ['lead-1'], [
            ['email' => 'a@example.test', 'consent' => true],
            ['email' => 'new@example.test', 'consent' => false],
        ], [$lead, new LeadRecord('entity-2', 'lead-2', 'B', 'Org', 'import', 'a@example.test', true)]);

        self::assertSame('allow', $result['decision']);
        self::assertSame('duplicate', $result['items'][0]->classification);
        self::assertSame('new', $result['items'][1]->classification);
        self::assertTrue($result['items'][0]->consentGranted);
        self::assertStringNotContainsString('example.test', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function test_revoked_or_other_assignment_is_denied_without_fixture_leakage(): void
    {
        $lead = new LeadRecord('entity-1', 'lead-1', 'A', 'Org', 'import', 'a@example.test', true);
        $adapter = new LeadAccessDiagnosticAdapter;
        foreach ([
            $adapter->preview('actor-1', 'entity-1', [], [['email' => 'a@example.test']], [$lead]),
            $adapter->preview('actor-1', 'entity-1', ['lead-1'], [['email' => 'a@example.test']], [$lead], true),
        ] as $result) {
            self::assertSame(['decision' => 'deny', 'code' => 'access_denied', 'items' => []], $result);
            self::assertStringNotContainsString('example.test', json_encode($result, JSON_THROW_ON_ERROR));
        }
    }
}

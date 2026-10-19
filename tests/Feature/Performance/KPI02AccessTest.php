<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Modules\Performance\Infrastructure\KPI02\ApprovedResultAccessDiagnosticAdapter;
use Tests\TestCase;

final class KPI02AccessTest extends TestCase
{
    public function test_only_approved_results_are_exposed(): void
    {
        $r = (new ApprovedResultAccessDiagnosticAdapter)->project('e1', 'o1', [['entity_id' => 'e1', 'owner_id' => 'o1', 'metric' => 'revenue', 'value' => '10.00', 'period' => 'p', 'status' => 'approved'], ['entity_id' => 'e1', 'owner_id' => 'o1', 'metric' => 'cost', 'value' => '1.00', 'period' => 'p', 'status' => 'draft']]);
        self::assertSame('allow', $r['decision']);
        self::assertCount(1, $r['items']);
    }

    public function test_revoked_is_denied(): void
    {
        self::assertSame('deny', (new ApprovedResultAccessDiagnosticAdapter)->project('e1', 'o1', [], true)['decision']);
    }
}

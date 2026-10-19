<?php

declare(strict_types=1);

namespace Tests\Unit\Performance;

use App\Modules\Performance\Application\KPI02\ProjectApprovedResults;
use PHPUnit\Framework\TestCase;

final class KPI02DomainTest extends TestCase
{
    public function test_only_approved_owner_results_are_projected(): void
    {
        $r = (new ProjectApprovedResults)->execute('e1', 'o1', [['entity_id' => 'e1', 'owner_id' => 'o1', 'metric' => 'revenue', 'value' => '10.00', 'period' => '2026-01', 'status' => 'approved'], ['entity_id' => 'e1', 'owner_id' => 'o1', 'metric' => 'cost', 'value' => '5.00', 'period' => '2026-01', 'status' => 'draft'], ['entity_id' => 'e1', 'owner_id' => 'o2', 'metric' => 'revenue', 'value' => '8.00', 'period' => '2026-01', 'status' => 'approved']]);
        self::assertCount(1, $r);
        self::assertSame('revenue', $r[0]->metric);
    }

    public function test_projection_rejects_non_decimal_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ProjectApprovedResults)->execute('e1', 'o1', [['entity_id' => 'e1', 'owner_id' => 'o1', 'metric' => 'x', 'value' => '1', 'period' => 'p', 'status' => 'approved']]);
    }
}

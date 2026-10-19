<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Modules\Employees\Infrastructure\EMP02\WorkerAttributionDiagnosticAdapter;
use DateTimeImmutable;
use Tests\TestCase;

final class EMP02AccessTest extends TestCase
{
    public function test_worker_attribution_is_recorded_in_utc(): void
    {
        $r = (new WorkerAttributionDiagnosticAdapter)->attribute('e1', 'w1', 'j1', 'system', new DateTimeImmutable('2026-01-01T02:00:00+02:00'));
        self::assertSame('allow', $r['decision']);
        self::assertSame('2026-01-01T00:00:00+00:00', $r['occurred_at_utc']);
    }

    public function test_revoked_worker_is_denied(): void
    {
        self::assertSame('deny', (new WorkerAttributionDiagnosticAdapter)->attribute('e1', 'w1', 'j1', 'system', new DateTimeImmutable, true)['decision']);
    }
}

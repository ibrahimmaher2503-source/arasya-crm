<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Modules\Performance\Infrastructure\KPI03\TrajectoryAlertAccessDiagnosticAdapter;
use DateTimeImmutable;
use Tests\TestCase;

final class KPI03AccessTest extends TestCase
{
    public function test_assigned_snapshot_alert_is_deduplicated(): void
    {
        $seen = [];
        $adapter = new TrajectoryAlertAccessDiagnosticAdapter;
        $first = $adapter->detect('u1', 'e1', 'e1', 't1', $seen, 'approved-events', '0.5', '1.0', 't1:lag', new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $second = $adapter->detect('u1', 'e1', 'e1', 't1', $seen, 'approved-events', '0.5', '1.0', 't1:lag', new DateTimeImmutable);
        self::assertSame('allow', $first['decision']);
        self::assertNotNull($first['snapshot']);
        self::assertSame('duplicate', $second['code']);
    }

    public function test_unassigned_or_revoked_snapshot_is_denied(): void
    {
        $seen = [];
        $adapter = new TrajectoryAlertAccessDiagnosticAdapter;
        self::assertSame('deny', $adapter->detect('u1', 'other', 'e1', 't1', $seen, 'src', '1', '2', 'k', new DateTimeImmutable)['decision']);
        self::assertSame('deny', $adapter->detect('u1', 'e1', 'e1', 't1', $seen, 'src', '1', '2', 'k', new DateTimeImmutable, true)['decision']);
    }
}

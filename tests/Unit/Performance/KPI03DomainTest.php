<?php

declare(strict_types=1);

namespace Tests\Unit\Performance;

use App\Modules\Performance\Application\KPI03\DetectTrajectoryAlert;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class KPI03DomainTest extends TestCase
{
    public function test_snapshot_preserves_source_deadline_elapsed_and_deduplicates(): void
    {
        $seen = [];
        $d = new DetectTrajectoryAlert;
        $first = $d->execute($seen, 't1', 'approved-events', '0.50', '1.00', 't1:lag', new DateTimeImmutable('2026-01-01T02:00:00+02:00'));
        $second = $d->execute($seen, 't1', 'approved-events', '0.50', '1.00', 't1:lag', new DateTimeImmutable);
        self::assertNotNull($first);
        self::assertNull($second);
        self::assertSame('2026-01-01T00:00:00+00:00', $first->capturedAtUtc->format('c'));
    }

    public function test_blank_source_is_rejected(): void
    {
        $seen = [];
        $this->expectException(\InvalidArgumentException::class);
        new DetectTrajectoryAlert()->execute($seen, 't1', '', '0.50', '1.00', 'k', new DateTimeImmutable);
    }
}

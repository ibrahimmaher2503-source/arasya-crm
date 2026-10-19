<?php

declare(strict_types=1);

namespace Tests\Unit\Reporting;

use App\Modules\Reporting\Application\DASH03\BuildAuthorizedDrillDown;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DASH03DomainTest extends TestCase
{
    public function test_drill_down_is_source_linked_and_freshness_is_utc(): void
    {
        $r = (new BuildAuthorizedDrillDown)->execute('e1', 'sales', 'posted_total', 'sales_orders', new DateTimeImmutable('2026-01-01T02:00:00+02:00'), 'review');
        self::assertSame('posted_total', $r->calculation);
        self::assertSame('sales_orders', $r->source);
        self::assertSame('2026-01-01T00:00:00+00:00', $r->freshAtUtc->format('c'));
    }

    public function test_missing_action_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BuildAuthorizedDrillDown()->execute('e1', 'sales', 'calc', 'source', new DateTimeImmutable, '');
    }
}

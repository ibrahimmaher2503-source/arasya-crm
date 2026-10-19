<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Reporting\Infrastructure\DASH03\AuthorizedDrillDownAccessDiagnosticAdapter;
use DateTimeImmutable;
use Tests\TestCase;

final class DASH03AccessTest extends TestCase
{
    public function test_authorized_drill_down_is_source_linked(): void
    {
        $result = (new AuthorizedDrillDownAccessDiagnosticAdapter)->build('u1', 'e1', 'e1', 'sales', 'posted_total', 'sales_orders', new DateTimeImmutable('2026-01-01T00:00:00Z'), 'review');
        self::assertSame('allow', $result['decision']);
        self::assertSame('sales_orders', $result['drill_down']->source);
    }

    public function test_other_entity_or_revoked_drill_down_is_denied(): void
    {
        $adapter = new AuthorizedDrillDownAccessDiagnosticAdapter;
        self::assertSame('deny', $adapter->build('u1', 'other', 'e1', 'sales', 'calc', 'src', new DateTimeImmutable, 'review')['decision']);
        self::assertSame('deny', $adapter->build('u1', 'e1', 'e1', 'sales', 'calc', 'src', new DateTimeImmutable, 'review', true)['decision']);
    }
}

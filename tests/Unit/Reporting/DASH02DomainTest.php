<?php

declare(strict_types=1);

namespace Tests\Unit\Reporting;

use App\Modules\Reporting\Application\DASH02\SelectRoleDashboard;
use PHPUnit\Framework\TestCase;

final class DASH02DomainTest extends TestCase
{
    public function test_role_dashboard_filters_to_allowed_metrics(): void
    {
        $r = (new SelectRoleDashboard)->execute('e1', 'manager', ['sales', 'secret'], ['sales']);
        self::assertSame(['sales'], $r->metricKeys);
    }

    public function test_empty_selection_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SelectRoleDashboard)->execute('e1', 'manager', ['secret'], ['sales']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Reporting\Infrastructure\DASH02\RoleDashboardDiagnosticAdapter;
use Tests\TestCase;

final class DASH02AccessTest extends TestCase
{
    public function test_role_dashboard_is_filtered_to_allowed_metrics(): void
    {
        $r = (new RoleDashboardDiagnosticAdapter)->select('e1', 'manager', ['sales', 'secret'], ['sales']);
        self::assertSame('allow', $r['decision']);
        self::assertSame(['sales'], $r['metrics']);
    }

    public function test_revoked_access_is_denied(): void
    {
        self::assertSame('deny', (new RoleDashboardDiagnosticAdapter)->select('e1', 'manager', ['sales'], ['sales'], true)['decision']);
    }
}

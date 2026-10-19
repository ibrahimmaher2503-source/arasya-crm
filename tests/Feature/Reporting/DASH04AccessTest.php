<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Reporting\Domain\DASH04\OperationalAlert;
use App\Modules\Reporting\Infrastructure\DASH04\OperationalAlertAccessDiagnosticAdapter;
use Tests\TestCase;

final class DASH04AccessTest extends TestCase
{
    public function test_alert_is_scoped(): void
    {
        $a = new OperationalAlert('a1', 'e1', 'high', 'u1');
        $r = (new OperationalAlertAccessDiagnosticAdapter)->read('u1', 'e1', 'e1', $a);
        self::assertSame('allow', $r['decision']);
        self::assertSame('alert_ready', $r['code']);
    }

    public function test_other_entity_or_revoked_is_denied(): void
    {
        $a = new OperationalAlert('a1', 'e1', 'high', 'u1');
        $x = new OperationalAlertAccessDiagnosticAdapter;
        self::assertSame('deny', $x->read('u1', 'other', 'e1', $a)['decision']);
        self::assertSame('deny', $x->read('u1', 'e1', 'e1', $a, true)['decision']);
    }
}

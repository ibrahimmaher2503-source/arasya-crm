<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Reporting\Infrastructure\DASH05\MaskedExportAccessDiagnosticAdapter;
use Tests\TestCase;

final class DASH05AccessTest extends TestCase
{
    public function test_export_is_scoped_and_masked(): void
    {
        $r = (new MaskedExportAccessDiagnosticAdapter)->build('u1', 'e1', 'e1', ['sales'], [['entityId' => 'e1', 'sales' => '10', 'secret' => 'x']], ['secret']);
        self::assertSame('allow', $r['decision']);
        self::assertSame(['sales' => '10'], $r['export']['rows'][0]);
    }

    public function test_other_entity_or_revoked_is_denied(): void
    {
        $x = new MaskedExportAccessDiagnosticAdapter;
        self::assertSame('deny', $x->build('u1', 'other', 'e1', [], [['entityId' => 'e1']], [])['decision']);
        self::assertSame('deny', $x->build('u1', 'e1', 'e1', [], [['entityId' => 'e1']], [], true)['decision']);
    }
}

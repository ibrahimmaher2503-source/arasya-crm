<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Modules\Performance\Domain\KPI04\AchievementHistory;
use App\Modules\Performance\Infrastructure\KPI04\AchievementHistoryAccessDiagnosticAdapter;
use Tests\TestCase;

final class KPI04AccessTest extends TestCase
{
    public function test_history_is_scoped(): void
    {
        $r = (new AchievementHistoryAccessDiagnosticAdapter)->read('u1', 'e1', 'e1', new AchievementHistory('e1', []));
        self::assertSame('allow', $r['decision']);
        self::assertSame('history_ready', $r['code']);
    }

    public function test_other_entity_or_revoked_is_denied(): void
    {
        $h = new AchievementHistory('e1', []);
        $x = new AchievementHistoryAccessDiagnosticAdapter;
        self::assertSame('deny', $x->read('u1', 'other', 'e1', $h)['decision']);
        self::assertSame('deny', $x->read('u1', 'e1', 'e1', $h, true)['decision']);
    }
}

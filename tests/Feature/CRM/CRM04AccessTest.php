<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Modules\CRM\Domain\CRM04\LeadActivity;
use App\Modules\CRM\Infrastructure\CRM04\LeadActivityAccessDiagnosticAdapter;
use DateTimeImmutable;
use Tests\TestCase;

final class CRM04AccessTest extends TestCase
{
    public function test_activity_is_scoped_and_reminder_ready(): void
    {
        $r = (new LeadActivityAccessDiagnosticAdapter)->record('u1', 'e1', 'e1', new LeadActivity('l1', 'u1', 'interested', 'Call back', new DateTimeImmutable));
        self::assertSame('allow', $r['decision']);
        self::assertSame('activity_ready', $r['code']);
    }

    public function test_other_entity_or_revoked_is_denied(): void
    {
        $a = new LeadActivity('l1', 'u1', 'interested', 'Call back', new DateTimeImmutable);
        $x = new LeadActivityAccessDiagnosticAdapter;
        self::assertSame('deny', $x->record('u1', 'other', 'e1', $a)['decision']);
        self::assertSame('deny', $x->record('u1', 'e1', 'e1', $a, true)['decision']);
    }
}

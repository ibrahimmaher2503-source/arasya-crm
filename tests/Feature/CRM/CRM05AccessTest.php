<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Modules\CRM\Domain\CRM05\LeadHandover;
use App\Modules\CRM\Infrastructure\CRM05\LeadHandoverAccessDiagnosticAdapter;
use Tests\TestCase;

final class CRM05AccessTest extends TestCase
{
    public function test_recipient_can_accept_scoped_handover(): void
    {
        $r = (new LeadHandoverAccessDiagnosticAdapter)->accept('u2', 'e1', 'e1', new LeadHandover('l1', 'u1', 'u2'), 'u2');
        self::assertSame('allow', $r['decision']);
        self::assertTrue($r['handover']->accepted);
    }

    public function test_other_entity_or_revoked_is_denied(): void
    {
        $h = new LeadHandover('l1', 'u1', 'u2');
        $x = new LeadHandoverAccessDiagnosticAdapter;
        self::assertSame('deny', $x->accept('u2', 'other', 'e1', $h, 'u2')['decision']);
        self::assertSame('deny', $x->accept('u2', 'e1', 'e1', $h, 'u2', true)['decision']);
    }
}

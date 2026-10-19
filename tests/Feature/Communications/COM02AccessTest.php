<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Modules\Communications\Infrastructure\COM02\ConversationMembershipDiagnosticAdapter;
use Tests\TestCase;

final class COM02AccessTest extends TestCase
{
    public function test_member_can_create_membership(): void
    {
        $r = (new ConversationMembershipDiagnosticAdapter)->create('e1', 'c1', ['u1', 'u2'], 'u1');
        self::assertSame('allow', $r['decision']);
        self::assertSame(['u1', 'u2'], $r['members']);
    }

    public function test_non_member_is_denied(): void
    {
        self::assertSame('deny', (new ConversationMembershipDiagnosticAdapter)->create('e1', 'c1', ['u1'], 'u2')['decision']);
    }
}

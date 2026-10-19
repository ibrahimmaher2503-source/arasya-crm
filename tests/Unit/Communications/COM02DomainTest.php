<?php

declare(strict_types=1);

namespace Tests\Unit\Communications;

use App\Modules\Communications\Application\COM02\ManageConversationMembership;
use PHPUnit\Framework\TestCase;

final class COM02DomainTest extends TestCase
{
    public function test_membership_is_entity_scoped_and_explicit(): void
    {
        $r = (new ManageConversationMembership)->create('e1', 'c1', ['u1', 'u2']);
        self::assertTrue($r->contains('u1'));
        self::assertFalse($r->contains('u3'));
    }

    public function test_duplicate_members_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ManageConversationMembership)->create('e1', 'c1', ['u1', 'u1']);
    }
}

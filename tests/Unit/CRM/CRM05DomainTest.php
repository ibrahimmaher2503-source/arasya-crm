<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use App\Modules\CRM\Application\CRM05\HandoverLead;
use App\Modules\CRM\Domain\CRM05\LeadHandover;
use PHPUnit\Framework\TestCase;

final class CRM05DomainTest extends TestCase
{
    public function test_owner_remains_until_recipient_accepts(): void
    {
        $h = new LeadHandover('l1', 'old', 'new');
        $service = new HandoverLead;
        self::assertSame('old', $service->propose($h)->ownerId);
        $accepted = $service->accept($h, 'new');
        self::assertSame('new', $accepted->ownerId);
    }

    public function test_wrong_recipient_cannot_accept(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new HandoverLead)->accept(new LeadHandover('l1', 'old', 'new'), 'other');
    }
}

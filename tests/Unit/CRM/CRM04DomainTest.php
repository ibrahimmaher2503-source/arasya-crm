<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use App\Modules\CRM\Application\CRM04\RecordLeadActivity;
use App\Modules\CRM\Domain\CRM04\LeadActivity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CRM04DomainTest extends TestCase
{
    public function test_activity_records_context_and_one_reminder(): void
    {
        $seen = [];
        $a = new LeadActivity('l1', 'u1', 'interested', 'Call back', new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $r = (new RecordLeadActivity)->execute($a, $seen);
        self::assertSame('l1', $r->leadId);
        self::assertCount(1, $seen);
        self::assertNull((new RecordLeadActivity)->execute($a, $seen));
    }

    public function test_invalid_activity_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LeadActivity('', '', '', '', new DateTimeImmutable);
    }
}

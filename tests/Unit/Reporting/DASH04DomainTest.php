<?php

declare(strict_types=1);

namespace Tests\Unit\Reporting;

use App\Modules\Reporting\Application\DASH04\TransitionAlert;
use App\Modules\Reporting\Domain\DASH04\OperationalAlert;
use PHPUnit\Framework\TestCase;

final class DASH04DomainTest extends TestCase
{
    public function test_alert_lifecycle_is_idempotent(): void
    {
        $a = new OperationalAlert('a1', 'e1', 'high', 'u1');
        $a = (new TransitionAlert)->acknowledge($a, 'evt-1');
        $a = (new TransitionAlert)->acknowledge($a, 'evt-1');
        self::assertSame('acknowledged', $a->status);
        self::assertCount(1, $a->events);
        $a = (new TransitionAlert)->resolve($a, 'evt-2');
        self::assertSame('resolved', $a->status);
    }

    public function test_invalid_owner_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OperationalAlert('a1', 'e1', 'high', '');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Employees;

use App\Modules\Employees\Application\EMP02\AttributeWorker;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EMP02DomainTest extends TestCase
{
    public function test_non_login_worker_attribution_is_explicit_and_utc(): void
    {
        $r = (new AttributeWorker)->execute('e1', 'worker-1', 'job-1', 'system', new DateTimeImmutable('2026-01-01T02:00:00+02:00'));
        self::assertSame('worker-1', $r->workerId);
        self::assertSame('2026-01-01T00:00:00+00:00', $r->occurredAtUtc->format('c'));
    }

    public function test_missing_worker_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AttributeWorker)->execute('e1', '', 'job', 'system', new DateTimeImmutable);
    }
}

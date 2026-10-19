<?php

declare(strict_types=1);

namespace Tests\Unit\Performance;

use App\Modules\Performance\Application\KPI04\RecordAchievement;
use App\Modules\Performance\Domain\KPI04\AchievementHistory;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class KPI04DomainTest extends TestCase
{
    public function test_history_is_traceable_without_payroll_penalty(): void
    {
        $h = new AchievementHistory('e1', []);
        $next = (new RecordAchievement)->execute($h, 'target-1', '90.00', new DateTimeImmutable('2026-01-01T00:00:00Z'));
        self::assertSame('target-1', $next->entries[0]['targetId']);
        self::assertArrayNotHasKey('payrollPenalty', $next->entries[0]);
    }

    public function test_blank_target_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AchievementHistory('e1', [['targetId' => '', 'value' => '1', 'recordedAtUtc' => 'x']]);
    }
}

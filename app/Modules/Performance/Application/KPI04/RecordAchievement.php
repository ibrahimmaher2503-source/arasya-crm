<?php

declare(strict_types=1);

namespace App\Modules\Performance\Application\KPI04;

use App\Modules\Performance\Domain\KPI04\AchievementHistory;
use DateTimeImmutable;
use DateTimeZone;

final class RecordAchievement
{
    public function execute(AchievementHistory $history, string $targetId, string $value, DateTimeImmutable $recordedAt): AchievementHistory
    {
        return $history->append($targetId, $value, $recordedAt->setTimezone(new DateTimeZone('UTC'))->format('c'));
    }
}

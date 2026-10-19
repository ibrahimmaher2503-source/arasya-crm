<?php

declare(strict_types=1);

namespace App\Modules\Performance\Application\KPI03;

use App\Modules\Performance\Domain\KPI03\TrajectorySnapshot;
use DateTimeImmutable;

final class DetectTrajectoryAlert
{
    /** @param array<string,TrajectorySnapshot> $seen */
    public function execute(array &$seen, string $targetId, string $source, string $elapsed, string $deadline, string $alertKey, DateTimeImmutable $capturedAt): ?TrajectorySnapshot
    {
        if (isset($seen[$alertKey])) {
            return null;
        }$snapshot = new TrajectorySnapshot($targetId, $source, $elapsed, $deadline, $alertKey, $capturedAt);
        $seen[$alertKey] = $snapshot;

        return $snapshot;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Performance\Infrastructure\KPI03;

use App\Modules\Performance\Application\KPI03\DetectTrajectoryAlert;
use App\Modules\Performance\Domain\KPI03\TrajectorySnapshot;
use DateTimeImmutable;

final class TrajectoryAlertAccessDiagnosticAdapter
{
    /**
     * @param  array<string, TrajectorySnapshot>  $seen
     * @return array{decision: string, code: string, snapshot?: TrajectorySnapshot}
     */
    public function detect(string $actorId, string $entityId, string $assignedEntityId, string $targetId, array &$seen, string $source, string $elapsed, string $deadline, string $alertKey, DateTimeImmutable $capturedAt, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '' || $entityId !== $assignedEntityId || trim($targetId) === '') {
            return ['decision' => 'deny', 'code' => 'access_denied'];
        }
        $snapshot = (new DetectTrajectoryAlert)->execute($seen, $targetId, $source, $elapsed, $deadline, $alertKey, $capturedAt);

        return $snapshot === null ? ['decision' => 'allow', 'code' => 'duplicate'] : ['decision' => 'allow', 'code' => 'alert_ready', 'snapshot' => $snapshot];
    }
}

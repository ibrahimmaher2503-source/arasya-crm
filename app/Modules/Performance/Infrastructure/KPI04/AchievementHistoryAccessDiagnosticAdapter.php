<?php

declare(strict_types=1);

namespace App\Modules\Performance\Infrastructure\KPI04;

use App\Modules\Performance\Domain\KPI04\AchievementHistory;

final class AchievementHistoryAccessDiagnosticAdapter
{
    /** @return array{decision:string,code:string,history?:AchievementHistory} */
    public function read(string $actorId, string $entityId, string $assignedEntityId, AchievementHistory $history, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '' || $entityId !== $assignedEntityId || $history->entityId !== $entityId) {
            return ['decision' => 'deny', 'code' => 'access_denied'];
        }

        return ['decision' => 'allow', 'code' => 'history_ready', 'history' => $history];
    }
}

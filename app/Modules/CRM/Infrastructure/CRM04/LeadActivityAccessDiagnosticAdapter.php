<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\CRM04;

use App\Modules\CRM\Domain\CRM04\LeadActivity;

final class LeadActivityAccessDiagnosticAdapter
{
    /** @return array{decision:string,code:string,activity?:LeadActivity} */
    public function record(string $actorId, string $entityId, string $assignedEntityId, LeadActivity $activity, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '' || $entityId !== $assignedEntityId || $activity->authorId !== $actorId) {
            return ['decision' => 'deny', 'code' => 'access_denied'];
        }

        return ['decision' => 'allow', 'code' => 'activity_ready', 'activity' => $activity];
    }
}

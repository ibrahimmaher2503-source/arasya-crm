<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\DASH04;

use App\Modules\Reporting\Domain\DASH04\OperationalAlert;

final class OperationalAlertAccessDiagnosticAdapter
{
    /** @return array{decision:string,code:string,alert?:OperationalAlert} */
    public function read(string $actorId, string $entityId, string $assignedEntityId, OperationalAlert $alert, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '' || $entityId !== $assignedEntityId || $alert->entityId !== $entityId || $alert->ownerId !== $actorId) {
            return ['decision' => 'deny', 'code' => 'access_denied'];
        }

        return ['decision' => 'allow', 'code' => 'alert_ready', 'alert' => $alert];
    }
}

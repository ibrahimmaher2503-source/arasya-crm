<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\DASH02;

use App\Modules\Reporting\Application\DASH02\SelectRoleDashboard;

final class RoleDashboardDiagnosticAdapter
{
    /**
     * @param  list<string>  $requested
     * @param  list<string>  $allowed
     * @return array{decision:string,code:string,entity_id?:string,role?:string,metrics:list<string>}
     */
    public function select(string $entityId, string $role, array $requested, array $allowed, bool $revoked = false): array
    {
        if ($revoked || trim($entityId) === '' || trim($role) === '') {
            return ['decision' => 'deny', 'code' => 'access_denied', 'metrics' => []];
        }
        $selection = (new SelectRoleDashboard)->execute($entityId, $role, $requested, $allowed);

        return ['decision' => 'allow', 'code' => 'dashboard_ready', 'entity_id' => $selection->entityId, 'role' => $selection->role, 'metrics' => $selection->metricKeys];
    }
}

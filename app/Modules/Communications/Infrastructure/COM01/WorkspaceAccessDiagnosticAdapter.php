<?php

declare(strict_types=1);

namespace App\Modules\Communications\Infrastructure\COM01;

use App\Modules\Communications\Application\COM01\ProjectWorkspace;
use App\Modules\Communications\Domain\COM01\WorkspaceItem;
use App\Modules\Communications\Domain\COM01\WorkspaceProjection;

/** Internal workspace projection; no external channel or raw destination is exposed. */
final class WorkspaceAccessDiagnosticAdapter
{
    /**
     * @param  list<WorkspaceItem>  $items
     * @param  list<string>  $authorizedEmployeeIds
     * @return array{decision: string, code: string, workspace: WorkspaceProjection|null}
     */
    public function inspect(string $entityId, string $employeeId, array $authorizedEmployeeIds, array $items, bool $revoked = false): array
    {
        if ($revoked || ! in_array($employeeId, $authorizedEmployeeIds, true)) {
            return ['decision' => 'deny', 'code' => 'access_denied', 'workspace' => null];
        }

        return ['decision' => 'allow', 'code' => 'workspace_visible', 'workspace' => (new ProjectWorkspace)->project($entityId, $employeeId, $items)];
    }
}

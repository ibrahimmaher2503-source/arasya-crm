<?php

declare(strict_types=1);

namespace App\Modules\Performance\Infrastructure\KPI01;

use App\Modules\Performance\Domain\KPI01\TargetDefinition;

/** Read-only KPI projection filtered by server-side owner authorization. */
final class TargetAccessDiagnosticAdapter
{
    /**
     * @param  list<string>  $authorizedOwnerIds
     * @param  list<TargetDefinition>  $targets
     * @return array{decision: string, code: string, targets: list<TargetDefinition>}
     */
    public function inspect(string $actorId, array $authorizedOwnerIds, array $targets, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '') {
            return ['decision' => 'deny', 'code' => 'access_denied', 'targets' => []];
        }
        $allowed = array_fill_keys($authorizedOwnerIds, true);
        $visible = array_values(array_filter($targets, static fn (TargetDefinition $target): bool => isset($allowed[$target->ownerId])));

        return ['decision' => 'allow', 'code' => 'targets_visible', 'targets' => $visible];
    }
}

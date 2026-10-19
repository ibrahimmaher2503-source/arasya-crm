<?php

declare(strict_types=1);

namespace App\Modules\Performance\Infrastructure\KPI02;

use App\Modules\Performance\Application\KPI02\ProjectApprovedResults;

final class ApprovedResultAccessDiagnosticAdapter
{
    /**
     * @param  list<array{entity_id:string,owner_id:string,metric:string,value:string,period:string,status:string}>  $results
     * @return array{decision:string,code:string,items:list<array{entity_id:string,owner_id:string,metric:string,value:string,period:string}>}
     */
    public function project(string $entityId, string $ownerId, array $results, bool $revoked = false): array
    {
        if ($revoked || trim($entityId) === '' || trim($ownerId) === '') {
            return ['decision' => 'deny', 'code' => 'access_denied', 'items' => []];
        }

        return ['decision' => 'allow', 'code' => 'projection_ready', 'items' => array_map(static fn ($r): array => $r->toArray(), (new ProjectApprovedResults)->execute($entityId, $ownerId, $results))];
    }
}

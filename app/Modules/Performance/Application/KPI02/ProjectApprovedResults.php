<?php

declare(strict_types=1);

namespace App\Modules\Performance\Application\KPI02;

use App\Modules\Performance\Domain\KPI02\ApprovedResultProjection;

final class ProjectApprovedResults
{
    /**
     * @param  list<array{entity_id:string,owner_id:string,metric:string,value:string,period:string,status:string}>  $results
     * @return list<ApprovedResultProjection>
     */
    public function execute(string $entityId, string $ownerId, array $results): array
    {
        return array_values(array_map(fn (array $r): ApprovedResultProjection => new ApprovedResultProjection($r['entity_id'], $r['owner_id'], $r['metric'], $r['value'], $r['period']), array_filter($results, fn (array $r): bool => $r['entity_id'] === $entityId && $r['owner_id'] === $ownerId && $r['status'] === 'approved')));
    }
}

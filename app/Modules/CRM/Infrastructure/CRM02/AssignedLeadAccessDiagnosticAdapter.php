<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\CRM02;

use App\Modules\CRM\Application\CRM02\QueryAssignedLeads;
use App\Modules\CRM\Domain\CRM01\LeadRecord;

final class AssignedLeadAccessDiagnosticAdapter
{
    /**
     * @param  list<string>  $assignedLeadIds
     * @param  list<LeadRecord>  $leads
     * @return array{decision:string,code:string,items:list<array{id:string,name:string,organization:string}>}
     */
    public function query(string $actorId, string $entityId, array $assignedLeadIds, array $leads, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '' || trim($entityId) === '' || $assignedLeadIds === []) {
            return ['decision' => 'deny', 'code' => 'access_denied', 'items' => []];
        }
        $projection = (new QueryAssignedLeads)->execute($actorId, $entityId, $assignedLeadIds, $leads);

        return ['decision' => 'allow', 'code' => 'projection_ready', 'items' => $projection->toArray()];
    }
}

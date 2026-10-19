<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\CRM02;

use App\Modules\CRM\Domain\CRM01\LeadRecord;
use App\Modules\CRM\Domain\CRM02\AssignedLeadProjection;

final class QueryAssignedLeads
{
    /**
     * @param  list<string>  $assignedLeadIds
     * @param  list<LeadRecord>  $leads
     */
    public function execute(string $actorId, string $entityId, array $assignedLeadIds, array $leads): AssignedLeadProjection
    {
        $allowed = array_fill_keys($assignedLeadIds, true);
        $visible = array_values(array_filter($leads, fn (LeadRecord $lead): bool => $lead->entityId === $entityId && isset($allowed[$lead->id])));

        return new AssignedLeadProjection($entityId, $actorId, $visible);
    }
}

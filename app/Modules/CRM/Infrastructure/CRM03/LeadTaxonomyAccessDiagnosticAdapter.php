<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\CRM03;

use App\Modules\CRM\Application\CRM03\RecordLeadTaxonomy;
use App\Modules\CRM\Domain\CRM03\LeadTaxonomyHistory;

final class LeadTaxonomyAccessDiagnosticAdapter
{
    /** @return array{decision:string,code:string,history?:LeadTaxonomyHistory} */
    public function record(string $actorId, string $entityId, string $assignedEntityId, string $leadId, LeadTaxonomyHistory $history, string $stage, string $outcome, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '' || trim($entityId) === '' || $entityId !== $assignedEntityId || $leadId !== $history->leadId) {
            return ['decision' => 'deny', 'code' => 'access_denied'];
        }

        return ['decision' => 'allow', 'code' => 'taxonomy_recorded', 'history' => (new RecordLeadTaxonomy)->execute($history, $stage, $outcome)];
    }
}

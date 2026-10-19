<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\CRM05;

use App\Modules\CRM\Application\CRM05\HandoverLead;
use App\Modules\CRM\Domain\CRM05\LeadHandover;

final class LeadHandoverAccessDiagnosticAdapter
{
    /** @return array{decision:string,code:string,handover?:LeadHandover} */
    public function accept(string $actorId, string $entityId, string $assignedEntityId, LeadHandover $handover, string $recipientId, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '' || $entityId !== $assignedEntityId || $recipientId !== $actorId || $handover->recipientId !== $actorId) {
            return ['decision' => 'deny', 'code' => 'access_denied'];
        }

        return ['decision' => 'allow', 'code' => 'handover_accepted', 'handover' => (new HandoverLead)->accept($handover, $actorId)];
    }
}

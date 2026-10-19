<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\CRM05;

use App\Modules\CRM\Domain\CRM05\LeadHandover;

final class HandoverLead
{
    public function propose(LeadHandover $handover): LeadHandover
    {
        return $handover;
    }

    public function accept(LeadHandover $handover, string $actorId): LeadHandover
    {
        return $handover->accept($actorId);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\CRM01;

use DateTimeImmutable;
use DateTimeZone;

final class LeadLifecycle
{
    public function archive(LeadRecord $lead, string $entityId, string $actorId, string $requestId, ?DateTimeImmutable $occurredAt = null): LeadTransition
    {
        if ($lead->entityId !== $entityId || $lead->archived) {
            return new LeadTransition(false, $lead, null);
        }
        $entry = new LeadHistoryEntry('archived', $actorId, $requestId, $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $updated = new LeadRecord($lead->entityId, $lead->id, $lead->name, $lead->organization, $lead->source, $lead->email, $lead->consentGranted, true, [...$lead->history, $entry]);

        return new LeadTransition(true, $updated, $entry);
    }
}

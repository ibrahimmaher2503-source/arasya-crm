<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\CRM01;

use App\Shared\Outbox\OutboxEvent;

final readonly class LeadTransition
{
    public function __construct(
        public bool $accepted,
        public LeadRecord $lead,
        public ?LeadHistoryEntry $auditOutboxIntent,
    ) {}

    public function outboxEvent(): ?OutboxEvent
    {
        if ($this->auditOutboxIntent === null) {
            return null;
        }
        $entry = $this->auditOutboxIntent;

        return new OutboxEvent(
            'lead.'.$entry->requestId,
            'lead.'.$entry->action,
            1,
            'Lead',
            $this->lead->id,
            count($this->lead->history),
            ['entity_id' => $this->lead->entityId, 'lead_id' => $this->lead->id, 'action' => $entry->action, 'actor_id' => $entry->actorId],
        );
    }
}

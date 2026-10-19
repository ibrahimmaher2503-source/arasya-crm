<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\CRM01;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class LeadHistoryEntry
{
    public DateTimeImmutable $occurredAtUtc;

    public string $eventType;

    public function __construct(
        public string $action,
        public string $actorId,
        public string $requestId,
        DateTimeImmutable $occurredAt,
    ) {
        foreach ([$action, $actorId, $requestId] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Lead history identifiers must be non-empty.');
            }
        }
        $this->occurredAtUtc = $occurredAt->setTimezone(new DateTimeZone('UTC'));
        $this->eventType = 'lead.'.$action;
    }
}

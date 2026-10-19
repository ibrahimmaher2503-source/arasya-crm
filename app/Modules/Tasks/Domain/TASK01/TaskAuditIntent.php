<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Domain\TASK01;

use DateTimeImmutable;
use DateTimeZone;
use JsonSerializable;

final readonly class TaskAuditIntent implements JsonSerializable
{
    public DateTimeImmutable $occurredAtUtc;

    public function __construct(
        public string $taskId,
        public string $action,
        public string $actorId,
        public string $requestId,
        ?DateTimeImmutable $occurredAt,
    ) {
        $this->occurredAtUtc = ($occurredAt ?? new DateTimeImmutable)->setTimezone(new DateTimeZone('UTC'));
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return ['task_id' => $this->taskId, 'action' => $this->action, 'actor_id' => $this->actorId, 'request_id' => $this->requestId, 'occurred_at_utc' => $this->occurredAtUtc->format('c')];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Domain\TASK01;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;

final readonly class TaskEvidence implements JsonSerializable
{
    public DateTimeImmutable $occurredAtUtc;

    public function __construct(
        public string $id,
        public string $taskId,
        public string $type,
        ?DateTimeImmutable $occurredAt,
    ) {
        if (trim($id) === '' || trim($taskId) === '' || trim($type) === '') {
            throw new InvalidArgumentException('Malformed task evidence.');
        }
        $this->occurredAtUtc = ($occurredAt ?? new DateTimeImmutable)->setTimezone(new DateTimeZone('UTC'));
    }

    /** @return array{id: string, task_id: string, type: string, occurred_at_utc: string} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'task_id' => $this->taskId, 'type' => $this->type, 'occurred_at_utc' => $this->occurredAtUtc->format('c')];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Domain\TASK01;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class TaskRecord
{
    public DateTimeImmutable $dueAtUtc;

    /** @var list<TaskEvidence> */
    public array $evidence;

    /** @param array<array-key, mixed> $evidence */
    public function __construct(
        public string $id,
        public string $entityId,
        public string $ownerId,
        public ?string $managerId,
        public string $departmentId,
        public string $title,
        public TaskStatus $status,
        ?DateTimeImmutable $dueAt,
        array $evidence = [],
        public int $version = 0,
    ) {
        foreach ([$id, $entityId, $ownerId, $departmentId, $title] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Malformed task record.');
            }
        }
        if ($managerId !== null && trim($managerId) === '') {
            throw new InvalidArgumentException('Malformed task manager.');
        }
        if ($version < 0 || ! array_is_list($evidence)) {
            throw new InvalidArgumentException('Malformed task record.');
        }
        foreach ($evidence as $item) {
            if (! $item instanceof TaskEvidence || $item->taskId !== $id) {
                throw new InvalidArgumentException('Malformed task evidence.');
            }
        }
        $this->dueAtUtc = ($dueAt ?? new DateTimeImmutable)->setTimezone(new DateTimeZone('UTC'));
        $this->evidence = $evidence;
    }

    public function isOverdue(DateTimeImmutable $at): bool
    {
        return $this->status !== TaskStatus::Completed && $this->status !== TaskStatus::Cancelled
            && $this->dueAtUtc < $at->setTimezone(new DateTimeZone('UTC'));
    }
}

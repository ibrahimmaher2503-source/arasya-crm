<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Application\TASK01;

use App\Modules\Tasks\Domain\TASK01\TaskAuditIntent;
use App\Modules\Tasks\Domain\TASK01\TaskEvidence;
use App\Modules\Tasks\Domain\TASK01\TaskRecord;
use App\Modules\Tasks\Domain\TASK01\TaskStatus;
use DateTimeImmutable;

final class ManageTasks
{
    /**
     * @param  list<TaskRecord>  $records
     * @return list<TaskRecord>
     */
    public function visibleFor(string $entityId, string $ownerId, ?string $managerId, array $records, DateTimeImmutable $at): array
    {
        if (trim($entityId) === '' || trim($ownerId) === '') {
            return [];
        }

        return array_values(array_filter($records, static fn (TaskRecord $task): bool => $task->entityId === $entityId
            && $task->ownerId === $ownerId
            && ($task->managerId === null || $managerId === null || $task->managerId === $managerId)
        ));
    }

    public function transition(TaskRecord $task, TaskStatus $to, string $actorId, string $requestId, DateTimeImmutable $occurredAt): TaskMutationResult
    {
        $allowed = match ($task->status) {
            TaskStatus::Pending => [TaskStatus::InProgress, TaskStatus::Cancelled],
            TaskStatus::InProgress => [TaskStatus::Completed, TaskStatus::Cancelled],
            default => [],
        };
        if (! in_array($to, $allowed, true) || trim($actorId) === '' || trim($requestId) === '') {
            return new TaskMutationResult(false, 'invalid_transition', $task);
        }
        $next = new TaskRecord($task->id, $task->entityId, $task->ownerId, $task->managerId, $task->departmentId, $task->title, $to, $task->dueAtUtc, $task->evidence, $task->version + 1);

        return new TaskMutationResult(true, 'accepted', $next, new TaskAuditIntent($task->id, 'transition', $actorId, $requestId, $occurredAt));
    }

    public function reschedule(TaskRecord $task, DateTimeImmutable $dueAt, string $actorId, string $requestId, DateTimeImmutable $occurredAt): TaskMutationResult
    {
        if (trim($actorId) === '' || trim($requestId) === '' || $task->status === TaskStatus::Completed || $task->status === TaskStatus::Cancelled) {
            return new TaskMutationResult(false, 'invalid_reschedule', $task);
        }
        $next = new TaskRecord($task->id, $task->entityId, $task->ownerId, $task->managerId, $task->departmentId, $task->title, $task->status, $dueAt, $task->evidence, $task->version + 1);

        return new TaskMutationResult(true, 'accepted', $next, new TaskAuditIntent($task->id, 'reschedule', $actorId, $requestId, $occurredAt));
    }

    public function attachEvidence(TaskRecord $task, string $evidenceId, string $type, string $actorId, string $requestId, DateTimeImmutable $occurredAt): TaskMutationResult
    {
        if (trim($actorId) === '' || trim($requestId) === '' || $task->status === TaskStatus::Cancelled || trim($evidenceId) === '' || trim($type) === '') {
            return new TaskMutationResult(false, 'invalid_evidence', $task);
        }
        $evidence = new TaskEvidence($evidenceId, $task->id, $type, $occurredAt);
        $next = new TaskRecord($task->id, $task->entityId, $task->ownerId, $task->managerId, $task->departmentId, $task->title, $task->status, $task->dueAtUtc, [...$task->evidence, $evidence], $task->version + 1);

        return new TaskMutationResult(true, 'accepted', $next, new TaskAuditIntent($task->id, 'evidence_attached', $actorId, $requestId, $occurredAt));
    }
}

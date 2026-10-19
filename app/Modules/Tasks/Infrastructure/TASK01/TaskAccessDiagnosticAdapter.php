<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Infrastructure\TASK01;

use App\Modules\Tasks\Application\TASK01\ManageTasks;
use App\Modules\Tasks\Domain\TASK01\TaskRecord;
use DateTimeImmutable;

/** Synthetic read boundary; authority is server-derived by the caller. */
final class TaskAccessDiagnosticAdapter
{
    /**
     * @param  list<TaskRecord>  $records
     * @param  list<string>  $authorizedTaskIds
     * @return array{decision: string, code: string, tasks: list<TaskRecord>}
     */
    public function inspect(string $entityId, string $actorId, ?string $managerId, array $records, array $authorizedTaskIds, DateTimeImmutable $at, bool $revoked = false): array
    {
        if ($revoked || trim($entityId) === '' || trim($actorId) === '' || $authorizedTaskIds === []) {
            return ['decision' => 'deny', 'code' => 'access_denied', 'tasks' => []];
        }
        $allowed = array_fill_keys($authorizedTaskIds, true);
        $visible = array_values(array_filter((new ManageTasks)->visibleFor($entityId, $actorId, $managerId, $records, $at), static fn (TaskRecord $task): bool => isset($allowed[$task->id])));

        return ['decision' => 'allow', 'code' => 'tasks_visible', 'tasks' => $visible];
    }
}

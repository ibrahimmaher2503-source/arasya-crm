<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Application\TASK01;

use App\Modules\Tasks\Domain\TASK01\TaskAuditIntent;
use App\Modules\Tasks\Domain\TASK01\TaskRecord;

final readonly class TaskMutationResult
{
    public function __construct(
        public bool $accepted,
        public string $reason,
        public TaskRecord $task,
        public ?TaskAuditIntent $auditOutboxIntent = null,
    ) {}
}

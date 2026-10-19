<?php

declare(strict_types=1);

namespace App\Modules\Employees\Application\EMP02;

use App\Modules\Employees\Domain\EMP02\WorkerAttribution;
use DateTimeImmutable;

final class AttributeWorker
{
    public function execute(string $entityId, string $workerId, string $jobId, string $actorId, DateTimeImmutable $occurredAt): WorkerAttribution
    {
        return new WorkerAttribution($entityId, $workerId, $jobId, $actorId, $occurredAt);
    }
}

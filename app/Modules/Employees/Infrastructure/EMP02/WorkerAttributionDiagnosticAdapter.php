<?php

declare(strict_types=1);

namespace App\Modules\Employees\Infrastructure\EMP02;

use App\Modules\Employees\Application\EMP02\AttributeWorker;
use DateTimeImmutable;

final class WorkerAttributionDiagnosticAdapter
{
    /** @return array{decision:string,code:string,worker_id?:string,job_id?:string,occurred_at_utc?:string} */
    public function attribute(string $entityId, string $workerId, string $jobId, string $actorId, DateTimeImmutable $occurredAt, bool $revoked = false): array
    {
        if ($revoked || trim($entityId) === '' || trim($workerId) === '' || trim($jobId) === '') {
            return ['decision' => 'deny', 'code' => 'access_denied'];
        }
        $r = (new AttributeWorker)->execute($entityId, $workerId, $jobId, $actorId, $occurredAt);

        return ['decision' => 'allow', 'code' => 'attributed', 'worker_id' => $r->workerId, 'job_id' => $r->jobId, 'occurred_at_utc' => $r->occurredAtUtc->format('c')];
    }
}

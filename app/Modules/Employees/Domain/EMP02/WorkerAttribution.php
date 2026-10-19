<?php

declare(strict_types=1);

namespace App\Modules\Employees\Domain\EMP02;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class WorkerAttribution
{
    public DateTimeImmutable $occurredAtUtc;

    public function __construct(public string $entityId, public string $workerId, public string $jobId, public string $actorId, DateTimeImmutable $occurredAt)
    {
        foreach ([$entityId, $workerId, $jobId, $actorId] as $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException('Worker attribution identifiers are required.');
            }
        }$this->occurredAtUtc = $occurredAt->setTimezone(new DateTimeZone('UTC'));
    }
}

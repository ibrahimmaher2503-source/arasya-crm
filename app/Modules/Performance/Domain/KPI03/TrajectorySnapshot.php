<?php

declare(strict_types=1);

namespace App\Modules\Performance\Domain\KPI03;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class TrajectorySnapshot
{
    public DateTimeImmutable $capturedAtUtc;

    public function __construct(public string $targetId, public string $source, public string $elapsed, public string $deadline, public string $alertKey, DateTimeImmutable $capturedAt)
    {
        foreach ([$targetId, $source, $elapsed, $deadline, $alertKey] as $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException('Trajectory fields are required.');
            }
        }$this->capturedAtUtc = $capturedAt->setTimezone(new DateTimeZone('UTC'));
    }
}

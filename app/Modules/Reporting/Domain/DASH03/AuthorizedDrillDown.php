<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\DASH03;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class AuthorizedDrillDown
{
    public DateTimeImmutable $freshAtUtc;

    public function __construct(public string $entityId, public string $metricKey, public string $calculation, public string $source, DateTimeImmutable $freshAt, public string $permittedAction)
    {
        foreach ([$entityId, $metricKey, $calculation, $source, $permittedAction] as $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException('Drill-down contract is incomplete.');
            }
        }$this->freshAtUtc = $freshAt->setTimezone(new DateTimeZone('UTC'));
    }
}

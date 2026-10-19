<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\DASH02;

use InvalidArgumentException;

final readonly class RoleDashboardSelection
{
    /** @param list<string> $metricKeys */
    public function __construct(public string $entityId, public string $role, public array $metricKeys)
    {
        if (trim($entityId) === '' || trim($role) === '') {
            throw new InvalidArgumentException('Dashboard scope is required.');
        }if ($metricKeys === []) {
            throw new InvalidArgumentException('Dashboard must select at least one metric.');
        }foreach ($metricKeys as $key) {
            if (trim($key) === '') {
                throw new InvalidArgumentException('Metric key is required.');
            }
        }
    }
}

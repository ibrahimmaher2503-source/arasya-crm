<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\DASH02;

use App\Modules\Reporting\Domain\DASH02\RoleDashboardSelection;

final class SelectRoleDashboard
{
    /**
     * @param  list<string>  $requested
     * @param  list<string>  $allowedMetricKeys
     */
    public function execute(string $entityId, string $role, array $requested, array $allowedMetricKeys): RoleDashboardSelection
    {
        $allowed = array_fill_keys($allowedMetricKeys, true);
        $keys = array_values(array_filter($requested, fn (string $key): bool => isset($allowed[$key])));

        return new RoleDashboardSelection($entityId, $role, $keys);
    }
}

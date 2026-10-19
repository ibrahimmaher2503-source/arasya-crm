<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\DASH01;

use App\Modules\Reporting\Domain\DASH01\ExecutiveMetric;
use App\Modules\Reporting\Domain\DASH01\ExecutiveMetricProjection;

final class ProjectExecutiveMetrics
{
    /** @param array<int, ExecutiveMetric> $metrics */
    public function execute(string $entityId, array $metrics): ExecutiveMetricProjection
    {
        return new ExecutiveMetricProjection($entityId, array_values($metrics));
    }
}

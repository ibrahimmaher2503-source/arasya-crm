<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\DASH01;

use App\Modules\Reporting\Application\DASH01\ProjectExecutiveMetrics;
use App\Modules\Reporting\Domain\DASH01\ExecutiveMetric;
use App\Modules\Reporting\Domain\DASH01\ExecutiveMetricProjection;

/** Scoped reporting projection; it never computes or mutates source facts. */
final class ExecutiveMetricAccessDiagnosticAdapter
{
    /**
     * @param  list<ExecutiveMetric>  $metrics
     * @return array{decision: string, code: string, metrics: ExecutiveMetricProjection|list<mixed>}
     */
    public function inspect(string $entityId, array $metrics, bool $authorized, bool $revoked = false): array
    {
        if (! $authorized || $revoked || trim($entityId) === '') {
            return ['decision' => 'deny', 'code' => 'access_denied', 'metrics' => []];
        }

        return ['decision' => 'allow', 'code' => 'metrics_visible', 'metrics' => (new ProjectExecutiveMetrics)->execute($entityId, $metrics)];
    }
}

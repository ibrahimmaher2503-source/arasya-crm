<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Reporting\Domain\DASH01\ExecutiveMetric;
use App\Modules\Reporting\Domain\DASH01\MetricSource;
use App\Modules\Reporting\Infrastructure\DASH01\ExecutiveMetricAccessDiagnosticAdapter;
use DateTimeImmutable;
use Tests\TestCase;

final class DASH01AccessTest extends TestCase
{
    public function test_authorized_projection_is_scoped(): void
    {
        $metric = new ExecutiveMetric('metric-1', MetricSource::Revenue, 'entity-1', '100.00', 'EGP', new DateTimeImmutable('2026-01-01'));
        $result = (new ExecutiveMetricAccessDiagnosticAdapter)->inspect('entity-1', [$metric], true);
        self::assertSame('allow', $result['decision']);
        self::assertSame('revenue', $result['metrics']->metrics[0]->source->value);
    }

    public function test_unauthorized_and_revoked_projection_is_empty(): void
    {
        $metric = new ExecutiveMetric('metric-1', MetricSource::Revenue, 'entity-1', '100.00', 'EGP', new DateTimeImmutable('2026-01-01'));
        $adapter = new ExecutiveMetricAccessDiagnosticAdapter;
        self::assertSame('deny', $adapter->inspect('entity-1', [$metric], false)['decision']);
        self::assertSame('deny', $adapter->inspect('entity-1', [$metric], true, true)['decision']);
    }
}

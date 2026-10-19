<?php

declare(strict_types=1);

namespace Tests\Unit\Reporting;

use App\Modules\Reporting\Application\DASH01\ProjectExecutiveMetrics;
use App\Modules\Reporting\Domain\DASH01\ExecutiveMetric;
use App\Modules\Reporting\Domain\DASH01\ExecutiveMetricProjection;
use App\Modules\Reporting\Domain\DASH01\MetricSource;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DASH01DomainTest extends TestCase
{
    public function test_projection_keeps_distinct_scoped_sources_and_utc_money_values(): void
    {
        $at = new DateTimeImmutable('2026-10-17 12:00:00+03:00');
        $metrics = [];
        foreach (MetricSource::cases() as $index => $source) {
            $metrics[] = new ExecutiveMetric(
                'metric-'.$index,
                $source,
                'entity-1',
                sprintf('%d.00', $index + 1),
                'EGP',
                $at,
            );
        }

        $projection = new ProjectExecutiveMetrics()->execute('entity-1', $metrics);

        self::assertInstanceOf(ExecutiveMetricProjection::class, $projection);
        self::assertCount(7, $projection->metrics);
        self::assertSame('2026-10-17T09:00:00+00:00', $projection->metrics[0]->observedAtUtc->format('c'));
        self::assertSame('EGP', $projection->metrics[0]->currency);
        self::assertSame('1.00', $projection->metrics[0]->value);
        self::assertSame('sales', $projection->metrics[0]->source->value);
    }

    public function test_serialization_exposes_only_safe_metric_fields(): void
    {
        $metric = new ExecutiveMetric(
            'metric-1', MetricSource::Risk, 'entity-1', '2.00', 'EGP',
            new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC')),
        );

        $serialized = json_encode(new ExecutiveMetricProjection('entity-1', [$metric]), JSON_THROW_ON_ERROR);
        self::assertStringContainsString('risk', $serialized);
        self::assertStringNotContainsString('password', $serialized);
        self::assertStringNotContainsString('secret', $serialized);
    }

    public function test_projection_rejects_cross_entity_or_duplicate_sources(): void
    {
        $metric = new ExecutiveMetric('metric-1', MetricSource::Sales, 'entity-1', '1.00', 'EGP', new DateTimeImmutable);
        $otherEntity = new ExecutiveMetric('metric-2', MetricSource::Revenue, 'entity-2', '2.00', 'EGP', new DateTimeImmutable);

        $this->expectException(InvalidArgumentException::class);
        new ExecutiveMetricProjection('entity-1', [$metric, $otherEntity]);
    }

    public function test_metric_rejects_invalid_money_or_empty_scope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExecutiveMetric('', MetricSource::Cash, 'entity-1', '1.00', 'EGP', new DateTimeImmutable);
    }
}

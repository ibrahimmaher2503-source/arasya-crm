<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\DASH01;

use InvalidArgumentException;
use JsonSerializable;

final readonly class ExecutiveMetricProjection implements JsonSerializable
{
    /** @param list<ExecutiveMetric> $metrics */
    public function __construct(public string $entityId, public array $metrics)
    {
        if (trim($entityId) === '') {
            throw new InvalidArgumentException('Projection entity scope must be non-empty.');
        }
        $sources = [];
        foreach ($metrics as $metric) {
            if ($metric->entityId !== $entityId) {
                throw new InvalidArgumentException('Projection metrics must share the entity scope.');
            }
            if (isset($sources[$metric->source->value])) {
                throw new InvalidArgumentException('Projection cannot contain duplicate metric sources.');
            }
            $sources[$metric->source->value] = true;
        }
    }

    /** @return array{entity_id: string, metrics: list<ExecutiveMetric>} */
    public function jsonSerialize(): array
    {
        return ['entity_id' => $this->entityId, 'metrics' => $this->metrics];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\DASH01;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;

final readonly class ExecutiveMetric implements JsonSerializable
{
    public DateTimeImmutable $observedAtUtc;

    public function __construct(
        public string $id,
        public MetricSource $source,
        public string $entityId,
        public string $value,
        public string $currency,
        DateTimeImmutable $observedAt,
    ) {
        foreach ([$id, $entityId, $currency] as $dimension) {
            if (trim($dimension) === '') {
                throw new InvalidArgumentException('Metric dimensions must be non-empty.');
            }
        }
        if (preg_match('/\A\d+\.\d{2}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Metric value must be a decimal with two places.');
        }
        if (preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1) {
            throw new InvalidArgumentException('Metric currency must be an ISO-style code.');
        }
        $this->observedAtUtc = $observedAt->setTimezone(new DateTimeZone('UTC'));
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source->value,
            'entity_id' => $this->entityId,
            'value' => $this->value,
            'currency' => $this->currency,
            'observed_at_utc' => $this->observedAtUtc->format('c'),
        ];
    }
}

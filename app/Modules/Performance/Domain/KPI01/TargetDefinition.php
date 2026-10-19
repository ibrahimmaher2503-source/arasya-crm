<?php

declare(strict_types=1);

namespace App\Modules\Performance\Domain\KPI01;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;

final readonly class TargetDefinition implements JsonSerializable
{
    public DateTimeImmutable $periodStartUtc;

    public DateTimeImmutable $periodEndUtc;

    public function __construct(
        public string $id,
        public string $metric,
        public string $unit,
        public string $period,
        public string $threshold,
        public string $weight,
        public TargetOwnerType $ownerType,
        public string $ownerId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
        public TargetStatus $status = TargetStatus::Draft,
    ) {
        foreach ([$id, $metric, $unit, $period, $ownerId] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Target dimensions must be non-empty.');
            }
        }
        foreach (['threshold' => $threshold, 'weight' => $weight] as $name => $value) {
            if (preg_match('/\A\d+\.\d{2}\z/D', $value) !== 1) {
                throw new InvalidArgumentException($name.' must be a decimal with two places.');
            }
        }
        if ((float) $threshold < 0 || (float) $weight < 0 || (float) $weight > 100) {
            throw new InvalidArgumentException('Target threshold and weight are out of range.');
        }
        $start = $periodStart->setTimezone(new DateTimeZone('UTC'));
        $end = $periodEnd->setTimezone(new DateTimeZone('UTC'));
        if ($end <= $start) {
            throw new InvalidArgumentException('Target period must end after it starts.');
        }
        $this->periodStartUtc = $start;
        $this->periodEndUtc = $end;
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'metric' => $this->metric,
            'unit' => $this->unit,
            'period' => $this->period,
            'threshold' => $this->threshold,
            'weight' => $this->weight,
            'owner_type' => $this->ownerType->value,
            'owner_id' => $this->ownerId,
            'period_start_utc' => $this->periodStartUtc->format('c'),
            'period_end_utc' => $this->periodEndUtc->format('c'),
            'status' => $this->status->value,
        ];
    }
}

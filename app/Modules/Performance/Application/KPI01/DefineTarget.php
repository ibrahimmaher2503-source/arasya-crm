<?php

declare(strict_types=1);

namespace App\Modules\Performance\Application\KPI01;

use App\Modules\Performance\Domain\KPI01\TargetDefinition;
use App\Shared\Outbox\InMemoryOutboxStore;
use App\Shared\Outbox\OutboxEvent;

final class DefineTarget
{
    public function __construct(private InMemoryOutboxStore $outbox) {}

    public function execute(TargetDefinition $target): TargetDefinition
    {
        $this->outbox->append(new OutboxEvent(
            'kpi-target-'.$target->id,
            'kpi.target.defined',
            1,
            'KpiTarget',
            $target->id,
            1,
            [
                'metric' => $target->metric,
                'unit' => $target->unit,
                'period' => $target->period,
                'threshold' => $target->threshold,
                'weight' => $target->weight,
                'owner_type' => $target->ownerType->value,
                'owner_id' => $target->ownerId,
                'period_start_utc' => $target->periodStartUtc->format('c'),
                'period_end_utc' => $target->periodEndUtc->format('c'),
                'status' => $target->status->value,
            ],
        ));

        return $target;
    }
}

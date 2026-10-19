<?php

declare(strict_types=1);

namespace App\Modules\Performance\Domain\KPI01;

final class TargetTransitionGuard
{
    /** @var array<string, list<TargetStatus>> */
    private const ALLOWED = [
        'draft' => [TargetStatus::Active],
        'active' => [TargetStatus::Archived],
    ];

    public function propose(TargetDefinition $target, TargetStatus $to): TargetTransition
    {
        if (! in_array($to, self::ALLOWED[$target->status->value] ?? [], true)) {
            return new TargetTransition(false, 'transition_not_allowed', $target);
        }

        return new TargetTransition(true, 'accepted', new TargetDefinition(
            $target->id, $target->metric, $target->unit, $target->period,
            $target->threshold, $target->weight, $target->ownerType, $target->ownerId,
            $target->periodStartUtc, $target->periodEndUtc, $to,
        ));
    }
}

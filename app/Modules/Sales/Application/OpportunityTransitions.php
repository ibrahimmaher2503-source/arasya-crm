<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class OpportunityTransitions
{
    public const STAGES = ['lead', 'contact', 'proposal', 'negotiation', 'review', 'closed'];

    public function assertInitialStage(string $stage): void
    {
        if ($stage !== 'lead') {
            throw ValidationException::withMessages(['stage' => 'New opportunities must start in the lead stage.']);
        }
    }

    public function assertAllowed(string $from, string $target, ?string $reason, ?string $outcome): void
    {
        $fromIndex = array_search($from, self::STAGES, true);
        if ($fromIndex === false) {
            throw ValidationException::withMessages(['stage' => 'The current opportunity stage is invalid.']);
        }

        $targetIndex = array_search($target, self::STAGES, true);
        if ($targetIndex === false) {
            throw ValidationException::withMessages(['target_stage' => 'The target opportunity stage is invalid.']);
        }

        if ($from === 'closed' || $target === 'closed') {
            throw new AccessDeniedHttpException('Closing and reopening opportunities are not authorized.');
        }

        if ($outcome !== null) {
            throw ValidationException::withMessages(['outcome' => 'An outcome cannot be set on an open-stage transition.']);
        }

        $distance = $targetIndex - $fromIndex;
        if (abs($distance) !== 1) {
            throw ValidationException::withMessages(['target_stage' => 'Only adjacent stage transitions are allowed.']);
        }

        if ($distance < 0 && trim($reason ?? '') === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for a backward transition.']);
        }
    }

    /** @return list<string> */
    public function openTargets(string $stage): array
    {
        $index = array_search($stage, self::STAGES, true);
        if ($index === false || $stage === 'closed') {
            return [];
        }

        $targets = [];
        if ($index > 0) {
            $targets[] = self::STAGES[$index - 1];
        }
        if (isset(self::STAGES[$index + 1]) && self::STAGES[$index + 1] !== 'closed') {
            $targets[] = self::STAGES[$index + 1];
        }

        return $targets;
    }
}

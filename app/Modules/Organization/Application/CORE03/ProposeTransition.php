<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\CORE03;

use App\Modules\Organization\Domain\CORE03\TransitionGuard;
use App\Modules\Organization\Domain\CORE03\TransitionProposal;
use App\Modules\Organization\Domain\CORE03\WorkflowState;
use DateTimeImmutable;

final readonly class ProposeTransition
{
    public function __construct(private TransitionGuard $guard) {}

    /** @param list<mixed> $permissions */
    public function propose(
        WorkflowState $source,
        string $entityId,
        string $to,
        int $expectedVersion,
        array $permissions,
        bool $validatorsPassed,
        string $actorId,
        string $correlationId,
        string $requestId,
        DateTimeImmutable $occurredAt,
    ): TransitionProposal {
        return $this->guard->propose(
            $source,
            $entityId,
            $to,
            $expectedVersion,
            $permissions,
            $validatorsPassed,
            $actorId,
            $correlationId,
            $requestId,
            $occurredAt,
        );
    }
}

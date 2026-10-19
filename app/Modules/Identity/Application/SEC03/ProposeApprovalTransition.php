<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\SEC03;

use App\Modules\Identity\Domain\SEC03\ApprovalRequestState;
use App\Modules\Identity\Domain\SEC03\ApprovalStatus;
use App\Modules\Identity\Domain\SEC03\ApprovalTransition;
use App\Modules\Identity\Domain\SEC03\ApprovalTransitionGuard;
use DateTimeImmutable;

final readonly class ProposeApprovalTransition
{
    public function __construct(private ApprovalTransitionGuard $guard) {}

    public function propose(ApprovalRequestState $source, string $actorId, ApprovalStatus $to, string $requestId, DateTimeImmutable $occurredAt, string $reason = '', ?string $delegateId = null, bool $authorized = true, ?string $entityId = null): ApprovalTransition
    {
        return $this->guard->propose($source, $actorId, $to, $requestId, $occurredAt, $reason, $delegateId, $authorized, $entityId);
    }
}

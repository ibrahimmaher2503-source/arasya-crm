<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\SEC03;

final readonly class ApprovalTransition
{
    public function __construct(
        public bool $accepted,
        public string $reason,
        public ApprovalRequestState $state,
        public ?ApprovalHistoryEntry $auditOutboxIntent = null,
    ) {}
}

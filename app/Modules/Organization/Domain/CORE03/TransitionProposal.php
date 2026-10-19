<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE03;

final readonly class TransitionProposal
{
    public function __construct(
        public bool $accepted,
        public string $reason,
        public WorkflowState $state,
        public ?TransitionHistoryEntry $auditOutboxIntent = null,
        public bool $replayed = false,
    ) {}
}

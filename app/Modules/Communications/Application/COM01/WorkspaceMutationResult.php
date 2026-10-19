<?php

declare(strict_types=1);

namespace App\Modules\Communications\Application\COM01;

use App\Modules\Communications\Domain\COM01\WorkspaceItem;

final readonly class WorkspaceMutationResult
{
    public function __construct(
        public bool $accepted,
        public string $reason,
        public WorkspaceItem $item,
    ) {}
}

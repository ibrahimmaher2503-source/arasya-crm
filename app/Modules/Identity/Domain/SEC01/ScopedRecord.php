<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\SEC01;

final readonly class ScopedRecord
{
    public function __construct(
        public string $recordId,
        public string $entityId,
        public string $departmentId,
        public ?string $ownerId = null,
        public ?string $assignedActorId = null,
    ) {}
}

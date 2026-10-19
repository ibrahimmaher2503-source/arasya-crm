<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\SEC01;

final readonly class ActorAccess
{
    /**
     * @param  list<string>  $inheritedPermissions
     * @param  array<string, bool>  $overrides
     */
    public function __construct(
        public string $actorId,
        public string $entityId,
        public string $departmentId,
        public array $inheritedPermissions = [],
        public array $overrides = [],
        public ?string $branchId = null,
    ) {}
}

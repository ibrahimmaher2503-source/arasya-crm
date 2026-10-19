<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\SEC01;

use App\Modules\Identity\Domain\SEC01\ActorAccess;
use App\Modules\Identity\Domain\SEC01\ScopedPermissionGuard;
use App\Modules\Identity\Domain\SEC01\ScopedRecord;

final readonly class AuthorizeScopedRecord
{
    public function __construct(private ScopedPermissionGuard $guard) {}

    public function allows(ActorAccess $actor, string $permission, ScopedRecord $record): bool
    {
        return $this->guard->allows($actor, $permission, $record);
    }
}

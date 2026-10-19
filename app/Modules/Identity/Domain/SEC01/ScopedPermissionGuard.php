<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\SEC01;

final class ScopedPermissionGuard
{
    public function allows(ActorAccess $actor, string $permission, ScopedRecord $record): bool
    {
        if (! $this->hasValidScope($actor, $permission, $record)
            || ! $this->hasValidGrants($actor->inheritedPermissions, $actor->overrides)) {
            return false;
        }

        if (array_key_exists($permission, $actor->overrides)) {
            $hasPermission = $actor->overrides[$permission];
        } else {
            $hasPermission = in_array($permission, $actor->inheritedPermissions, true);
        }

        return $hasPermission
            && $actor->entityId === $record->entityId
            && $actor->departmentId === $record->departmentId
            && ($actor->actorId === $record->ownerId || $actor->actorId === $record->assignedActorId);
    }

    private function hasValidScope(ActorAccess $actor, string $permission, ScopedRecord $record): bool
    {
        foreach ([
            $actor->actorId,
            $actor->entityId,
            $actor->departmentId,
            $permission,
            $record->recordId,
            $record->entityId,
            $record->departmentId,
        ] as $identifier) {
            if (trim($identifier) === '') {
                return false;
            }
        }

        foreach ([$record->ownerId, $record->assignedActorId] as $identity) {
            if ($identity !== null && trim($identity) === '') {
                return false;
            }
        }

        return true;
    }

    private function hasValidGrants(mixed $inheritedPermissions, mixed $overrides): bool
    {
        if (! is_array($inheritedPermissions) || ! is_array($overrides)) {
            return false;
        }

        foreach ($inheritedPermissions as $inheritedPermission) {
            if (! is_string($inheritedPermission) || trim($inheritedPermission) === '') {
                return false;
            }
        }

        foreach ($overrides as $overridePermission => $allowed) {
            if (! is_string($overridePermission) || trim($overridePermission) === '' || ! is_bool($allowed)) {
                return false;
            }
        }

        return true;
    }
}

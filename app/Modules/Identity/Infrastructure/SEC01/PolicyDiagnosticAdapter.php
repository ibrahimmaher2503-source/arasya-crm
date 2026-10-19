<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\SEC01;

use App\Modules\Identity\Application\SEC01\AuthorizeScopedRecord;
use App\Modules\Identity\Domain\SEC01\ActorAccess;
use App\Modules\Identity\Domain\SEC01\ScopedPermissionGuard;
use App\Modules\Identity\Domain\SEC01\ScopedRecord;

/** Local, read-only evaluator for explicitly supplied synthetic fixtures only. */
final class PolicyDiagnosticAdapter
{
    private const MAX_IDENTIFIER_LENGTH = 128;

    private const MAX_PERMISSIONS = 64;

    /**
     * @return array{decision: 'allow'|'deny', code: 'policy_allowed'|'policy_denied'|'invalid_diagnostic_input'}
     */
    public function evaluate(mixed $input): array
    {
        if (! $this->isValidInput($input)) {
            return ['decision' => 'deny', 'code' => 'invalid_diagnostic_input'];
        }

        $actor = $input['actor'];
        $record = $input['record'];
        $authorization = new AuthorizeScopedRecord(new ScopedPermissionGuard);
        $allowed = $authorization->allows(
            new ActorAccess(
                $actor['actorId'],
                $actor['entityId'],
                $actor['departmentId'],
                $actor['inheritedPermissions'],
                $actor['overrides'],
            ),
            $input['permission'],
            new ScopedRecord(
                $record['recordId'],
                $record['entityId'],
                $record['departmentId'],
                $record['ownerId'],
                $record['assignedActorId'],
            ),
        );

        return $allowed
            ? ['decision' => 'allow', 'code' => 'policy_allowed']
            : ['decision' => 'deny', 'code' => 'policy_denied'];
    }

    private function isValidInput(mixed $input): bool
    {
        if (! is_array($input)
            || ! $this->hasExactKeys($input, ['permission', 'actor', 'record'])
            || ! $this->isIdentifier($input['permission'])
            || ! is_array($input['actor'])
            || ! is_array($input['record'])) {
            return false;
        }

        $actor = $input['actor'];
        if (! $this->hasExactKeys($actor, ['actorId', 'entityId', 'departmentId', 'inheritedPermissions', 'overrides'])
            || ! $this->isIdentifier($actor['actorId'])
            || ! $this->isIdentifier($actor['entityId'])
            || ! $this->isIdentifier($actor['departmentId'])
            || ! is_array($actor['inheritedPermissions'])
            || ! array_is_list($actor['inheritedPermissions'])
            || count($actor['inheritedPermissions']) > self::MAX_PERMISSIONS
            || ! is_array($actor['overrides'])
            || count($actor['overrides']) > self::MAX_PERMISSIONS) {
            return false;
        }

        foreach ($actor['inheritedPermissions'] as $permission) {
            if (! $this->isIdentifier($permission)) {
                return false;
            }
        }

        foreach ($actor['overrides'] as $permission => $allowed) {
            if (! is_string($permission) || ! $this->isIdentifier($permission) || ! is_bool($allowed)) {
                return false;
            }
        }

        $record = $input['record'];

        return $this->hasExactKeys($record, ['recordId', 'entityId', 'departmentId', 'ownerId', 'assignedActorId'])
            && $this->isIdentifier($record['recordId'])
            && $this->isIdentifier($record['entityId'])
            && $this->isIdentifier($record['departmentId'])
            && $this->isOptionalIdentifier($record['ownerId'])
            && $this->isOptionalIdentifier($record['assignedActorId']);
    }

    /**
     * @param  array<mixed>  $value
     * @param  list<string>  $keys
     */
    private function hasExactKeys(array $value, array $keys): bool
    {
        if (count($value) !== count($keys)) {
            return false;
        }

        foreach ($keys as $key) {
            if (! array_key_exists($key, $value)) {
                return false;
            }
        }

        return true;
    }

    private function isIdentifier(mixed $value): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && strlen($value) <= self::MAX_IDENTIFIER_LENGTH;
    }

    private function isOptionalIdentifier(mixed $value): bool
    {
        return $value === null || $this->isIdentifier($value);
    }
}

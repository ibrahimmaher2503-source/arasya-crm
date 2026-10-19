<?php

declare(strict_types=1);

namespace App\Modules\Employees\Infrastructure\EMP01;

use App\Modules\Employees\Application\EMP01\ReadScopedEmployeeProfiles;
use App\Modules\Employees\Domain\EMP01\EmployeeProfile;

/** Server-scoped profile projection with private fields denied by default. */
final class EmployeeAccessDiagnosticAdapter
{
    /**
     * @param  list<EmployeeProfile>  $profiles
     * @param  list<string>  $authorizedEmployeeIds
     * @return array{decision: string, code: string, profiles: list<array<string, mixed>>}
     */
    public function inspect(string $entityId, array $profiles, array $authorizedEmployeeIds, bool $revoked = false, bool $includePrivateNotes = false): array
    {
        if ($revoked) {
            return ['decision' => 'deny', 'code' => 'access_denied', 'profiles' => []];
        }

        return ['decision' => 'allow', 'code' => 'profiles_visible', 'profiles' => (new ReadScopedEmployeeProfiles)->query($entityId, $profiles, $authorizedEmployeeIds, $includePrivateNotes)];
    }
}

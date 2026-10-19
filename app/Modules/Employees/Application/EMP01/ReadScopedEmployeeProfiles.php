<?php

declare(strict_types=1);

namespace App\Modules\Employees\Application\EMP01;

use App\Modules\Employees\Domain\EMP01\EmployeeProfile;

final class ReadScopedEmployeeProfiles
{
    /**
     * @param  list<EmployeeProfile>  $profiles
     * @param  list<string>  $authorizedEmployeeIds
     * @return list<array<string, mixed>>
     */
    public function query(string $entityId, array $profiles, array $authorizedEmployeeIds, bool $includePrivateNotes = false): array
    {
        if (trim($entityId) === '') {
            return [];
        }
        $authorized = array_fill_keys(array_filter($authorizedEmployeeIds, static fn (string $id): bool => trim($id) !== ''), true);
        $result = [];
        foreach ($profiles as $profile) {
            if ($profile->entityId !== $entityId || ! isset($authorized[$profile->employeeId])) {
                continue;
            }
            $result[] = $profile->toArray($includePrivateNotes);
        }

        return $result;
    }
}

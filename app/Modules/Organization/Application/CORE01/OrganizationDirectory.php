<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\CORE01;

use App\Modules\Identity\Application\SEC04\AccessControl;
use App\Modules\Organization\Domain\CORE01\OrganizationReference;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class OrganizationDirectory
{
    public function __construct(
        private AccessControl $accessControl,
        private LinkOrganizationReferences $references,
    ) {}

    /** @param array<string, mixed> $actor
     * @return array{company: array<string, mixed>, branches: list<array<string, mixed>>, departments: list<array<string, mixed>>}
     */
    public function overview(array $actor): array
    {
        $this->accessControl->requirePermission($actor, 'organization.read');

        $companyId = $this->actorString($actor, 'company_id');
        $company = DB::table('access_companies')->where('id', $companyId)->first();

        if ($company === null) {
            throw new NotFoundHttpException('Company not found.');
        }

        $branchesQuery = DB::table('access_branches')->where('company_id', $companyId);
        $departmentsQuery = DB::table('access_departments')->where('company_id', $companyId);

        if (! $this->isAdmin($actor)) {
            $branchId = $this->actorString($actor, 'branch_id');
            $branchesQuery->where('id', $branchId);
            $departmentsQuery->where('branch_id', $branchId);
        }

        $branches = $branchesQuery->orderBy('name')->orderBy('id')->get();
        $departments = $departmentsQuery->orderBy('name')->orderBy('id')->get();

        return [
            'company' => $this->presentCompany($company),
            'branches' => array_values(array_map($this->presentBranch(...), $branches->all())),
            'departments' => array_values(array_map($this->presentDepartment(...), $departments->all())),
        ];
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveCompany(array $actor, array $data): array
    {
        $companyId = $this->actorString($actor, 'company_id');
        $name = $this->requiredString($data, 'name', 255);
        $version = $this->requiredVersion($data);

        return DB::transaction(function () use ($actor, $companyId, $name, $version): array {
            $company = $this->lockCompany($companyId);
            $this->accessControl->requirePermission($actor, 'organization.write');

            if (! $this->isAdmin($actor)) {
                throw new AccessDeniedHttpException('Company access denied.');
            }
            if ((int) $company->version !== $version) {
                throw new ConflictHttpException('Company version is stale.');
            }

            $updated = [
                'id' => (string) $company->id,
                'name' => $name,
                'version' => $version + 1,
            ];

            DB::table('access_companies')->where('id', $companyId)->update([
                'name' => $name,
                'version' => $updated['version'],
            ]);

            $this->accessControl->record($actor, 'organization.company.updated', $companyId, [
                'before' => $this->presentCompany($company),
                'after' => $updated,
            ]);

            return $updated;
        }, 3);
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveBranch(array $actor, array $data, ?string $id = null): array
    {
        $companyId = $this->actorString($actor, 'company_id');
        $name = $this->requiredString($data, 'name', 255);
        $code = $this->requiredString($data, 'code', 64);
        $version = $id === null ? null : $this->requiredVersion($data);
        $branchId = $id ?? (string) Str::ulid();

        try {
            return DB::transaction(function () use ($actor, $companyId, $name, $code, $version, $branchId, $id, $data): array {
                $company = $this->lockCompany($companyId);
                $this->accessControl->requirePermission($actor, 'organization.write');

                if ($id === null && ! $this->isAdmin($actor)) {
                    throw new AccessDeniedHttpException('Only an administrator can create a branch.');
                }

                $before = null;
                if ($id !== null) {
                    $branch = DB::table('access_branches')->where('id', $id)->lockForUpdate()->first();

                    if ($branch === null || (string) $branch->company_id !== (string) $company->id
                        || (! $this->isAdmin($actor) && (string) $branch->id !== $this->actorString($actor, 'branch_id'))) {
                        throw new NotFoundHttpException('Branch not found.');
                    }
                    if ((int) $branch->version !== $version) {
                        throw new ConflictHttpException('Branch version is stale.');
                    }
                    $before = $this->presentBranch($branch);
                }

                $active = $this->optionalBoolean($data, 'active', $before['active'] ?? true);
                if ($before !== null && $before['active'] && ! $active
                    && $this->actorString($actor, 'branch_id') === $branchId) {
                    throw new AccessDeniedHttpException('The current branch cannot be deactivated.');
                }

                $after = [
                    'id' => $branchId,
                    'company_id' => (string) $company->id,
                    'name' => $name,
                    'code' => $code,
                    'active' => $active,
                    'version' => $before === null ? 0 : $version + 1,
                ];

                if ($before === null) {
                    DB::table('access_branches')->insert($after);
                } else {
                    DB::table('access_branches')->where('id', $branchId)->update([
                        'name' => $name,
                        'code' => $code,
                        'active' => $active,
                        'version' => $after['version'],
                    ]);
                }

                $this->accessControl->record(
                    $actor,
                    $before === null ? 'organization.branch.created' : 'organization.branch.updated',
                    $branchId,
                    ['before' => $before, 'after' => $after],
                );

                return $after;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $this->invalid('code', 'The branch code is already in use for this company.');
        }
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveDepartment(array $actor, array $data, ?string $id = null): array
    {
        $companyId = $this->actorString($actor, 'company_id');
        $name = $this->requiredString($data, 'name', 255);
        $code = $this->requiredString($data, 'code', 64);
        $branchId = $this->requiredString($data, 'branch_id', 26);
        $version = $id === null ? null : $this->requiredVersion($data);
        $departmentId = $id ?? (string) Str::ulid();

        try {
            return DB::transaction(function () use ($actor, $companyId, $name, $code, $branchId, $version, $departmentId, $id, $data): array {
                $company = $this->lockCompany($companyId);
                $this->accessControl->requirePermission($actor, 'organization.write');

                $department = null;
                $before = null;
                if ($id !== null) {
                    $department = DB::table('access_departments')->where('id', $id)->lockForUpdate()->first();

                    if ($department === null || (string) $department->company_id !== (string) $company->id
                        || (! $this->isAdmin($actor) && (string) $department->branch_id !== $this->actorString($actor, 'branch_id'))) {
                        throw new NotFoundHttpException('Department not found.');
                    }
                    if ((int) $department->version !== $version) {
                        throw new ConflictHttpException('Department version is stale.');
                    }
                    $before = $this->presentDepartment($department);
                } elseif (! $this->isAdmin($actor) && $branchId !== $this->actorString($actor, 'branch_id')) {
                    throw new NotFoundHttpException('Branch not found.');
                }

                $branch = DB::table('access_branches')
                    ->where('id', $branchId)
                    ->where('company_id', $company->id)
                    ->lockForUpdate()
                    ->first();

                if ($branch === null) {
                    throw new NotFoundHttpException('Branch not found.');
                }
                if (! $this->isAdmin($actor) && (string) $branch->id !== $this->actorString($actor, 'branch_id')) {
                    throw new NotFoundHttpException('Branch not found.');
                }
                if (! (bool) $branch->active) {
                    $this->invalid('branch_id', 'Departments must belong to an active branch.');
                }
                if (! $this->references->allowsLink(
                    new OrganizationReference('department', $departmentId, (string) $company->id),
                    new OrganizationReference('branch', (string) $branch->id, (string) $branch->company_id),
                )) {
                    $this->invalid('branch_id', 'The department and branch must belong to the same company.');
                }

                $active = $this->optionalBoolean($data, 'active', $before['active'] ?? true);
                if ($before !== null && $before['active'] && ! $active
                    && $this->actorString($actor, 'department_id') === $departmentId) {
                    throw new AccessDeniedHttpException('The current department cannot be deactivated.');
                }

                $after = [
                    'id' => $departmentId,
                    'company_id' => (string) $company->id,
                    'branch_id' => (string) $branch->id,
                    'name' => $name,
                    'code' => $code,
                    'active' => $active,
                    'version' => $before === null ? 0 : $version + 1,
                ];

                if ($before === null) {
                    DB::table('access_departments')->insert($after);
                } else {
                    DB::table('access_departments')->where('id', $departmentId)->update([
                        'branch_id' => $after['branch_id'],
                        'name' => $name,
                        'code' => $code,
                        'active' => $active,
                        'version' => $after['version'],
                    ]);
                }

                $this->accessControl->record(
                    $actor,
                    $before === null ? 'organization.department.created' : 'organization.department.updated',
                    $departmentId,
                    ['before' => $before, 'after' => $after],
                );

                return $after;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $this->invalid('code', 'The department code is already in use for this branch.');
        }
    }

    private function lockCompany(string $companyId): stdClass
    {
        $company = DB::table('access_companies')->where('id', $companyId)->lockForUpdate()->first();

        if ($company === null) {
            throw new NotFoundHttpException('Company not found.');
        }

        return $company;
    }

    /** @param array<string, mixed> $actor */
    private function isAdmin(array $actor): bool
    {
        return ($actor['is_admin'] ?? false) === true;
    }

    /** @param array<string, mixed> $actor */
    private function actorString(array $actor, string $key): string
    {
        $value = $actor[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new AccessDeniedHttpException('Organization access denied.');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $key, int $maxLength): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maxLength) {
            $this->invalid($key, 'A non-empty value within the allowed length is required.');
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    private function requiredVersion(array $data): int
    {
        $value = $data['version'] ?? null;

        if (! is_int($value) && ! is_string($value)) {
            $this->invalid('version', 'A non-negative integer version is required.');
        }

        $version = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if (! is_int($version)) {
            $this->invalid('version', 'A non-negative integer version is required.');
        }

        return $version;
    }

    /** @param array<string, mixed> $data */
    private function optionalBoolean(array $data, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        return match ($data[$key]) {
            true, 1, '1' => true,
            false, 0, '0' => false,
            default => $this->invalid($key, 'A boolean value is required.'),
        };
    }

    /** @return array{id: string, name: string, version: int} */
    private function presentCompany(stdClass $company): array
    {
        return [
            'id' => (string) $company->id,
            'name' => (string) $company->name,
            'version' => (int) $company->version,
        ];
    }

    /** @return array{id: string, company_id: string, name: string, code: string, active: bool, version: int} */
    private function presentBranch(stdClass $branch): array
    {
        return [
            'id' => (string) $branch->id,
            'company_id' => (string) $branch->company_id,
            'name' => (string) $branch->name,
            'code' => (string) $branch->code,
            'active' => (bool) $branch->active,
            'version' => (int) $branch->version,
        ];
    }

    /** @return array{id: string, company_id: string, branch_id: string, name: string, code: string, active: bool, version: int} */
    private function presentDepartment(stdClass $department): array
    {
        return [
            'id' => (string) $department->id,
            'company_id' => (string) $department->company_id,
            'branch_id' => (string) $department->branch_id,
            'name' => (string) $department->name,
            'code' => (string) $department->code,
            'active' => (bool) $department->active,
            'version' => (int) $department->version,
        ];
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

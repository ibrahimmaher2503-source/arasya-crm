<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\SEC04;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class BrowseAccessDirectory
{
    public function __construct(private AccessControl $access) {}

    /** @param array<string, mixed> $actor
     * @return array<string, int>
     */
    public function overview(array $actor): array
    {
        $summary = [];
        if (in_array('users.read', $actor['permissions'], true)) {
            $summary['users'] = $this->usersQuery($actor)->count('users.id');
        }
        if (in_array('roles.read', $actor['permissions'], true)) {
            $summary['roles'] = $this->rolesQuery($actor)->count('access_roles.id');
        }
        if (in_array('organization.read', $actor['permissions'], true)) {
            $summary['branches'] = $this->branchesQuery($actor)->count('access_branches.id');
        }
        if (in_array('audit.read', $actor['permissions'], true)) {
            $summary['audit'] = $this->auditQuery($actor)->count('access_audit.id');
        }

        return $summary;
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $filters
     * @return array{data: array{company: array<string, mixed>, branches: LengthAwarePaginator<int, array<string, mixed>>, departments: LengthAwarePaginator<int, array<string, mixed>>}, summary: array{branches: int, departments: int}, branchOptions: LengthAwarePaginator<int, array<string, mixed>>, departmentOptions: LengthAwarePaginator<int, array<string, mixed>>, roleOptions: LengthAwarePaginator<int, array<string, mixed>>}
     */
    public function organization(array $actor, array $filters, int $perPage): array
    {
        $this->access->requirePermission($actor, 'organization.read');

        $company = DB::table('access_companies')
            ->where('id', $actor['company_id'])
            ->first(['id', 'name', 'version']);

        if ($company === null) {
            abort(404);
        }

        $branchBase = $this->branchesQuery($actor);
        $departmentBase = $this->departmentsQuery($actor);
        $summary = [
            'branches' => (clone $branchBase)->count('access_branches.id'),
            'departments' => (clone $departmentBase)->count('access_departments.id'),
        ];

        $branches = $this->applyOrganizationFilters(clone $branchBase, $filters, 'branch', $actor)
            ->orderBy('access_branches.name', $filters['direction'] ?? 'asc')
            ->orderBy('access_branches.id')
            ->paginate($perPage, ['access_branches.id', 'access_branches.company_id', 'access_branches.name', 'access_branches.code', 'access_branches.active', 'access_branches.version'], 'branches_page')
            ->appends($filters);

        $departments = $this->applyOrganizationFilters(clone $departmentBase, $filters, 'department', $actor)
            ->leftJoin('access_branches as branches', 'branches.id', '=', 'access_departments.branch_id')
            ->orderBy('access_departments.name', $filters['direction'] ?? 'asc')
            ->orderBy('access_departments.id')
            ->paginate($perPage, ['access_departments.id', 'access_departments.company_id', 'access_departments.branch_id', 'access_departments.name', 'access_departments.code', 'access_departments.active', 'access_departments.version', 'branches.name as branch_name'], 'departments_page')
            ->appends($filters);

        $branches->setCollection($branches->getCollection()->map(fn (stdClass $row): array => $this->presentBranch($row)));
        $departments->setCollection($departments->getCollection()->map(fn (stdClass $row): array => $this->presentDepartment($row) + ['role_ids' => [], 'roles_editable' => false]));

        $roleOptions = $this->roleOptions($actor, $filters, $perPage);
        $assignmentStates = $this->departmentRoleAssignments($actor, array_column($departments->items(), 'id'));
        $departments->setCollection($departments->getCollection()->map(static function (array $department) use ($assignmentStates): array {
            $state = $assignmentStates[$department['id']] ?? ['role_ids' => [], 'roles_editable' => true];
            $department['role_ids'] = $state['role_ids'];
            $department['roles_editable'] = $state['roles_editable'];

            return $department;
        }));

        return [
            'data' => [
                'company' => ['id' => (string) $company->id, 'name' => (string) $company->name, 'version' => (int) $company->version],
                'branches' => $branches,
                'departments' => $departments,
            ],
            'summary' => $summary,
            'branchOptions' => $this->branchOptions($actor, $filters, $perPage),
            'departmentOptions' => $this->departmentOptions($actor, $filters, $perPage),
            'roleOptions' => $roleOptions,
        ];
    }

    /** @param array<string, mixed> $actor
     * @param  list<string>  $departmentIds
     * @return array<string, array{role_ids: list<string>, roles_editable: bool}>
     */
    public function departmentRoleAssignments(array $actor, array $departmentIds): array
    {
        $states = [];
        foreach ($departmentIds as $departmentId) {
            $states[$departmentId] = ['role_ids' => [], 'roles_editable' => true];
        }
        if ($departmentIds === []) {
            return $states;
        }

        $allAssignments = DB::table('access_department_roles')
            ->where('company_id', $actor['company_id'])
            ->whereIn('department_id', $departmentIds)
            ->get(['department_id', 'role_id'])
            ->groupBy('department_id');

        $visibleAssignments = collect();
        if (in_array('roles.read', $actor['permissions'], true)) {
            $visibleAssignments = DB::table('access_department_roles')
                ->where('company_id', $actor['company_id'])
                ->whereIn('department_id', $departmentIds)
                ->whereIn('role_id', $this->rolesQuery($actor)->select('access_roles.id'))
                ->orderBy('role_id')
                ->get(['department_id', 'role_id'])
                ->groupBy('department_id');
        }

        foreach ($departmentIds as $departmentId) {
            $all = $allAssignments->get($departmentId, collect());
            $visible = $visibleAssignments->get($departmentId, collect());
            $states[$departmentId] = [
                'role_ids' => array_values($visible->pluck('role_id')->map(static fn ($id): string => (string) $id)->all()),
                'roles_editable' => $visible->count() === $all->count(),
            ];
        }

        return $states;
    }

    /** @param array<string, mixed> $actor
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function visibleRoleIds(array $actor, array $ids): array
    {
        $this->access->requirePermission($actor, 'roles.read');
        if ($ids === []) {
            return [];
        }

        return array_values($this->rolesQuery($actor)
            ->whereIn('access_roles.id', $ids)
            ->orderBy('access_roles.id')
            ->pluck('access_roles.id')
            ->map(static fn ($id): string => (string) $id)
            ->all());
    }

    /** @param array<string, mixed> $actor
     * @param  list<string>  $catalog
     */
    public function canEditUserAssignments(array $actor, string $userId, array $catalog): bool
    {
        $this->access->requirePermission($actor, 'users.write');
        $this->access->requirePermission($actor, 'roles.write');
        $target = DB::table('access_users')
            ->where('company_id', $actor['company_id'])
            ->where('id', $userId)
            ->when(! $actor['is_admin'], static fn (Builder $query): Builder => $query->where('branch_id', $actor['branch_id']))
            ->first(['id', 'is_admin']);
        if ($target === null) {
            abort(404);
        }
        if ((string) $target->id === (string) $actor['id'] || ((bool) $target->is_admin && ! $actor['is_admin'])) {
            return false;
        }

        $assignedRoleIds = array_values(DB::table('access_user_roles')->where('user_id', $userId)->pluck('role_id')->map(static fn ($id): string => (string) $id)->all());
        $visibleRoleCount = $assignedRoleIds === [] ? 0 : $this->rolesQuery($actor)
            ->whereIn('access_roles.id', $assignedRoleIds)
            ->count('access_roles.id');
        $overridePermissions = DB::table('access_user_permissions')->where('user_id', $userId)->pluck('permission')->map(static fn ($permission): string => (string) $permission)->all();

        return $visibleRoleCount === count($assignedRoleIds)
            && array_diff($overridePermissions, $catalog) === [];
    }

    /** @param array<string, mixed> $actor
     */
    public function canEditDepartmentRoles(array $actor, string $departmentId): bool
    {
        $this->access->requirePermission($actor, 'organization.write');
        $this->access->requirePermission($actor, 'roles.write');

        $department = $this->departmentsQuery($actor)
            ->where('access_departments.id', $departmentId)
            ->where('access_departments.active', true)
            ->first(['access_departments.id']);
        if ($department === null) {
            abort(404);
        }
        if (! $actor['is_admin'] && (string) $department->id === (string) $actor['department_id']) {
            return false;
        }

        $assignmentQuery = DB::table('access_department_roles')
            ->where('company_id', $actor['company_id'])
            ->where('department_id', $departmentId);
        $assignmentCount = (clone $assignmentQuery)->count();
        $visibleAssignmentCount = $assignmentCount === 0 ? 0 : (clone $assignmentQuery)
            ->whereIn('role_id', $this->rolesQuery($actor)->select('access_roles.id'))
            ->count();

        return $visibleAssignmentCount === $assignmentCount;
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $filters
     * @return array{branches: LengthAwarePaginator<int, array<string, mixed>>, departments: LengthAwarePaginator<int, array<string, mixed>>, roles: LengthAwarePaginator<int, array<string, mixed>>}
     */
    public function assignmentOptions(array $actor, array $filters, int $perPage = 50): array
    {
        $this->access->requirePermission($actor, 'users.read');
        $perPage = min(50, max(10, $perPage));

        return [
            'branches' => $this->branchOptions($actor, $filters, $perPage),
            'departments' => $this->departmentOptions($actor, $filters, $perPage),
            'roles' => $this->roleOptions($actor, $filters, $perPage),
        ];
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $filters
     * @return array{users: LengthAwarePaginator<int, array<string, mixed>>, summary: array{total: int, active: int, inactive: int}, branchOptions: LengthAwarePaginator<int, array<string, mixed>>, departmentOptions: LengthAwarePaginator<int, array<string, mixed>>, roleOptions: LengthAwarePaginator<int, array<string, mixed>>}
     */
    public function users(array $actor, array $filters, int $perPage): array
    {
        $this->access->requirePermission($actor, 'users.read');

        $base = $this->usersQuery($actor);
        $summary = [
            'total' => (clone $base)->count('users.id'),
            'active' => (clone $base)->where('users.active', true)->count('users.id'),
            'inactive' => (clone $base)->where('users.active', false)->count('users.id'),
        ];

        $query = clone $base;
        if (filled($filters['q'] ?? null)) {
            $like = $this->like($filters['q']);
            $query->where(static function (Builder $query) use ($like): void {
                $query->where('users.name', 'like', $like)->orWhere('users.email', 'like', $like);
            });
        }
        if (($filters['status'] ?? null) === 'active') {
            $query->where('users.active', true);
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->where('users.active', false);
        }
        if (filled($filters['branch_id'] ?? null)) {
            $query->where('users.branch_id', $filters['branch_id']);
        }
        if (filled($filters['department_id'] ?? null)) {
            $query->where('users.department_id', $filters['department_id']);
        }
        if (filled($filters['role_id'] ?? null)) {
            if (in_array('roles.read', $actor['permissions'], true)) {
                $query->where(function (Builder $query) use ($actor, $filters): void {
                    $query->whereExists(function (Builder $query) use ($actor, $filters): void {
                        $query->selectRaw('1')
                            ->from('access_user_roles as role_filter')
                            ->whereColumn('role_filter.user_id', 'users.id')
                            ->where('role_filter.role_id', $filters['role_id'])
                            ->whereIn('role_filter.role_id', $this->rolesQuery($actor)->select('access_roles.id'));
                    })->orWhereExists(function (Builder $query) use ($actor, $filters): void {
                        $query->selectRaw('1')
                            ->from('access_department_roles as role_filter')
                            ->whereColumn('role_filter.department_id', 'users.department_id')
                            ->where('role_filter.company_id', $actor['company_id'])
                            ->where('role_filter.role_id', $filters['role_id'])
                            ->whereIn('role_filter.role_id', $this->rolesQuery($actor)->select('access_roles.id'));
                        if (! $actor['is_admin']) {
                            $query->where('role_filter.branch_id', $actor['branch_id']);
                        }
                    });
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $sort = $filters['sort'] ?? 'name';
        $column = ['name' => 'users.name', 'email' => 'users.email', 'date' => 'users.created_at'][$sort] ?? 'users.name';
        $users = $query->orderBy($column, $filters['direction'] ?? 'asc')
            ->orderBy('users.id')
            ->paginate($perPage, ['users.id', 'users.company_id', 'users.branch_id', 'users.department_id', 'users.name', 'users.email', 'users.active', 'users.is_admin', 'users.version', 'branches.name as branch_name', 'departments.name as department_name'], 'page')
            ->appends($filters);

        $users->setCollection($users->getCollection()->map(static fn (stdClass $row): array => [
            'id' => (string) $row->id,
            'company_id' => (string) $row->company_id,
            'branch_id' => (string) $row->branch_id,
            'department_id' => (string) $row->department_id,
            'name' => (string) $row->name,
            'email' => (string) $row->email,
            'active' => (bool) $row->active,
            'is_admin' => (bool) $row->is_admin,
            'version' => (int) $row->version,
            'branch_name' => $row->branch_name === null ? null : (string) $row->branch_name,
            'department_name' => $row->department_name === null ? null : (string) $row->department_name,
        ]));

        if (in_array('roles.read', $actor['permissions'], true) && $users->items() !== []) {
            $this->access->requirePermission($actor, 'roles.read');
            $userIds = array_column($users->items(), 'id');
            $visibleRoles = $this->rolesQuery($actor)->select('access_roles.id', 'access_roles.name');
            $roleRows = DB::query()
                ->fromSub($visibleRoles, 'visible_roles')
                ->join('access_user_roles as assigned', 'assigned.role_id', '=', 'visible_roles.id')
                ->whereIn('assigned.user_id', $userIds)
                ->orderBy('visible_roles.name')
                ->get(['assigned.user_id', 'visible_roles.name']);
            $defaultRoleQuery = DB::query()
                ->fromSub($this->rolesQuery($actor)->select('access_roles.id', 'access_roles.name'), 'visible_roles')
                ->join('access_department_roles as assigned_defaults', 'assigned_defaults.role_id', '=', 'visible_roles.id')
                ->join('access_users as assigned_users', 'assigned_users.department_id', '=', 'assigned_defaults.department_id')
                ->where('assigned_defaults.company_id', $actor['company_id'])
                ->whereIn('assigned_users.id', $userIds)
                ->orderBy('visible_roles.name');
            if (! $actor['is_admin']) {
                $defaultRoleQuery->where('assigned_defaults.branch_id', $actor['branch_id']);
            }
            $defaultRoleRows = $defaultRoleQuery->get(['assigned_users.id as user_id', 'visible_roles.name']);
            $directNames = $roleRows->groupBy('user_id')->map(static fn ($rows): array => $rows->pluck('name')->map(static fn ($name): string => (string) $name)->all());
            $defaultNames = $defaultRoleRows->groupBy('user_id')->map(static fn ($rows): array => $rows->pluck('name')->map(static fn ($name): string => (string) $name)->all());
            $users->setCollection($users->getCollection()->map(static function (array $user) use ($directNames, $defaultNames): array {
                $roleNames = array_values(array_unique(array_merge(
                    $directNames->get($user['id'], []),
                    $defaultNames->get($user['id'], []),
                )));
                sort($roleNames, SORT_STRING);
                $user['role_names'] = $roleNames;

                return $user;
            }));
        }

        return [
            'users' => $users,
            'summary' => $summary,
            'branchOptions' => $this->branchOptions($actor, $filters, $perPage),
            'departmentOptions' => $this->departmentOptions($actor, $filters, $perPage),
            'roleOptions' => $this->roleOptions($actor, $filters, $perPage),
        ];
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $filters
     * @return array{roles: LengthAwarePaginator<int, array<string, mixed>>, summary: array{total: int, permissions: int}}
     */
    public function roles(array $actor, array $filters, int $perPage): array
    {
        $this->access->requirePermission($actor, 'roles.read');

        $base = $this->rolesQuery($actor);
        $summary = [
            'total' => (clone $base)->count('access_roles.id'),
            'permissions' => DB::table('access_role_permissions')
                ->whereIn('role_id', (clone $base)->select('access_roles.id'))
                ->count(),
        ];

        $query = clone $base;
        if (filled($filters['q'] ?? null)) {
            $query->where('access_roles.name', 'like', $this->like($filters['q']));
        }
        if (filled($filters['role_id'] ?? null)) {
            $query->where('access_roles.id', $filters['role_id']);
        }

        $roles = $query->orderBy('access_roles.name', $filters['direction'] ?? 'asc')
            ->orderBy('access_roles.id')
            ->paginate($perPage, ['access_roles.id', 'access_roles.company_id', 'access_roles.name', 'access_roles.version'], 'page')
            ->appends($filters);

        $roles->setCollection($this->presentRoles($roles->getCollection()));

        return ['roles' => $roles, 'summary' => $summary];
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $filters
     * @return array{entries: LengthAwarePaginator<int, array<string, mixed>>, summary: array{total: int}}
     */
    public function audit(array $actor, array $filters, int $perPage): array
    {
        $this->access->requirePermission($actor, 'audit.read');

        $base = $this->auditQuery($actor);
        $summary = ['total' => (clone $base)->count('access_audit.id')];
        $query = clone $base;
        foreach (['actor_id', 'action', 'subject_id'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where('access_audit.'.$field, $filters[$field]);
            }
        }
        if (filled($filters['q'] ?? null)) {
            $like = $this->like($filters['q']);
            $query->where(static function (Builder $query) use ($like): void {
                $query->where('access_audit.action', 'like', $like)->orWhere('access_audit.subject_id', 'like', $like);
            });
        }
        if (filled($filters['from'] ?? null)) {
            $query->where('access_audit.created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (filled($filters['to'] ?? null)) {
            $query->where('access_audit.created_at', '<=', $filters['to'].' 23:59:59');
        }

        $entries = $query->orderBy('access_audit.created_at', $filters['direction'] ?? 'desc')
            ->orderBy('access_audit.id', 'desc')
            ->paginate($perPage, ['access_audit.id', 'access_audit.company_id', 'access_audit.actor_id', 'access_audit.action', 'access_audit.subject_id', 'access_audit.metadata', 'access_audit.created_at'], 'page')
            ->appends($filters);

        $entries->setCollection($entries->getCollection()->map(fn (stdClass $row): array => [
            'id' => (string) $row->id,
            'company_id' => (string) $row->company_id,
            'actor_id' => $row->actor_id === null ? null : (string) $row->actor_id,
            'action' => (string) $row->action,
            'subject_id' => (string) $row->subject_id,
            'metadata' => $this->redactMetadata($this->decodeMetadata($row->metadata)),
            'created_at' => (string) $row->created_at,
        ]));

        return ['entries' => $entries, 'summary' => $summary];
    }

    /** @param array<string, mixed> $actor */
    private function usersQuery(array $actor): Builder
    {
        $query = DB::table('access_users as users')
            ->leftJoin('access_branches as branches', 'branches.id', '=', 'users.branch_id')
            ->leftJoin('access_departments as departments', 'departments.id', '=', 'users.department_id')
            ->where('users.company_id', $actor['company_id']);

        if (! $actor['is_admin']) {
            $query->where('users.branch_id', $actor['branch_id']);
        }

        return $query;
    }

    /** @param array<string, mixed> $actor */
    private function rolesQuery(array $actor): Builder
    {
        $query = DB::table('access_roles')->where('access_roles.company_id', $actor['company_id']);
        if (! $actor['is_admin']) {
            $permissions = $actor['permissions'] ?? [];
            $query->whereNotExists(static function (Builder $query) use ($permissions): void {
                $query->selectRaw('1')
                    ->from('access_role_permissions as role_permissions')
                    ->whereColumn('role_permissions.role_id', 'access_roles.id')
                    ->whereNotIn('role_permissions.permission', $permissions);
            })->whereNotExists(static function (Builder $query) use ($actor): void {
                $query->selectRaw('1')
                    ->from('access_user_roles as assigned')
                    ->join('access_users as assigned_users', 'assigned_users.id', '=', 'assigned.user_id')
                    ->whereColumn('assigned.role_id', 'access_roles.id')
                    ->where(static function (Builder $query) use ($actor): void {
                        $query->where('assigned_users.company_id', '<>', $actor['company_id'])
                            ->orWhere('assigned_users.branch_id', '<>', $actor['branch_id'])
                            ->orWhere('assigned_users.is_admin', true);
                    });
            })->whereNotExists(static function (Builder $query) use ($actor): void {
                $query->selectRaw('1')
                    ->from('access_department_roles as assigned_departments')
                    ->whereColumn('assigned_departments.role_id', 'access_roles.id')
                    ->where(static function (Builder $query) use ($actor): void {
                        $query->where('assigned_departments.company_id', '<>', $actor['company_id'])
                            ->orWhere('assigned_departments.branch_id', '<>', $actor['branch_id']);
                    });
            });
        }

        return $query;
    }

    /** @param array<string, mixed> $actor */
    private function branchesQuery(array $actor): Builder
    {
        $query = DB::table('access_branches')->where('access_branches.company_id', $actor['company_id']);
        if (! $actor['is_admin']) {
            $query->where('access_branches.id', $actor['branch_id']);
        }

        return $query;
    }

    /** @param array<string, mixed> $actor */
    private function departmentsQuery(array $actor): Builder
    {
        $query = DB::table('access_departments')->where('access_departments.company_id', $actor['company_id']);
        if (! $actor['is_admin']) {
            $query->where('access_departments.branch_id', $actor['branch_id']);
        }

        return $query;
    }

    /** @param array<string, mixed> $actor */
    private function auditQuery(array $actor): Builder
    {
        $query = DB::table('access_audit')->where('company_id', $actor['company_id']);
        if (! $actor['is_admin']) {
            $query->where(static function (Builder $query) use ($actor): void {
                $query->where('actor_id', $actor['id'])
                    ->orWhere('metadata->actor_branch_id', $actor['branch_id']);
            });
        }

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @param  array<string, mixed>  $actor
     */
    private function applyOrganizationFilters(Builder $query, array $filters, string $type, array $actor): Builder
    {
        $table = $type === 'branch' ? 'access_branches' : 'access_departments';
        if (filled($filters['q'] ?? null)) {
            $like = $this->like($filters['q']);
            $query->where(static function (Builder $query) use ($table, $like): void {
                $query->where($table.'.name', 'like', $like)->orWhere($table.'.code', 'like', $like);
            });
        }
        if (($filters['status'] ?? null) !== null) {
            $query->where($table.'.active', $filters['status'] === 'active');
        }
        if ($type === 'branch') {
            if (filled($filters['branch_id'] ?? null)) {
                $query->where('access_branches.id', $filters['branch_id']);
            }
            if (filled($filters['department_id'] ?? null)) {
                $query->whereExists(static function (Builder $query) use ($filters): void {
                    $query->selectRaw('1')->from('access_departments as matching_departments')
                        ->whereColumn('matching_departments.branch_id', 'access_branches.id')
                        ->where('matching_departments.id', $filters['department_id']);
                });
            }
            if (filled($filters['role_id'] ?? null)) {
                if (! in_array('roles.read', $actor['permissions'], true)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereExists(function (Builder $query) use ($actor, $filters): void {
                        $query->selectRaw('1')->from('access_departments as matching_departments')
                            ->join('access_department_roles as matching_roles', 'matching_roles.department_id', '=', 'matching_departments.id')
                            ->whereColumn('matching_departments.branch_id', 'access_branches.id')
                            ->where('matching_roles.role_id', $filters['role_id'])
                            ->whereIn('matching_roles.role_id', $this->rolesQuery($actor)->select('access_roles.id'));
                    });
                }
            }
        } else {
            if (filled($filters['branch_id'] ?? null)) {
                $query->where('access_departments.branch_id', $filters['branch_id']);
            }
            if (filled($filters['department_id'] ?? null)) {
                $query->where('access_departments.id', $filters['department_id']);
            }
            if (filled($filters['role_id'] ?? null)) {
                if (! in_array('roles.read', $actor['permissions'], true)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereExists(function (Builder $query) use ($actor, $filters): void {
                        $query->selectRaw('1')->from('access_department_roles as matching_roles')
                            ->whereColumn('matching_roles.department_id', 'access_departments.id')
                            ->where('matching_roles.role_id', $filters['role_id'])
                            ->whereIn('matching_roles.role_id', $this->rolesQuery($actor)->select('access_roles.id'));
                    });
                }
            }
        }

        return $query;
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function branchOptions(array $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = DB::table('access_branches')->where('access_branches.company_id', $actor['company_id'])->where('access_branches.active', true);
        if (! $actor['is_admin']) {
            $query->where('access_branches.id', $actor['branch_id']);
        }
        if (filled($filters['branch_q'] ?? null)) {
            $query->where('access_branches.name', 'like', $this->like($filters['branch_q']));
        }

        $options = $query->orderBy('access_branches.name')->orderBy('access_branches.id')
            ->paginate($perPage, ['access_branches.id', 'access_branches.company_id', 'access_branches.name', 'access_branches.code', 'access_branches.active', 'access_branches.version'], 'branch_options_page')
            ->appends($filters);
        $options->setCollection($options->getCollection()->map(fn (stdClass $row): array => $this->presentBranch($row)));

        return $options;
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function departmentOptions(array $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = DB::table('access_departments')
            ->leftJoin('access_branches as branches', 'branches.id', '=', 'access_departments.branch_id')
            ->where('access_departments.company_id', $actor['company_id'])
            ->where('access_departments.active', true);
        if (! $actor['is_admin']) {
            $query->where('access_departments.branch_id', $actor['branch_id']);
        }
        if (filled($filters['department_q'] ?? null)) {
            $query->where('access_departments.name', 'like', $this->like($filters['department_q']));
        }

        $options = $query->orderBy('access_departments.name')->orderBy('access_departments.id')
            ->paginate($perPage, ['access_departments.id', 'access_departments.company_id', 'access_departments.branch_id', 'access_departments.name', 'access_departments.code', 'access_departments.active', 'access_departments.version', 'branches.name as branch_name'], 'department_options_page')
            ->appends($filters);
        $options->setCollection($options->getCollection()->map(fn (stdClass $row): array => $this->presentDepartment($row)));

        return $options;
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function roleOptions(array $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->rolesQuery($actor);
        if (! in_array('roles.read', $actor['permissions'], true)) {
            $query->whereRaw('1 = 0');
        }
        if (filled($filters['role_q'] ?? null)) {
            $query->where('access_roles.name', 'like', $this->like($filters['role_q']));
        }

        $roles = $query->orderBy('access_roles.name')->orderBy('access_roles.id')
            ->paginate($perPage, ['access_roles.id', 'access_roles.company_id', 'access_roles.name', 'access_roles.version'], 'role_options_page')
            ->appends($filters);
        $roles->setCollection($this->presentRoles($roles->getCollection()));

        return $roles;
    }

    /** @param iterable<stdClass> $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function presentRoles(iterable $rows): Collection
    {
        $rows = collect($rows);
        $ids = $rows->pluck('id')->all();
        $permissions = $ids === []
            ? collect()
            : DB::table('access_role_permissions')->whereIn('role_id', $ids)->orderBy('permission')->get(['role_id', 'permission'])->groupBy('role_id');

        return $rows->map(static fn (stdClass $row): array => [
            'id' => (string) $row->id,
            'company_id' => (string) $row->company_id,
            'name' => (string) $row->name,
            'permissions' => $permissions->get($row->id, collect())->pluck('permission')->map(static fn ($permission): string => (string) $permission)->all(),
            'version' => (int) $row->version,
        ])->values();
    }

    /** @return array{id: string, company_id: string, name: string, code: string, active: bool, version: int} */
    private function presentBranch(stdClass $row): array
    {
        return [
            'id' => (string) $row->id,
            'company_id' => (string) $row->company_id,
            'name' => (string) $row->name,
            'code' => (string) $row->code,
            'active' => (bool) $row->active,
            'version' => (int) $row->version,
        ];
    }

    /** @return array<string, mixed> */
    private function presentDepartment(stdClass $row): array
    {
        return [
            'id' => (string) $row->id,
            'company_id' => (string) $row->company_id,
            'branch_id' => (string) $row->branch_id,
            'name' => (string) $row->name,
            'code' => (string) $row->code,
            'active' => (bool) $row->active,
            'version' => (int) $row->version,
            'branch_name' => $row->branch_name === null ? null : (string) $row->branch_name,
        ];
    }

    private function like(string $value): string
    {
        return '%'.addcslashes($value, '%_\\').'%';
    }

    /** @return array<string, mixed> */
    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }
        if (! is_string($metadata)) {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<mixed> $metadata
     * @return array<string, mixed>
     */
    private function redactMetadata(array $metadata): array
    {
        $safe = [];
        foreach ($metadata as $key => $value) {
            if (is_string($key) && preg_match('/password|hash|token|secret|credential|authorization/i', $key) === 1) {
                continue;
            }
            if (is_array($value)) {
                $value = $this->redactMetadata($value);
            } elseif (! is_scalar($value) && $value !== null) {
                continue;
            }
            $safe[$key] = $value;
        }

        return $safe;
    }
}

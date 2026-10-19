<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Application\SEC04\AccessControl;
use App\Modules\Identity\Application\SEC04\BrowseAccessDirectory;
use App\Modules\Organization\Application\CORE01\OrganizationDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final readonly class AccessController
{
    public function __construct(private AccessControl $access, private BrowseAccessDirectory $browse, private OrganizationDirectory $directory) {}

    public function index(Request $request): View|JsonResponse
    {
        $actor = $this->actor($request);
        abort_if($actor['permissions'] === [], 403);

        if ($request->expectsJson()) {
            return response()->json(['user' => $actor, 'permissions' => $actor['permissions']]);
        }

        return view('access.index', [
            'actor' => $actor,
            'permissions' => $actor['permissions'],
            'summary' => $this->browse->overview($actor),
            'auditPreview' => in_array('audit.read', $actor['permissions'], true)
                ? $this->browse->audit($actor, [], 5)['entries']->items()
                : [],
            'title' => 'المؤسسة والوصول',
            'locale' => $this->locale($request),
        ]);
    }

    public function organization(Request $request): View|JsonResponse
    {
        $actor = $this->actor($request);
        if ($request->expectsJson()) {
            $data = $this->directory->overview($actor);
            $roles = [];
            if (in_array('roles.read', $actor['permissions'], true)) {
                $roles = $this->access->roles($actor);
            }
            $assignmentStates = $this->browse->departmentRoleAssignments($actor, array_column($data['departments'], 'id'));
            foreach ($data['departments'] as &$department) {
                $state = $assignmentStates[$department['id']] ?? ['role_ids' => [], 'roles_editable' => false];
                $department['role_ids'] = $state['role_ids'];
                $department['roles_editable'] = $state['roles_editable'];
            }
            unset($department);

            return $this->page($request, 'organization', 'المؤسسة', ['data' => $data, 'roles' => $roles], $actor, $data);
        }

        $filters = $this->browseFilters($request, ['name'], [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
            'branch_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'department_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'role_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'branch_q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'department_q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'role_q' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);
        $result = $this->browse->organization($actor, $filters, (int) ($filters['per_page'] ?? 10));

        return $this->page($request, 'organization', 'المؤسسة', $result + ['roles' => $result['roleOptions']], $actor, $result['data']);
    }

    public function departmentRoles(Request $request, string $department): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request);

        return DB::transaction(function () use ($request, $actor, $department): JsonResponse|RedirectResponse {
            $companyId = $actor['company_id'];
            DB::table('access_companies')->where('id', $companyId)->lockForUpdate()->first();

            $currentActor = $this->actor($request);
            abort_unless((string) $currentActor['company_id'] === (string) $companyId, 403);
            abort_unless($this->browse->canEditDepartmentRoles($currentActor, $department), 403);

            $data = $request->validate(['role_ids' => ['sometimes', 'array'], 'role_ids.*' => ['required', 'string', 'size:26', 'distinct'], 'version' => ['required', 'integer', 'min:0']]);
            $data['role_ids'] ??= [];

            return $this->saved($request, $this->access->assignDepartmentRoles($currentActor, $department, $data), '/access/organization');
        });
    }

    public function company(Request $request): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request);
        $this->access->requirePermission($actor, 'organization.write');
        $data = $request->validate(['name' => ['required', 'string', 'max:200'], 'version' => ['required', 'integer', 'min:0']]);

        return $this->saved($request, $this->directory->saveCompany($actor, $data), '/access/organization');
    }

    public function branch(Request $request, ?string $branch = null): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request);
        $this->access->requirePermission($actor, 'organization.write');
        $data = $request->validate($this->directoryRules($branch));

        return $this->saved($request, $this->directory->saveBranch($actor, $data, $branch), '/access/organization', $branch === null);
    }

    public function department(Request $request, ?string $department = null): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request);
        $this->access->requirePermission($actor, 'organization.write');
        $data = $request->validate($this->directoryRules($department) + ['branch_id' => ['required', 'string', 'size:26']]);

        return $this->saved($request, $this->directory->saveDepartment($actor, $data, $department), '/access/organization', $department === null);
    }

    public function users(Request $request): View|JsonResponse
    {
        $actor = $this->actor($request);
        if ($request->expectsJson()) {
            $users = $this->access->users($actor);

            return $this->page($request, 'users', 'المستخدمون', ['users' => $users], $actor, $users);
        }

        $filters = $this->browseFilters($request, ['name', 'email', 'date'], [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
            'branch_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'department_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'role_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'branch_q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'department_q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'role_q' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);
        $result = $this->browse->users($actor, $filters, (int) ($filters['per_page'] ?? 10));

        return $this->page($request, 'users', 'المستخدمون', $result + [
            'branches' => $result['branchOptions'],
            'departments' => $result['departmentOptions'],
        ], $actor, $result['users']);
    }

    public function user(Request $request, string $user): View|JsonResponse
    {
        $actor = $this->actor($request);
        $data = $this->access->user($actor, $user);
        $canReadAssignments = in_array('roles.read', $actor['permissions'], true);
        $catalog = [];
        if ($canReadAssignments) {
            $catalog = $this->permissionCatalog($actor);
            $assignedRoleIds = array_values(DB::table('access_user_roles')->where('user_id', $user)->pluck('role_id')->map(static fn ($id): string => (string) $id)->all());
            $roleIds = $this->browse->visibleRoleIds($actor, $assignedRoleIds);
            $storedOverrides = DB::table('access_user_permissions')
                ->where('user_id', $user)
                ->pluck('allowed', 'permission')
                ->map(static fn ($allowed): bool => (bool) $allowed)
                ->all();
            $visibleOverrides = array_intersect_key($storedOverrides, array_flip($catalog));
            $data['role_ids'] = $roleIds;
            $data['permissions'] = $visibleOverrides;
            $data['assignments_editable'] = in_array('users.write', $actor['permissions'], true)
                && in_array('roles.write', $actor['permissions'], true)
                && (string) $data['id'] !== (string) $actor['id']
                && (! $data['is_admin'] || $actor['is_admin'])
                && count($roleIds) === count($assignedRoleIds)
                && count($visibleOverrides) === count($storedOverrides);
        }

        $location = DB::table('access_users as users')
            ->leftJoin('access_branches as branches', 'branches.id', '=', 'users.branch_id')
            ->leftJoin('access_departments as departments', 'departments.id', '=', 'users.department_id')
            ->where('users.company_id', $actor['company_id'])
            ->where('users.id', $user)
            ->when(! $actor['is_admin'], static fn ($query) => $query->where('users.branch_id', $actor['branch_id']))
            ->first(['branches.name as branch_name', 'departments.name as department_name']);
        $data['branch_name'] = $location?->branch_name === null ? null : (string) $location->branch_name;
        $data['department_name'] = $location?->department_name === null ? null : (string) $location->department_name;

        if ($request->expectsJson()) {
            return $this->page($request, 'user', 'تفاصيل المستخدم', ['user' => $data], $actor, $data);
        }

        $selectorFilters = $this->browseFilters($request, [], [
            'branch_q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'department_q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'role_q' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);
        $options = $this->browse->assignmentOptions($actor, $selectorFilters, (int) ($selectorFilters['per_page'] ?? 50));

        $variables = ['user' => $data] + $options;
        if ($canReadAssignments) {
            $variables['catalog'] = $catalog;
        }

        return $this->page($request, 'user', 'تفاصيل المستخدم', $variables, $actor, $data);
    }

    public function saveUser(Request $request, ?string $user = null): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request);
        $this->access->requirePermission($actor, 'users.write');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:254'],
            'password' => [$user === null ? 'required' : 'nullable', 'string', 'min:12', 'max:128'],
            'branch_id' => ['required', 'string', 'size:26'],
            'department_id' => ['required', 'string', 'size:26'],
            'active' => ['sometimes', 'boolean'],
            'version' => [$user === null ? 'sometimes' : 'required', 'integer', 'min:0'],
            'is_admin' => ['prohibited'],
            'company_id' => ['prohibited'],
            'session_version' => ['prohibited'],
        ]);
        $result = $this->access->saveUser($actor, $data, $user);

        return $this->saved($request, $result, '/access/users/'.$result['id'], $user === null);
    }

    public function roles(Request $request): View|JsonResponse
    {
        $actor = $this->actor($request);
        if ($request->expectsJson()) {
            $roles = $this->access->roles($actor);

            return $this->page($request, 'roles', 'الأدوار', ['roles' => $roles, 'catalog' => AccessControl::PERMISSIONS], $actor, $roles);
        }

        $filters = $this->browseFilters($request, ['name'], [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'role_id' => ['sometimes', 'nullable', 'string', 'size:26'],
        ]);
        $result = $this->browse->roles($actor, $filters, (int) ($filters['per_page'] ?? 10));

        return $this->page($request, 'roles', 'الأدوار', $result + ['catalog' => $this->permissionCatalog($actor)], $actor, $result['roles']);
    }

    public function role(Request $request, string $role): View|JsonResponse
    {
        $actor = $this->actor($request);
        if ($request->expectsJson()) {
            $data = collect($this->access->roles($actor))->firstWhere('id', $role);
            abort_if($data === null, 404);

            return $this->page($request, 'role', 'تفاصيل الدور', ['role' => $data, 'catalog' => AccessControl::PERMISSIONS], $actor, $data);
        }

        $result = $this->browse->roles($actor, ['role_id' => $role], 10);
        $data = $result['roles']->items()[0] ?? null;
        abort_if($data === null, 404);

        return $this->page($request, 'role', 'تفاصيل الدور', ['role' => $data, 'catalog' => $this->permissionCatalog($actor)], $actor, $data);
    }

    public function saveRole(Request $request, ?string $role = null): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request);
        $this->access->requirePermission($actor, 'roles.write');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(AccessControl::PERMISSIONS)],
            'version' => [$role === null ? 'sometimes' : 'required', 'integer', 'min:0'],
        ]);
        $data['permissions'] ??= [];
        $result = $this->access->saveRole($actor, $data, $role);

        return $this->saved($request, $result, '/access/roles/'.$result['id'], $role === null);
    }

    public function assignment(Request $request, string $user): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request);

        return DB::transaction(function () use ($request, $actor, $user): JsonResponse|RedirectResponse {
            $companyId = $actor['company_id'];
            DB::table('access_companies')->where('id', $companyId)->lockForUpdate()->first();

            $currentActor = $this->actor($request);
            abort_unless((string) $currentActor['company_id'] === (string) $companyId, 403);
            $this->access->requirePermission($currentActor, 'roles.write');

            $data = $request->validate([
                'role_ids' => ['sometimes', 'array'],
                'role_ids.*' => ['string', 'size:26', 'distinct'],
                'permissions' => ['sometimes', 'array'],
                'version' => ['required', 'integer', 'min:0'],
            ]);
            abort_unless($this->browse->canEditUserAssignments($currentActor, $user, $this->permissionCatalog($currentActor)), 403);

            $catalog = $this->permissionCatalog($currentActor);
            $overrides = [];
            foreach ($data['permissions'] ?? [] as $permission => $allowed) {
                abort_unless(in_array($permission, AccessControl::PERMISSIONS, true), 422);
                if ($allowed === 'inherit' || $allowed === '') {
                    continue;
                }
                abort_unless(in_array($allowed, [true, false, 0, 1, '0', '1'], true), 422);
                $overrides[$permission] = (bool) $allowed;
            }
            $data['permissions'] = $overrides;
            $data['role_ids'] ??= [];

            return $this->saved($request, $this->access->assignAccess($currentActor, $user, $data), '/access/users/'.$user);
        });
    }

    public function audit(Request $request): View|JsonResponse
    {
        $actor = $this->actor($request);
        if ($request->expectsJson()) {
            $entries = $this->access->audit($actor);
            $this->access->record($actor, 'audit.viewed', $actor['company_id']);

            return $this->page($request, 'audit', 'سجل التدقيق', ['entries' => $entries], $actor, $entries);
        }

        $filters = $this->browseFilters($request, ['date', 'created_at'], [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'actor_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'action' => ['sometimes', 'nullable', 'string', 'max:100'],
            'subject_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);
        $result = $this->browse->audit($actor, $filters, (int) ($filters['per_page'] ?? 10));
        $this->access->record($actor, 'audit.viewed', $actor['company_id']);

        return $this->page($request, 'audit', 'سجل التدقيق', $result, $actor, $result['entries']);
    }

    /** @return array<string, mixed> */
    private function actor(Request $request): array
    {
        abort_unless(app()->environment(['local', 'testing']) === true, 404);
        app()->setLocale('ar');

        return $this->access->actor($request);
    }

    /** @param list<string> $sorts
     * @param  array<string, array<mixed>>  $rules
     * @return array<string, mixed>
     */
    private function browseFilters(Request $request, array $sorts, array $rules): array
    {
        return $request->validate([
            'locale' => ['sometimes', Rule::in(['ar', 'en'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50])],
            'sort' => ['sometimes', Rule::in($sorts)],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'branches_page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'departments_page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'branch_options_page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'department_options_page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'role_options_page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
        ] + $rules);
    }

    /** @param array<string, mixed> $actor
     * @return list<string>
     */
    private function permissionCatalog(array $actor): array
    {
        if ($actor['is_admin']) {
            return AccessControl::PERMISSIONS;
        }

        return array_values(array_intersect(AccessControl::PERMISSIONS, $actor['permissions']));
    }

    private function locale(Request $request): string
    {
        $locale = $request->validate(['locale' => ['sometimes', Rule::in(['ar', 'en'])]])['locale'] ?? 'ar';
        app()->setLocale($locale);

        return $locale;
    }

    /** @return array<string, list<string>> */
    private function directoryRules(?string $id): array
    {
        return ['name' => ['required', 'string', 'max:200'], 'code' => ['required', 'string', 'max:40'], 'active' => ['sometimes', 'boolean'], 'version' => [$id === null ? 'sometimes' : 'required', 'integer', 'min:0']];
    }

    /** @param array<string, mixed> $variables
     * @param  array<string, mixed>  $actor
     */
    private function page(Request $request, string $view, string $title, array $variables, array $actor, mixed $data): View|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['data' => $data]) : view('access.'.$view, $variables + ['actor' => $actor, 'permissions' => $actor['permissions'], 'title' => $title, 'locale' => $this->locale($request)]);
    }

    /** @param array<string, mixed> $data */
    private function saved(Request $request, array $data, string $url, bool $created = false): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['data' => $data], $created ? 201 : 200) : redirect($url)->with('status', 'تم حفظ التغييرات');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\SEC04;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AccessControl
{
    public const PERMISSIONS = [
        'organization.read',
        'organization.write',
        'users.read',
        'users.write',
        'roles.read',
        'roles.write',
        'audit.read',
        'employees.read',
        'employees.write',
        'employees.manage_all',
        'employees.evidence.write',
        'employee.private',
        'crm.read',
        'crm.manage',
        'crm.write',
        'crm.assign',
        'crm.transfer',
        'crm.convert',
        'crm.taxonomy',
        'contact.reveal',
        'tasks.read',
        'tasks.write',
        'tasks.assign',
        'tasks.assist',
        'cycles.read',
        'cycles.write',
    ];

    /** @return array<string, mixed> */
    public function authenticate(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL)
            ? DB::table('access_users')->where('email', $email)->first()
            : null;

        if ($user === null
            || ! Hash::check($password, (string) $user->password)
            || ! $this->hasActiveOrganization($user)) {
            throw ValidationException::withMessages(['email' => 'The provided credentials are invalid.']);
        }

        return $this->presentUser($user, true) + ['permissions' => $this->permissionsForUser($user)];
    }

    /** @return array<string, mixed> */
    public function actor(Request $request): array
    {
        if (($snapshot = $this->readActorSnapshot($request)) !== null) {
            return $snapshot;
        }
        $request->attributes->remove(self::class.'.read_actor');
        if (! $request->hasSession()) {
            throw new AuthenticationException('An authenticated access session is required.');
        }

        $userId = $request->session()->get('access_user_id');
        $sessionVersion = $this->integerValue($request->session()->get('access_session_version'));

        if (! is_string($userId) || $userId === '' || $sessionVersion === null) {
            throw new AuthenticationException('An authenticated access session is required.');
        }

        $user = DB::table('access_users')->where('id', $userId)->first();

        if ($user === null
            || ! $this->hasActiveOrganization($user)
            || (int) $user->session_version !== $sessionVersion) {
            throw new AuthenticationException('The access session is no longer valid.');
        }

        $actor = $this->presentUser($user, true) + ['permissions' => $this->permissionsForUser($user)];
        $this->rememberReadActor($request, $actor);

        return $actor;
    }

    /** @param list<string> $permissions
     * @param  callable(array<string, mixed>): mixed  $operation
     */
    public function withReadLock(Request $request, array $permissions, callable $operation): mixed
    {
        $initial = $this->actor($request);
        $verified = null;
        $result = DB::transaction(function () use ($request, $initial, $permissions, $operation, &$verified): mixed {
            if (DB::table('access_companies')->where('id', $initial['company_id'])->lockForUpdate()->first() === null) {
                throw new AuthenticationException('The access actor company is no longer active.');
            }
            // Recheck after acquiring the same lock used by role, scope and permission mutations.
            $verified = $this->actor($request);
            if ($verified['id'] !== $initial['id'] || $verified['company_id'] !== $initial['company_id']) {
                throw new AuthenticationException('The access actor scope has changed.');
            }
            foreach ($permissions as $permission) {
                $this->assertVerifiedPermission($verified, $permission);
            }

            return $operation($verified);
        }, 3);
        if ($verified !== null) {
            $this->rememberReadActor($request, $verified);
        }

        return $result;
    }

    /** @param array<string, mixed> $actor */
    public function requirePermission(array $actor, string $permission): void
    {
        $this->authorizedActor($actor, $permission);
    }

    /** @return list<string> */
    public function permissions(string $userId): array
    {
        $snapshot = $this->readActorSnapshot();
        if ($snapshot !== null && $snapshot['id'] === $userId) {
            return $snapshot['permissions'];
        }
        $user = DB::table('access_users')->where('id', $userId)->first();

        return $user === null || ! $this->hasActiveOrganization($user)
            ? []
            : $this->permissionsForUser($user);
    }

    /** @param array<string, mixed> $actor
     * @return list<array<string, mixed>>
     */
    public function users(array $actor): array
    {
        $current = $this->authorizedActor($actor, 'users.read');
        $query = DB::table('access_users')->where('company_id', $current['company_id']);

        if (! $current['is_admin']) {
            $query->where('branch_id', $current['branch_id']);
        }

        return array_values(array_map(
            fn (stdClass $user): array => $this->presentUser($user),
            $query->orderBy('name')->orderBy('id')->get()->all(),
        ));
    }

    /** @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    public function user(array $actor, string $id): array
    {
        $current = $this->authorizedActor($actor, 'users.read');
        $query = DB::table('access_users')
            ->where('company_id', $current['company_id'])
            ->where('id', $id);

        if (! $current['is_admin']) {
            $query->where('branch_id', $current['branch_id']);
        }

        $user = $query->first();

        if ($user === null) {
            throw new NotFoundHttpException('User not found.');
        }

        return $this->presentUser($user);
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveUser(array $actor, array $data, ?string $id = null): array
    {
        $this->onlyKeys($data, ['name', 'email', 'password', 'branch_id', 'department_id', 'active', 'version', 'is_admin']);
        if (($data['is_admin'] ?? false) === true || ($data['is_admin'] ?? false) === 1 || ($data['is_admin'] ?? false) === '1') {
            $this->invalid('is_admin', 'Administrator access can only be assigned by a fixture.');
        }

        $name = $this->requiredString($data, 'name', 200);
        $email = mb_strtolower(trim($this->requiredString($data, 'email', 254)));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->invalid('email', 'A valid email address is required.');
        }

        $branchId = $this->requiredString($data, 'branch_id', 26);
        $departmentId = $this->requiredString($data, 'department_id', 26);
        $active = array_key_exists('active', $data) ? $this->booleanInput($data['active'], 'active') : null;
        $password = $data['password'] ?? null;

        if ($password !== null && (! is_string($password) || mb_strlen($password) < 12 || mb_strlen($password) > 255)) {
            $this->invalid('password', 'The password must be between 12 and 255 characters.');
        }
        if ($id === null && $password === null) {
            $this->invalid('password', 'A password is required for a new user.');
        }

        $expectedVersion = $this->versionInput($data, $id === null ? 0 : null);

        try {
            return $this->withWriteLock($actor, 'users.write', function (array $current) use ($id, $expectedVersion, $name, $email, $branchId, $departmentId, $active, $password): array {
                $target = null;

                if ($id !== null) {
                    $target = DB::table('access_users')
                        ->where('company_id', $current['company_id'])
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->first();

                    if ($target === null) {
                        throw new NotFoundHttpException('User not found.');
                    }
                    if ($target->id === $current['id']) {
                        throw new AccessDeniedHttpException('Users cannot change their own account.');
                    }
                    if ((bool) $target->is_admin && ! $current['is_admin']) {
                        throw new AccessDeniedHttpException('Only the root administrator may change a root account.');
                    }
                    if (! $current['is_admin'] && $target->branch_id !== $current['branch_id']) {
                        throw new NotFoundHttpException('User not found.');
                    }
                    $this->assertVersion($expectedVersion, (int) $target->version, 'User version is stale.');
                }

                $this->assertUserScope($current, $branchId, $departmentId);
                $timestamp = now()->toDateTimeString();

                if ($target === null) {
                    $userId = (string) Str::ulid();
                    $values = [
                        'id' => $userId,
                        'company_id' => $current['company_id'],
                        'branch_id' => $branchId,
                        'department_id' => $departmentId,
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make((string) $password),
                        'active' => $active ?? true,
                        'is_admin' => false,
                        'session_version' => 0,
                        'version' => 0,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    DB::table('access_users')->insert($values);
                    $saved = DB::table('access_users')->where('id', $userId)->first();
                    $this->insertAudit($current, 'user.created', $userId, [
                        'branch_id' => $branchId,
                        'changes' => ['name' => $name, 'email' => $email, 'active' => $active ?? true],
                        'roles_before' => [],
                        'roles_after' => [],
                        'effective_permissions_before' => [],
                        'effective_permissions_after' => [],
                    ]);
                } else {
                    $oldPermissions = $this->permissionsForUser($target);
                    $rolesBefore = $this->roleIdsForUser((string) $target->id);
                    $changedSessionState = (bool) $target->active !== ($active ?? (bool) $target->active)
                        || $target->branch_id !== $branchId
                        || $target->department_id !== $departmentId;
                    $passwordChanged = $password !== null && ! Hash::check($password, (string) $target->password);
                    $changedSessionState = $changedSessionState || $passwordChanged;

                    $values = [
                        'branch_id' => $branchId,
                        'department_id' => $departmentId,
                        'name' => $name,
                        'email' => $email,
                        'active' => $active ?? (bool) $target->active,
                        'version' => $expectedVersion + 1,
                        'updated_at' => $timestamp,
                    ];
                    if ($passwordChanged) {
                        $values['password'] = Hash::make($password);
                    }
                    if ($changedSessionState) {
                        $values['session_version'] = (int) $target->session_version + 1;
                    }

                    DB::table('access_users')->where('id', $id)->update($values);
                    $saved = DB::table('access_users')->where('id', $id)->first();
                    if ($saved === null) {
                        throw new \LogicException('Saved user could not be read back.');
                    }
                    $this->insertAudit($current, 'user.updated', (string) $id, [
                        'branch_id' => $branchId,
                        'changes' => [
                            'name' => [$target->name, $name],
                            'email' => [$target->email, $email],
                            'branch_id' => [$target->branch_id, $branchId],
                            'department_id' => [$target->department_id, $departmentId],
                            'active' => [(bool) $target->active, $active ?? (bool) $target->active],
                            'password_changed' => $passwordChanged,
                        ],
                        'roles_before' => $rolesBefore,
                        'roles_after' => $rolesBefore,
                        'effective_permissions_before' => $oldPermissions,
                        'effective_permissions_after' => $this->permissionsForUser($saved),
                    ]);
                }

                if ($saved === null) {
                    throw new \LogicException('Saved user could not be read back.');
                }

                return $this->presentUser($saved);
            });
        } catch (UniqueConstraintViolationException) {
            $this->invalid('email', 'The email address has already been taken.');
        }
    }

    /** @param array<string, mixed> $actor
     * @return list<array<string, mixed>>
     */
    public function roles(array $actor): array
    {
        $current = $this->authorizedActor($actor, 'roles.read');
        $rows = DB::table('access_roles')
            ->where('company_id', $current['company_id'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $roles = [];
        foreach ($rows as $role) {
            $permissions = $this->permissionsForRole((string) $role->id);
            if (! $current['is_admin'] && array_diff($permissions, $current['permissions']) !== []) {
                continue;
            }
            if (! $current['is_admin'] && ! $this->roleIsWithinBranch(
                (string) $role->id,
                (string) $current['company_id'],
                (string) $current['branch_id'],
            )) {
                continue;
            }
            $roles[] = $this->presentRole($role, $permissions);
        }

        return $roles;
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveRole(array $actor, array $data, ?string $id = null): array
    {
        $this->onlyKeys($data, ['name', 'permissions', 'version']);
        $name = trim($this->requiredString($data, 'name', 200));
        $permissions = $this->permissionList($data['permissions'] ?? null);
        $expectedVersion = $this->versionInput($data, $id === null ? 0 : null);

        try {
            return $this->withWriteLock($actor, 'roles.write', function (array $current) use ($id, $name, $permissions, $expectedVersion): array {
                if (! $current['is_admin'] && array_diff($permissions, $current['permissions']) !== []) {
                    throw new AccessDeniedHttpException('A role cannot grant permissions the actor does not hold.');
                }

                if ($id === null) {
                    $roleId = (string) Str::ulid();
                    DB::table('access_roles')->insert([
                        'id' => $roleId,
                        'company_id' => $current['company_id'],
                        'name' => $name,
                        'version' => 0,
                    ]);
                    $this->replaceRolePermissions($roleId, $permissions);
                    $role = DB::table('access_roles')->where('id', $roleId)->first();
                    $beforePermissions = [];
                    $rolesBefore = [];
                    $action = 'role.created';
                } else {
                    $role = DB::table('access_roles')
                        ->where('company_id', $current['company_id'])
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->first();
                    if ($role === null) {
                        throw new NotFoundHttpException('Role not found.');
                    }
                    $this->assertVersion($expectedVersion, (int) $role->version, 'Role version is stale.');
                    $this->assertRoleEditable($current, $id);
                    $beforePermissions = $this->permissionsForRole($id);
                    $rolesBefore = [['id' => $id, 'name' => (string) $role->name]];

                    DB::table('access_roles')->where('id', $id)->update([
                        'name' => $name,
                        'version' => $expectedVersion + 1,
                    ]);
                    $this->replaceRolePermissions($id, $permissions);
                    $role = DB::table('access_roles')->where('id', $id)->first();
                    $action = 'role.updated';
                }

                if ($role === null) {
                    throw new \LogicException('Saved role could not be read back.');
                }

                $roleId = (string) $role->id;
                $rolesAfter = [['id' => $roleId, 'name' => (string) $role->name]];
                $this->insertAudit($current, $action, $roleId, [
                    'branch_id' => $current['branch_id'],
                    'changes' => ['name' => $name, 'permissions' => $permissions],
                    'roles_before' => $rolesBefore,
                    'roles_after' => $rolesAfter,
                    'effective_permissions_before' => $beforePermissions,
                    'effective_permissions_after' => $permissions,
                ]);

                return $this->presentRole($role, $permissions);
            });
        } catch (UniqueConstraintViolationException) {
            $this->invalid('name', 'The role name has already been taken.');
        }
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function assignAccess(array $actor, string $userId, array $data): array
    {
        $this->onlyKeys($data, ['role_ids', 'permissions', 'version']);
        $roleIds = $this->idList($data['role_ids'] ?? null, 'role_ids');
        $overrides = $this->permissionOverrides($data['permissions'] ?? null);
        $expectedVersion = $this->versionInput($data, null);

        return $this->withWriteLock($actor, 'users.write', function (array $current) use ($userId, $roleIds, $overrides, $expectedVersion): array {
            $this->requirePermission($current, 'roles.write');
            $target = DB::table('access_users')
                ->where('company_id', $current['company_id'])
                ->where('id', $userId)
                ->lockForUpdate()
                ->first();

            if ($target === null) {
                throw new NotFoundHttpException('User not found.');
            }
            if ($target->id === $current['id']) {
                throw new AccessDeniedHttpException('Users cannot assign access to themselves.');
            }
            if ((bool) $target->is_admin && ! $current['is_admin']) {
                throw new AccessDeniedHttpException('Only the root administrator may change a root account.');
            }
            if (! $current['is_admin'] && $target->branch_id !== $current['branch_id']) {
                throw new NotFoundHttpException('User not found.');
            }
            $this->assertVersion($expectedVersion, (int) $target->version, 'User version is stale.');

            if (! $current['is_admin']) {
                foreach ($overrides as $permission => $allowed) {
                    if ($allowed && ! in_array($permission, $current['permissions'], true)) {
                        throw new AccessDeniedHttpException('An actor cannot grant a permission they do not hold.');
                    }
                }
            }

            $roles = [];
            if ($roleIds !== []) {
                $roles = DB::table('access_roles')
                    ->where('company_id', $current['company_id'])
                    ->whereIn('id', $roleIds)
                    ->get()
                    ->all();
                if (count($roles) !== count($roleIds)) {
                    $this->invalid('role_ids', 'One or more selected roles are invalid.');
                }
                if (! $current['is_admin']) {
                    foreach ($roles as $role) {
                        if (! $this->roleIsWithinBranch(
                            (string) $role->id,
                            (string) $current['company_id'],
                            (string) $current['branch_id'],
                        )) {
                            throw new AccessDeniedHttpException('An actor cannot assign a role used outside their branch.');
                        }
                        if (array_diff($this->permissionsForRole((string) $role->id), $current['permissions']) !== []) {
                            throw new AccessDeniedHttpException('An actor cannot grant a role with permissions they do not hold.');
                        }
                    }
                }
            }

            $rolesBefore = $this->roleIdsForUser($userId);
            $permissionsBefore = $this->permissionsForUser($target);
            DB::table('access_user_roles')->where('user_id', $userId)->delete();
            if ($roleIds !== []) {
                DB::table('access_user_roles')->insert(array_map(
                    fn (string $roleId): array => ['user_id' => $userId, 'role_id' => $roleId],
                    $roleIds,
                ));
            }

            DB::table('access_user_permissions')->where('user_id', $userId)->delete();
            if ($overrides !== []) {
                DB::table('access_user_permissions')->insert(array_map(
                    fn (string $permission, bool $allowed): array => [
                        'user_id' => $userId,
                        'permission' => $permission,
                        'allowed' => $allowed,
                    ],
                    array_keys($overrides),
                    array_values($overrides),
                ));
            }

            DB::table('access_users')->where('id', $userId)->update([
                'version' => $expectedVersion + 1,
                'updated_at' => now()->toDateTimeString(),
            ]);
            $saved = DB::table('access_users')->where('id', $userId)->first();
            if ($saved === null) {
                throw new \LogicException('Assigned user could not be read back.');
            }

            $rolesAfter = $this->roleIdsForUser($userId);
            $permissionsAfter = $this->permissionsForUser($saved);
            $this->insertAudit($current, 'access.assigned', $userId, [
                'branch_id' => (string) $target->branch_id,
                'changes' => [
                    'role_ids' => [$rolesBefore, $rolesAfter],
                    'user_permissions' => $overrides,
                ],
                'roles_before' => $rolesBefore,
                'roles_after' => $rolesAfter,
                'effective_permissions_before' => $permissionsBefore,
                'effective_permissions_after' => $permissionsAfter,
            ]);

            return $this->presentUser($saved) + [
                'role_ids' => $rolesAfter,
                'permissions' => $permissionsAfter,
                'user_permissions' => $overrides,
            ];
        });
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function assignDepartmentRoles(array $actor, string $departmentId, array $data): array
    {
        $this->onlyKeys($data, ['role_ids', 'version']);
        $roleIds = $this->idList($data['role_ids'] ?? null, 'role_ids');
        sort($roleIds);
        $expectedVersion = $this->versionInput($data, null);

        return $this->withWriteLock($actor, 'organization.write', function (array $current) use ($departmentId, $roleIds, $expectedVersion): array {
            $this->requirePermission($current, 'roles.write');
            $department = DB::table('access_departments')->where('id', $departmentId)->lockForUpdate()->first();
            if ($department === null || (string) $department->company_id !== (string) $current['company_id']) {
                $this->invalid('department_id', 'The selected department is invalid.');
            }
            if (! $current['is_admin'] && (string) $department->branch_id !== (string) $current['branch_id']) {
                throw new NotFoundHttpException('Department not found.');
            }
            if (! $current['is_admin'] && (string) $department->id === (string) $current['department_id']) {
                throw new AccessDeniedHttpException('Users cannot change defaults for their own department.');
            }
            $this->assertVersion($expectedVersion, (int) $department->version, 'Department version is stale.');

            $roles = $roleIds === [] ? [] : DB::table('access_roles')
                ->where('company_id', $current['company_id'])
                ->whereIn('id', $roleIds)
                ->get()
                ->all();
            if (count($roles) !== count($roleIds)) {
                $this->invalid('role_ids', 'One or more selected roles are invalid.');
            }
            if (! $current['is_admin']) {
                foreach ($roles as $role) {
                    if (! $this->roleIsWithinBranch(
                        (string) $role->id,
                        (string) $current['company_id'],
                        (string) $current['branch_id'],
                    )) {
                        throw new AccessDeniedHttpException('An actor cannot assign a role used outside their branch.');
                    }
                    if (array_diff($this->permissionsForRole((string) $role->id), $current['permissions']) !== []) {
                        throw new AccessDeniedHttpException('An actor cannot grant a role with permissions they do not hold.');
                    }
                }
            }

            $rolesBefore = $this->roleIdsForDepartment($departmentId);
            $permissionsBefore = $this->permissionsForRoles($rolesBefore);
            DB::table('access_department_roles')->where('department_id', $departmentId)->delete();
            if ($roleIds !== []) {
                DB::table('access_department_roles')->insert(array_map(
                    fn (string $roleId): array => [
                        'company_id' => $current['company_id'],
                        'branch_id' => (string) $department->branch_id,
                        'department_id' => $departmentId,
                        'role_id' => $roleId,
                    ],
                    $roleIds,
                ));
            }

            DB::table('access_departments')->where('id', $departmentId)->update([
                'version' => $expectedVersion + 1,
            ]);
            $saved = DB::table('access_departments')->where('id', $departmentId)->first();
            if ($saved === null) {
                throw new \LogicException('Assigned department roles could not be read back.');
            }

            $rolesAfter = $this->roleIdsForDepartment($departmentId);
            $permissionsAfter = $this->permissionsForRoles($rolesAfter);
            $this->insertAudit($current, 'department.roles.assigned', $departmentId, [
                'branch_id' => (string) $department->branch_id,
                'changes' => ['role_ids' => [$rolesBefore, $rolesAfter]],
                'roles_before' => $rolesBefore,
                'roles_after' => $rolesAfter,
                'effective_permissions_before' => $permissionsBefore,
                'effective_permissions_after' => $permissionsAfter,
            ]);

            return [
                'id' => (string) $saved->id,
                'company_id' => (string) $saved->company_id,
                'branch_id' => (string) $saved->branch_id,
                'name' => (string) $saved->name,
                'code' => (string) $saved->code,
                'active' => (bool) $saved->active,
                'version' => (int) $saved->version,
                'role_ids' => $rolesAfter,
            ];
        });
    }

    /** @param array<string, mixed> $actor
     * @return list<array<string, mixed>>
     */
    public function audit(array $actor): array
    {
        $current = $this->authorizedActor($actor, 'audit.read');
        $query = DB::table('access_audit')->where('company_id', $current['company_id']);

        if (! $current['is_admin']) {
            $query->where(function ($query) use ($current): void {
                $query->where('actor_id', $current['id'])
                    ->orWhere('metadata->actor_branch_id', $current['branch_id']);
            });
        }

        return array_values(array_map(function (stdClass $row): array {
            return [
                'id' => (string) $row->id,
                'company_id' => (string) $row->company_id,
                'actor_id' => $row->actor_id === null ? null : (string) $row->actor_id,
                'action' => (string) $row->action,
                'subject_id' => (string) $row->subject_id,
                'metadata' => $this->sanitizeMetadata($this->decodeMetadata($row->metadata)),
                'created_at' => (string) $row->created_at,
            ];
        }, $query->orderByDesc('created_at')->orderByDesc('id')->get()->all()));
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $metadata
     */
    public function record(array $actor, string $action, string $subjectId, array $metadata = []): void
    {
        $this->withWriteLock($actor, null, function (array $current) use ($action, $subjectId, $metadata): void {
            $this->insertAudit($current, $action, $subjectId, $metadata);
        });
    }

    /** @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    private function authorizedActor(array $actor, string $permission): array
    {
        $current = $this->readActorSnapshot();
        foreach (['id', 'company_id', 'branch_id', 'department_id', 'session_version'] as $field) {
            if ($current === null || ! array_key_exists($field, $actor) || (string) $actor[$field] !== (string) $current[$field]) {
                $current = $this->reauthenticateActor($actor);
                break;
            }
        }

        $this->assertVerifiedPermission($current, $permission);

        return $current;
    }

    /** @param array<string, mixed> $actor */
    private function assertVerifiedPermission(array $actor, string $permission): void
    {
        if (! in_array($permission, self::PERMISSIONS, true) || ! in_array($permission, $actor['permissions'], true)) {
            throw new AccessDeniedHttpException('The requested permission is required.');
        }
    }

    /** @param array<string, mixed> $actor */
    private function rememberReadActor(Request $request, array $actor): void
    {
        if ($request->isMethodSafe() && DB::transactionLevel() === 0) {
            $request->attributes->set(self::class.'.read_actor', [
                'request' => \WeakReference::create($request),
                'session_id' => $request->session()->getId(),
                'actor' => $actor,
            ]);
        }
    }

    /** @param array<string, mixed> $actor
     * @param  callable(array<string, mixed>): mixed  $operation
     */
    private function withWriteLock(array $actor, ?string $permission, callable $operation): mixed
    {
        // Mutations always reauthenticate under the existing company lock, even after a GET read.
        if (app()->bound('request')) {
            app('request')->attributes->remove(self::class.'.read_actor');
        }
        $companyId = $actor['company_id'] ?? null;
        if (! is_string($companyId) || $companyId === '') {
            throw new AuthenticationException('The access actor is invalid.');
        }

        return DB::transaction(function () use ($actor, $companyId, $permission, $operation): mixed {
            $company = DB::table('access_companies')->where('id', $companyId)->lockForUpdate()->first();
            if ($company === null) {
                throw new AuthenticationException('The access actor is invalid.');
            }

            $current = $this->reauthenticateActor($actor);
            if ($current['company_id'] !== $companyId) {
                throw new AuthenticationException('The access actor scope has changed.');
            }
            $current['effective_role_ids'] = $this->effectiveRoleIdsForUser(
                (string) $current['id'],
                (string) $current['department_id'],
            );
            if ($permission !== null && ! in_array($permission, $current['permissions'], true)) {
                throw new AccessDeniedHttpException('The requested permission is required.');
            }

            return $operation($current);
        }, 3);
    }

    /** @return array<string, mixed>|null */
    private function readActorSnapshot(?Request $request = null): ?array
    {
        $request ??= app()->bound('request') ? app('request') : null;
        if (! $request instanceof Request || ! $request->isMethodSafe() || ! $request->hasSession() || DB::transactionLevel() !== 0) {
            return null;
        }
        $snapshot = $request->attributes->get(self::class.'.read_actor');
        if (! is_array($snapshot) || ! ($snapshot['request'] ?? null) instanceof \WeakReference
            || $snapshot['request']->get() !== $request || ! is_array($snapshot['actor'] ?? null)) {
            return null;
        }
        $actor = $snapshot['actor'];
        if (($snapshot['session_id'] ?? null) !== $request->session()->getId()
            || ($actor['id'] ?? null) !== $request->session()->get('access_user_id')
            || ($actor['session_version'] ?? null) !== $this->integerValue($request->session()->get('access_session_version'))) {
            return null;
        }

        return $actor;
    }

    /** @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    private function reauthenticateActor(array $actor): array
    {
        $userId = $actor['id'] ?? null;
        if (! is_string($userId) || $userId === '') {
            throw new AuthenticationException('The access actor is invalid.');
        }

        $user = DB::table('access_users')->where('id', $userId)->first();
        if ($user === null || ! $this->hasActiveOrganization($user)) {
            throw new AuthenticationException('The access actor is no longer valid.');
        }

        foreach (['company_id', 'branch_id', 'department_id', 'session_version'] as $field) {
            if (! array_key_exists($field, $actor) || (string) $actor[$field] !== (string) $user->{$field}) {
                throw new AuthenticationException('The access actor scope has changed.');
            }
        }

        return $this->presentUser($user, true) + ['permissions' => $this->permissionsForUser($user)];
    }

    private function hasActiveOrganization(stdClass $user): bool
    {
        return (bool) $user->active
            && DB::table('access_companies')->where('id', $user->company_id)->exists()
            && DB::table('access_branches')
                ->where('id', $user->branch_id)
                ->where('company_id', $user->company_id)
                ->where('active', true)
                ->exists()
            && DB::table('access_departments')
                ->where('id', $user->department_id)
                ->where('company_id', $user->company_id)
                ->where('branch_id', $user->branch_id)
                ->where('active', true)
                ->exists();
    }

    /** @return list<string> */
    private function permissionsForUser(stdClass $user): array
    {
        // Disclosure and delegation need explicit grants, including administrators.
        $granted = (bool) $user->is_admin
            ? array_fill_keys(array_values(array_diff(self::PERMISSIONS, ['contact.reveal', 'tasks.assign'])), true)
            : [];
        $roleIds = $this->effectiveRoleIdsForUser((string) $user->id, (string) $user->department_id);
        $rolePermissions = $roleIds === [] ? [] : DB::table('access_role_permissions as permissions')
            ->join('access_roles as roles', 'roles.id', '=', 'permissions.role_id')
            ->where('roles.company_id', $user->company_id)
            ->whereIn('roles.id', $roleIds)
            ->pluck('permissions.permission');

        foreach ($rolePermissions as $permission) {
            if (in_array($permission, self::PERMISSIONS, true)) {
                $granted[$permission] = true;
            }
        }

        foreach (DB::table('access_user_permissions')->where('user_id', $user->id)->get(['permission', 'allowed']) as $override) {
            if (! in_array($override->permission, self::PERMISSIONS, true)) {
                continue;
            }
            if ((bool) $override->allowed) {
                $granted[$override->permission] = true;
            } else {
                unset($granted[$override->permission]);
            }
        }

        return array_values(array_filter(self::PERMISSIONS, fn (string $permission): bool => isset($granted[$permission])));
    }

    /** @return list<string> */
    private function permissionsForRole(string $roleId): array
    {
        $granted = DB::table('access_role_permissions')->where('role_id', $roleId)->pluck('permission')->all();

        return array_values(array_filter(
            self::PERMISSIONS,
            fn (string $permission): bool => in_array($permission, $granted, true),
        ));
    }

    /** @return list<string> */
    private function roleIdsForUser(string $userId): array
    {
        return array_values(DB::table('access_user_roles')
            ->where('user_id', $userId)
            ->orderBy('role_id')
            ->pluck('role_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());
    }

    /** @return list<string> */
    private function roleIdsForDepartment(string $departmentId): array
    {
        return array_values(DB::table('access_department_roles')
            ->where('department_id', $departmentId)
            ->orderBy('role_id')
            ->pluck('role_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());
    }

    /** @return list<string> */
    private function effectiveRoleIdsForUser(string $userId, string $departmentId): array
    {
        $roleIds = array_values(array_unique(array_merge(
            $this->roleIdsForUser($userId),
            $this->roleIdsForDepartment($departmentId),
        )));
        sort($roleIds);

        return $roleIds;
    }

    /** @param list<string> $roleIds
     * @return list<string>
     */
    private function permissionsForRoles(array $roleIds): array
    {
        $granted = $roleIds === [] ? [] : DB::table('access_role_permissions')
            ->whereIn('role_id', $roleIds)
            ->pluck('permission')
            ->all();

        return array_values(array_filter(
            self::PERMISSIONS,
            fn (string $permission): bool => in_array($permission, $granted, true),
        ));
    }

    /** @param array<string, mixed> $actor */
    private function assertUserScope(array $actor, string $branchId, string $departmentId): void
    {
        $branch = DB::table('access_branches')
            ->where('id', $branchId)
            ->where('company_id', $actor['company_id'])
            ->where('active', true)
            ->first();
        if ($branch === null) {
            $this->invalid('branch_id', 'Select an active branch in this company.');
        }
        if (! $actor['is_admin'] && $branchId !== $actor['branch_id']) {
            throw new AccessDeniedHttpException('Users can only be assigned within the actor branch.');
        }

        $departmentExists = DB::table('access_departments')
            ->where('id', $departmentId)
            ->where('company_id', $actor['company_id'])
            ->where('branch_id', $branchId)
            ->where('active', true)
            ->exists();
        if (! $departmentExists) {
            $this->invalid('department_id', 'Select an active department in the selected branch.');
        }
    }

    /** @param array<string, mixed> $actor */
    private function assertRoleEditable(array $actor, string $roleId): void
    {
        $assignments = DB::table('access_user_roles as assigned')
            ->join('access_users as users', 'users.id', '=', 'assigned.user_id')
            ->where('assigned.role_id', $roleId)
            ->get(['users.id', 'users.company_id', 'users.branch_id', 'users.is_admin']);

        foreach ($assignments as $user) {
            if ($user->id === $actor['id']) {
                throw new AccessDeniedHttpException('Users cannot edit a role assigned to themselves.');
            }
            if ((string) $user->company_id !== $actor['company_id']
                || (! $actor['is_admin'] && ((bool) $user->is_admin || $user->branch_id !== $actor['branch_id']))) {
                throw new AccessDeniedHttpException('The role has assignees outside the actor branch.');
            }
        }

        $departments = DB::table('access_department_roles as assigned')
            ->join('access_departments as departments', function ($join): void {
                $join->on('departments.id', '=', 'assigned.department_id')
                    ->on('departments.company_id', '=', 'assigned.company_id')
                    ->on('departments.branch_id', '=', 'assigned.branch_id');
            })
            ->where('assigned.role_id', $roleId)
            ->get(['departments.id', 'departments.company_id', 'departments.branch_id']);

        foreach ($departments as $department) {
            if ((string) $department->id === (string) $actor['department_id']) {
                throw new AccessDeniedHttpException('Users cannot edit a role assigned to their own department.');
            }
            if ((string) $department->company_id !== (string) $actor['company_id']
                || (! $actor['is_admin'] && (string) $department->branch_id !== (string) $actor['branch_id'])) {
                throw new AccessDeniedHttpException('The role has departments outside the actor branch.');
            }
        }
    }

    private function roleIsWithinBranch(string $roleId, string $companyId, string $branchId): bool
    {
        if (DB::table('access_user_roles as assigned')
            ->join('access_users as users', 'users.id', '=', 'assigned.user_id')
            ->where('assigned.role_id', $roleId)
            ->where(function ($query) use ($companyId, $branchId): void {
                $query->where('users.company_id', '<>', $companyId)
                    ->orWhere('users.branch_id', '<>', $branchId)
                    ->orWhere('users.is_admin', true);
            })
            ->exists()) {
            return false;
        }

        return ! DB::table('access_department_roles')
            ->where('role_id', $roleId)
            ->where(function ($query) use ($companyId, $branchId): void {
                $query->where('company_id', '<>', $companyId)
                    ->orWhere('branch_id', '<>', $branchId);
            })
            ->exists();
    }

    /** @param list<string> $permissions */
    private function replaceRolePermissions(string $roleId, array $permissions): void
    {
        DB::table('access_role_permissions')->where('role_id', $roleId)->delete();
        if ($permissions !== []) {
            DB::table('access_role_permissions')->insert(array_map(
                fn (string $permission): array => ['role_id' => $roleId, 'permission' => $permission],
                $permissions,
            ));
        }
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $metadata
     */
    private function insertAudit(array $actor, string $action, string $subjectId, array $metadata): void
    {
        if ($action === '' || mb_strlen($action) > 100 || preg_match('/password|hash|token|secret|credential/i', $action) === 1) {
            $this->invalid('action', 'The audit action is invalid.');
        }
        if ($subjectId === '' || mb_strlen($subjectId) > 100) {
            $this->invalid('subject_id', 'The audit subject is invalid.');
        }

        $safeMetadata = $this->sanitizeMetadata($metadata);
        $safeMetadata['correlation_id'] ??= (string) Str::ulid();
        $safeMetadata['origin'] ??= 'web';
        $safeMetadata['outcome'] ??= 'success';
        $safeMetadata['approval_path'] ??= 'not_applicable';
        $safeMetadata['actor_branch_id'] = $actor['branch_id'];
        $safeMetadata['effective_role_ids'] = $actor['effective_role_ids'] ?? [];
        $safeMetadata['actor_is_admin'] = (bool) $actor['is_admin'];

        DB::table('access_audit')->insert([
            'id' => (string) Str::ulid(),
            'company_id' => $actor['company_id'],
            'actor_id' => $actor['id'],
            'action' => $action,
            'subject_id' => $subjectId,
            'metadata' => json_encode($safeMetadata, JSON_THROW_ON_ERROR),
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    /** @param array<mixed> $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        $safe = [];
        foreach ($metadata as $key => $value) {
            if (is_string($key) && preg_match('/password|hash|token|secret|credential|authorization/i', $key) === 1) {
                continue;
            }
            if (is_array($value)) {
                $value = $this->sanitizeMetadata($value);
            } elseif (! is_scalar($value) && $value !== null) {
                continue;
            }
            $safe[$key] = $value;
        }

        return $safe;
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

    /** @param array<string, mixed> $data
     * @param  list<string>  $allowed
     */
    private function onlyKeys(array $data, array $allowed): void
    {
        foreach (array_keys($data) as $key) {
            if (! in_array($key, $allowed, true)) {
                $this->invalid($key, 'The submitted field is not supported.');
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $field, int $maxLength): string
    {
        $value = $data[$field] ?? null;
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maxLength) {
            $this->invalid($field, 'A valid value is required.');
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    private function versionInput(array $data, ?int $default): int
    {
        $version = array_key_exists('version', $data) ? $this->integerValue($data['version']) : $default;
        if ($version === null || $version < 0 || ($default === 0 && $version !== 0)) {
            $this->invalid('version', 'A valid version is required.');
        }

        return $version;
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function booleanInput(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === '0') {
            return false;
        }
        if ($value === 1 || $value === '1') {
            return true;
        }

        $this->invalid($field, 'A valid boolean value is required.');
    }

    /** @return list<string> */
    private function permissionList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $this->invalid('permissions', 'Permissions must be a list.');
        }

        $permissions = [];
        foreach ($value as $permission) {
            if (! is_string($permission) || ! in_array($permission, self::PERMISSIONS, true)) {
                $this->invalid('permissions', 'One or more permissions are invalid.');
            }
            if (in_array($permission, $permissions, true)) {
                $this->invalid('permissions', 'Permissions cannot be duplicated.');
            }
            $permissions[] = $permission;
        }

        return array_values(array_filter(self::PERMISSIONS, fn (string $permission): bool => in_array($permission, $permissions, true)));
    }

    /** @return list<string> */
    private function idList(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $this->invalid($field, 'The selected IDs must be a list.');
        }

        $ids = [];
        foreach ($value as $id) {
            if (! is_string($id) || $id === '' || mb_strlen($id) > 64 || in_array($id, $ids, true)) {
                $this->invalid($field, 'The selected IDs are invalid.');
            }
            $ids[] = $id;
        }

        return $ids;
    }

    /** @return array<string, bool> */
    private function permissionOverrides(mixed $value): array
    {
        if (! is_array($value)) {
            $this->invalid('permissions', 'Permission overrides must be an object.');
        }

        $overrides = [];
        foreach ($value as $permission => $allowed) {
            if (! is_string($permission) || ! in_array($permission, self::PERMISSIONS, true)) {
                $this->invalid('permissions', 'One or more permissions are invalid.');
            }
            if (! is_bool($allowed) && ! in_array($allowed, [0, 1, '0', '1'], true)) {
                $this->invalid('permissions', 'Each permission override must be boolean.');
            }
            $overrides[$permission] = $allowed === true || $allowed === 1 || $allowed === '1';
        }

        return $overrides;
    }

    private function assertVersion(int $expected, int $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new ConflictHttpException($message);
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /** @return array<string, mixed> */
    private function presentUser(stdClass $user, bool $includeSessionVersion = false): array
    {
        $result = [
            'id' => (string) $user->id,
            'company_id' => (string) $user->company_id,
            'branch_id' => (string) $user->branch_id,
            'department_id' => (string) $user->department_id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'active' => (bool) $user->active,
            'is_admin' => (bool) $user->is_admin,
            'version' => (int) $user->version,
        ];
        if ($includeSessionVersion) {
            $result['session_version'] = (int) $user->session_version;
        }

        return $result;
    }

    /** @param list<string> $permissions
     * @return array<string, mixed>
     */
    private function presentRole(stdClass $role, array $permissions): array
    {
        return [
            'id' => (string) $role->id,
            'company_id' => (string) $role->company_id,
            'name' => (string) $role->name,
            'permissions' => $permissions,
            'version' => (int) $role->version,
        ];
    }
}

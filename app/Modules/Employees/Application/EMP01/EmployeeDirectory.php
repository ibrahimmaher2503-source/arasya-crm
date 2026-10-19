<?php

declare(strict_types=1);

namespace App\Modules\Employees\Application\EMP01;

use App\Modules\Employees\Application\EMP02\AttributeWorker;
use App\Modules\Employees\Domain\EMP01\EmployeeProfile;
use App\Modules\Employees\Domain\EMP01\EmployeeStatus;
use App\Modules\Identity\Application\SEC04\AccessControl;
use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use App\Modules\Tasks\Application\TASK01\TaskCycle;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class EmployeeDirectory
{
    public function __construct(
        private AccessControl $access,
        private TaskCycle $tasks,
        private RuntimeIdentity $identity,
    ) {}

    /** @return list<array<string, mixed>> */
    public function listing(Request $request): array
    {
        $actor = $this->actor($request, 'employees.read');

        return $this->presentRows(array_values($this->visibleQuery($actor)->orderBy('code')->orderBy('id')->limit(50)->get()->all()), $this->hasPermission($actor, 'employee.private'), $actor);
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function browse(Request $request, bool $workersOnly = false, ?string $managerId = null): LengthAwarePaginator
    {
        $actor = $this->actor($request, 'employees.read');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'branch_id' => ['nullable', 'string', 'size:26'],
            'department_id' => ['nullable', 'string', 'size:26'],
            'manager_id' => ['nullable', 'string', 'size:26'],
            'status' => ['nullable', 'in:active,on_leave,suspended,terminated'],
            'sort' => ['nullable', 'in:code,name,hire_date,status'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $query = $this->visibleQuery($actor);
        if ($workersOnly) {
            if ($this->hasPermission($actor, 'employees.manage_all')) {
                $query->whereNull('user_id');
            } else {
                $query->where('manager_id', $this->supervisorProfile($actor)->id ?? '');
            }
        }
        if ($managerId !== null) {
            $query->where('manager_id', $managerId);
        }
        foreach (['branch_id', 'department_id', 'manager_id', 'status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn (Builder $search) => $search->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('role', 'like', $term));
        }
        $page = $query->orderBy($filters['sort'] ?? 'code', $filters['direction'] ?? 'asc')->orderBy('id')->paginate((int) ($filters['per_page'] ?? 10));
        $page->setCollection(collect($this->presentRows(array_values($page->items()), $this->hasPermission($actor, 'employee.private'), $actor)));

        return $page->withQueryString()->appends(['per_page' => $filters['per_page'] ?? 10]);
    }

    /** @return array<string, int> */
    public function counts(Request $request, bool $workersOnly = false): array
    {
        $actor = $this->actor($request, 'employees.read');
        $query = $this->visibleQuery($actor);
        if ($workersOnly) {
            if ($this->hasPermission($actor, 'employees.manage_all')) {
                $query->whereNull('user_id');
            } else {
                $query->where('manager_id', $this->supervisorProfile($actor)->id ?? '');
            }
        }

        return ['total' => (clone $query)->count(), 'active' => (clone $query)->where('status', 'active')->count(), 'without_login' => (clone $query)->whereNull('user_id')->count(), 'suspended' => (clone $query)->where('status', 'suspended')->count()];
    }

    /** @return array<string, string>|null */
    public function workerDefaults(Request $request): ?array
    {
        $actor = $this->actor($request, 'employees.write');
        $manager = $this->supervisorProfile($actor);

        return $manager === null ? null : ['branch_id' => (string) $manager->branch_id, 'department_id' => (string) $manager->department_id, 'manager_id' => (string) $manager->id];
    }

    /** @param array<string, mixed>|null $employee
     * @return array<string, mixed>
     */
    public function options(Request $request, ?array $employee = null): array
    {
        $actor = $this->actor($request, 'employees.read');
        $all = $this->hasPermission($actor, 'employees.manage_all');
        $company = $this->actorString($actor, 'company_id');
        $queries = [
            'branches' => DB::table('access_branches')->where('company_id', $company),
            'departments' => DB::table('access_departments')->where('company_id', $company),
            'managers' => $this->visibleQuery($actor),
            'users' => DB::table('access_users')->where('company_id', $company),
        ];
        if (! $all) {
            $queries['branches']->where('id', $actor['branch_id']);
            $queries['departments']->where('id', $actor['department_id']);
            $queries['users']->where('id', $actor['id']);
        } elseif (! $actor['is_admin']) {
            $queries['users']->where('branch_id', $actor['branch_id']);
        }
        if (! $this->hasPermission($actor, 'users.read')) {
            $queries['users']->whereRaw('1 = 0');
        }
        if ($employee !== null && ! ($employee['can_assign_account'] ?? false)) {
            $queries['users']->whereRaw('1 = 0');
        }
        $result = ['optionSources' => []];
        $selectedFields = ['branches' => 'branch_id', 'departments' => 'department_id', 'managers' => 'manager_id', 'users' => 'user_id'];
        foreach ($queries as $name => $query) {
            $key = rtrim($name, 's');
            if ($name === 'branches') {
                $key = 'branch';
            }
            $search = $request->query($key.'_q', '');
            if (! is_string($search) || mb_strlen($search) > 160) {
                $this->invalid($key.'_q', 'Search must be a string of at most 160 characters.');
            }
            $request->validate([$key.'_page' => ['nullable', 'integer', 'min:1', 'max:1000000']]);
            $selectedValue = $request->old($selectedFields[$name], $employee[$selectedFields[$name]] ?? $request->query($selectedFields[$name], ''));
            $selected = is_string($selectedValue) ? $selectedValue : '';
            $current = $selected === '' ? null : (clone $query)->where('id', $selected)->first(['id', 'name']);
            if (in_array($name, ['branches', 'departments', 'users'], true)) {
                $query->where('active', true);
            } else {
                $query->where('status', 'active');
                if (isset($employee['id'])) {
                    $query->where('id', '!=', $employee['id']);
                }
            }
            if ($name === 'users') {
                $employeeId = $employee['id'] ?? null;
                $query->whereNotIn('id', DB::table('employee_profiles')->whereNotNull('user_id')->when($employeeId !== null, fn (Builder $profiles) => $profiles->where('id', '!=', $employeeId))->select('user_id'));
            }
            $query->where('name', 'like', '%'.$search.'%');
            $page = $query->orderBy('name')->orderBy('id')->paginate(25, ['id', 'name'], $key.'_page')->withQueryString();
            $options = array_map(static fn (stdClass $row): array => (array) $row, $page->items());
            if ($current !== null && ! in_array($selected, array_column($options, 'id'), true)) {
                $options[] = (array) $current;
            }
            $result[$name] = $options;
            $labels = ['branches' => ['الفروع', 'Branches'], 'departments' => ['الأقسام', 'Departments'], 'managers' => ['المشرفون', 'Supervisors'], 'users' => ['حسابات الدخول', 'Login accounts']];
            $result['optionSources'][] = ['label' => $labels[$name][$request->query('locale') === 'en' ? 1 : 0], 'query' => $key.'_q', 'paginator' => $page];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function find(Request $request, string $id): array
    {
        $actor = $this->actor($request, 'employees.read');
        $row = $this->visibleQuery($actor)->where('id', $id)->first();

        if ($row === null) {
            throw new NotFoundHttpException('Employee not found.');
        }

        return $this->presentRows([$row], $this->hasPermission($actor, 'employee.private'), $actor)[0];
    }

    /** @return array<string, mixed> */
    public function history(Request $request, string $id): array
    {
        $profile = $this->find($request, $id);
        $actor = $this->actor($request, 'employees.read');
        $request->validate(['evidence_page' => ['nullable', 'integer', 'min:1', 'max:1000000'], 'audit_page' => ['nullable', 'integer', 'min:1', 'max:1000000']]);
        $evidenceQuery = DB::table('employee_evidence')->where('company_id', $profile['company_id'])->where('employee_id', $id);
        if (! $this->hasPermission($actor, 'tasks.read')) {
            $evidenceQuery->whereRaw('1 = 0');
        }
        $evidence = $evidenceQuery->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(25, ['id', 'job_id', 'actor_id', 'note', 'created_at'], 'evidence_page')->withQueryString();
        $visibleEvidence = [];
        $taskVisibility = [];
        $taskActor = null;
        foreach ($evidence->items() as $row) {
            $jobId = (string) $row->job_id;
            try {
                if (! array_key_exists($jobId, $taskVisibility)) {
                    $taskActor ??= $this->identity->fromRequest($request);
                    $this->tasks->find($taskActor, $jobId, withDetails: false);
                    $taskVisibility[$jobId] = true;
                }
                if ($taskVisibility[$jobId]) {
                    $visibleEvidence[] = (array) $row;
                }
            } catch (NotFoundHttpException|AccessDeniedHttpException) {
                // Employee visibility never grants access to another task's private evidence.
                $taskVisibility[$jobId] = false;
            }
        }
        $auditQuery = DB::table('employee_audit')->where('company_id', $profile['company_id'])->where('employee_id', $id);
        if (! $this->hasPermission($actor, 'audit.read')) {
            $auditQuery->whereRaw('1 = 0');
        }
        $audit = $auditQuery->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(25, ['action', 'actor_id', 'created_at'], 'audit_page')->withQueryString();
        $ar = $request->query('locale') !== 'en';
        $links = static fn ($page): array => [['label' => $ar ? 'السابق' : 'Previous', 'url' => $page->previousPageUrl()], ['label' => $ar ? 'التالي' : 'Next', 'url' => $page->nextPageUrl()]];

        return ['evidence' => $visibleEvidence, 'audit' => array_map(static fn (stdClass $row): array => (array) $row, $audit->items()), 'evidenceLinks' => $links($evidence), 'auditLinks' => $links($audit)];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function save(Request $request, array $data, ?string $id = null): array
    {
        $initialActor = $this->access->actor($request);
        $companyId = $this->actorString($initialActor, 'company_id');

        try {
            return DB::transaction(function () use ($request, $data, $id, $initialActor, $companyId): array {
                $this->lockCompany($companyId);
                $actor = $this->reauthenticate($request, $initialActor, $companyId);
                $this->access->requirePermission($actor, 'employees.write');
                $manageAll = $this->hasPermission($actor, 'employees.manage_all');
                $privateAllowed = $this->hasPermission($actor, 'employee.private');

                $row = null;
                if ($id !== null) {
                    $row = $this->visibleQuery($actor, $manageAll)->where('id', $id)->lockForUpdate()->first();
                    if ($row === null) {
                        if (! $manageAll && DB::table('employee_profiles')->where('id', $id)->where('company_id', $companyId)->exists()) {
                            throw new AccessDeniedHttpException('Employee access denied.');
                        }
                        throw new NotFoundHttpException('Employee not found.');
                    }
                } elseif (! $manageAll) {
                    $manager = $this->supervisorProfile($actor);
                    if ($manager === null || ($data['manager_id'] ?? null) !== (string) $manager->id
                        || ($data['branch_id'] ?? null) !== (string) $manager->branch_id
                        || ($data['department_id'] ?? null) !== (string) $manager->department_id
                        || ! empty($data['user_id']) || ($data['status'] ?? null) !== 'active') {
                        throw new AccessDeniedHttpException('Supervisors can only create active non-login workers assigned to themselves in their own scope.');
                    }
                }

                $values = $this->validatedValues($data, $row);
                if (array_intersect(['phone', 'email', 'private_notes'], array_keys($data)) !== [] && ! $privateAllowed) {
                    $this->access->requirePermission($actor, 'employee.private');
                }

                $this->validateReferences($companyId, $values, $row);
                $this->assertUniqueValues($companyId, $values['code'], $values['user_id'], $id);
                if (($row->user_id ?? null) !== $values['user_id']) {
                    if (! $actor['is_admin'] && $values['branch_id'] !== $actor['branch_id']) {
                        throw new AccessDeniedHttpException('Account assignments outside your user-management scope are read-only.');
                    }
                }

                $employeeId = $id ?? (string) Str::ulid();
                $this->assertNoReportingCycle($companyId, $employeeId, $values['manager_id']);

                if ($row === null) {
                    $now = now();
                    DB::table('employee_profiles')->insert([
                        'id' => $employeeId,
                        'company_id' => $companyId,
                        ...$values,
                        'version' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $created = DB::table('employee_profiles')->where('id', $employeeId)->first();
                    if ($created === null) {
                        throw new ConflictHttpException('Employee profile could not be read after creation.');
                    }
                    $this->audit($actor, $employeeId, 'employee.created', [
                        ...$this->changes(null, $values, array_keys($values)),
                        'fields' => array_keys($values),
                        'employee_id' => $employeeId,
                        'status' => $values['status'],
                        'version' => 0,
                    ]);

                    return $this->presentRows([$created], $privateAllowed, $actor)[0];
                }

                $version = $this->requiredVersion($data);
                if ((int) $row->version !== $version) {
                    throw new ConflictHttpException('Employee version is stale.');
                }

                $changedFields = $this->changedFields($row, $values);
                $supervisorFields = $privateAllowed ? ['name', 'role', 'phone', 'email', 'private_notes'] : ['name', 'role'];
                if (! $manageAll && array_diff($changedFields, $supervisorFields) !== []) {
                    throw new AccessDeniedHttpException('Supervisors may update employee names and roles only.');
                }
                if ($changedFields === []) {
                    return $this->presentRows([$row], $privateAllowed, $actor)[0];
                }

                $statusIntent = $this->validateStatusTransition($row, $values['status'], $actor);
                $nextVersion = $version + 1;
                DB::table('employee_profiles')->where('id', $employeeId)->update([
                    ...$values,
                    'version' => $nextVersion,
                    'updated_at' => now(),
                ]);
                $updated = DB::table('employee_profiles')->where('id', $employeeId)->first();
                if ($updated === null) {
                    throw new ConflictHttpException('Employee profile could not be read after update.');
                }
                $this->audit($actor, $employeeId, 'employee.updated', [
                    ...$this->changes($row, $values, $changedFields),
                    'outbox_intent' => $statusIntent,
                    'fields' => $changedFields,
                    'employee_id' => $employeeId,
                    'before_status' => (string) $row->status,
                    'after_status' => $values['status'],
                    'before_version' => $version,
                    'after_version' => $nextVersion,
                ]);

                return $this->presentRows([$updated], $privateAllowed, $actor)[0];
            }, 3);
        } catch (QueryException $exception) {
            if ($id !== null && isset($data['user_id']) && is_string($data['user_id']) && $data['user_id'] !== ''
                && DB::table('employee_profiles')->where('user_id', $data['user_id'])->where('id', '!=', $id)->exists()) {
                $this->invalid('user_id', 'The access account is already linked to an employee.');
            }
            if (isset($data['code']) && is_string($data['code'])
                && DB::table('employee_profiles')->where('company_id', $companyId)->where('code', trim($data['code']))->where('id', '!=', $id ?? '')->exists()) {
                $this->invalid('code', 'The employee code is already in use for this company.');
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function recordEvidence(Request $request, string $employeeId, array $data): array
    {
        $initialActor = $this->access->actor($request);
        $companyId = $this->actorString($initialActor, 'company_id');

        return DB::transaction(function () use ($request, $employeeId, $data, $initialActor, $companyId): array {
            $this->lockCompany($companyId);
            $actor = $this->reauthenticate($request, $initialActor, $companyId);
            $this->access->requirePermission($actor, 'employees.evidence.write');
            $employee = $this->visibleQuery($actor)->where('id', $employeeId)->lockForUpdate()->first();
            if ($employee === null) {
                throw new NotFoundHttpException('Employee not found.');
            }
            if (in_array($employee->status, [EmployeeStatus::Terminated->value, EmployeeStatus::Suspended->value], true)) {
                throw new ConflictHttpException('Evidence cannot be recorded for an inactive worker.');
            }

            $this->onlyKeys($data, ['job_id', 'note']);
            $jobId = $this->requiredString($data, 'job_id', 26);
            $note = $this->requiredString($data, 'note', 4000);
            $task = $this->tasks->find($this->identity->fromRequest($request), $jobId, withDetails: false);
            if ((string) ($task['id'] ?? '') !== $jobId) {
                throw new NotFoundHttpException('Task not found.');
            }

            $attribution = (new AttributeWorker)->execute(
                $companyId,
                $employeeId,
                $jobId,
                $this->actorString($actor, 'id'),
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
            );
            $evidenceId = (string) Str::ulid();
            DB::table('employee_evidence')->insert([
                'id' => $evidenceId,
                'company_id' => $attribution->entityId,
                'employee_id' => $attribution->workerId,
                'job_id' => $attribution->jobId,
                'actor_id' => $attribution->actorId,
                'note' => $note,
                'created_at' => $attribution->occurredAtUtc,
            ]);
            $this->audit($actor, $employeeId, 'employee.evidence.recorded', [
                'fields' => ['job_id', 'note'],
                'employee_id' => $employeeId,
                'job_id' => $jobId,
                'version' => (int) $employee->version,
            ]);

            return ['id' => $evidenceId, 'company_id' => $companyId, 'employee_id' => $employeeId, 'job_id' => $jobId, 'actor_id' => $attribution->actorId, 'note' => $note];
        }, 3);
    }

    /** @param list<stdClass> $rows
     * @param  array<string, mixed>  $actor
     * @return list<array<string, mixed>>
     */
    private function presentRows(array $rows, bool $includePrivate, array $actor): array
    {
        if ($rows === []) {
            return [];
        }

        $profiles = array_map(fn (stdClass $row): EmployeeProfile => $this->domainProfile($row), $rows);
        $companyId = (string) $rows[0]->company_id;
        $branchNames = DB::table('access_branches')->where('company_id', $companyId)->whereIn('id', array_column($rows, 'branch_id'))->pluck('name', 'id')->all();
        $departmentNames = DB::table('access_departments')->where('company_id', $companyId)->whereIn('id', array_column($rows, 'department_id'))->pluck('name', 'id')->all();
        $managerNames = DB::table('employee_profiles')->where('company_id', $companyId)->whereIn('id', array_filter(array_column($rows, 'manager_id')))->pluck('name', 'id')->all();
        $accountVisible = $this->hasPermission($actor, 'users.read');
        $accountQuery = DB::table('access_users')->where('company_id', $companyId)->whereIn('id', array_filter(array_column($rows, 'user_id')));
        if (! $actor['is_admin']) {
            $accountQuery->where('branch_id', $actor['branch_id']);
        }
        $accountNames = $accountVisible ? $accountQuery->pluck('name', 'id')->all() : [];
        $ids = array_map(static fn (stdClass $row): string => (string) $row->id, $rows);
        $projection = (new ReadScopedEmployeeProfiles)->query((string) $rows[0]->company_id, $profiles, $ids, $includePrivate);
        $byId = [];
        foreach ($projection as $profile) {
            $byId[$profile['employee_id']] = $profile;
        }

        return array_map(function (stdClass $row) use ($byId, $includePrivate, $branchNames, $departmentNames, $managerNames, $accountNames, $accountVisible, $actor): array {
            $profile = $byId[(string) $row->id];
            $result = [
                'id' => (string) $profile['employee_id'],
                'company_id' => (string) $profile['entity_id'],
                'branch_id' => (string) $row->branch_id,
                'branch_name' => $branchNames[(string) $row->branch_id] ?? '',
                'department_id' => (string) $profile['department_id'],
                'department_name' => $departmentNames[(string) $row->department_id] ?? '',
                'manager_id' => $profile['manager_id'],
                'manager_name' => $managerNames[(string) $row->manager_id] ?? null,
                'user_id' => $row->user_id !== null && array_key_exists((string) $row->user_id, $accountNames) ? (string) $row->user_id : null,
                'account_visible' => $accountVisible && ($row->user_id === null || array_key_exists((string) $row->user_id, $accountNames)),
                'can_assign_account' => $accountVisible && ($actor['is_admin'] || (string) $row->branch_id === $actor['branch_id']),
                'code' => (string) $row->code,
                'name' => (string) $profile['name'],
                'role' => (string) $profile['role'],
                'hire_date' => substr((string) $profile['hire_date_utc'], 0, 10),
                'status' => (string) $profile['status'],
                'version' => (int) $row->version,
            ];
            if ($result['account_visible']) {
                $result['user_name'] = $row->user_id === null ? null : $accountNames[(string) $row->user_id];
            }
            if ($includePrivate) {
                $result += [
                    'phone' => $row->phone,
                    'email' => $row->email,
                    'private_notes' => $profile['private_notes'],
                ];
            }

            return $result;
        }, $rows);
    }

    private function domainProfile(stdClass $row): EmployeeProfile
    {
        return new EmployeeProfile(
            (string) $row->company_id,
            (string) $row->id,
            (string) $row->name,
            (string) $row->role,
            (string) $row->department_id,
            $row->manager_id === null ? null : (string) $row->manager_id,
            new DateTimeImmutable((string) $row->hire_date, new DateTimeZone('UTC')),
            EmployeeStatus::from((string) $row->status),
            privateNotes: $row->private_notes === null ? null : (string) $row->private_notes,
        );
    }

    /** @return array<string, mixed> */
    private function actor(Request $request, string $permission): array
    {
        $actor = $this->access->actor($request);
        $this->access->requirePermission($actor, $permission);

        return $actor;
    }

    /** @param array<string, mixed> $initialActor
     * @return array<string, mixed>
     */
    private function reauthenticate(Request $request, array $initialActor, string $companyId): array
    {
        $actor = $this->access->actor($request);
        if ($this->actorString($actor, 'id') !== $this->actorString($initialActor, 'id')
            || $this->actorString($actor, 'company_id') !== $companyId) {
            throw new AuthenticationException('The access actor changed during the operation.');
        }

        return $actor;
    }

    private function lockCompany(string $companyId): void
    {
        if (DB::table('access_companies')->where('id', $companyId)->lockForUpdate()->first() === null) {
            throw new AuthenticationException('The access actor company is no longer active.');
        }
    }

    /** @param array<string, mixed> $actor */
    private function visibleQuery(array $actor, ?bool $manageAll = null): Builder
    {
        $companyId = $this->actorString($actor, 'company_id');
        $manageAll ??= $this->hasPermission($actor, 'employees.manage_all');
        $query = DB::table('employee_profiles')->where('company_id', $companyId);
        if ($manageAll) {
            return $query;
        }

        $actorId = $this->actorString($actor, 'id');
        $managerId = DB::table('employee_profiles')
            ->where('company_id', $companyId)
            ->where('user_id', $actorId)
            ->value('id');
        $query->where('branch_id', $this->actorString($actor, 'branch_id'))
            ->where('department_id', $this->actorString($actor, 'department_id'))
            ->where(function ($scope) use ($actorId, $managerId): void {
                $scope->where('user_id', $actorId);
                if ($managerId !== null) {
                    $scope->orWhere('manager_id', (string) $managerId);
                }
            });

        return $query;
    }

    /** @param array<string, mixed> $actor */
    private function supervisorProfile(array $actor): ?stdClass
    {
        return DB::table('employee_profiles')->where('company_id', $this->actorString($actor, 'company_id'))
            ->where('user_id', $this->actorString($actor, 'id'))->where('branch_id', $this->actorString($actor, 'branch_id'))
            ->where('department_id', $this->actorString($actor, 'department_id'))->where('status', 'active')->first();
    }

    /** @param array<string, mixed> $actor */
    private function hasPermission(array $actor, string $permission): bool
    {
        return is_array($actor['permissions'] ?? null) && in_array($permission, $actor['permissions'], true);
    }

    /** @param array<string, mixed> $data
     * @return array{code: string, name: string, role: string, branch_id: string, department_id: string, manager_id: ?string, user_id: ?string, hire_date: string, status: string, phone: ?string, email: ?string, private_notes: ?string}
     */
    private function validatedValues(array $data, ?stdClass $before = null): array
    {
        $creating = $before === null;
        $this->onlyKeys($data, ['code', 'name', 'role', 'branch_id', 'department_id', 'manager_id', 'user_id', 'hire_date', 'status', 'phone', 'email', 'private_notes', 'version']);
        if ($before !== null) {
            $this->requiredVersion($data);
            $data = array_replace([
                'code' => (string) $before->code,
                'name' => (string) $before->name,
                'role' => (string) $before->role,
                'branch_id' => (string) $before->branch_id,
                'department_id' => (string) $before->department_id,
                'manager_id' => $before->manager_id === null ? null : (string) $before->manager_id,
                'user_id' => $before->user_id === null ? null : (string) $before->user_id,
                'hire_date' => (string) $before->hire_date,
                'status' => (string) $before->status,
                'phone' => $before->phone === null ? null : (string) $before->phone,
                'email' => $before->email === null ? null : (string) $before->email,
                'private_notes' => $before->private_notes === null ? null : (string) $before->private_notes,
                'version' => $data['version'],
            ], $data);
        }
        if ($creating && array_key_exists('version', $data) && $this->requiredVersion($data) !== 0) {
            $this->invalid('version', 'New employee profiles start at version zero.');
        }

        $status = $this->requiredString($data, 'status', 24);
        if (EmployeeStatus::tryFrom($status) === null || ($status === EmployeeStatus::Terminated->value && ($before->status ?? null) !== $status)) {
            $this->invalid('status', 'The selected employee status is invalid or requires the offboarding workflow.');
        }
        $hireDate = $this->requiredString($data, 'hire_date', 10);
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $hireDate);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $hireDate) {
            $this->invalid('hire_date', 'Hire date must use YYYY-MM-DD.');
        }

        $email = $this->nullableString($data, 'email', 254);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->invalid('email', 'A valid email address is required.');
        }

        return [
            'code' => $this->requiredString($data, 'code', 64),
            'name' => $this->requiredString($data, 'name', 200),
            'role' => $this->requiredString($data, 'role', 200),
            'branch_id' => $this->requiredString($data, 'branch_id', 26),
            'department_id' => $this->requiredString($data, 'department_id', 26),
            'manager_id' => $this->nullableString($data, 'manager_id', 26),
            'user_id' => $this->nullableString($data, 'user_id', 26),
            'hire_date' => $hireDate,
            'status' => $status,
            'phone' => $this->nullableString($data, 'phone', 64),
            'email' => $email,
            'private_notes' => $this->nullableString($data, 'private_notes', 4000),
        ];
    }

    /** @param array<string, mixed> $values */
    private function validateReferences(string $companyId, array $values, ?stdClass $before): void
    {
        $employeeId = $before === null ? null : (string) $before->id;
        $department = DB::table('access_departments')
            ->where('id', $values['department_id'])
            ->where('company_id', $companyId)
            ->where('branch_id', $values['branch_id'])
            ->first();
        if ($department === null) {
            $this->invalid('department_id', 'The department must belong to the selected branch and company.');
        }
        if ($before === null || (string) $before->branch_id !== $values['branch_id'] || (string) $before->department_id !== $values['department_id']) {
            $branchActive = DB::table('access_branches')->where('id', $values['branch_id'])->where('company_id', $companyId)->where('active', true)->exists();
            if (! $branchActive || ! (bool) $department->active) {
                $this->invalid('department_id', 'New assignments require an active branch and department.');
            }
        }

        if ($values['user_id'] !== null) {
            $user = DB::table('access_users')->where('id', $values['user_id'])->first();
            if ($user === null || (string) $user->company_id !== $companyId
                || (string) $user->branch_id !== $values['branch_id']
                || (string) $user->department_id !== $values['department_id']) {
                $this->invalid('user_id', 'The access account must belong to the selected company, branch and department.');
            }
            if (($before === null || $before->user_id !== $values['user_id']) && ! (bool) $user->active) {
                $this->invalid('user_id', 'A new account link requires an active account.');
            }
        }

        if ($values['manager_id'] !== null) {
            $manager = DB::table('employee_profiles')->where('id', $values['manager_id'])->first();
            if ($manager === null || ($employeeId !== null && (string) $manager->id === $employeeId)
                || (string) $manager->company_id !== $companyId
                || (string) $manager->branch_id !== $values['branch_id']
                || (string) $manager->department_id !== $values['department_id']) {
                $this->invalid('manager_id', 'The manager must be a different employee in the selected company, branch and department.');
            }
        }
    }

    private function assertUniqueValues(string $companyId, string $code, ?string $userId, ?string $employeeId): void
    {
        $codes = DB::table('employee_profiles')->where('company_id', $companyId)->where('code', $code);
        if ($employeeId !== null) {
            $codes->where('id', '!=', $employeeId);
        }
        if ($codes->exists()) {
            $this->invalid('code', 'The employee code is already in use for this company.');
        }

        if ($userId !== null) {
            $users = DB::table('employee_profiles')->where('user_id', $userId);
            if ($employeeId !== null) {
                $users->where('id', '!=', $employeeId);
            }
            if ($users->exists()) {
                $this->invalid('user_id', 'The access account is already linked to an employee.');
            }
        }
    }

    private function assertNoReportingCycle(string $companyId, string $employeeId, ?string $managerId): void
    {
        $visited = [];
        while ($managerId !== null) {
            if ($managerId === $employeeId || isset($visited[$managerId])) {
                $this->invalid('manager_id', 'Reporting lines cannot contain a cycle.');
            }
            $visited[$managerId] = true;
            $next = DB::table('employee_profiles')->where('id', $managerId)->where('company_id', $companyId)->value('manager_id');
            $managerId = $next === null ? null : (string) $next;
        }
    }

    /** @param array<string, mixed> $actor
     * @return array<string, mixed>|null
     */
    private function validateStatusTransition(stdClass $before, string $status, array $actor): ?array
    {
        if ((string) $before->status === $status) {
            return null;
        }

        $profile = $this->domainProfile($before);
        $transition = $profile->transitionStatus(EmployeeStatus::from($status), $this->actorString($actor, 'id'), (string) Str::ulid(), new DateTimeImmutable('now', new DateTimeZone('UTC')));
        if (! $transition->accepted || $transition->intent === null) {
            $this->invalid('status', 'The employee status transition is not allowed.');
        }

        // Retain the domain intent atomically; publication remains disabled until shared outbox integration.
        return [...$transition->intent->jsonSerialize(), 'aggregate_version' => (int) $before->version + 1];
    }

    /** @param array<string, mixed> $values
     * @return list<string>
     */
    private function changedFields(stdClass $before, array $values): array
    {
        return array_values(array_filter(array_keys($values), static fn (string $field): bool => ($before->{$field} === null ? null : (string) $before->{$field}) !== $values[$field]));
    }

    /** @param array<string, mixed> $values
     * @param  list<string>  $fields
     * @return array{changes: array<string, mixed>, private_changes: ?string}
     */
    private function changes(?stdClass $before, array $values, array $fields): array
    {
        $public = [];
        $private = [];
        foreach ($fields as $field) {
            $change = ['before' => $before?->{$field}, 'after' => $values[$field]];
            if (in_array($field, ['phone', 'email', 'private_notes'], true)) {
                $private[$field] = $change;
            } else {
                $public[$field] = $change;
            }
        }

        return ['changes' => $public, 'private_changes' => $private === [] ? null : Crypt::encryptString(json_encode($private, JSON_THROW_ON_ERROR))];
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $metadata
     */
    private function audit(array $actor, string $employeeId, string $action, array $metadata): void
    {
        $id = (string) Str::ulid();
        $metadata += [
            'correlation_id' => (string) Str::uuid(),
            'origin' => 'web',
            'outcome' => 'accepted',
            'approval_path' => [],
            'effective_permissions' => $actor['permissions'],
        ];
        DB::table('employee_audit')->insert([
            'id' => $id,
            'company_id' => $this->actorString($actor, 'company_id'),
            'employee_id' => $employeeId,
            'actor_id' => $this->actorString($actor, 'id'),
            'action' => $action,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
        $canonical = array_intersect_key($metadata, array_flip(['fields', 'employee_id', 'job_id', 'correlation_id', 'origin', 'outcome', 'approval_path']));
        $this->access->record($actor, $action, $employeeId, [...$canonical, 'employee_audit_id' => $id]);
    }

    /** @param array<string, mixed> $data
     * @param  list<string>  $allowed
     */
    private function onlyKeys(array $data, array $allowed): void
    {
        $unknown = array_values(array_diff(array_map('strval', array_keys($data)), $allowed));
        if ($unknown !== []) {
            $this->invalid($unknown[0], 'This field is not accepted.');
        }
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $key, int $max): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $max) {
            $this->invalid($key, 'A non-empty value of the allowed length is required.');
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    private function nullableString(array $data, string $key, int $max): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (! is_string($value) || mb_strlen(trim($value)) > $max) {
            $this->invalid($key, 'The value exceeds the allowed length.');
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    private function requiredVersion(array $data): int
    {
        $value = $data['version'] ?? null;
        if (is_int($value)) {
            return $value >= 0 ? $value : $this->invalid('version', 'Version must be a non-negative integer.');
        }
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value >= 0) {
            return (int) $value;
        }

        return $this->invalid('version', 'Version must be a non-negative integer.');
    }

    /** @param array<string, mixed> $actor */
    private function actorString(array $actor, string $key): string
    {
        $value = $actor[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new AuthenticationException('The access actor is invalid.');
        }

        return $value;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

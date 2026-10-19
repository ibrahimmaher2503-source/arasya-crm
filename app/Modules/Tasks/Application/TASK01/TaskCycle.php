<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Application\TASK01;

use App\Modules\Identity\Application\SEC01\AuthorizeScopedRecord;
use App\Modules\Identity\Domain\SEC01\ActorAccess;
use App\Modules\Identity\Domain\SEC01\ScopedRecord;
use App\Modules\Tasks\Domain\TASK01\TaskEvidence;
use App\Modules\Tasks\Domain\TASK01\TaskRecord;
use App\Modules\Tasks\Domain\TASK01\TaskStatus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TaskCycle
{
    public function __construct(
        private ManageTasks $manageTasks,
        private AuthorizeScopedRecord $authorizeScopedRecord,
    ) {}

    public function assertAssistantAccess(ActorAccess $actor): void
    {
        $this->requirePermission($actor, 'tasks.assist');
        $this->requirePermission($actor, 'tasks.write');
    }

    /** @return list<array<string, mixed>> */
    public function list(ActorAccess $actor): array
    {
        $rows = $this->scopedTaskQuery($actor, 'tasks.read')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return array_values(array_map(
            fn (stdClass $row): array => $this->presentTask($row),
            array_filter($rows->all(), fn (stdClass $row): bool => $this->canAccess($actor, 'tasks.read', $row)),
        ));
    }

    /** @param array<string, mixed> $filters
     * @return array{tasks: LengthAwarePaginator<int|string, array<string, mixed>>, summary: array<string, int|null>, week: array<string, int|null>, today: list<array<string, mixed>>, upcoming: list<array<string, mixed>>, evidence: list<array<string, mixed>>}
     */
    public function workspace(ActorAccess $actor, array $filters = []): array
    {
        $scope = $this->scopedTaskQuery($actor, 'tasks.read');
        $now = Carbon::now('UTC');
        $todayStart = $now->copy()->startOfDay();
        $tomorrowStart = $todayStart->copy()->addDay();
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $nextWeekStart = $weekStart->copy()->addWeek();
        $upcomingEnd = $tomorrowStart->copy()->addDays(7);

        $total = (clone $scope)->count();
        $dueToday = (clone $scope)->whereIn('status', ['pending', 'in_progress'])
            ->where('due_at', '>=', $todayStart->toDateTimeString())
            ->where('due_at', '<', $tomorrowStart->toDateTimeString())
            ->count();
        $overdue = (clone $scope)->whereIn('status', ['pending', 'in_progress'])
            ->where('due_at', '<', $todayStart->toDateTimeString())
            ->count();
        $completedThisWeek = DB::table('tasks_cycle_audit')
            ->where('action', 'complete')
            ->where('created_at', '>=', $weekStart->toDateTimeString())
            ->where('created_at', '<', $nextWeekStart->toDateTimeString())
            ->whereIn('task_id', (clone $scope)->where('status', 'completed')->select('id'))
            ->distinct()
            ->count('task_id');
        $weekTasks = (clone $scope)->where('status', '!=', 'cancelled')
            ->where('due_at', '>=', $weekStart->toDateTimeString())
            ->where('due_at', '<', $nextWeekStart->toDateTimeString());
        $weekDue = (clone $weekTasks)->count();
        $weekCompleted = (clone $weekTasks)->where('status', 'completed')->count();
        $completionShare = $weekDue > 0 ? (int) round(($weekCompleted / $weekDue) * 100) : null;

        $today = (clone $scope)->whereIn('status', ['pending', 'in_progress'])
            ->where('due_at', '>=', $todayStart->toDateTimeString())
            ->where('due_at', '<', $tomorrowStart->toDateTimeString())
            ->orderBy('due_at')->orderBy('id')->limit(5)->get()
            ->map(fn (stdClass $row): array => $this->presentTask($row))->all();
        $upcoming = (clone $scope)->whereIn('status', ['pending', 'in_progress'])
            ->where('due_at', '>=', $tomorrowStart->toDateTimeString())
            ->where('due_at', '<', $upcomingEnd->toDateTimeString())
            ->orderBy('due_at')->orderBy('id')->limit(5)->get()
            ->map(fn (stdClass $row): array => $this->presentTask($row))->all();
        $evidence = DB::table('tasks_cycle_evidence as evidence')
            ->join('tasks_cycle as task', 'task.id', '=', 'evidence.task_id')
            ->whereIn('task.id', (clone $scope)->select('id'))
            ->orderByDesc('evidence.created_at')->orderByDesc('evidence.id')->limit(5)
            ->get(['task.id as task_id', 'task.title', 'evidence.id as evidence_id', 'evidence.name', 'evidence.created_at'])
            ->map(fn (stdClass $row): array => [
                'task_id' => (string) $row->task_id,
                'title' => (string) $row->title,
                'evidence_id' => (string) $row->evidence_id,
                'name' => (string) $row->name,
                'created_at' => (string) $row->created_at,
            ])->all();

        $query = clone $scope;
        if (filled($filters['q'] ?? null)) {
            $query->where('title', 'like', '%'.$filters['q'].'%');
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['priority'] ?? null)) {
            $query->where('priority', $filters['priority']);
        }
        if (filled($filters['due_from'] ?? null)) {
            $from = Carbon::parse($filters['due_from'], 'UTC')->startOfDay();
            $query->where('due_at', '>=', $from->toDateTimeString());
        }
        if (filled($filters['due_to'] ?? null)) {
            $through = Carbon::parse($filters['due_to'], 'UTC')->addDay()->startOfDay();
            $query->where('due_at', '<', $through->toDateTimeString());
        }
        match ($filters['due_preset'] ?? null) {
            'today' => $query->whereIn('status', ['pending', 'in_progress'])
                ->where('due_at', '>=', $todayStart->toDateTimeString())
                ->where('due_at', '<', $tomorrowStart->toDateTimeString()),
            'overdue' => $query->whereIn('status', ['pending', 'in_progress'])
                ->where('due_at', '<', $todayStart->toDateTimeString()),
            'upcoming' => $query->whereIn('status', ['pending', 'in_progress'])
                ->where('due_at', '>=', $tomorrowStart->toDateTimeString())
                ->where('due_at', '<', $upcomingEnd->toDateTimeString()),
            default => null,
        };

        $sortColumns = [
            'due_at' => 'due_at',
            'title' => 'title',
            'status' => 'status',
            'priority' => 'priority',
            'created_at' => 'created_at',
        ];
        $sort = $sortColumns[$filters['sort'] ?? 'due_at'];
        $direction = $filters['direction'] ?? 'asc';
        if ($sort === 'due_at') {
            $query->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END');
        }
        $query->orderBy($sort, $direction)->orderBy('id');

        $perPage = (int) ($filters['per_page'] ?? 10);
        $page = (int) ($filters['page'] ?? 1);
        $tasks = $query->paginate($perPage, [
            'id', 'title', 'owner_id', 'assignee_id', 'priority', 'due_at', 'status', 'version',
        ], 'page', $page)->through(fn (stdClass $row): array => $this->presentTask($row));
        $tasks->appends(array_diff_key($filters, ['page' => true]));

        return [
            'tasks' => $tasks,
            'summary' => [
                'total' => $total,
                'due_today' => $dueToday,
                'overdue' => $overdue,
                'completed_this_week' => $completedThisWeek,
                'week_completion_share' => $completionShare,
            ],
            'week' => [
                'due' => $weekDue,
                'completed' => $weekCompleted,
                'remaining' => $weekDue - $weekCompleted,
            ],
            'today' => array_values($today),
            'upcoming' => array_values($upcoming),
            'evidence' => array_values($evidence),
        ];
    }

    /** @return array<string, mixed> */
    public function find(ActorAccess $actor, string $id, bool $withDetails = true): array
    {
        $this->requirePermission($actor, 'tasks.read');
        $row = DB::table('tasks_cycle')->where('id', $id)->first();

        if ($row === null || ! $this->canAccess($actor, 'tasks.read', $row)) {
            throw new NotFoundHttpException('Task not found.');
        }

        return $this->presentTask($row, $withDetails);
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function create(ActorAccess $actor, array $validated, string $key): array
    {
        $this->requirePermission($actor, 'tasks.write');
        $this->requireKey($key);

        $title = $validated['title'] ?? null;
        $priority = $validated['priority'] ?? null;
        $assigneeId = $validated['assignee_id'] ?? $actor->actorId;

        if (! is_string($title) || trim($title) === '' || mb_strlen(trim($title)) > 255) {
            $this->invalid('title', 'A task title of at most 255 characters is required.');
        }
        if (! is_string($priority) || ! in_array($priority, ['low', 'normal', 'high'], true)) {
            $this->invalid('priority', 'The selected task priority is invalid.');
        }
        if (! is_string($assigneeId) || ($assigneeId !== $actor->actorId && ! Str::isUlid($assigneeId))) {
            $this->invalid('assignee_id', 'Select an eligible task assignee.');
        }
        if ($assigneeId !== $actor->actorId) {
            $this->requirePermission($actor, 'tasks.assign');
        }

        $dueAt = $this->normalizeDueAt($validated['due_at'] ?? null);
        $payloadHash = $this->fingerprint([
            'title' => trim($title),
            'priority' => $priority,
            'due_at' => $dueAt,
            'assignee_id' => $assigneeId,
        ]);
        $createKeyHash = $this->fingerprint([$actor->actorId, $actor->entityId, $actor->departmentId, $key]);

        try {
            return DB::transaction(function () use ($actor, $key, $title, $priority, $assigneeId, $dueAt, $payloadHash, $createKeyHash): array {
                if ($assigneeId !== $actor->actorId) {
                    $this->requireAssignee($actor, $assigneeId);
                }
                $existing = DB::table('tasks_cycle')->where('create_key_hash', $createKeyHash)->lockForUpdate()->first();

                if ($existing !== null) {
                    return $this->replayCreate($existing, $payloadHash);
                }

                $id = (string) Str::ulid();
                $receiptId = (string) Str::ulid();
                $timestamp = now();

                DB::table('tasks_cycle')->insert([
                    'id' => $id,
                    'entity_id' => $actor->entityId,
                    'department_id' => $actor->departmentId,
                    'owner_id' => $actor->actorId,
                    'assignee_id' => $assigneeId,
                    'title' => trim($title),
                    'priority' => $priority,
                    'due_at' => $dueAt,
                    'status' => TaskStatus::Pending->value,
                    'version' => 0,
                    'create_key_hash' => $createKeyHash,
                    'create_payload_hash' => $payloadHash,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $this->appendAudit($id, $actor->actorId, 'create', $key, $payloadHash, $receiptId, [
                    'assignee_id' => $assigneeId,
                    'status' => TaskStatus::Pending->value,
                    'version' => 0,
                ]);

                return [...$this->presentTask($this->taskRow($id)), 'receipt_id' => $receiptId];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = DB::table('tasks_cycle')->useWritePdo()->where('create_key_hash', $createKeyHash)->first();
            if ($existing === null) {
                throw $exception;
            }

            return $this->replayCreate($existing, $payloadHash);
        }
    }

    /** @return array<string, mixed> */
    public function assign(ActorAccess $actor, string $id, string $assigneeId, int $expectedVersion, string $key): array
    {
        $this->requirePermission($actor, 'tasks.write');
        $this->requirePermission($actor, 'tasks.assign');
        $this->requireKey($key);
        $payloadHash = $this->fingerprint(['action' => 'assign', 'assignee_id' => $assigneeId, 'expected_version' => $expectedVersion]);

        return DB::transaction(function () use ($actor, $id, $assigneeId, $expectedVersion, $key, $payloadHash): array {
            $row = $this->lockedTask($actor, $id, 'tasks.write');
            $replay = $this->replayMutation($row, $key, 'assign', $payloadHash);
            if ($replay !== null) {
                return $replay;
            }
            if ((int) $row->version !== $expectedVersion) {
                throw new ConflictHttpException('Task version is stale.');
            }
            if (! in_array($row->status, ['pending', 'in_progress'], true)) {
                throw new ConflictHttpException('A closed task cannot be reassigned.');
            }
            $this->requireAssignee($actor, $assigneeId);
            $version = (int) $row->version + 1;
            DB::table('tasks_cycle')->where('id', $id)->update([
                'assignee_id' => $assigneeId, 'version' => $version, 'updated_at' => now(),
            ]);
            $receiptId = (string) Str::ulid();
            $this->appendAudit($id, $actor->actorId, 'assign', $key, $payloadHash, $receiptId, [
                'previous_assignee_id' => $row->assignee_id, 'assignee_id' => $assigneeId, 'version' => $version,
            ]);

            return [...$this->presentTask($this->taskRow($id)), 'receipt_id' => $receiptId];
        }, 3);
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, stdClass>
     */
    public function assigneePage(ActorAccess $actor, array $filters = []): LengthAwarePaginator
    {
        $this->requirePermission($actor, 'tasks.write');
        $this->requirePermission($actor, 'tasks.assign');
        $query = $this->assigneeQuery($actor);
        if (filled($filters['assignee_q'] ?? null)) {
            $query->where('users.name', 'like', '%'.$filters['assignee_q'].'%');
        }

        return $query->orderBy('users.name')->orderBy('users.id')
            ->paginate(25, ['users.id', 'users.name'], 'assignee_page', (int) ($filters['assignee_page'] ?? 1))
            ->appends($filters);
    }

    public function assigneeOption(ActorAccess $actor, string $id): ?stdClass
    {
        $this->requirePermission($actor, 'tasks.write');
        $this->requirePermission($actor, 'tasks.assign');

        return $this->assigneeQuery($actor)->where('users.id', $id)->first(['users.id', 'users.name']);
    }

    private function requireAssignee(ActorAccess $actor, string $id): void
    {
        if (! $this->assigneeQuery($actor)->where('users.id', $id)->exists()) {
            $this->invalid('assignee_id', 'Select an eligible task assignee.');
        }
    }

    private function assigneeQuery(ActorAccess $actor): Builder
    {
        $query = DB::table('access_users as users')->where('users.active', true)
            ->where('users.company_id', $actor->entityId)->where('users.branch_id', $actor->branchId)
            ->where('users.department_id', $actor->departmentId);
        foreach (['tasks.read', 'tasks.write'] as $permission) {
            $query->where(function (Builder $effective) use ($permission): void {
                $effective->where('users.is_admin', true)
                    ->orWhereExists(function (Builder $grant) use ($permission): void {
                        $grant->selectRaw('1')->from('access_role_permissions as rp')
                            ->join('access_roles as roles', 'roles.id', '=', 'rp.role_id')
                            ->whereColumn('roles.company_id', 'users.company_id')->where('rp.permission', $permission)
                            ->where(function (Builder $assignment): void {
                                $assignment->whereExists(function (Builder $direct): void {
                                    $direct->selectRaw('1')->from('access_user_roles as ur')
                                        ->whereColumn('ur.user_id', 'users.id')->whereColumn('ur.role_id', 'roles.id');
                                })->orWhereExists(function (Builder $department): void {
                                    $department->selectRaw('1')->from('access_department_roles as dr')
                                        ->whereColumn('dr.role_id', 'roles.id')->whereColumn('dr.company_id', 'users.company_id')
                                        ->whereColumn('dr.branch_id', 'users.branch_id')->whereColumn('dr.department_id', 'users.department_id');
                                });
                            });
                    })->orWhereExists(function (Builder $grant) use ($permission): void {
                        $grant->selectRaw('1')->from('access_user_permissions as up')
                            ->whereColumn('up.user_id', 'users.id')->where('up.permission', $permission)->where('up.allowed', true);
                    });
            })->whereNotExists(function (Builder $deny) use ($permission): void {
                $deny->selectRaw('1')->from('access_user_permissions as up')
                    ->whereColumn('up.user_id', 'users.id')->where('up.permission', $permission)->where('up.allowed', false);
            });
        }

        return $query;
    }

    /** @return array<string, mixed> */
    public function start(ActorAccess $actor, string $id, string $key, ?int $expectedVersion = null): array
    {
        $this->requirePermission($actor, 'tasks.write');
        $this->requireKey($key);
        $payloadHash = $this->fingerprint(['action' => 'start', 'expected_version' => $expectedVersion]);

        return DB::transaction(function () use ($actor, $id, $key, $expectedVersion, $payloadHash): array {
            $row = $this->lockedTask($actor, $id, 'tasks.write');
            $replay = $this->replayMutation($row, $key, 'start', $payloadHash);
            if ($replay !== null) {
                return $replay;
            }

            if ($expectedVersion !== null && (int) $row->version !== $expectedVersion) {
                throw new ConflictHttpException('Task version is stale.');
            }

            $result = $this->manageTasks->transition(
                $this->domainTask($row),
                TaskStatus::InProgress,
                $actor->actorId,
                $key,
                $this->utcNow(),
            );

            if (! $result->accepted) {
                throw new ConflictHttpException('Task cannot be started in its current state.');
            }

            DB::table('tasks_cycle')->where('id', $id)->update([
                'status' => $result->task->status->value,
                'version' => $result->task->version,
                'updated_at' => now(),
            ]);
            $receiptId = (string) Str::ulid();
            $this->appendAudit($id, $actor->actorId, 'start', $key, $payloadHash, $receiptId, [
                'from' => TaskStatus::Pending->value,
                'to' => $result->task->status->value,
                'version' => $result->task->version,
            ]);

            return [...$this->presentTask($this->taskRow($id)), 'receipt_id' => $receiptId];
        });
    }

    /** @return array<string, mixed> */
    public function evidence(ActorAccess $actor, string $id, UploadedFile $file, string $key): array
    {
        $this->requirePermission($actor, 'tasks.write');
        $this->requireKey($key);
        $fileData = $this->evidenceFileData($file);
        $payloadHash = $this->fingerprint([
            'action' => 'evidence',
            'name' => $fileData['name'],
            'mime' => $fileData['mime'],
            'size' => $fileData['size'],
            'sha256' => $fileData['sha256'],
        ]);
        $storedPath = null;

        try {
            return DB::transaction(function () use ($actor, $id, $file, $key, $fileData, $payloadHash, &$storedPath): array {
                $row = $this->lockedTask($actor, $id, 'tasks.write');
                $replay = $this->replayMutation($row, $key, 'evidence', $payloadHash);
                if ($replay !== null) {
                    return $replay;
                }
                if ($row->status !== TaskStatus::InProgress->value) {
                    throw new ConflictHttpException('Evidence can only be added to an in-progress task.');
                }

                $evidenceId = (string) Str::ulid();
                $storedPath = 'task-evidence/'.$id.'/'.$evidenceId.'.'.$fileData['extension'];
                if ($file->storeAs('task-evidence/'.$id, $evidenceId.'.'.$fileData['extension'], 'local') === false) {
                    throw new RuntimeException('Task evidence could not be stored.');
                }

                $result = $this->manageTasks->attachEvidence(
                    $this->domainTask($row),
                    $evidenceId,
                    $fileData['mime'],
                    $actor->actorId,
                    $key,
                    $this->utcNow(),
                );
                if (! $result->accepted) {
                    throw new ConflictHttpException('Evidence cannot be added to this task.');
                }

                DB::table('tasks_cycle')->where('id', $id)->update([
                    'version' => $result->task->version,
                    'updated_at' => now(),
                ]);
                DB::table('tasks_cycle_evidence')->insert([
                    'id' => $evidenceId,
                    'task_id' => $id,
                    'path' => $storedPath,
                    'name' => $fileData['name'],
                    'mime' => $fileData['mime'],
                    'size' => $fileData['size'],
                    'created_at' => now(),
                ]);

                $receiptId = (string) Str::ulid();
                $this->appendAudit($id, $actor->actorId, 'evidence', $key, $payloadHash, $receiptId, [
                    'evidence_id' => $evidenceId,
                    'mime' => $fileData['mime'],
                    'size' => $fileData['size'],
                    'version' => $result->task->version,
                ]);

                return [...$this->presentTask($this->taskRow($id)), 'receipt_id' => $receiptId, 'evidence_id' => $evidenceId];
            });
        } catch (\Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function complete(ActorAccess $actor, string $id, string $key): array
    {
        $this->requirePermission($actor, 'tasks.write');
        $this->requireKey($key);
        $payloadHash = $this->fingerprint(['action' => 'complete']);

        return DB::transaction(function () use ($actor, $id, $key, $payloadHash): array {
            $row = $this->lockedTask($actor, $id, 'tasks.write');
            $replay = $this->replayMutation($row, $key, 'complete', $payloadHash);
            if ($replay !== null) {
                return $replay;
            }
            if ((int) DB::table('tasks_cycle_evidence')->where('task_id', $id)->count() === 0) {
                throw new ConflictHttpException('Task evidence is required before completion.');
            }

            $result = $this->manageTasks->transition(
                $this->domainTask($row),
                TaskStatus::Completed,
                $actor->actorId,
                $key,
                $this->utcNow(),
            );
            if (! $result->accepted) {
                throw new ConflictHttpException('Task cannot be completed in its current state.');
            }

            DB::table('tasks_cycle')->where('id', $id)->update([
                'status' => $result->task->status->value,
                'version' => $result->task->version,
                'updated_at' => now(),
            ]);
            $receiptId = (string) Str::ulid();
            $this->appendAudit($id, $actor->actorId, 'complete', $key, $payloadHash, $receiptId, [
                'from' => TaskStatus::InProgress->value,
                'to' => $result->task->status->value,
                'version' => $result->task->version,
            ]);

            return [...$this->presentTask($this->taskRow($id)), 'receipt_id' => $receiptId];
        });
    }

    /** @return array{path: string, name: string, mime: string, size: int} */
    public function download(ActorAccess $actor, string $id, string $evidenceId): array
    {
        $this->requirePermission($actor, 'tasks.read');
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id) !== 1 || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $evidenceId) !== 1) {
            throw new NotFoundHttpException('Task evidence not found.');
        }
        $row = DB::table('tasks_cycle')->where('id', $id)->first();
        if ($row === null || ! $this->canAccess($actor, 'tasks.read', $row)) {
            throw new NotFoundHttpException('Task not found.');
        }

        $evidence = DB::table('tasks_cycle_evidence')->where('task_id', $id)->where('id', $evidenceId)->first();
        $allowedPaths = array_map(fn (string $extension): string => 'task-evidence/'.$id.'/'.$evidenceId.'.'.$extension, ['pdf', 'png', 'jpg', 'txt']);
        if ($evidence === null || ! in_array($evidence->path, $allowedPaths, true) || ! Storage::disk('local')->exists($evidence->path)) {
            throw new NotFoundHttpException('Task evidence not found.');
        }

        return ['path' => $evidence->path, 'name' => $evidence->name, 'mime' => $evidence->mime, 'size' => (int) $evidence->size];
    }

    /** @return array<string, mixed> */
    private function replayCreate(stdClass $row, string $payloadHash): array
    {
        if (! hash_equals((string) $row->create_payload_hash, $payloadHash)) {
            throw new ConflictHttpException('Idempotency key has already been used for different request data.');
        }

        $receipt = DB::table('tasks_cycle_audit')->where('task_id', $row->id)->where('action', 'create')->first();
        if ($receipt === null) {
            throw new RuntimeException('Task creation receipt is missing.');
        }

        return [...$this->presentTask($row), 'receipt_id' => $receipt->receipt_id];
    }

    /** @return array<string, mixed>|null */
    private function replayMutation(stdClass $row, string $key, string $action, string $payloadHash): ?array
    {
        $audit = DB::table('tasks_cycle_audit')->where('task_id', $row->id)->where('request_key', $key)->first();
        if ($audit === null) {
            return null;
        }
        if ($audit->action !== $action || ! hash_equals((string) $audit->payload_hash, $payloadHash)) {
            throw new ConflictHttpException('Idempotency key has already been used for a different task action.');
        }

        return [...$this->presentTask($row), 'receipt_id' => $audit->receipt_id];
    }

    /** @param array<string, mixed> $payload */
    private function appendAudit(string $taskId, string $actorId, string $action, string $key, string $payloadHash, string $receiptId, array $payload): void
    {
        DB::table('tasks_cycle_audit')->insert([
            'id' => (string) Str::ulid(),
            'task_id' => $taskId,
            'actor_id' => $actorId,
            'action' => $action,
            'request_key' => $key,
            'payload_hash' => $payloadHash,
            'receipt_id' => $receiptId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ]);
    }

    private function lockedTask(ActorAccess $actor, string $id, string $permission): stdClass
    {
        $row = DB::table('tasks_cycle')->where('id', $id)->lockForUpdate()->first();
        if ($row === null || ! $this->canAccess($actor, $permission, $row)) {
            throw new NotFoundHttpException('Task not found.');
        }

        return $row;
    }

    private function taskRow(string $id): stdClass
    {
        $row = DB::table('tasks_cycle')->where('id', $id)->first();
        if ($row === null) {
            throw new RuntimeException('Task disappeared during a locked mutation.');
        }

        return $row;
    }

    private function scopedTaskQuery(ActorAccess $actor, string $permission): Builder
    {
        $this->requirePermission($actor, $permission);

        // Normalize PHP trim's ASCII whitespace; HEX handles SQLite's embedded NUL bytes.
        return DB::table('tasks_cycle')
            ->whereRaw($this->nonBlankIdentifier('id'))
            ->where('entity_id', $actor->entityId)
            ->where('department_id', $actor->departmentId)
            ->whereRaw($this->nonBlankIdentifier('owner_id'))
            ->where(fn (Builder $query) => $query->whereNull('assignee_id')->orWhereRaw($this->nonBlankIdentifier('assignee_id')))
            ->where(fn (Builder $query) => $query->where('owner_id', $actor->actorId)->orWhere('assignee_id', $actor->actorId));
    }

    /**
     * @param  'id'|'owner_id'|'assignee_id'  $column
     * @return literal-string
     */
    private function nonBlankIdentifier(string $column): string
    {
        return "REPLACE(HEX(TRIM(REPLACE(REPLACE(REPLACE(REPLACE({$column}, CHAR(9), CHAR(32)), CHAR(10), CHAR(32)), CHAR(13), CHAR(32)), CHAR(11), CHAR(32)))), '00', '') <> ''";
    }

    private function canAccess(ActorAccess $actor, string $permission, stdClass $row): bool
    {
        return $this->authorizeScopedRecord->allows($actor, $permission, new ScopedRecord(
            (string) $row->id,
            (string) $row->entity_id,
            (string) $row->department_id,
            (string) $row->owner_id,
            $row->assignee_id === null ? null : (string) $row->assignee_id,
        ));
    }

    private function requirePermission(ActorAccess $actor, string $permission): void
    {
        if (! $this->authorizeScopedRecord->allows($actor, $permission, new ScopedRecord(
            'tasks-cycle-boundary',
            $actor->entityId,
            $actor->departmentId,
            $actor->actorId,
            $actor->actorId,
        ))) {
            throw new AccessDeniedHttpException('Task access denied.');
        }
    }

    /** @return array<string, mixed> */
    private function presentTask(stdClass $row, bool $withDetails = false): array
    {
        $task = [
            'id' => (string) $row->id,
            'title' => (string) $row->title,
            'owner_id' => (string) $row->owner_id,
            'assignee_id' => (string) $row->assignee_id,
            'priority' => (string) $row->priority,
            'due_at' => $row->due_at === null ? null : (new DateTimeImmutable((string) $row->due_at, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            'status' => (string) $row->status,
            'version' => (int) $row->version,
        ];

        if (! $withDetails) {
            return $task;
        }

        $task['evidence'] = DB::table('tasks_cycle_evidence')->where('task_id', $row->id)
            ->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (stdClass $evidence): array => [
                'id' => (string) $evidence->id,
                'name' => (string) $evidence->name,
                'mime' => (string) $evidence->mime,
                'size' => (int) $evidence->size,
            ])->all();
        $task['audit'] = DB::table('tasks_cycle_audit')->where('task_id', $row->id)
            ->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (stdClass $audit): array => [
                'id' => (string) $audit->id,
                'actor_id' => (string) $audit->actor_id,
                'action' => (string) $audit->action,
                'receipt_id' => (string) $audit->receipt_id,
                'created_at' => (string) $audit->created_at,
            ])->all();

        return $task;
    }

    private function domainTask(stdClass $row): TaskRecord
    {
        $evidence = DB::table('tasks_cycle_evidence')->where('task_id', $row->id)
            ->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (stdClass $item): TaskEvidence => new TaskEvidence(
                (string) $item->id,
                (string) $item->task_id,
                (string) $item->mime,
                new DateTimeImmutable((string) $item->created_at, new DateTimeZone('UTC')),
            ))->all();

        return new TaskRecord(
            (string) $row->id,
            (string) $row->entity_id,
            (string) $row->owner_id,
            null,
            (string) $row->department_id,
            (string) $row->title,
            TaskStatus::from((string) $row->status),
            $row->due_at === null ? null : new DateTimeImmutable((string) $row->due_at, new DateTimeZone('UTC')),
            $evidence,
            (int) $row->version,
        );
    }

    /** @return array{name: string, mime: string, size: int, extension: string, sha256: string} */
    private function evidenceFileData(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            $this->invalid('evidence', 'The uploaded evidence is invalid.');
        }
        $size = $file->getSize();
        $mime = $file->getMimeType();
        $extensions = [
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'text/plain' => 'txt',
        ];
        if (! is_int($size) || $size > 5 * 1024 * 1024 || ! is_string($mime) || ! isset($extensions[$mime])) {
            $this->invalid('evidence', 'Evidence must be a PDF, PNG, JPEG, or text file no larger than 5 MB.');
        }

        $realPath = $file->getRealPath();
        $sha256 = is_string($realPath) ? hash_file('sha256', $realPath) : false;
        if (! is_string($sha256)) {
            throw new RuntimeException('Task evidence could not be read.');
        }

        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $name));
        if ($name === '') {
            $name = 'evidence.'.$extensions[$mime];
        }

        return ['name' => mb_substr($name, 0, 255), 'mime' => $mime, 'size' => $size, 'extension' => $extensions[$mime], 'sha256' => $sha256];
    }

    private function normalizeDueAt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            $this->invalid('due_at', 'The due date is invalid.');
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            $this->invalid('due_at', 'The due date is invalid.');
        }
    }

    private function requireKey(string $key): void
    {
        if (trim($key) === '' || mb_strlen($key) > 128) {
            $this->invalid('idempotency_key', 'A request key of at most 128 characters is required.');
        }
    }

    /** @param array<mixed> $payload */
    private function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function utcNow(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

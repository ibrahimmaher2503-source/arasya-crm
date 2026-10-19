<?php

declare(strict_types=1);

namespace App\Modules\Employees\Application\EMP01;

use App\Modules\Identity\Application\SEC01\AuthorizeScopedRecord;
use App\Modules\Identity\Application\SEC04\AccessControl;
use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use App\Modules\Identity\Domain\SEC01\ActorAccess;
use App\Modules\Identity\Domain\SEC01\ScopedRecord;
use App\Modules\Tasks\Domain\TASK01\TaskStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class EmployeeWorkDirectory
{
    public function __construct(
        private RuntimeIdentity $identity,
        private AuthorizeScopedRecord $scopedRecords,
        private EmployeeDirectory $employees,
        private AccessControl $access,
    ) {}

    /** @return array<string, mixed> */
    public function read(Request $request): array
    {
        abort_unless(app()->environment(['local', 'testing']) === true, 404);

        return $this->access->withReadLock($request, ['tasks.read'], fn (): array => $this->readLocked($request));
    }

    /** @return array<string, mixed> */
    private function readLocked(Request $request): array
    {
        $locale = $request->query('locale') === 'en' ? 'en' : 'ar';
        app()->setLocale($locale);
        $actor = $this->identity->fromRequest($request);
        abort_unless($this->scopedRecords->allows($actor, 'tasks.read', new ScopedRecord(
            'tasks-development', $actor->entityId, $actor->departmentId, $actor->actorId, $actor->actorId,
        )), 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'employee_id' => ['nullable', 'string', 'size:26'],
            'branch_id' => ['nullable', 'string', 'size:26'],
            'department_id' => ['nullable', 'string', 'size:26'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:'.implode(',', array_map(static fn (TaskStatus $status): string => $status->value, TaskStatus::cases()))],
            'sort' => ['nullable', 'in:title,priority,status,due_at,updated_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        if (isset($filters['date_from'], $filters['date_to']) && $filters['date_to'] < $filters['date_from']) {
            throw ValidationException::withMessages(['date_to' => 'The end date must be on or after the start date.']);
        }

        $linkedEmployeeId = DB::table('employee_profiles')
            ->where('company_id', $actor->entityId)
            ->where('user_id', $actor->actorId)
            ->value('id');
        $linkedEmployeeId = is_string($linkedEmployeeId) ? $linkedEmployeeId : null;

        if (($filters['employee_id'] ?? null) !== null && $filters['employee_id'] !== $linkedEmployeeId) {
            throw new NotFoundHttpException('Employee not found.');
        }

        $employee = null;
        if ($linkedEmployeeId !== null && $this->hasPermission($actor, 'employees.read')) {
            try {
                $employee = Arr::only($this->employees->find($request, $linkedEmployeeId), [
                    'id', 'code', 'name', 'role', 'branch_id', 'department_id', 'manager_id',
                    'branch_name', 'department_name', 'manager_name', 'status',
                ]);
            } catch (AccessDeniedHttpException|NotFoundHttpException) {
                $employee = null;
            }
        }

        $counts = $this->counts($actor);
        $query = $this->filteredTasks($actor, $filters, $employee);
        $sort = $filters['sort'] ?? 'due_at';
        $direction = $filters['direction'] ?? 'asc';
        if ($sort === 'due_at') {
            $query->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }
        $tasks = $query->orderBy('id', $direction)
            ->paginate((int) ($filters['per_page'] ?? 10), [
                'id', 'entity_id', 'department_id', 'owner_id', 'assignee_id', 'title', 'priority', 'due_at', 'status', 'updated_at',
            ])
            ->withQueryString();
        foreach ($tasks->items() as $task) {
            $this->assertTaskVisible($actor, $task);
        }

        $todayTasks = $this->authorizedTasks($actor)
            ->whereDate('due_at', now()->toDateString())
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit(5)
            ->get(['id', 'entity_id', 'department_id', 'owner_id', 'assignee_id', 'title', 'due_at']);
        foreach ($todayTasks as $task) {
            $this->assertTaskVisible($actor, $task);
        }

        $taskEvidence = $this->evidence($actor);
        foreach ($taskEvidence as $evidence) {
            $this->assertTaskVisible($actor, $evidence);
        }

        $employeeEvidence = $employee === null
            ? []
            : array_slice($this->employees->history($request, (string) $employee['id'])['evidence'], 0, 5);

        return [
            'actor' => $actor,
            'permissions' => $actor->inheritedPermissions,
            'locale' => $locale,
            'title' => $this->text($locale, 'متابعة أعمال الموظفين', 'Employee work follow-up'),
            'pageSubtitle' => $this->text($locale, 'عرض ومتابعة المهام المرتبطة بهويتك الحالية فقط', 'Follow only tasks authorized to your current identity'),
            'pageIcon' => 'users',
            'breadcrumbs' => [
                ['label' => $this->text($locale, 'مركز العمل', 'Workspace'), 'url' => '/dashboard'],
                ['label' => $this->text($locale, 'الموظفون', 'Employees'), 'url' => '/employees?locale='.$locale],
                ['label' => $this->text($locale, 'متابعة الأعمال', 'Work follow-up')],
            ],
            'employee' => $employee,
            'employeeId' => $linkedEmployeeId,
            'branchId' => $actor->branchId,
            'departmentId' => $actor->departmentId,
            'branchLabel' => $employee['branch_name'] ?? $this->text($locale, 'الفرع الحالي ضمن نطاقك', 'Current authorized branch'),
            'departmentLabel' => $employee['department_name'] ?? $this->text($locale, 'القسم الحالي ضمن نطاقك', 'Current authorized department'),
            'summaryCounts' => $counts,
            'summary' => $this->summary($counts, $locale),
            'tasks' => $tasks,
            'todayTasks' => $todayTasks,
            'taskEvidence' => $taskEvidence,
            'employeeEvidence' => $employeeEvidence,
            'filters' => $filters,
        ];
    }

    private function hasPermission(ActorAccess $actor, string $permission): bool
    {
        return ($actor->overrides[$permission] ?? in_array($permission, $actor->inheritedPermissions, true)) === true;
    }

    /** @param array<string, mixed> $filters
     * @param  array<string, mixed>|null  $employee
     */
    private function filteredTasks(ActorAccess $actor, array $filters, ?array $employee): Builder
    {
        $query = $this->authorizedTasks($actor);

        if (filled($filters['branch_id'] ?? null) && $filters['branch_id'] !== $actor->branchId) {
            $query->whereRaw('1 = 0');
        }
        if (filled($filters['department_id'] ?? null) && $filters['department_id'] !== $actor->departmentId) {
            $query->whereRaw('1 = 0');
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('due_at', '>=', $filters['date_from']);
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('due_at', '<=', $filters['date_to']);
        }

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '' && ! $this->matchesEmployee($employee, $term)) {
            $query->where('title', 'like', '%'.$term.'%');
        }

        return $query;
    }

    /** @param array<string, mixed>|null $employee */
    private function matchesEmployee(?array $employee, string $term): bool
    {
        if ($employee === null) {
            return false;
        }

        foreach (['name', 'code', 'role'] as $field) {
            if (mb_stripos((string) ($employee[$field] ?? ''), $term) !== false) {
                return true;
            }
        }

        return false;
    }

    /** @return array{total: int, pending: int, in_progress: int, completed: int} */
    private function counts(ActorAccess $actor): array
    {
        $counts = $this->authorizedTasks($actor)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as pending', [TaskStatus::Pending->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as in_progress', [TaskStatus::InProgress->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as completed', [TaskStatus::Completed->value])
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'pending' => (int) ($counts->pending ?? 0),
            'in_progress' => (int) ($counts->in_progress ?? 0),
            'completed' => (int) ($counts->completed ?? 0),
        ];
    }

    /**
     * @param  array{total: int, pending: int, in_progress: int, completed: int}  $counts
     * @return list<array{label: string, value: string, icon: string, tone: string, description: string}>
     */
    private function summary(array $counts, string $locale): array
    {
        $definitions = [
            ['total', 'إجمالي المهام', 'Total tasks', 'grid', 'info'],
            ['pending', 'قيد الانتظار', 'Pending', 'clock', 'warning'],
            ['in_progress', 'قيد التنفيذ', 'In progress', 'arrow', 'info'],
            ['completed', 'مكتملة', 'Completed', 'audit', 'success'],
        ];

        return array_map(fn (array $definition): array => [
            'label' => $this->text($locale, $definition[1], $definition[2]),
            'value' => (string) $counts[$definition[0]],
            'icon' => $definition[3],
            'tone' => $definition[4],
            'description' => $this->text($locale, 'ضمن المهام المصرح بها', 'Within authorized tasks'),
        ], $definitions);
    }

    /** @return array<int, stdClass> */
    private function evidence(ActorAccess $actor): array
    {
        $authorizedTaskIds = $this->authorizedTasks($actor)->select('id');

        return DB::table('tasks_cycle_evidence as evidence')
            ->join('tasks_cycle as tasks', 'tasks.id', '=', 'evidence.task_id')
            ->whereIn('evidence.task_id', $authorizedTaskIds)
            ->orderByDesc('evidence.created_at')
            ->orderByDesc('evidence.id')
            ->limit(5)
            ->get([
                'evidence.id', 'evidence.task_id', 'evidence.name', 'evidence.created_at', 'tasks.id as id', 'tasks.title',
                'tasks.entity_id', 'tasks.department_id', 'tasks.owner_id', 'tasks.assignee_id',
            ])
            ->all();
    }

    private function authorizedTasks(ActorAccess $actor): Builder
    {
        // Normalize PHP trim's ASCII whitespace; HEX handles SQLite's embedded NUL bytes.
        return DB::table('tasks_cycle')
            ->whereRaw($this->nonBlankIdentifier('id'))
            ->where('entity_id', $actor->entityId)
            ->where('department_id', $actor->departmentId)
            ->whereRaw($this->nonBlankIdentifier('owner_id'))
            ->where(fn (Builder $query) => $query->whereNull('assignee_id')->orWhereRaw($this->nonBlankIdentifier('assignee_id')))
            ->where(fn (Builder $query): Builder => $query->where('owner_id', $actor->actorId)->orWhere('assignee_id', $actor->actorId));
    }

    /**
     * @param  'id'|'owner_id'|'assignee_id'  $column
     * @return literal-string
     */
    private function nonBlankIdentifier(string $column): string
    {
        return "REPLACE(HEX(TRIM(REPLACE(REPLACE(REPLACE(REPLACE({$column}, CHAR(9), CHAR(32)), CHAR(10), CHAR(32)), CHAR(13), CHAR(32)), CHAR(11), CHAR(32)))), '00', '') <> ''";
    }

    private function assertTaskVisible(ActorAccess $actor, stdClass $task): void
    {
        abort_unless($this->scopedRecords->allows($actor, 'tasks.read', new ScopedRecord(
            (string) $task->id,
            (string) $task->entity_id,
            (string) $task->department_id,
            (string) $task->owner_id,
            $task->assignee_id === null ? null : (string) $task->assignee_id,
        )), 403);
    }

    private function text(string $locale, string $arabic, string $english): string
    {
        return $locale === 'ar' ? $arabic : $english;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Application\SEC04\AccessControl;
use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use App\Modules\Identity\Domain\SEC01\ActorAccess;
use App\Modules\Identity\Domain\SEC01\ScopedPermissionGuard;
use App\Modules\Identity\Domain\SEC01\ScopedRecord;
use App\Modules\Tasks\Application\TASK01\TaskCycle;
use App\Modules\Tasks\Application\TASK01\TaskTransitionPreview;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TaskCycleController
{
    public function __construct(
        private readonly RuntimeIdentity $identity,
        private readonly TaskCycle $cycle,
        private readonly TaskTransitionPreview $preview,
        private readonly ScopedPermissionGuard $guard,
        private readonly AccessControl $access,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $actor = $this->actor($request, 'tasks.read');
        if ($request->expectsJson()) {
            return response()->json(['data' => $this->cycle->list($actor)]);
        }

        $locale = $request->validate(['locale' => ['nullable', Rule::in(['ar', 'en'])]])['locale'] ?? 'ar';
        app()->setLocale($locale);
        $dueToRules = ['nullable', 'date_format:Y-m-d'];
        if ($request->filled('due_from')) {
            $dueToRules[] = 'after_or_equal:due_from';
        }
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'due_from' => ['nullable', 'date_format:Y-m-d'],
            'due_to' => $dueToRules,
            'due_preset' => ['nullable', Rule::in(['today', 'overdue', 'upcoming'])],
            'sort' => ['nullable', Rule::in(['due_at', 'title', 'status', 'priority', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters['locale'] = $locale;
        $workspace = $this->cycle->workspace($actor, $filters);
        $title = $locale === 'ar' ? 'مهامي اليومية' : 'My daily tasks';
        $canCreate = $this->guard->allows($actor, 'tasks.write', new ScopedRecord(
            'tasks-development', $actor->entityId, $actor->departmentId, $actor->actorId, $actor->actorId,
        ));

        return view('tasks.index', [
            ...$workspace,
            'actor' => $actor,
            'canCreate' => $canCreate,
            'filters' => $filters,
            'locale' => $locale,
            'title' => $title,
            'pageSubtitle' => $locale === 'ar'
                ? 'رتّب مهامك بحسب الاستحقاق وتابع ما أُنجز ضمن نطاقك.'
                : 'Review due work and completed tasks within your authorized scope.',
            'pageIcon' => 'grid',
            'breadcrumbs' => [
                ['label' => $locale === 'ar' ? 'مركز العمل' : 'Workspace', 'url' => route('tasks.index', ['locale' => $locale])],
                ['label' => $title],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $this->actor($request, 'tasks.write');

        return view('tasks.create', [
            'actor' => $actor,
            ...$this->assignmentOptions($request, $actor),
            'idempotencyKey' => (string) Str::ulid(), 'title' => 'مهمة جديدة',
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->hasSession() && $request->session()->has('access_user_id')) {
            return $this->access->withReadLock($request, ['tasks.write'], fn (): JsonResponse|RedirectResponse => $this->storeTask($request));
        }

        return $this->storeTask($request);
    }

    private function storeTask(Request $request): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request, 'tasks.write');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'priority' => ['required', 'in:low,normal,high'],
            'due_at' => ['required', 'date'],
            'assignee_id' => ['sometimes', 'required', 'string', 'max:26'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);
        $task = $this->cycle->create($actor, $data, $data['idempotency_key']);

        return $this->result($request, $task, 201);
    }

    public function assign(Request $request, string $task): JsonResponse|RedirectResponse
    {
        abort_unless(app()->environment(['local', 'testing']) === true, 404);

        return $this->access->withReadLock($request, ['tasks.write', 'tasks.assign'], function (array $verified) use ($request, $task): JsonResponse|RedirectResponse {
            $data = $request->validate([
                'assignee_id' => ['required', 'string', 'ulid'],
                'expected_version' => ['required', 'integer', 'min:0'],
                'idempotency_key' => ['required', 'string', 'max:128'],
            ]);

            return $this->result($request, $this->cycle->assign(
                $this->identity->fromActor($verified), $task, $data['assignee_id'], (int) $data['expected_version'], $data['idempotency_key'],
            ));
        });
    }

    public function show(Request $request, string $task): View|JsonResponse
    {
        $actor = $this->actor($request, 'tasks.read');
        $record = $this->cycle->find($actor, $task);
        if ($request->expectsJson()) {
            return response()->json(['data' => $record]);
        }

        $preview = $request->session()->get('tasks_preview');
        $canWrite = $this->guard->allows($actor, 'tasks.write', new ScopedRecord(
            'tasks-development', $actor->entityId, $actor->departmentId, $actor->actorId, $actor->actorId,
        ));

        return view('tasks.show', [
            'task' => $record, 'actor' => $actor, 'title' => $record['title'],
            'canWrite' => $canWrite,
            ...$this->assignmentOptions($request, $actor, (string) $record['assignee_id']),
            'idempotencyKey' => (string) Str::ulid(),
            'preview' => is_array($preview) && ($preview['task_id'] ?? null) === $task ? $preview : null,
        ]);
    }

    public function start(Request $request, string $task): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request, 'tasks.write');

        return $this->result($request, $this->cycle->start($actor, $task, $this->key($request)));
    }

    public function evidence(Request $request, string $task): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request, 'tasks.write');
        $key = $this->key($request);
        $request->validate(['evidence' => ['required', 'file', 'max:5120', 'mimetypes:application/pdf,image/png,image/jpeg,text/plain']]);
        $file = $request->file('evidence');
        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        return $this->result($request, $this->cycle->evidence($actor, $task, $file, $key));
    }

    public function complete(Request $request, string $task): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request, 'tasks.write');

        return $this->result($request, $this->cycle->complete($actor, $task, $this->key($request)));
    }

    public function download(Request $request, string $task, string $evidence): StreamedResponse
    {
        $actor = $this->actor($request, 'tasks.read');
        $file = $this->cycle->download($actor, $task, $evidence);
        $disk = Storage::disk('local');
        if (! $disk instanceof FilesystemAdapter) {
            throw new \LogicException('Task evidence requires the local filesystem adapter.');
        }
        abort_unless($disk->exists($file['path']), 404);

        return $disk->download($file['path'], $file['name'], [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function assistantPreview(Request $request, string $task): JsonResponse|RedirectResponse
    {
        $actor = $this->actor($request, 'tasks.assist');
        $this->actor($request, 'tasks.write');
        $preview = $this->preview->preview($actor, $task);

        return $request->expectsJson() ? response()->json(['data' => $preview])
            : redirect()->route('tasks.show', $task)->with('tasks_preview', $preview);
    }

    public function assistantConfirm(Request $request, string $task): JsonResponse
    {
        $actor = $this->actor($request, 'tasks.assist');
        $this->actor($request, 'tasks.write');
        $this->cycle->find($actor, $task);

        return response()->json([
            'message' => 'Shared governed AI gateway is not available.',
            'code' => 'shared_ai_gateway_blocked',
        ], 503);
    }

    private function actor(Request $request, string $permission): ActorAccess
    {
        abort_unless(app()->environment(['local', 'testing']) === true, 404);
        app()->setLocale('ar');
        $actor = $this->identity->fromRequest($request);
        abort_unless($this->guard->allows($actor, $permission, new ScopedRecord(
            'tasks-development', $actor->entityId, $actor->departmentId, $actor->actorId, $actor->actorId,
        )), 403);

        return $actor;
    }

    private function key(Request $request): string
    {
        return $request->validate(['idempotency_key' => ['required', 'string', 'max:128']])['idempotency_key'];
    }

    /** @return array<string, mixed> */
    private function assignmentOptions(Request $request, ActorAccess $actor, ?string $currentAssignee = null): array
    {
        $scope = new ScopedRecord('tasks-development', $actor->entityId, $actor->departmentId, $actor->actorId, $actor->actorId);
        $canAssign = $this->guard->allows($actor, 'tasks.assign', $scope) && $this->guard->allows($actor, 'tasks.write', $scope);
        $filters = $request->validate([
            'assignee_q' => ['nullable', 'string', 'max:120'],
            'assignee_page' => ['nullable', 'integer', 'min:1'],
            'locale' => ['nullable', Rule::in(['ar', 'en'])],
        ]);

        $selected = $request->old('assignee_id', $currentAssignee ?? $actor->actorId);
        $selected = is_string($selected) ? $selected : ($currentAssignee ?? $actor->actorId);

        return [
            'canAssign' => $canAssign,
            'assignees' => $canAssign ? $this->cycle->assigneePage($actor, $filters) : null,
            'selectedAssigneeId' => $selected,
            'selectedAssignee' => $canAssign && $selected !== $actor->actorId ? $this->cycle->assigneeOption($actor, $selected) : null,
        ];
    }

    /** @param array<string, mixed> $task */
    private function result(Request $request, array $task, int $status = 200): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['data' => $task], $status)
            : redirect()->route('tasks.show', $task['id']);
    }
}

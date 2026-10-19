<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Employees\Application\EMP01\EmployeeDirectory;
use App\Modules\Identity\Application\SEC04\AccessControl;
use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use App\Modules\Tasks\Application\TASK01\TaskCycle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final readonly class EmployeeController
{
    public function __construct(private EmployeeDirectory $directory, private AccessControl $access, private TaskCycle $tasks, private RuntimeIdentity $identity) {}

    public function index(Request $request): View|JsonResponse
    {
        $context = $this->context($request);
        $employees = $this->directory->browse($request);

        return $request->expectsJson() ? response()->json(['data' => $employees])->header('Cache-Control', 'private, no-store') : view('employees.index', $context + $this->directory->options($request) + ['employees' => $employees, 'workersOnly' => false, 'summary' => $this->summary($request, false), 'canCreateWorker' => in_array('employees.write', $context['permissions'], true) && $this->directory->workerDefaults($request) !== null]);
    }

    public function workers(Request $request): View|JsonResponse
    {
        $context = $this->context($request, 'المشرفون والعمال', 'Supervisors and workers');
        $employees = $this->directory->browse($request, true);

        return $request->expectsJson() ? response()->json(['data' => $employees])->header('Cache-Control', 'private, no-store') : view('employees.index', $context + $this->directory->options($request) + ['employees' => $employees, 'workersOnly' => true, 'summary' => $this->summary($request, true), 'canCreateWorker' => in_array('employees.write', $context['permissions'], true) && $this->directory->workerDefaults($request) !== null]);
    }

    public function create(Request $request): View
    {
        $context = $this->context($request, 'إضافة موظف', 'Add employee');
        $this->access->requirePermission($context['actor'], 'employees.write');
        $manageAll = in_array('employees.manage_all', $context['permissions'], true);
        $workerDefaults = $manageAll ? null : $this->directory->workerDefaults($request);
        abort_if(! $manageAll && $workerDefaults === null, 403);

        return view('employees.form', $context + $this->directory->options($request) + ['employee' => null, 'workerCreate' => ! $manageAll, 'workerDefaults' => $workerDefaults ?? []]);
    }

    public function edit(Request $request, string $employee): View
    {
        $context = $this->context($request, 'تعديل الموظف', 'Edit employee');
        $this->access->requirePermission($context['actor'], 'employees.write');
        $profile = $this->directory->find($request, $employee);

        return view('employees.form', $context + $this->directory->options($request, $profile) + ['employee' => $profile, 'workerCreate' => false, 'workerDefaults' => []]);
    }

    public function show(Request $request, string $employee): View|JsonResponse
    {
        $context = $this->context($request, 'تفاصيل الموظف', 'Employee details');
        $profile = $this->directory->find($request, $employee);
        if ($request->expectsJson()) {
            return response()->json(['data' => $profile])->header('Cache-Control', 'private, no-store');
        }
        $tab = $request->query('tab', 'overview');
        $tab = in_array($tab, ['overview', 'workers', 'evidence', 'audit'], true) ? $tab : 'overview';
        $history = in_array($tab, ['evidence', 'audit'], true) ? $this->directory->history($request, $employee) : ['audit' => [], 'evidence' => []];
        $tasks = [];
        if ($request->query('tab') === 'evidence' && in_array('tasks.read', $context['permissions'], true)) {
            $lookup = $request->validate(['task_id' => ['nullable', 'string', 'size:26']]);
            if (! empty($lookup['task_id'])) {
                $tasks[] = $this->tasks->find($this->identity->fromRequest($request), $lookup['task_id'], withDetails: false);
            }
        }

        $auditEntries = array_map(static fn (array $entry): array => ['action' => (string) $entry['action'], 'actor' => $entry['actor_id'] === $context['actor']['id'] ? $context['actor']['name'] : $entry['actor_id'], 'date' => (string) $entry['created_at'], 'description' => ''], $history['audit']);

        return view('employees.show', $context + $history + ['employee' => $profile, 'tasks' => $tasks, 'auditEntries' => $auditEntries, 'reports' => $this->directory->browse($request, managerId: $employee), 'profileTab' => $tab]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->context($request);
        $profile = $this->directory->save($request, $this->input($request));

        return $this->saved($request, $profile, 201);
    }

    public function update(Request $request, string $employee): JsonResponse|RedirectResponse
    {
        $this->context($request);

        return $this->saved($request, $this->directory->save($request, $this->input($request), $employee));
    }

    public function evidence(Request $request, string $employee): JsonResponse|RedirectResponse
    {
        $this->context($request);
        $evidence = $this->directory->recordEvidence($request, $employee, $this->input($request));

        return $request->expectsJson() ? response()->json(['data' => $evidence], 201) : redirect('/employees/'.rawurlencode($employee).'?locale='.$this->locale($request).'&tab=evidence')->with('status', $this->locale($request) === 'ar' ? 'تم تسجيل دليل العمل' : 'Work evidence recorded');
    }

    /** @return array{actor: array<string, mixed>, permissions: list<string>, locale: string, title: string} */
    private function context(Request $request, string $arabic = 'الموظفون', string $english = 'Employees'): array
    {
        abort_unless(app()->environment(['local', 'testing']) === true, 404);
        $actor = $this->access->actor($request);
        $this->access->requirePermission($actor, 'employees.read');
        $locale = $this->locale($request);
        app()->setLocale($locale);

        $title = $locale === 'ar' ? $arabic : $english;

        return ['actor' => $actor, 'permissions' => $this->access->permissions((string) $actor['id']), 'locale' => $locale, 'title' => $title, 'pageIcon' => 'users', 'pageSubtitle' => $locale === 'ar' ? 'بيانات الموظفين والمشرفين والعمال ضمن نطاق صلاحياتك' : 'Employees, supervisors and workers within your authorized scope', 'breadcrumbs' => [['label' => $locale === 'ar' ? 'مركز العمل' : 'Workspace', 'url' => '/dashboard'], ['label' => $locale === 'ar' ? 'الموظفون' : 'Employees', 'url' => '/employees?locale='.$locale], ['label' => $title]]];
    }

    private function locale(Request $request): string
    {
        return $request->query('locale') === 'en' ? 'en' : 'ar';
    }

    /** @return array<string, mixed> */
    private function input(Request $request): array
    {
        return Arr::except($request->isJson() ? $request->json()->all() : $request->request->all(), ['_token', 'locale', '_method']);
    }

    /** @return list<array<string, mixed>> */
    private function summary(Request $request, bool $workersOnly): array
    {
        $counts = $this->directory->counts($request, $workersOnly);
        $ar = $this->locale($request) === 'ar';

        return [['label' => $ar ? 'إجمالي الموظفين' : 'Total employees', 'value' => (string) $counts['total'], 'icon' => 'users', 'tone' => 'info'], ['label' => $ar ? 'الموظفون النشطون' : 'Active employees', 'value' => (string) $counts['active'], 'icon' => 'users', 'tone' => 'success'], ['label' => $ar ? 'بدون حساب دخول' : 'Without a login account', 'value' => (string) $counts['without_login'], 'icon' => 'shield', 'tone' => 'warning'], ['label' => $ar ? 'الموقوفون' : 'Suspended employees', 'value' => (string) $counts['suspended'], 'icon' => 'clock', 'tone' => 'danger']];
    }

    /** @param array<string, mixed> $profile */
    private function saved(Request $request, array $profile, int $status = 200): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['data' => $profile], $status) : redirect('/employees/'.rawurlencode((string) $profile['id']).'?locale='.$this->locale($request))->with('status', $this->locale($request) === 'ar' ? 'تم حفظ الموظف' : 'Employee saved');
    }
}

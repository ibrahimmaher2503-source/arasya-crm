@php
    $t = static fn (string $ar, string $en): string => $locale === 'ar' ? $ar : $en;
    $statuses = [
        'pending' => ['ar' => 'قيد الانتظار', 'en' => 'Pending', 'tone' => 'info'],
        'in_progress' => ['ar' => 'قيد التنفيذ', 'en' => 'In progress', 'tone' => 'warning'],
        'completed' => ['ar' => 'مكتملة', 'en' => 'Completed', 'tone' => 'success'],
        'cancelled' => ['ar' => 'ملغاة', 'en' => 'Cancelled', 'tone' => 'neutral'],
    ];
    $priorities = [
        'low' => $t('منخفضة', 'Low'),
        'normal' => $t('متوسطة', 'Normal'),
        'high' => $t('عالية', 'High'),
    ];
    $sorts = [
        'due_at' => $t('تاريخ الاستحقاق', 'Due date'),
        'title' => $t('المهمة', 'Task'),
        'priority' => $t('الأولوية', 'Priority'),
        'status' => $t('الحالة', 'Status'),
        'updated_at' => $t('آخر تحديث', 'Last updated'),
    ];
    $dateText = fn ($value): string => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : $t('غير محدد', 'Not set');
@endphp
@extends('layouts.shell')
@section('content')
<style>{!! file_get_contents(resource_path('css/employee-work.css')) !!}</style>
<div class="ui-stack employee-work" data-testid="employee-work">
    <x-ui.summary-strip
        class="employee-work__summary"
        data-testid="work-summary"
        variant="cards"
        :data-summary-total="$summaryCounts['total']"
        :items="$summary"
    />

    <form method="get" action="{{ route('employees.work') }}" class="ui-filter-bar admin-toolbar admin-section employee-work__filters" aria-label="{{ $t('البحث والتصفية في مهام الموظف', 'Search and filter employee work') }}">
        <input type="hidden" name="locale" value="{{ $locale }}">
        <x-ui.field id="work-search" class="employee-work__search" :label="$t('البحث', 'Search')">
            <x-ui.search-input id="work-search" name="q" :value="$filters['q'] ?? ''" :placeholder="$t('ابحث باسم الموظف أو المهمة', 'Search employee or task')" />
        </x-ui.field>
        <x-ui.field id="work-employee" :label="$t('الموظف', 'Employee')">
            <x-ui.select id="work-employee" name="employee_id" :disabled="$employee === null">
                @if($employee)
                    <option value="{{ $employee['id'] }}" selected>{{ $employee['name'] }}</option>
                @else
                    <option value="">{{ $t('بيانات الموظف غير متاحة', 'Employee profile unavailable') }}</option>
                @endif
            </x-ui.select>
        </x-ui.field>
        <x-ui.field id="work-branch" :label="$t('الفرع', 'Branch')">
            <x-ui.select id="work-branch" name="branch_id">
                <option value="">{{ $t('كل النطاق المصرح', 'All authorized scope') }}</option>
                @if($branchId)
                    <option value="{{ $branchId }}" @selected(($filters['branch_id'] ?? '') === $branchId)>{{ $branchLabel }}</option>
                @endif
            </x-ui.select>
        </x-ui.field>
        <x-ui.field id="work-department" :label="$t('القسم', 'Department')">
            <x-ui.select id="work-department" name="department_id">
                <option value="">{{ $t('كل النطاق المصرح', 'All authorized scope') }}</option>
                @if($departmentId)
                    <option value="{{ $departmentId }}" @selected(($filters['department_id'] ?? '') === $departmentId)>{{ $departmentLabel }}</option>
                @endif
            </x-ui.select>
        </x-ui.field>
        <x-ui.field id="work-supervisor" :label="$t('المشرف', 'Supervisor')" :hint="$t('غير متاح: سجل المهمة لا يتضمن إسناداً للمشرف.', 'Unavailable: task records do not include supervisor attribution.')">
            <x-ui.select id="work-supervisor" name="supervisor_id" disabled aria-describedby="work-supervisor-hint">
                <option value="">{{ $t('غير متاح ضمن بيانات المهام', 'Unavailable in task data') }}</option>
            </x-ui.select>
        </x-ui.field>
        <x-ui.field id="work-date-from" :label="$t('من تاريخ الاستحقاق', 'Due from')">
            <x-ui.input id="work-date-from" type="date" name="date_from" :value="$filters['date_from'] ?? ''" />
        </x-ui.field>
        <x-ui.field id="work-date-to" :label="$t('إلى تاريخ الاستحقاق', 'Due to')">
            <x-ui.input id="work-date-to" type="date" name="date_to" :value="$filters['date_to'] ?? ''" />
        </x-ui.field>
        <x-ui.field id="work-status" :label="$t('الحالة', 'Status')">
            <x-ui.select id="work-status" name="status">
                <option value="">{{ $t('كل الحالات', 'All statuses') }}</option>
                @foreach($statuses as $value => $status)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $status[$locale] }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.list-controls :locale="$locale" :sorts="$sorts" />
        <div class="employee-work__filter-actions">
            <x-ui.button type="submit">{{ $t('تطبيق', 'Apply filters') }}</x-ui.button>
            <a class="ui-button ui-button--secondary" href="{{ route('employees.work', ['locale' => $locale]) }}">{{ $t('مسح', 'Clear') }}</a>
        </div>
    </form>

    <section class="admin-section employee-work__profile" aria-label="{{ $t('ملخص الموظف', 'Employee summary') }}" data-testid="employee-profile">
        @if($employee)
            <div class="employee-work__identity">
                <x-ui.avatar :name="$employee['name']" />
                <div>
                    <h2>{{ $employee['name'] }}</h2>
                    <p class="ui-muted">{{ $employee['role'] }} · {{ $employee['code'] }}</p>
                    <x-ui.status-badge :status="match($employee['status'] ?? '') { 'active' => 'success', 'on_leave' => 'warning', 'suspended' => 'danger', 'terminated' => 'neutral', default => 'neutral' }">
                        {{ ['active' => $t('نشط', 'Active'), 'on_leave' => $t('في إجازة', 'On leave'), 'suspended' => $t('موقوف', 'Suspended'), 'terminated' => $t('انتهت الخدمة', 'Terminated')][$employee['status'] ?? ''] ?? $t('غير محدد', 'Unknown') }}
                    </x-ui.status-badge>
                </div>
            </div>
            <dl class="employee-work__profile-fields">
                <div><dt>{{ $t('الفرع', 'Branch') }}</dt><dd>{{ $employee['branch_name'] ?: $t('غير متاح', 'Unavailable') }}</dd></div>
                <div><dt>{{ $t('القسم', 'Department') }}</dt><dd>{{ $employee['department_name'] ?: $t('غير متاح', 'Unavailable') }}</dd></div>
                <div><dt>{{ $t('المشرف المباشر', 'Supervisor') }}</dt><dd>{{ $employee['manager_name'] ?: $t('غير متاح', 'Unavailable') }}</dd></div>
            </dl>
        @else
            <div class="employee-work__profile-unavailable" role="status">
                <x-ui.icon name="users" />
                <div>
                    <h2>{{ $t('بيانات ملف الموظف غير متاحة', 'Employee profile unavailable') }}</h2>
                    <p class="ui-muted">{{ $t('تعرض هذه الصفحة المهام المصرح بها لهويتك فقط.', 'This page still shows tasks authorized to your identity only.') }}</p>
                </div>
            </div>
        @endif
    </section>

    <div class="employee-work__layout">
        <section class="admin-section employee-work__task-section" aria-labelledby="work-tasks-heading">
            <header class="admin-section-heading">
                <div>
                    <h2 id="work-tasks-heading"><x-ui.icon name="grid" />{{ $t('قائمة المهام', 'Task list') }}</h2>
                    <p class="ui-muted">{{ $t('النتائج مرتبة ومحدودة ضمن نطاق الوصول الحالي.', 'Results are ordered and bounded to the current access scope.') }}</p>
                </div>
            </header>
            <x-ui.table :caption="$t('مهام الموظف المصرح بها', 'Authorized employee tasks')" class="employee-work__table">
                <thead>
                    <tr>
                        <th scope="col">{{ $t('المهمة', 'Task') }}</th>
                        <th scope="col">{{ $t('الأولوية', 'Priority') }}</th>
                        <th scope="col">{{ $t('الحالة', 'Status') }}</th>
                        <th scope="col">{{ $t('تاريخ الاستحقاق', 'Due date') }}</th>
                        <th scope="col">{{ $t('آخر تحديث', 'Last updated') }}</th>
                        <th scope="col">{{ $t('الإجراء', 'Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        <tr data-task-id="{{ $task->id }}">
                            <th scope="row"><a href="{{ route('tasks.show', ['task' => $task->id, 'locale' => $locale]) }}">{{ $task->title }}</a></th>
                            <td>{{ $priorities[$task->priority] ?? $task->priority }}</td>
                            <td>
                                <x-ui.status-badge :status="$statuses[$task->status]['tone'] ?? 'neutral'" data-testid="task-status" data-status="{{ $task->status }}">
                                    {{ $statuses[$task->status][$locale] ?? $task->status }}
                                </x-ui.status-badge>
                            </td>
                            <td>{{ $dateText($task->due_at) }}</td>
                            <td>{{ $dateText($task->updated_at) }}</td>
                            <td><a href="{{ route('tasks.show', ['task' => $task->id, 'locale' => $locale]) }}">{{ $t('فتح المهمة', 'Open task') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state :title="$t('لا توجد مهام مطابقة', 'No tasks match')" :description="$t('جرّب تعديل عوامل التصفية أو امسحها لعرض المهام المصرح بها.', 'Adjust or clear filters to see authorized tasks.')" /></td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$tasks" :locale="$locale" />
        </section>

        <aside class="employee-work__side" aria-label="{{ $t('متابعة إضافية', 'Additional follow-up') }}">
            <section class="admin-section" aria-labelledby="today-work-heading">
                <header class="admin-section-heading">
                    <h2 id="today-work-heading"><x-ui.icon name="clock" />{{ $t('مهام مستحقة اليوم', 'Due today') }}</h2>
                </header>
                @if($todayTasks->isNotEmpty())
                    <ul class="employee-work__panel-list" data-testid="today-work-list">
                        @foreach($todayTasks as $task)
                            <li>
                                <a href="{{ route('tasks.show', ['task' => $task->id, 'locale' => $locale]) }}">{{ $task->title }}</a>
                                <time datetime="{{ $task->due_at }}">{{ $dateText($task->due_at) }}</time>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-ui.empty-state :title="$t('لا توجد مهام مستحقة اليوم', 'No tasks are due today')" :description="$t('لا توجد مهام ضمن نطاقك مصنفة للاستحقاق اليوم.', 'No tasks in your authorized scope are due today.')" />
                @endif
            </section>

            <section class="admin-section" aria-labelledby="task-evidence-heading">
                <header class="admin-section-heading">
                    <h2 id="task-evidence-heading"><x-ui.icon name="audit" />{{ $t('إثباتات المهام', 'Task evidence') }}</h2>
                </header>
                @if(count($taskEvidence))
                    <ul class="employee-work__panel-list" data-testid="task-evidence-list">
                        @foreach($taskEvidence as $evidence)
                            <li>
                                <span>{{ $evidence->name }}</span>
                                <a href="{{ route('tasks.show', ['task' => $evidence->task_id, 'locale' => $locale]) }}">{{ $evidence->title }}</a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-ui.empty-state :title="$t('لا توجد إثباتات مسجلة', 'No task evidence recorded')" :description="$t('تظهر هنا الإثباتات المسجلة فعلياً للمهام المصرح بها.', 'Only evidence already recorded for authorized tasks appears here.')" />
                @endif
            </section>

            <section class="admin-section" aria-labelledby="employee-notes-heading">
                <header class="admin-section-heading">
                    <h2 id="employee-notes-heading"><x-ui.icon name="audit" />{{ $t('ملاحظات إثبات العمل', 'Work-evidence notes') }}</h2>
                </header>
                @if($employee === null)
                    <x-ui.empty-state :title="$t('ملاحظات الموظف غير متاحة', 'Employee notes unavailable')" :description="$t('يتطلب عرضها ملفاً مرتبطاً وصلاحية قراءة الموظفين.', 'A linked profile and employee read permission are required.')" />
                @elseif(count($employeeEvidence))
                    <ul class="employee-work__panel-list" data-testid="employee-notes-list">
                        @foreach($employeeEvidence as $note)
                            <li>
                                <p>{{ $note['note'] }}</p>
                                <a href="{{ route('tasks.show', ['task' => $note['job_id'], 'locale' => $locale]) }}">{{ $t('فتح المهمة المرتبطة', 'Open linked task') }}</a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-ui.empty-state :title="$t('لا توجد ملاحظات إثبات مسجلة', 'No work-evidence notes recorded')" :description="$t('تعرض الملاحظات المرتبطة بمهام يمكنك الوصول إليها فقط.', 'Only notes linked to tasks you can access are shown.')" />
                @endif
            </section>
        </aside>
    </div>
</div>
@endsection

@php
    $locale = ($locale ?? request()->query('locale', 'ar')) === 'en' ? 'en' : 'ar';
    $t = static fn (string $arabic, string $english): string => $locale === 'ar' ? $arabic : $english;
    $statusLabels = [
        'pending' => ['variant' => 'warning', 'label' => $t('قيد الانتظار', 'Pending')],
        'in_progress' => ['variant' => 'info', 'label' => $t('قيد التنفيذ', 'In progress')],
        'completed' => ['variant' => 'success', 'label' => $t('مكتملة', 'Completed')],
        'cancelled' => ['variant' => 'neutral', 'label' => $t('ملغاة', 'Cancelled')],
    ];
    $priorityLabels = [
        'low' => $t('منخفضة', 'Low'),
        'normal' => $t('عادية', 'Normal'),
        'high' => $t('عالية', 'High'),
    ];
    $sortLabels = [
        'due_at' => $t('تاريخ الاستحقاق', 'Due date'),
        'title' => $t('عنوان المهمة', 'Task title'),
        'status' => $t('الحالة', 'Status'),
        'priority' => $t('الأولوية', 'Priority'),
        'created_at' => $t('تاريخ الإضافة', 'Created date'),
    ];
    $summaryItems = [
        ['label' => $t('إجمالي المهام', 'Total tasks'), 'value' => $summary['total'], 'icon' => 'grid', 'description' => $t('ضمن نطاقك المصرح به', 'Within your authorized scope')],
        ['label' => $t('تستحق اليوم', 'Due today'), 'value' => $summary['due_today'], 'icon' => 'clock', 'tone' => 'warning'],
        ['label' => $t('متأخرة', 'Overdue'), 'value' => $summary['overdue'], 'icon' => 'clock', 'tone' => 'danger'],
        ['label' => $t('مكتملة هذا الأسبوع', 'Completed this week'), 'value' => $summary['completed_this_week'], 'icon' => 'audit', 'tone' => 'success'],
        [
            'label' => $t('إنجاز مهام الأسبوع', 'Weekly completion'),
            'value' => $summary['week_completion_share'] === null ? '—' : $summary['week_completion_share'].'%',
            'icon' => 'grid',
            'tone' => 'info',
            'description' => $summary['week_completion_share'] === null
                ? $t('لا توجد مهام مستحقة هذا الأسبوع', 'No tasks due this week')
                : $week['completed'].' / '.$week['due'].' '.$t('مهمة مستحقة مكتملة', 'due tasks completed'),
        ],
    ];
    $hasActiveFilters = filled($filters['q'] ?? null)
        || filled($filters['status'] ?? null)
        || filled($filters['priority'] ?? null)
        || filled($filters['due_preset'] ?? null)
        || filled($filters['due_from'] ?? null)
        || filled($filters['due_to'] ?? null);
@endphp
@extends('layouts.shell', ['locale' => $locale])

@section('content')
    <style>{!! file_get_contents(resource_path('css/tasks.css')) !!}</style>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="ui-stack tasks-workspace">
        <x-ui.summary-strip
            class="tasks-summary"
            data-testid="tasks-summary"
            :aria-label="$t('ملخص المهام', 'Task summary')"
            :items="$summaryItems"
        />

        @if ($canCreate)
            <div class="tasks-workspace__actions">
                <a class="ui-button ui-button--primary" data-testid="tasks-create-action" href="{{ route('tasks.create', ['locale' => $locale]) }}">{{ $t('مهمة جديدة', 'New task') }}</a>
            </div>
        @endif

        <x-ui.filter-bar
            id="tasks-filter"
            class="tasks-filter-bar"
            :action="route('tasks.index')"
            :aria-label="$t('البحث والتصفية', 'Search and filters')"
        >
            <input type="hidden" name="locale" value="{{ $locale }}">
            <x-ui.field id="tasks-filter-q" :label="$t('بحث في المهام', 'Search tasks')">
                <x-ui.search-input id="tasks-filter-q" name="q" :value="$filters['q'] ?? ''" :placeholder="$t('ابحث بعنوان المهمة', 'Search task titles')" maxlength="120" />
            </x-ui.field>
            <x-ui.field id="tasks-filter-status" :label="$t('الحالة', 'Status')">
                <x-ui.select id="tasks-filter-status" name="status">
                    <option value="">{{ $t('كل الحالات', 'All statuses') }}</option>
                    @foreach ($statusLabels as $value => $status)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $status['label'] }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field id="tasks-filter-priority" :label="$t('الأولوية', 'Priority')">
                <x-ui.select id="tasks-filter-priority" name="priority">
                    <option value="">{{ $t('كل الأولويات', 'All priorities') }}</option>
                    @foreach ($priorityLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field id="tasks-filter-due-preset" :label="$t('موعد الاستحقاق', 'Due date')">
                <x-ui.select id="tasks-filter-due-preset" name="due_preset">
                    <option value="">{{ $t('كل المواعيد', 'Any due date') }}</option>
                    <option value="today" @selected(($filters['due_preset'] ?? '') === 'today')>{{ $t('اليوم', 'Today') }}</option>
                    <option value="overdue" @selected(($filters['due_preset'] ?? '') === 'overdue')>{{ $t('متأخرة', 'Overdue') }}</option>
                    <option value="upcoming" @selected(($filters['due_preset'] ?? '') === 'upcoming')>{{ $t('الأيام السبعة القادمة', 'Next seven days') }}</option>
                </x-ui.select>
            </x-ui.field>
            <div class="tasks-filter-actions">
                <x-ui.button type="submit">{{ $t('تطبيق', 'Apply') }}</x-ui.button>
                <a href="{{ route('tasks.index', ['locale' => $locale]) }}">{{ $t('إعادة الضبط', 'Reset') }}</a>
            </div>
            <details class="ui-filter-bar__advanced tasks-filter-advanced" @if (filled($filters['due_from'] ?? null) || filled($filters['due_to'] ?? null)) open @endif>
                <summary>{{ $t('نطاق التاريخ والترتيب', 'Date range and sorting') }}</summary>
                <div class="tasks-filter-advanced__fields">
                    <x-ui.field id="tasks-filter-due-from" :label="$t('من تاريخ', 'Due from')">
                        <x-ui.date-input id="tasks-filter-due-from" name="due_from" :value="$filters['due_from'] ?? ''" />
                    </x-ui.field>
                    <x-ui.field id="tasks-filter-due-to" :label="$t('إلى تاريخ', 'Due to')">
                        <x-ui.date-input id="tasks-filter-due-to" name="due_to" :value="$filters['due_to'] ?? ''" />
                    </x-ui.field>
                    <x-ui.list-controls :locale="$locale" :sorts="$sortLabels" />
                </div>
            </details>
        </x-ui.filter-bar>

        <div class="tasks-layout-grid">
            <section class="tasks-main-column" aria-label="{{ $t('قائمة المهام', 'Task list') }}">
                <div class="tasks-list-summary">
                    <h2>{{ $t('قائمة المهام', 'Task list') }}</h2>
                    <p class="ui-muted">{{ $tasks->total() }} {{ $t('مهمة ضمن الفلاتر الحالية', 'tasks match the current filters') }}</p>
                </div>
                @if ($tasks->isEmpty())
                    <x-ui.empty-state
                        data-testid="tasks-empty-state"
                        :title="$hasActiveFilters ? $t('لا توجد نتائج مطابقة', 'No matching tasks') : $t('لا توجد مهام بعد', 'No tasks yet')"
                        :description="$hasActiveFilters ? $t('جرّب تعديل معايير البحث أو أعد ضبط الفلاتر.', 'Try changing your search or reset the filters.') : $t('ستظهر هنا المهام المسندة إليك ضمن نطاقك.', 'Tasks assigned within your scope will appear here.')"
                    >
                        @if ($hasActiveFilters)
                            <a class="ui-button ui-button--secondary" href="{{ route('tasks.index', ['locale' => $locale]) }}">{{ $t('إعادة الضبط', 'Reset filters') }}</a>
                        @elseif ($canCreate)
                            <a class="ui-button ui-button--primary" href="{{ route('tasks.create', ['locale' => $locale]) }}">{{ $t('إنشاء مهمة', 'Create a task') }}</a>
                        @endif
                    </x-ui.empty-state>
                @else
                    <x-ui.table data-testid="tasks-table" :caption="$t('المهام اليومية', 'Daily tasks')">
                        <thead>
                            <tr>
                                <th scope="col">{{ $t('المهمة', 'Task') }}</th>
                                <th scope="col">{{ $t('الحالة', 'Status') }}</th>
                                <th scope="col">{{ $t('الأولوية', 'Priority') }}</th>
                                <th scope="col">{{ $t('تاريخ الاستحقاق', 'Due date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tasks as $task)
                                @php
                                    $status = $statusLabels[$task['status']] ?? ['variant' => 'neutral', 'label' => $task['status']];
                                    $priorityStatus = match ($task['priority']) {
                                        'high' => 'danger',
                                        'low' => 'neutral',
                                        default => 'info',
                                    };
                                @endphp
                                <tr data-task-id="{{ $task['id'] }}">
                                    <th scope="row"><a dir="auto" class="tasks-task-link" href="{{ route('tasks.show', ['task' => $task['id'], 'locale' => $locale]) }}">{{ $task['title'] }}</a></th>
                                    <td><x-ui.status-badge :status="$status['variant']">{{ $status['label'] }}</x-ui.status-badge></td>
                                    <td><x-ui.status-badge :status="$priorityStatus">{{ $priorityLabels[$task['priority']] ?? $task['priority'] }}</x-ui.status-badge></td>
                                    <td>
                                        @if ($task['due_at'])
                                            <x-erp.date-time-display :value="\Illuminate\Support\Carbon::parse($task['due_at'])->locale($locale)->translatedFormat('j M Y H:i')" :datetime="$task['due_at']" />
                                        @else
                                            <span class="ui-muted">{{ $t('غير محدد', 'Not set') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                @endif
                <x-ui.list-pagination :paginator="$tasks" :locale="$locale" />
            </section>

            <aside class="tasks-context-column" aria-label="{{ $t('جدول المهام القادمة', 'Upcoming task schedule') }}">
                <x-ui.detail-panel data-testid="tasks-today" :title="$t('مواعيد اليوم', 'Due today')">
                    @if ($today)
                        <ul data-testid="tasks-today-list" class="tasks-context-list">
                            @foreach ($today as $task)
                                <li>
                                    <a href="{{ route('tasks.show', ['task' => $task['id'], 'locale' => $locale]) }}">{{ $task['title'] }}</a>
                                    <x-erp.date-time-display :value="\Illuminate\Support\Carbon::parse($task['due_at'])->locale($locale)->translatedFormat('H:i')" :datetime="$task['due_at']" />
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="ui-muted">{{ $t('لا توجد مهام مفتوحة مستحقة اليوم.', 'No open tasks are due today.') }}</p>
                    @endif
                </x-ui.detail-panel>
                <x-ui.detail-panel data-testid="tasks-upcoming" :title="$t('الأيام السبعة القادمة', 'Next seven days')">
                    @if ($upcoming)
                        <ul data-testid="tasks-upcoming-list" class="tasks-context-list">
                            @foreach ($upcoming as $task)
                                <li>
                                    <a href="{{ route('tasks.show', ['task' => $task['id'], 'locale' => $locale]) }}">{{ $task['title'] }}</a>
                                    <x-erp.date-time-display :value="\Illuminate\Support\Carbon::parse($task['due_at'])->locale($locale)->translatedFormat('j M')" :datetime="$task['due_at']" />
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="ui-muted">{{ $t('لا توجد مهام مفتوحة قادمة خلال الأيام السبعة المقبلة.', 'No open tasks are due in the next seven days.') }}</p>
                    @endif
                </x-ui.detail-panel>
            </aside>
        </div>

        <div class="tasks-lower-grid">
            <x-ui.detail-panel data-testid="tasks-week-stats" :title="$t('ملخص هذا الأسبوع', 'This week')">
                <dl class="tasks-week-stats">
                    <div><dt>{{ $t('المهام المستحقة', 'Tasks due') }}</dt><dd>{{ $week['due'] }}</dd></div>
                    <div><dt>{{ $t('المكتملة', 'Completed') }}</dt><dd>{{ $week['completed'] }}</dd></div>
                    <div><dt>{{ $t('المتبقية', 'Remaining') }}</dt><dd>{{ $week['remaining'] }}</dd></div>
                </dl>
            </x-ui.detail-panel>
            <x-ui.detail-panel data-testid="tasks-evidence" :title="$t('أدلة المهام', 'Task evidence')">
                @if ($evidence)
                    <ul class="tasks-evidence-list">
                        @foreach ($evidence as $item)
                            <li>
                                <a href="{{ route('tasks.download', ['task' => $item['task_id'], 'evidence' => $item['evidence_id']]) }}">{{ $item['name'] }}</a>
                                <span class="ui-muted">{{ $item['title'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="ui-muted">{{ $t('لا توجد أدلة مرفقة ضمن مهامك الحالية.', 'No evidence is attached to tasks in your current scope.') }}</p>
                @endif
            </x-ui.detail-panel>
        </div>
    </div>
@endsection

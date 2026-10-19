@php
    $kindLabels = ['call' => $t('اتصال هاتفي', 'Phone call'), 'message' => $t('رسالة', 'Message'), 'meeting' => $t('اجتماع', 'Meeting'), 'visit' => $t('زيارة', 'Visit'), 'note' => $t('ملاحظة', 'Note'), 'quotation' => $t('عرض سعر', 'Quotation')];
    $kindIcons = ['call' => 'phone', 'message' => 'mail', 'meeting' => 'users', 'visit' => 'building', 'note' => 'audit', 'quotation' => 'audit'];
    $stageLabel = $stageLabels[$lead['stage_id'] ?? ''] ?? $t('بدون مرحلة', 'No stage');
    $baseUrl = '/crm/leads/'.$lead['id'].'/workspace/'.$section;
    $canWrite = ! $lead['archived'] && in_array('crm.write', $permissions, true);
    $addLabel = $section === 'notes' ? $t('إضافة ملاحظة', 'Add note') : ($section === 'tasks' ? $t('إضافة متابعة', 'Add follow-up') : $t('إضافة نشاط', 'Add activity'));
    $taskStatuses = ['pending' => $t('قيد الانتظار', 'Pending'), 'in_progress' => $t('قيد التنفيذ', 'In progress'), 'completed' => $t('مكتملة', 'Completed'), 'cancelled' => $t('ملغاة', 'Cancelled')];
    $taskPriorities = ['high' => $t('عالية', 'High'), 'normal' => $t('متوسطة', 'Normal'), 'low' => $t('منخفضة', 'Low')];
    $chartLabels = $section === 'tasks' ? $taskStatuses : $kindLabels;
    $chartCounts = $section === 'tasks' ? $rows->getCollection()->pluck('task')->filter()->countBy('status')->all() : $kindCounts;
@endphp
@extends('layouts.shell')
@section('content')
<div class="ui-stack crm-page">
    @include('crm._entity-header')
    @include('crm._workspace-nav')

    <div @class(['crm-workspace-layout', 'crm-workspace-layout--detail' => $section === 'notes'])>
        <section class="ui-detail-panel crm-workspace-main" aria-label="{{ $title }}">
            <x-ui.filter-bar :action="$baseUrl" class="crm-workspace-filters">
                <input type="hidden" name="locale" value="{{ $locale }}">
                <x-ui.search-input name="q" :value="request('q')" :aria-label="$t('البحث في السجلات', 'Search records')" :placeholder="$t('البحث في السجلات...', 'Search records...')" />
                @if ($section !== 'notes')
                    <x-ui.select name="kind" :aria-label="$t('نوع النشاط', 'Activity type')">
                        <option value="">{{ $t('جميع الأنواع', 'All types') }}</option>
                        @foreach ($kindLabels as $kind => $label)<option value="{{ $kind }}" @selected(request('kind') === $kind)>{{ $label }}</option>@endforeach
                    </x-ui.select>
                @endif
                <x-ui.button type="submit" variant="secondary"><x-ui.icon name="filter" />{{ $t('تطبيق', 'Apply') }}</x-ui.button>
                <a class="ui-button ui-button--ghost" href="{{ $baseUrl }}?locale={{ $locale }}">{{ $t('مسح', 'Clear') }}</a>
                @if ($canWrite && ($section !== 'tasks' || in_array('tasks.write', $permissions, true)))
                    <x-ui.button data-open-dialog="crm-workspace-create"><x-ui.icon name="plus" />{{ $addLabel }}</x-ui.button>
                @endif
            </x-ui.filter-bar>

            <x-ui.table :caption="$title" class="crm-workspace-table">
                <thead><tr>
                    <th scope="col">{{ $t('العنوان / الخطوة التالية', 'Title / next step') }}</th>
                    <th scope="col">{{ $t('النوع', 'Type') }}</th>
                    <th scope="col">{{ $section === 'tasks' ? $t('الحالة', 'Status') : $t('النتيجة', 'Outcome') }}</th>
                    @if ($section === 'tasks')<th scope="col">{{ $t('الأولوية', 'Priority') }}</th>@endif
                    <th scope="col">{{ $t('المسؤول', 'Author') }}</th>
                    <th scope="col">{{ $t('التاريخ والوقت', 'Date and time') }}</th>
                    <th scope="col">{{ $t('الإجراءات', 'Actions') }}</th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr @if (($selectedNote['id'] ?? null) === $row['id']) class="crm-workspace-row-selected" @endif>
                        <td><strong>{{ $row['next_step'] }}</strong>@if ($section !== 'tasks' && filled($row['note']))<p class="ui-muted crm-workspace-note-excerpt">{{ $row['note'] }}</p>@endif</td>
                        <td><span class="ui-inline"><x-ui.icon :name="$kindIcons[$row['kind']] ?? 'audit'" />{{ $kindLabels[$row['kind']] ?? $row['kind'] }}</span></td>
                        <td><x-ui.status-badge :status="($row['task']['status'] ?? '') === 'completed' ? 'success' : 'info'">{{ $section === 'tasks' ? ($taskStatuses[$row['task']['status'] ?? ''] ?? $t('غير متاحة', 'Unavailable')) : $row['outcome_label'] }}</x-ui.status-badge></td>
                        @if ($section === 'tasks')<td>{{ $taskPriorities[$row['task']['priority'] ?? ''] ?? '—' }}</td>@endif
                        <td><span class="ui-inline"><x-ui.avatar :name="$row['actor_name'] ?? $t('مستخدم', 'User')" />{{ $row['actor_name'] ?? '—' }}</span></td>
                        <td><bdi>{{ $row[$section === 'tasks' ? 'next_follow_up' : 'occurred_at'] ?? '—' }}</bdi></td>
                        <td>
                            @if ($section === 'notes')
                                <a class="ui-button ui-button--ghost" href="{{ request()->fullUrlWithQuery(['selected' => $row['id']]) }}">{{ $t('عرض', 'View') }}</a>
                            @elseif ($row['task'])
                                <a class="ui-button ui-button--ghost" href="/tasks/{{ $row['task_id'] }}?locale={{ $locale }}">{{ $t('فتح المهمة', 'Open task') }}</a>
                            @else
                                <span class="ui-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $section === 'tasks' ? 7 : 6 }}"><x-ui.empty-state :title="$t('لا توجد سجلات', 'No records found')" :description="$t('أضف سجلاً أو غيّر مرشحات البحث.', 'Add a record or change the search filters.')" /></td></tr>
                @endforelse
                </tbody>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$rows" :locale="$locale" />
        </section>

        <aside class="ui-stack crm-workspace-aside">
            @if ($section === 'notes' && $selectedNote)
                <x-ui.detail-panel :title="$selectedNote['next_step']" :aria-label="$t('الملاحظة المحددة', 'Selected note')">
                    <p class="ui-muted">{{ $selectedNote['actor_name'] }} · <bdi>{{ $selectedNote['occurred_at'] }}</bdi></p>
                    <x-ui.status-badge status="info">{{ $selectedNote['outcome_label'] }}</x-ui.status-badge>
                    <p class="crm-workspace-note-body">{{ $selectedNote['note'] }}</p>
                    <a class="ui-button ui-button--secondary" href="{{ request()->fullUrlWithoutQuery('selected') }}">{{ $t('إغلاق', 'Close') }}</a>
                </x-ui.detail-panel>
            @elseif ($section === 'notes')
                <x-ui.detail-panel :title="$t('تفاصيل الملاحظة', 'Note details')"><x-ui.empty-state :title="$t('اختر ملاحظة لعرضها', 'Select a note to view')" /></x-ui.detail-panel>
            @else
                <x-ui.stat-card :label="$t('نتائج العرض الحالي', 'Current results')" :value="(string) $rows->total()" />
                <x-ui.detail-panel :title="$section === 'tasks' ? $t('حالات المهام المتاحة في هذه الصفحة', 'Accessible task statuses on this page') : $t('الأنشطة حسب النوع', 'Activities by type')">
                    @php($chartOffset = 25)
                    <svg class="crm-distribution-chart" viewBox="0 0 42 42" role="img" aria-label="{{ $t('توزيع السجلات', 'Record distribution') }}">
                        <circle cx="21" cy="21" r="15.9155" fill="none" stroke="var(--color-border)" stroke-width="6" />
                        @foreach ($chartLabels as $kind => $label)
                            @php($chartValue = 100 * ($chartCounts[$kind] ?? 0) / max(1, array_sum($chartCounts)))
                            <circle class="crm-chart-segment crm-chart-segment--{{ $loop->index }}" cx="21" cy="21" r="15.9155" fill="none" stroke-width="6" stroke-dasharray="{{ $chartValue }} {{ 100 - $chartValue }}" stroke-dashoffset="{{ $chartOffset }}"><title>{{ $label }}: {{ $chartCounts[$kind] ?? 0 }}</title></circle>
                            @php($chartOffset -= $chartValue)
                        @endforeach
                        <text x="21" y="23" text-anchor="middle">{{ array_sum($chartCounts) }}</text>
                    </svg>
                    <ul class="crm-breakdown-list">
                        @foreach ($chartLabels as $kind => $label)
                            <li><div class="crm-breakdown-list__heading"><span class="ui-inline"><x-ui.icon :name="$kindIcons[$kind] ?? 'shield'" />{{ $label }}</span><strong>{{ $chartCounts[$kind] ?? 0 }}</strong></div></li>
                        @endforeach
                    </ul>
                </x-ui.detail-panel>
            @endif
        </aside>
    </div>

    @if ($canWrite && ($section !== 'tasks' || in_array('tasks.write', $permissions, true)))
        <x-ui.modal id="crm-workspace-create" :title="$addLabel" :close-label="$t('إغلاق', 'Close')">
            @include('crm._activity-form', ['activitySection' => $section, 'activityDialog' => 'crm-workspace-create'])
        </x-ui.modal>
    @endif
</div>
@endsection

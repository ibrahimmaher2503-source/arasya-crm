@php
    $t = static fn (string $ar, string $en): string => $locale === 'ar' ? $ar : $en;
    $statuses = [
        'active' => $t('نشط', 'Active'),
        'on_leave' => $t('في إجازة', 'On leave'),
        'suspended' => $t('موقوف', 'Suspended'),
        'terminated' => $t('انتهت الخدمة', 'Terminated'),
    ];
    $statusVariants = ['active' => 'success', 'on_leave' => 'info', 'suspended' => 'warning', 'terminated' => 'danger'];
    $sorts = [
        'code' => $t('رقم الموظف', 'Employee code'),
        'name' => $t('الاسم', 'Name'),
        'hire_date' => $t('تاريخ التعيين', 'Hire date'),
        'status' => $t('الحالة', 'Status'),
    ];
    $directoryPath = $workersOnly ? '/employees/workers' : '/employees';
    $canWrite = in_array('employees.write', $permissions, true);
    $manageAll = in_array('employees.manage_all', $permissions, true);
@endphp
@extends('layouts.shell')
@section('content')
<div class="ui-stack">
    <x-ui.summary-strip :items="$summary ?? []" />

    <form method="get" action="{{ $directoryPath }}" class="admin-toolbar admin-section" role="search" data-tour="{{ $workersOnly ? 'employees.workers.filters' : 'employees.filters' }}" aria-label="{{ $t('البحث والتصفية في دليل الموظفين', 'Search and filter the employee directory') }}">
        <input type="hidden" name="locale" value="{{ $locale }}">
        <x-ui.field id="employee-search" class="admin-search" :label="$t('البحث', 'Search')">
            <x-ui.search-input id="employee-search" name="q" :value="request('q')" :placeholder="$t('ابحث بالاسم أو الرقم أو الوظيفة', 'Search by name, code or job title')" />
        </x-ui.field>
        <x-ui.field id="employee-branch-filter" :label="$t('الفرع', 'Branch')">
            <x-ui.select id="employee-branch-filter" name="branch_id">
                <option value="">{{ $t('جميع الفروع', 'All branches') }}</option>
                @foreach(($branches ?? []) as $branch)
                    <option value="{{ $branch['id'] }}" @selected((string) request('branch_id', '') === (string) $branch['id'])>{{ $branch['name'] }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field id="employee-department-filter" :label="$t('القسم', 'Department')">
            <x-ui.select id="employee-department-filter" name="department_id">
                <option value="">{{ $t('جميع الأقسام', 'All departments') }}</option>
                @foreach(($departments ?? []) as $department)
                    <option value="{{ $department['id'] }}" @selected((string) request('department_id', '') === (string) $department['id'])>{{ $department['name'] }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field id="employee-manager-filter" :label="$t('المشرف المباشر', 'Supervisor')">
            <x-ui.select id="employee-manager-filter" name="manager_id">
                <option value="">{{ $t('جميع المشرفين', 'All supervisors') }}</option>
                @foreach(($managers ?? []) as $manager)
                    <option value="{{ $manager['id'] }}" @selected((string) request('manager_id', '') === (string) $manager['id'])>{{ $manager['name'] }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field id="employee-status-filter" :label="$t('الحالة', 'Status')">
            <x-ui.select id="employee-status-filter" name="status">
                <option value="">{{ $t('جميع الحالات', 'All statuses') }}</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status', '') === $value)>{{ $label }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.list-controls :locale="$locale" :sorts="$sorts" />
        <x-ui.button type="submit" variant="secondary">{{ $t('تطبيق', 'Apply filters') }}</x-ui.button>
        @if($canWrite && $manageAll)
            <a class="ui-button ui-button--primary" href="/employees/create?locale={{ $locale }}">{{ $t('إضافة موظف', 'Add employee') }}</a>
        @elseif($workersOnly && ($canCreateWorker ?? false))
            <a class="ui-button ui-button--primary" href="/employees/create?worker=1&amp;locale={{ $locale }}">{{ $t('إضافة عامل', 'Add worker') }}</a>
        @endif
    </form>

    @if($manageAll && !empty($optionSources))
        <x-ui.option-browser :sources="$optionSources" :locale="$locale" />
    @endif

    <section class="ui-stack" data-tour="{{ $workersOnly ? 'employees.workers.results' : 'employees.results' }}" aria-label="{{ $workersOnly ? $t('قائمة العمال', 'Worker list') : $t('قائمة الموظفين', 'Employee list') }}">
        <x-ui.table class="admin-table" :caption="$workersOnly ? $t('العمال ضمن نطاقك', 'Workers in your authorized scope') : $t('الموظفون ضمن نطاقك', 'Employees in your authorized scope')">
            <thead>
                <tr>
                    <th scope="col">{{ $t('الموظف', 'Employee') }}</th>
                    <th scope="col">{{ $t('رقم الموظف', 'Employee code') }}</th>
                    <th scope="col">{{ $t('الفرع', 'Branch') }}</th>
                    <th scope="col">{{ $t('القسم', 'Department') }}</th>
                    <th scope="col">{{ $t('المشرف المباشر', 'Supervisor') }}</th>
                    <th scope="col">{{ $t('الحالة', 'Status') }}</th>
                    <th scope="col">{{ $t('حساب الدخول', 'Login account') }}</th>
                    <th scope="col">{{ $t('الإجراءات', 'Actions') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($employees as $employee)
                @php($status = $employee['status'] ?? '')
                <tr>
                    <th scope="row">
                        <div class="admin-entity">
                            <x-ui.avatar :name="$employee['name'] ?? ''" />
                            <div>
                                <a href="/employees/{{ rawurlencode((string) $employee['id']) }}?locale={{ $locale }}">{{ $employee['name'] ?? '—' }}</a>
                                <small>{{ $employee['role'] ?? '—' }}</small>
                            </div>
                        </div>
                    </th>
                    <td><bdi>{{ $employee['code'] ?? '—' }}</bdi></td>
                    <td>{{ $employee['branch_name'] ?? '—' }}</td>
                    <td>{{ $employee['department_name'] ?? '—' }}</td>
                    <td>{{ $employee['manager_name'] ?? '—' }}</td>
                    <td><x-ui.status-badge :status="$statusVariants[$status] ?? 'neutral'">{{ $statuses[$status] ?? $t('غير محدد', 'Unknown') }}</x-ui.status-badge></td>
                    <td>
                        @if($employee['account_visible'] ?? false)
                        <x-ui.status-badge :status="filled($employee['user_id'] ?? null) ? 'success' : 'neutral'">
                            {{ filled($employee['user_id'] ?? null) ? $t('مرتبط', 'Linked') : $t('بدون حساب', 'No account') }}
                        </x-ui.status-badge>
                        @else
                            <span class="ui-muted">{{ $t('مقيد', 'Restricted') }}</span>
                        @endif
                    </td>
                    <td>
                        @if($canWrite)
                            <a href="/employees/{{ rawurlencode((string) $employee['id']) }}/edit?locale={{ $locale }}">{{ $t('تعديل', 'Edit') }}</a>
                        @else
                            <span class="ui-muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">{{ $t('لا توجد سجلات ضمن نطاقك الحالي.', 'No records in your current authorized scope.') }}</td></tr>
            @endforelse
            </tbody>
        </x-ui.table>
        <x-ui.list-pagination :paginator="$employees" :locale="$locale" />
    </section>
    <x-ui.page-tour :id="$workersOnly ? 'employees-workers' : 'employees-directory'" :locale="$locale" :actor="$actor" :permissions="$permissions" />
</div>
@endsection

@php
    $t = static fn (string $ar, string $en): string => $locale === 'ar' ? $ar : $en;
    $statuses = [
        'active' => $t('نشط', 'Active'),
        'on_leave' => $t('في إجازة', 'On leave'),
        'suspended' => $t('موقوف', 'Suspended'),
        'terminated' => $t('انتهت الخدمة', 'Terminated'),
    ];
    $statusVariants = ['active' => 'success', 'on_leave' => 'info', 'suspended' => 'warning', 'terminated' => 'danger'];
    $status = $employee['status'] ?? '';
    $canWrite = in_array('employees.write', $permissions, true);
    $canAudit = in_array('audit.read', $permissions, true);
    $canRecordEvidence = in_array('employees.evidence.write', $permissions, true) && in_array('tasks.read', $permissions, true);
    $tabOptions = [
        'overview' => $t('نظرة عامة', 'Overview'),
        'workers' => $t('الفريق', 'Workers'),
    ];
    if (in_array('tasks.read', $permissions, true)) {
        $tabOptions['evidence'] = $t('أدلة العمل', 'Work evidence');
    }
    if ($canAudit) {
        $tabOptions['audit'] = $t('سجل التدقيق', 'Audit history');
    }
    $profileTab = $profileTab ?? request('tab', 'overview');
    if (! array_key_exists($profileTab, $tabOptions)) {
        $profileTab = 'overview';
    }
    $accountStatus = filled($employee['user_id'] ?? null) ? $t('حساب دخول مرتبط', 'Login account linked') : $t('عامل بدون حساب دخول', 'Worker without a login account');
    if (! ($employee['account_visible'] ?? false)) {
        $accountStatus = $t('معلومات الحساب مقيدة', 'Account information restricted');
    }
    $reportsForCount = $reports ?? null;
    $reportCount = is_object($reportsForCount) && method_exists($reportsForCount, 'total')
        ? $reportsForCount->total()
        : (is_countable($reportsForCount) ? count($reportsForCount) : null);
    $valueOrUnavailable = fn (string $key) => filled($employee[$key] ?? null)
        ? $employee[$key]
        : $t('غير متاح', 'Unavailable');
@endphp
@extends('layouts.shell')
@section('content')
<style>{!! file_get_contents(resource_path('css/employee-profile.css')) !!}</style>
<div class="ui-stack employee-profile" data-testid="employee-profile">
    @if(session('status'))
        <x-ui.alert>{{ session('status') }}</x-ui.alert>
    @endif

    <section class="admin-section employee-profile__top-summary" data-testid="employee-profile-summary" aria-label="{{ $t('ملخص الموظف', 'Employee summary') }}">
        <div class="employee-profile__hero" role="group" aria-label="{{ $t('هوية الموظف', 'Employee identity') }}">
            <div class="employee-profile__identity">
                <x-ui.avatar :name="$employee['name'] ?? ''" class="employee-profile__avatar" />
                <div class="employee-profile__identity-copy">
                    <p class="employee-profile__eyebrow">{{ $t('ملف الموظف', 'Employee profile') }}</p>
                    <h2>{{ $employee['name'] ?? '' }}</h2>
                    <p class="employee-profile__role">
                        <span>{{ $employee['role'] ?? $t('غير متاح', 'Unavailable') }}</span>
                        <span aria-hidden="true">·</span>
                        <bdi>{{ $employee['code'] ?? '—' }}</bdi>
                    </p>
                </div>
            </div>
            <div class="employee-profile__hero-actions" role="group" aria-label="{{ $t('إجراءات الموظف', 'Employee actions') }}">
                @if($canWrite)
                    <a class="ui-button ui-button--primary" href="/employees/{{ rawurlencode((string) $employee['id']) }}/edit?locale={{ $locale }}">{{ $t('تعديل الموظف', 'Edit employee') }}</a>
                @endif
                <a class="ui-button ui-button--secondary" href="/employees?locale={{ $locale }}">{{ $t('دليل الموظفين', 'Employee directory') }}</a>
            </div>
        </div>
        <dl class="employee-profile__summary-list">
            <div>
                <dt><x-ui.icon name="building" />{{ $t('الفرع', 'Branch') }}</dt>
                <dd>{{ $valueOrUnavailable('branch_name') }}</dd>
            </div>
            <div>
                <dt><x-ui.icon name="grid" />{{ $t('القسم', 'Department') }}</dt>
                <dd>{{ $valueOrUnavailable('department_name') }}</dd>
            </div>
            <div>
                <dt><x-ui.icon name="users" />{{ $t('المشرف المباشر', 'Supervisor') }}</dt>
                <dd>{{ $valueOrUnavailable('manager_name') }}</dd>
            </div>
            <div>
                <dt><x-ui.icon name="clock" />{{ $t('تاريخ التعيين', 'Hire date') }}</dt>
                <dd><bdi>{{ $valueOrUnavailable('hire_date') }}</bdi></dd>
            </div>
            <div>
                <dt>{{ $t('الحالة', 'Status') }}</dt>
                <dd><x-ui.status-badge :status="$statusVariants[$status] ?? 'neutral'">{{ $statuses[$status] ?? $t('غير محدد', 'Unknown') }}</x-ui.status-badge></dd>
            </div>
        </dl>
    </section>

    <nav class="ui-tabs employee-profile__tabs" aria-label="{{ $t('أقسام ملف الموظف', 'Employee profile sections') }}">
        <div class="ui-tabs__list">
            @foreach($tabOptions as $tabId => $label)
                <a class="ui-tabs__tab" href="/employees/{{ rawurlencode((string) $employee['id']) }}?locale={{ $locale }}&amp;tab={{ $tabId }}" @if($profileTab === $tabId) aria-current="page" @endif>
                    <x-ui.icon :name="['overview' => 'grid', 'workers' => 'users', 'evidence' => 'audit', 'audit' => 'clock'][$tabId]" />
                    @if($profileTab === $tabId)<strong>{{ $label }}</strong>@else{{ $label }}@endif
                </a>
            @endforeach
        </div>
    </nav>

    @if($profileTab === 'overview')
        <div class="employee-profile__overview" data-testid="employee-profile-overview">
            <div class="employee-profile__main">
                <div class="employee-profile__main-grid">
                    <section class="admin-section employee-profile__panel" aria-labelledby="employee-employment-heading">
                        <h2 id="employee-employment-heading" class="employee-profile__panel-title"><x-ui.icon name="building" />{{ $t('المعلومات الوظيفية', 'Employment details') }}</h2>
                        <x-ui.description-list :items="[
                            $t('المسمى الوظيفي', 'Job title') => $valueOrUnavailable('role'),
                            $t('رقم الموظف', 'Employee code') => $valueOrUnavailable('code'),
                            $t('الفرع', 'Branch') => $valueOrUnavailable('branch_name'),
                            $t('القسم', 'Department') => $valueOrUnavailable('department_name'),
                            $t('المشرف المباشر', 'Supervisor') => $valueOrUnavailable('manager_name'),
                            $t('تاريخ التعيين', 'Hire date') => $valueOrUnavailable('hire_date'),
                        ]" />
                    </section>

                    <section class="admin-section employee-profile__panel" aria-labelledby="employee-account-heading">
                        <h2 id="employee-account-heading" class="employee-profile__panel-title"><x-ui.icon name="users" />{{ $t('حساب الدخول', 'Login account') }}</h2>
                        <x-ui.description-list :items="[
                            $t('الحالة', 'Status') => $accountStatus,
                            ...(($employee['account_visible'] ?? false) && filled($employee['user_name'] ?? null) ? [$t('اسم المستخدم', 'Username') => $employee['user_name']] : []),
                        ]" />
                    </section>

                    @if(in_array('employee.private', $permissions, true))
                        <section class="admin-section employee-profile__panel" aria-labelledby="employee-private-heading">
                            <h2 id="employee-private-heading" class="employee-profile__panel-title"><x-ui.icon name="phone" />{{ $t('معلومات الاتصال الخاصة', 'Private contact details') }}</h2>
                            <x-ui.description-list :items="[
                                $t('الهاتف', 'Phone') => $valueOrUnavailable('phone'),
                                $t('البريد الإلكتروني', 'Email') => $valueOrUnavailable('email'),
                                $t('ملاحظات', 'Notes') => $valueOrUnavailable('private_notes'),
                            ]" />
                        </section>
                    @endif
                    <section class="admin-section employee-profile__panel employee-profile__completion" aria-labelledby="employee-completion-heading">
                        <h2 id="employee-completion-heading" class="employee-profile__panel-title"><x-ui.icon name="audit" />{{ $t('مستوى استكمال البيانات', 'Profile completeness') }}</h2>
                        <div class="employee-profile__completion-body"><div><strong>{{ $t('اكتمال ملف الموظف', 'Employee profile completeness') }}</strong><p class="ui-muted">{{ $t('مؤشر اكتمال البيانات غير متاح حاليًا.', 'A profile completeness measure is not currently available.') }}</p></div><span class="employee-profile__completion-unavailable" aria-label="{{ $t('غير متاح', 'Unavailable') }}">—</span></div>
                    </section>
                </div>
                <x-ui.alert variant="info">{{ $t('التكامل قيد الاستكمال: إدارة المستندات وإنهاء الخدمة غير متاحتين في هذا المسار حتى الآن.', 'Pending integration: document management and offboarding are not available in this workflow yet.') }}</x-ui.alert>
            </div>

            <aside class="employee-profile__aside" aria-label="{{ $t('ملخص إضافي', 'Additional summary') }}">
                <section class="admin-section employee-profile__side-panel" aria-labelledby="employee-status-heading">
                    <h2 id="employee-status-heading" class="employee-profile__panel-title"><x-ui.icon name="shield" />{{ $t('حالة الملف', 'Profile status') }}</h2>
                    <p class="employee-profile__status-line">
                        <span>{{ $t('حساب الدخول', 'Login account') }}</span>
                        <span>{{ $accountStatus }}</span>
                    </p>
                    <p class="employee-profile__status-line"><span>{{ $t('آخر تسجيل دخول', 'Last login') }}</span><span>{{ $t('غير متاح', 'Unavailable') }}</span></p>
                    <p class="employee-profile__status-line"><span>{{ $t('الجهاز الأخير', 'Last device') }}</span><span>{{ $t('غير متاح', 'Unavailable') }}</span></p>
                </section>

                <section class="admin-section employee-profile__side-panel" aria-labelledby="employee-team-heading">
                    <h2 id="employee-team-heading" class="employee-profile__panel-title"><x-ui.icon name="grid" />{{ $t('إحصائيات سريعة', 'Quick statistics') }}</h2>
                    <dl class="employee-profile__report-count">
                        <div>
                            <dt>{{ $t('الموظفون التابعون', 'Direct reports') }}</dt>
                            <dd data-testid="employee-report-count" @if($reportCount !== null) data-report-count="{{ $reportCount }}" @endif>{{ $reportCount ?? $t('غير متاح', 'Unavailable') }}</dd>
                        </div>
                        <div><dt>{{ $t('المهام المفتوحة', 'Open tasks') }}</dt><dd>{{ $t('غير متاح', 'Unavailable') }}</dd></div>
                        <div><dt>{{ $t('المشاريع الحالية', 'Current projects') }}</dt><dd>{{ $t('غير متاح', 'Unavailable') }}</dd></div>
                    </dl>
                    <a class="employee-profile__side-link" href="/employees/{{ rawurlencode((string) $employee['id']) }}?locale={{ $locale }}&amp;tab=workers">{{ $t('عرض الفريق', 'View team') }}</a>
                </section>
                <section class="admin-section employee-profile__side-panel employee-profile__recent" aria-labelledby="employee-recent-heading">
                    <h2 id="employee-recent-heading" class="employee-profile__panel-title"><x-ui.icon name="clock" />{{ $t('النشاط الأخير', 'Recent activity') }}</h2>
                    <p class="ui-muted">{{ $t('لا تتوفر معاينة للنشاط في هذه الصفحة.', 'An activity preview is not available on this page.') }}</p>
                    @if($canAudit)<a class="employee-profile__side-link" href="/employees/{{ rawurlencode((string) $employee['id']) }}?locale={{ $locale }}&amp;tab=audit">{{ $t('عرض سجل التدقيق', 'View audit history') }}</a>@endif
                </section>
            </aside>
        </div>
    @elseif($profileTab === 'workers')
        <x-ui.detail-panel class="employee-profile__history" :title="$t('الموظفون التابعون', 'Direct reports')">
            <x-ui.table class="admin-table" :caption="$t('الموظفون التابعون لهذا المشرف', 'Employees reporting to this supervisor')">
                <thead>
                    <tr>
                        <th scope="col">{{ $t('الموظف', 'Employee') }}</th>
                        <th scope="col">{{ $t('رقم الموظف', 'Employee code') }}</th>
                        <th scope="col">{{ $t('الفرع', 'Branch') }}</th>
                        <th scope="col">{{ $t('القسم', 'Department') }}</th>
                        <th scope="col">{{ $t('الحالة', 'Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse(($reports ?? []) as $report)
                    @php($reportStatus = $report['status'] ?? '')
                    <tr>
                        <th scope="row">
                            <div class="admin-entity">
                                <x-ui.avatar :name="$report['name'] ?? ''" />
                                <div>
                                    <a href="/employees/{{ rawurlencode((string) $report['id']) }}?locale={{ $locale }}">{{ $report['name'] ?? '—' }}</a>
                                    <small>{{ $report['role'] ?? '—' }}</small>
                                </div>
                            </div>
                        </th>
                        <td><bdi>{{ $report['code'] ?? '—' }}</bdi></td>
                        <td>{{ $report['branch_name'] ?? '—' }}</td>
                        <td>{{ $report['department_name'] ?? '—' }}</td>
                        <td><x-ui.status-badge :status="$statusVariants[$reportStatus] ?? 'neutral'">{{ $statuses[$reportStatus] ?? $t('غير محدد', 'Unknown') }}</x-ui.status-badge></td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ $t('لا يوجد موظفون تابعون ضمن النطاق المصرح به.', 'No direct reports are available in the authorized scope.') }}</td></tr>
                @endforelse
                </tbody>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$reports ?? []" :locale="$locale" />
        </x-ui.detail-panel>
    @elseif($profileTab === 'evidence')
        <x-ui.detail-panel class="employee-profile__history" :title="$t('أدلة العمل', 'Work evidence')">
            @if(!empty($evidence))
                <div class="ui-stack">
                    @foreach($evidence as $entry)
                        <article class="admin-section">
                            <p><strong>{{ $t('المهمة', 'Task') }}:</strong> <bdi>{{ $entry['job_id'] ?? '—' }}</bdi></p>
                            <p>{{ $entry['note'] ?? '' }}</p>
                            <p class="ui-muted">
                                @if(filled($entry['actor_id'] ?? null))<span>{{ $t('المسجل', 'Recorded by') }}: <bdi>{{ $entry['actor_id'] }}</bdi></span>@endif
                                @if(filled($entry['created_at'] ?? null)) <time>{{ $entry['created_at'] }}</time>@endif
                            </p>
                        </article>
                    @endforeach
                </div>
            @else
                <x-ui.empty-state :title="$t('لا توجد أدلة عمل مسجلة', 'No work evidence recorded')" />
            @endif

            <x-ui.pagination :links="$evidenceLinks ?? []" :aria-label="$t('صفحات أدلة العمل', 'Work evidence pages')" />
            @if($canRecordEvidence)
                <details class="admin-options">
                    <summary>{{ $t('البحث عن مهمة مصرح بها', 'Find an authorized task') }}</summary>
                <form method="get" action="/employees/{{ rawurlencode((string) $employee['id']) }}" class="admin-toolbar">
                    <input type="hidden" name="locale" value="{{ $locale }}">
                    <input type="hidden" name="tab" value="evidence">
                    <x-ui.field id="employee-task-lookup" :label="$t('مرجع المهمة المصرح بها', 'Authorized task reference')" :error="$errors->first('task_id')">
                        <x-ui.input id="employee-task-lookup" name="task_id" :value="request('task_id')" required minlength="26" maxlength="26" />
                    </x-ui.field>
                    <x-ui.button type="submit" variant="secondary">{{ $t('البحث عن المهمة', 'Find task') }}</x-ui.button>
                </form>
                </details>
                <form method="post" action="/employees/{{ rawurlencode((string) $employee['id']) }}/evidence?locale={{ $locale }}&amp;tab=evidence" class="ui-stack">
                    @csrf
                    <x-ui.form-section :title="$t('تسجيل دليل عمل', 'Record work evidence')">
                        <div class="ui-grid">
                            <x-ui.field id="employee-job-id" :label="$t('المهمة المصرح بها', 'Authorized task')" :error="$errors->first('job_id')">
                                <x-ui.select id="employee-job-id" name="job_id" :error="$errors->first('job_id')" required>
                                    <option value="">{{ $t('اختر مهمة', 'Choose a task') }}</option>
                                    @foreach($tasks as $task)
                                        <option value="{{ $task['id'] }}" @selected((string) old('job_id', request('task_id', '')) === (string) $task['id'])>{{ $task['title'] }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                            <x-ui.field id="employee-evidence-note" :label="$t('دليل العمل', 'Work evidence')" :error="$errors->first('note')">
                                <x-ui.textarea id="employee-evidence-note" name="note" :error="$errors->first('note')" required maxlength="4000">{{ old('note') }}</x-ui.textarea>
                            </x-ui.field>
                        </div>
                        <x-ui.button type="submit" :disabled="empty($tasks)">{{ $t('تسجيل دليل العمل', 'Record work evidence') }}</x-ui.button>
                    </x-ui.form-section>
                </form>
            @endif
        </x-ui.detail-panel>
    @elseif($profileTab === 'audit' && $canAudit)
        <x-ui.detail-panel class="employee-profile__history" :title="$t('سجل التدقيق', 'Audit history')">
            @if(!empty($auditEntries))
                <x-erp.audit-history :entries="$auditEntries" />
            @else
                <x-ui.empty-state :title="$t('لا توجد أنشطة مسجلة', 'No activity recorded')" />
            @endif
            <x-ui.pagination :links="$auditLinks ?? []" :aria-label="$t('صفحات سجل التدقيق', 'Audit history pages')" />
        </x-ui.detail-panel>
    @endif
</div>
@endsection

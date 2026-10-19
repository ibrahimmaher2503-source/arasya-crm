@php
    $locale = ($locale ?? request()->query('locale', 'ar')) === 'en' ? 'en' : 'ar';
    $t = static fn (string $arabic, string $english): string => $locale === 'ar' ? $arabic : $english;
    $status = $task['status'];
    $statusPresentation = match ($status) {
        'pending' => ['variant' => 'warning', 'label' => $t('قيد الانتظار', 'Pending')],
        'in_progress' => ['variant' => 'info', 'label' => $t('قيد التنفيذ', 'In progress')],
        'completed' => ['variant' => 'success', 'label' => $t('مكتملة', 'Completed')],
        default => ['variant' => 'neutral', 'label' => $status],
    };
    $priority = ['low' => $t('منخفضة', 'Low'), 'normal' => $t('عادية', 'Normal'), 'high' => $t('عالية', 'High')][$task['priority']] ?? $task['priority'];
    $timelineEvents = [
        ['title' => $t('قيد الانتظار', 'Pending'), 'note' => $t($status === 'pending' ? 'الحالة الحالية' : 'تم تجاوزها', $status === 'pending' ? 'Current stage' : 'Completed')],
        ['title' => $t('قيد التنفيذ', 'In progress'), 'note' => $t($status === 'in_progress' ? 'الحالة الحالية' : ($status === 'pending' ? 'بانتظار البدء' : 'تمت'), $status === 'in_progress' ? 'Current stage' : ($status === 'pending' ? 'Waiting to start' : 'Completed'))],
        ['title' => $t('مكتملة', 'Completed'), 'note' => $t($status === 'completed' ? 'اكتملت المهمة' : 'لم تكتمل بعد', $status === 'completed' ? 'Task completed' : 'Not completed yet')],
    ];
    $auditEntries = array_map(static fn (array $entry): array => [
        'action' => $entry['action'] ?? '',
        'actor' => $entry['actor_id'] ?? '',
        'date' => $entry['created_at'] ?? '',
        'description' => filled($entry['receipt_id'] ?? null) ? $t('إيصال: ', 'Receipt: ').$entry['receipt_id'] : null,
    ], $task['audit'] ?? []);
@endphp
@extends('layouts.shell', ['locale' => $locale, 'pageSubtitle' => $task['id'], 'pageIcon' => 'grid', 'breadcrumbs' => [
    ['label' => $t('مركز العمل', 'Workspace'), 'url' => '/dashboard?locale='.$locale],
    ['label' => $t('المهام', 'Tasks'), 'url' => route('tasks.index', ['locale' => $locale])],
    ['label' => $task['title']],
]])

@section('content')
    <style>{!! file_get_contents(resource_path('css/tasks.css')) !!}</style>
    <div class="task-detail ui-stack">
        <div class="task-detail__heading-actions">
                <x-erp.entity-status :status="$statusPresentation['variant']" data-testid="task-status" :data-status="$status">{{ $statusPresentation['label'] }}</x-erp.entity-status>
            <a class="ui-button ui-button--secondary" href="{{ route('tasks.index', ['locale' => $locale]) }}"><x-ui.icon name="arrow" />{{ $t('العودة إلى المهام', 'Back to tasks') }}</a>
        </div>
        <section class="task-detail-summary" data-testid="task-detail-summary" aria-label="{{ $t('ملخص المهمة', 'Task summary') }}">
            <dl>
                <div><dt><x-ui.icon name="audit" />{{ $t('المعرف', 'Identifier') }}</dt><dd><bdi dir="ltr">{{ $task['id'] }}</bdi></dd></div>
                <div><dt><x-ui.icon name="clock" />{{ $t('الحالة', 'Status') }}</dt><dd><x-erp.entity-status :status="$statusPresentation['variant']">{{ $statusPresentation['label'] }}</x-erp.entity-status></dd></div>
                <div><dt><x-ui.icon name="clock" />{{ $t('تاريخ الاستحقاق', 'Due date') }}</dt><dd><x-erp.date-time-display :value="$task['due_at'] ?? $t('غير محدد', 'Not set')" :datetime="$task['due_at'] ?? null" /></dd></div>
                <div><dt><x-ui.icon name="target" />{{ $t('الأولوية', 'Priority') }}</dt><dd><x-ui.status-badge status="neutral">{{ $priority }}</x-ui.status-badge></dd></div>
                <div><dt><x-ui.icon name="users" />{{ $t('المسؤول', 'Assignee') }}</dt><dd><bdi dir="ltr">{{ $task['assignee_id'] ?? '—' }}</bdi></dd></div>
                <div><dt><x-ui.icon name="shield" />{{ $t('المالك', 'Owner') }}</dt><dd><bdi dir="ltr">{{ $task['owner_id'] }}</bdi></dd></div>
            </dl>
            @if ($canWrite && $status === 'pending')
                <form method="post" action="{{ route('tasks.start', ['task' => $task['id']]) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                    <x-ui.button type="submit">{{ $t('بدء العمل', 'Start work') }}</x-ui.button>
                </form>
            @elseif ($canWrite && $status === 'in_progress')
                <form method="post" action="{{ route('tasks.complete', ['task' => $task['id']]) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                    <x-ui.button type="submit" :disabled="empty($task['evidence'])" aria-describedby="task-evidence-help"><x-ui.icon name="shield" />{{ $t('إكمال المهمة', 'Complete task') }}</x-ui.button>
                </form>
            @endif
        </section>

        @if ($canAssign && in_array($status, ['pending', 'in_progress'], true))
            <x-ui.detail-panel :title="$t('إسناد المهمة', 'Assign task')">
                @include('tasks._assignee-browser')
                <form class="ui-stack" method="post" action="{{ route('tasks.assign', ['task' => $task['id']]) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                    <input type="hidden" name="expected_version" value="{{ $task['version'] }}">
                    @include('tasks._assignee-field')
                    <x-ui.form-actions><x-ui.button type="submit">{{ $t('حفظ الإسناد', 'Save assignment') }}</x-ui.button></x-ui.form-actions>
                </form>
            </x-ui.detail-panel>
        @endif

        <div class="task-detail-grid">
            <x-ui.detail-panel class="task-detail__details" :title="$t('تفاصيل المهمة', 'Task details')">
                <x-ui.icon name="audit" class="task-detail__panel-icon" />
                <dl class="ui-description-list">
                    <div class="ui-description-list__item"><dt>{{ $t('المالك', 'Owner') }}</dt><dd><bdi dir="ltr">{{ $task['owner_id'] }}</bdi></dd></div>
                    <div class="ui-description-list__item"><dt>{{ $t('المسؤول', 'Assignee') }}</dt><dd><bdi dir="ltr">{{ $task['assignee_id'] ?? '—' }}</bdi></dd></div>
                    <div class="ui-description-list__item"><dt>{{ $t('الأولوية', 'Priority') }}</dt><dd>{{ $priority }}</dd></div>
                    <div class="ui-description-list__item"><dt>{{ $t('تاريخ الاستحقاق', 'Due date') }}</dt><dd><x-erp.date-time-display :value="$task['due_at'] ?? $t('غير محدد', 'Not set')" :datetime="$task['due_at'] ?? null" /></dd></div>
                    <div class="ui-description-list__item"><dt>{{ $t('الإصدار', 'Version') }}</dt><dd><bdi dir="ltr">{{ $task['version'] }}</bdi></dd></div>
                </dl>
            </x-ui.detail-panel>

            <x-ui.detail-panel class="task-detail__progress" :title="$t('تقدم المهمة', 'Task progress')">
                <x-ui.icon name="target" class="task-detail__panel-icon" />
                <ol class="task-detail__steps">
                    @foreach ($timelineEvents as $event)
                        @php($stepStatus = ['pending', 'in_progress', 'completed'][$loop->index])
                        <li @class(['is-passed' => in_array($status, array_slice(['pending', 'in_progress', 'completed'], $loop->index + 1), true)]) @if ($status === $stepStatus) aria-current="step" @endif>
                            <span class="task-detail__step-number" aria-hidden="true">{{ $loop->iteration }}</span>
                            <div><h3>{{ $event['title'] }}</h3><p>{{ $event['note'] }}</p></div>
                        </li>
                    @endforeach
                </ol>
            </x-ui.detail-panel>
        </div>

        <x-ui.detail-panel class="task-detail__evidence" :title="$t('الأدلة', 'Evidence')">
            <x-ui.icon name="download" class="task-detail__panel-icon" />
            <div class="ui-stack">
                @forelse ($task['evidence'] as $evidence)
                    <div class="ui-inline">
                        <a href="{{ route('tasks.download', ['task' => $task['id'], 'evidence' => $evidence['id']]) }}">{{ $evidence['name'] }}</a>
                        <span class="ui-muted"><bdi dir="ltr">{{ $evidence['mime'] }}</bdi> · <bdi dir="ltr">{{ $evidence['size'] }}</bdi> {{ $t('بايت', 'bytes') }}</span>
                    </div>
                @empty
                    <p class="ui-muted">{{ $t('لم يتم إرفاق دليل بعد.', 'No evidence has been attached yet.') }}</p>
                @endforelse

                <div class="task-detail__evidence-help" id="task-evidence-help"><x-ui.icon name="help" /><div><h3>{{ $t('إرفاق دليل إكمال المهمة', 'Attach task completion evidence') }}</h3><p>{{ $t('أرفق ملفًا يثبت تنفيذ المهمة. يلزم وجود دليل قبل إكمالها.', 'Attach a file showing the completed work. Evidence is required before completing this task.') }}</p></div></div>
                @if ($canWrite && $status === 'in_progress')
                    <form class="ui-stack task-detail__upload" method="post" enctype="multipart/form-data" action="{{ route('tasks.evidence', ['task' => $task['id']]) }}">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                        <div class="task-detail__dropzone">
                            <x-ui.icon name="download" />
                            <x-erp.attachment-uploader id="task-evidence" name="evidence" :label="$t('ملف الدليل', 'Evidence file')" accept="application/pdf,image/png,image/jpeg,text/plain" :error="$errors->first('evidence')" aria-describedby="task-evidence-formats{{ $errors->has('evidence') ? ' task-evidence-error' : '' }}" />
                            <p id="task-evidence-formats" class="ui-muted">{{ $t('PDF، PNG، JPEG أو نص — الحد الأقصى 5 ميجابايت.', 'PDF, PNG, JPEG or plain text — maximum 5 MB.') }}</p>
                        </div>
                        @if ($errors->first('evidence'))
                            <x-ui.validation-message id="task-evidence-error">{{ $errors->first('evidence') }}</x-ui.validation-message>
                        @endif
                        <x-ui.form-actions>
                            <x-ui.button type="submit">{{ $t('إرفاق الدليل', 'Attach evidence') }}</x-ui.button>
                        </x-ui.form-actions>
                    </form>

                @endif
            </div>
        </x-ui.detail-panel>

        <x-ui.detail-panel class="task-detail__audit" :title="$t('سجل التدقيق', 'Audit history')" data-testid="task-audit">
            <x-ui.icon name="clock" class="task-detail__panel-icon" />
            <x-erp.audit-history :entries="$auditEntries" />
        </x-ui.detail-panel>

        <x-ui.detail-panel class="task-detail__assistant" :title="$t('مساعد المهام', 'Task assistant')">
            <x-ui.icon name="help" class="task-detail__panel-icon" />
            <p class="ui-muted">{{ $t('راجع بيانات المهمة والأدلة أو تواصل مع المسؤول عند الحاجة إلى مساعدة.', 'Review task details and evidence, or contact the assignee if you need help.') }}</p>
            <div class="ui-stack">
                @if ($canWrite && $status === 'pending')
                    <form method="post" action="{{ route('tasks.assistant.preview', ['task' => $task['id']]) }}">
                        @csrf
                        <input type="hidden" name="action" value="start">
                        <input type="hidden" name="version" value="{{ $task['version'] }}">
                        <x-ui.button type="submit">{{ $t('معاينة إجراء المساعد', 'Preview assistant action') }}</x-ui.button>
                    </form>
                @endif

                @if ($preview)
                    <x-ai.action-preview :title="$t('معاينة إجراء المساعد', 'Assistant action preview')">
                        <x-ui.description-list :items="[$t('الإجراء المقترح', 'Proposed action') => $preview['action'] === 'start' ? $t('بدء العمل', 'Start work') : $preview['action'], $t('الإصدار', 'Version') => (string) $preview['version']]" />
                        <x-ui.alert variant="warning">{{ $t('تنفيذ المساعد غير متاح', 'Assistant execution is unavailable') }}</x-ui.alert>
                        <p class="ui-muted">{{ $t('ستظل هذه المعاينة للقراءة فقط حتى تصبح البوابة المشتركة جاهزة.', 'This preview remains read-only until the shared gateway is ready.') }}</p>
                    </x-ai.action-preview>
                    @if ($canWrite)
                    <form method="post" action="{{ route('tasks.assistant.confirm', ['task' => $task['id']]) }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $preview['token'] }}">
                        <input type="hidden" name="action" value="{{ $preview['action'] }}">
                        <input type="hidden" name="version" value="{{ $preview['version'] }}">
                        <x-ui.field id="assistant-confirmed" :label="$t('أؤكد تنفيذ الإجراء', 'I confirm the action')">
                            <x-ui.checkbox name="confirmed" value="1" />
                        </x-ui.field>
                        <x-ui.button type="submit" disabled>{{ $t('تأكيد التنفيذ', 'Confirm execution') }}</x-ui.button>
                    </form>
                    @endif
                @endif
            </div>
        </x-ui.detail-panel>
    </div>
@endsection

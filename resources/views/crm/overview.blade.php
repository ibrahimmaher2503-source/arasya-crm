@php
    $activityKindLabels = [
        'call' => $t('مكالمة', 'Call'), 'message' => $t('رسالة', 'Message'),
        'meeting' => $t('اجتماع', 'Meeting'), 'visit' => $t('زيارة', 'Visit'),
        'note' => $t('ملاحظة', 'Note'), 'quotation' => $t('عرض سعر', 'Quotation'),
    ];
    $summaryItems = [
        ['label' => $t('إجمالي العملاء المحتملين النشطين', 'Active leads'), 'value' => $overview['active_total'], 'icon' => 'users'],
        ['label' => $t('عملاء مسندون', 'Assigned leads'), 'value' => $overview['assigned_count'], 'icon' => 'building'],
        ['label' => $t('تم تحويلهم إلى عملاء', 'Converted to customers'), 'value' => $overview['converted_count'], 'icon' => 'grid'],
        ['label' => $t('طلبات نقل معلقة', 'Pending transfers'), 'value' => $overview['pending_transfers_count'], 'icon' => 'clock'],
    ];
@endphp
@extends('layouts.shell')
@section('content')
<div class="ui-stack crm-page">
    <div data-tour="crm.overview.nav">@include('crm._nav', ['current' => 'overview'])</div>

    <x-ui.summary-strip class="crm-overview-summary" data-tour="crm.overview.summary" :items="$summaryItems" variant="cards" :aria-label="$t('ملخص العملاء المحتملين', 'Lead summary')" />

    <div class="ui-grid crm-overview-grid">
        <x-ui.detail-panel class="crm-overview-panel" :title="$t('حسب المصدر', 'By source')">
            @if (count($overview['source_counts']))
                <ol class="crm-breakdown-list" aria-label="{{ $t('عدد العملاء المحتملين حسب المصدر', 'Lead counts by source') }}">
                    @foreach ($overview['source_counts'] as $row)
                        <li>
                            <div class="crm-breakdown-list__heading"><span>{{ $row['source'] }}</span><bdi>{{ $row['count'] }}</bdi></div>
                            <progress value="{{ $row['count'] }}" max="{{ $overview['active_total'] }}" aria-label="{{ $row['source'] }}: {{ $row['count'] }} {{ $t('من', 'of') }} {{ $overview['active_total'] }}"></progress>
                        </li>
                    @endforeach
                </ol>
            @else
                <x-ui.empty-state class="crm-overview-empty" :title="$t('لا توجد مصادر بعد', 'No sources yet')" />
            @endif
        </x-ui.detail-panel>

        <x-ui.detail-panel class="crm-overview-panel" :title="$t('حسب المرحلة', 'By stage')">
            @if (count($overview['stage_counts']))
                <ol class="crm-breakdown-list" aria-label="{{ $t('عدد العملاء المحتملين حسب المرحلة', 'Lead counts by stage') }}">
                    @foreach ($overview['stage_counts'] as $row)
                        @php($stageName = $row['stage'] ?? $t('بدون مرحلة', 'No stage'))
                        <li>
                            <div class="crm-breakdown-list__heading"><span>{{ $stageName }}</span><bdi>{{ $row['count'] }}</bdi></div>
                            <progress value="{{ $row['count'] }}" max="{{ $overview['active_total'] }}" aria-label="{{ $stageName }}: {{ $row['count'] }} {{ $t('من', 'of') }} {{ $overview['active_total'] }}"></progress>
                        </li>
                    @endforeach
                </ol>
            @else
                <x-ui.empty-state class="crm-overview-empty" :title="$t('لا توجد مراحل مسجلة', 'No stages recorded')" />
            @endif
        </x-ui.detail-panel>
        <x-ui.detail-panel class="crm-overview-panel" :title="$t('أحدث الأنشطة', 'Recent activity')">
            @if (count($overview['recent_activities']))
                <ol class="crm-activity-list">
                    @foreach ($overview['recent_activities'] as $activity)
                        <li>
                            <time datetime="{{ $activity['occurred_at'] }}">{{ $activity['occurred_at'] }}</time>
                            <p><a href="/crm/leads/{{ $activity['lead_id'] }}?locale={{ $locale }}">{{ $activity['lead_name'] }}</a></p>
                            <p class="ui-muted">{{ $activity['outcome_label'] ?: ($activityKindLabels[$activity['kind']] ?? $activity['kind']) }}</p>
                        </li>
                    @endforeach
                </ol>
            @else
                <x-ui.empty-state class="crm-overview-empty" :title="$t('لا توجد أنشطة بعد', 'No activity yet')" />
            @endif
        </x-ui.detail-panel>
    </div>

    <x-ui.detail-panel class="crm-overview-panel crm-overview-panel--wide" :title="$t('المتابعات القادمة', 'Upcoming follow-ups')">
        @if (count($overview['upcoming_followups']))
            <ul class="crm-follow-up-list">
                @foreach ($overview['upcoming_followups'] as $followUp)
                    <li>
                        <div>
                            <a href="/crm/leads/{{ $followUp['lead_id'] }}?locale={{ $locale }}">{{ $followUp['lead_name'] }}</a>
                            <p>{{ $followUp['next_step'] }}</p>
                        </div>
                        <time datetime="{{ $followUp['due_at'] }}">{{ $followUp['due_at'] }}</time>
                    </li>
                @endforeach
            </ul>
        @else
            <x-ui.empty-state class="crm-overview-empty" :title="$t('لا توجد متابعات قادمة', 'No upcoming follow-ups')" />
        @endif
    </x-ui.detail-panel>

    @if (in_array('crm.write', $permissions, true) && count($inbox))
        <x-ui.detail-panel class="crm-overview-panel" :title="$t('طلبات النقل الواردة', 'Incoming transfer requests')">
            <ol class="crm-inbox-list">
                @foreach ($inbox as $transfer)
                    <li>
                        <div>
                            <strong>{{ $transfer['name'] }}</strong>
                            <p>{{ $transfer['context'] }}</p>
                            <time datetime="{{ $transfer['created_at'] }}">{{ $transfer['created_at'] }}</time>
                        </div>
                        <div class="ui-inline">
                            @foreach (['accept' => [$t('قبول', 'Accept'), 'primary'], 'reject' => [$t('رفض', 'Reject'), 'secondary']] as $decision => [$label, $variant])
                                <form method="post" action="/crm/transfers/{{ $transfer['id'] }}/response?locale={{ $locale }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="{{ $decision }}">
                                    <input type="hidden" name="version" value="{{ $transfer['version'] }}">
                                    <x-ui.button type="submit" :variant="$variant">{{ $label }}</x-ui.button>
                                </form>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ol>
            <x-ui.list-pagination :paginator="$inboxPaginator" :locale="$locale" :aria-label="$t('صفحات طلبات النقل', 'Transfer request pages')" />
        </x-ui.detail-panel>
    @endif
    <x-ui.page-tour id="crm-overview" :locale="$locale" :actor="$actor" :permissions="$permissions" />
</div>
@endsection

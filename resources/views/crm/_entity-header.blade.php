@php
    $priorityLabels = ['high' => $t('عالية', 'High'), 'normal' => $t('متوسطة', 'Normal'), 'low' => $t('منخفضة', 'Low')];
    $showOverviewLink = $showOverviewLink ?? true;
@endphp
<section @class(['crm-reference-header', 'crm-reference-header--overview' => $overviewLayout ?? false]) aria-label="{{ $t('ملخص العميل', 'Lead summary') }}">
    @if ($overviewLayout ?? false)<span class="crm-lead-identity-icon"><x-ui.icon name="building" /></span>@endif
    <x-erp.entity-header class="crm-reference-identity" :title="$lead['organization'] ?: $lead['name']" :subtitle="$lead['name']" :identifier="$lead['id']">
        <x-slot:status><x-ui.status-badge :status="$lead['archived'] ? 'neutral' : 'success'">{{ $lead['archived'] ? $t('مؤرشف', 'Archived') : ($lead['customer_id'] ? $t('تم التحويل', 'Converted') : $t('عميل محتمل', 'Lead')) }}</x-ui.status-badge></x-slot:status>
    </x-erp.entity-header>
    <div class="crm-reference-metric"><span class="ui-muted">{{ $t('المرحلة الحالية', 'Current stage') }}</span><strong>{{ $stageLabels[$lead['stage_id'] ?? ''] ?? $t('غير محددة', 'Not set') }}</strong><x-ui.icon name="grid" /></div>
    <div class="crm-reference-metric"><span class="ui-muted">{{ $t('أولوية العميل', 'Lead priority') }}</span><strong>{{ $priorityLabels[$lead['priority']] ?? $lead['priority'] }}</strong><x-ui.icon name="target" /></div>
    @if ($overviewLayout ?? false)
        <div class="crm-reference-updated"><strong><x-ui.icon name="clock" />{{ $t('آخر تحديث', 'Last updated') }}</strong><time datetime="{{ $lead['updated_at'] }}"><bdi>{{ substr($lead['updated_at'], 0, 10) }}</bdi> · <bdi>{{ substr($lead['updated_at'], 11, 5) }}</bdi></time><span><x-ui.icon name="users" />{{ $lead['assignee_id'] !== null ? $t('مسند', 'Assigned') : $t('غير مسند', 'Unassigned') }}</span></div>
    @endif
    <div class="crm-reference-actions">
        @if ($overviewLayout ?? false)
            @if (! $lead['archived'] && $lead['customer_id'] === null && in_array('crm.convert', $permissions, true))<x-ui.button data-open-dialog="crm-lead-convert"><x-ui.icon name="users" />{{ $t('تحويل إلى عميل', 'Convert to customer') }}</x-ui.button>@endif
            @if (! $lead['archived'] && (in_array('crm.write', $permissions, true) || in_array('crm.manage', $permissions, true) || (in_array('crm.transfer', $permissions, true) && $lead['assignee_id'] !== null && ! filled($lead['pending_transfer'] ?? null))))<x-ui.button variant="secondary" data-open-dialog="crm-lead-actions"><x-ui.icon name="more" />{{ $t('إجراءات أخرى', 'More actions') }}</x-ui.button>@endif
        @endif
        @if (in_array('crm.manage', $permissions, true) && ! $lead['archived'])<a class="ui-button ui-button--secondary" href="/crm/leads/{{ $lead['id'] }}/edit?locale={{ $locale }}"><x-ui.icon name="edit" />{{ $t('تعديل السجل', 'Edit lead') }}</a>@endif
        @if ($showOverviewLink)<a class="ui-button ui-button--primary" href="/crm/leads/{{ $lead['id'] }}?locale={{ $locale }}">{{ $t('فتح نظرة عامة للسجل', 'Open lead overview') }}</a>@endif
        @if (! ($overviewLayout ?? false))<small class="ui-muted">{{ $t('آخر تحديث', 'Last updated') }} <bdi>{{ $lead['updated_at'] }}</bdi></small>@endif
    </div>
</section>

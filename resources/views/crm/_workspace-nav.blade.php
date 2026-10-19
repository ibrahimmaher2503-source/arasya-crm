<nav class="crm-section-nav crm-workspace-nav" aria-label="{{ $t('أقسام سجل العميل المحتمل', 'Lead record sections') }}">
    <a href="/crm/leads/{{ $lead['id'] }}?locale={{ $locale }}" @if (($section ?? 'overview') === 'overview') aria-current="page" @endif><x-ui.icon name="grid" />{{ $t('نظرة عامة', 'Overview') }}</a>
    @foreach (['activities' => [$t('الأنشطة', 'Activities'), 'clock'], 'notes' => [$t('الملاحظات', 'Notes'), 'audit'], 'tasks' => [$t('المهام', 'Tasks'), 'shield']] as $tab => [$label, $icon])
        <a href="/crm/leads/{{ $lead['id'] }}/workspace/{{ $tab }}?locale={{ $locale }}" @if (($section ?? '') === $tab) aria-current="page" @endif><x-ui.icon :name="$icon" />{{ $label }}</a>
    @endforeach
    @if (app()->environment('testing') && config('sales.synthetic_drafts') === true)
    <a href="/crm/leads/{{ $lead['id'] }}/quotations?locale={{ $locale }}" @if (($section ?? '') === 'quotations') aria-current="page" @endif><x-ui.icon name="audit" />{{ $t('العروض', 'Quotations') }}</a>
    @endif
    <a href="/crm/leads/{{ $lead['id'] }}/files?locale={{ $locale }}" @if (($section ?? '') === 'files') aria-current="page" @endif><x-ui.icon name="audit" />{{ $t('الملفات', 'Files') }}</a>
    @if ($overviewLayout ?? false)
        <x-ui.button variant="ghost" data-open-dialog="crm-lead-history"><x-ui.icon name="clock" />{{ $t('سجل التغييرات', 'Change history') }}</x-ui.button>
    @else
        <a href="/crm/leads/{{ $lead['id'] }}?locale={{ $locale }}#crm-lead-contact"><x-ui.icon name="users" />{{ $t('بيانات التواصل', 'Contact details') }}</a>
        <a href="/crm/leads/{{ $lead['id'] }}?locale={{ $locale }}&amp;history=1"><x-ui.icon name="audit" />{{ $t('سجل التغييرات', 'Change history') }}</a>
    @endif
</nav>

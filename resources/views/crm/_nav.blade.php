<nav class="crm-nav" aria-label="{{ $t('تنقل إدارة العملاء', 'CRM navigation') }}">
    <a class="crm-nav__link" href="/crm?locale={{ $locale }}" @if (($current ?? '') === 'overview') aria-current="page" @endif>
        {{ $t('نظرة عامة', 'Overview') }}
    </a>
    @if (in_array('crm.read', $permissions, true))
        <a class="crm-nav__link" href="/crm/leads?locale={{ $locale }}" @if (($current ?? '') === 'leads') aria-current="page" @endif>
            {{ $t('العملاء المحتملون', 'Leads') }}
        </a>
    @endif
    @if (app()->environment('testing') && config('sales.synthetic_drafts') === true && in_array('crm.read', $permissions, true))
    <a class="crm-nav__link" href="/crm/pipeline?locale={{ $locale }}" @if (($current ?? '') === 'pipeline') aria-current="page" @endif>{{ $t('الصفقات وخط المبيعات', 'Deals and pipeline') }}</a>
    @endif
</nav>

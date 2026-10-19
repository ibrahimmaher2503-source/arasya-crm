@extends('layouts.shell')
@section('content')
<div class="ui-stack crm-page">
    @include('crm._entity-header')
    @include('crm._workspace-nav', ['section' => 'quotations'])
    <p class="ui-muted">{{ $t('مسودات تجريبية غير ملزمة — الاعتماد والإرسال معطلان', 'Non-binding synthetic drafts — approval and sending are disabled') }}</p>
    <div class="crm-workspace-layout crm-workspace-layout--detail">
        <section class="ui-detail-panel crm-workspace-main">
            <x-ui.filter-bar :action="request()->url()" class="crm-workspace-filters">
                <input type="hidden" name="locale" value="{{ $locale }}">
                <x-ui.search-input name="q" :value="request('q')" :aria-label="$t('البحث في العروض', 'Search quotations')" :placeholder="$t('البحث في العروض...', 'Search quotations...')" />
                <x-ui.button type="submit" variant="secondary">{{ $t('بحث', 'Search') }}</x-ui.button>
                @if (! $lead['archived'] && in_array('crm.write', $permissions, true))<a class="ui-button ui-button--primary" href="/crm/leads/{{ $lead['id'] }}/quotations/create?locale={{ $locale }}"><x-ui.icon name="plus" />{{ $t('عرض جديد', 'New quotation') }}</a>@endif
            </x-ui.filter-bar>
            <x-ui.table :caption="$title" class="crm-workspace-table">
                <thead><tr><th>{{ $t('عنوان العرض', 'Quotation title') }}</th><th>{{ $t('القيمة الإجمالية', 'Total') }}</th><th>{{ $t('الحالة', 'Status') }}</th><th>{{ $t('تاريخ الإنشاء', 'Created') }}</th><th>{{ $t('الإجراءات', 'Actions') }}</th></tr></thead>
                <tbody>@forelse ($quotations as $quote)<tr><td>{{ $quote['title'] }}</td><td><bdi>{{ $quote['totals']['total'] }} {{ $quote['currency'] }}</bdi></td><td><x-ui.status-badge status="info">{{ $t('مسودة', 'Draft') }}</x-ui.status-badge></td><td><bdi>{{ $quote['created_at'] }}</bdi></td><td><a class="ui-button ui-button--ghost" href="{{ request()->fullUrlWithQuery(['selected' => $quote['id']]) }}">{{ $t('عرض', 'View') }}</a></td></tr>@empty<tr><td colspan="5"><x-ui.empty-state :title="$t('لا توجد عروض', 'No quotations found')" /></td></tr>@endforelse</tbody>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$quotations" :locale="$locale" />
        </section>
        <aside class="crm-workspace-aside ui-stack">
            @if ($selected)
            <x-ui.detail-panel :title="$selected['title']">
                <p><bdi>{{ $selected['id'] }}</bdi></p>
                <x-ui.status-badge status="info">{{ $t('مسودة', 'Draft') }}</x-ui.status-badge>
                <dl class="crm-contact-list">@foreach (['subtotal' => $t('قبل الخصم', 'Subtotal'), 'discount' => $t('الخصم', 'Discount'), 'tax' => $t('الضريبة', 'Tax'), 'total' => $t('الإجمالي', 'Total')] as $key => $label)<div><dt>{{ $label }}</dt><dd><bdi>{{ $selected['totals'][$key] }} {{ $selected['currency'] }}</bdi></dd></div>@endforeach</dl>
                <a class="ui-button ui-button--secondary" href="/crm/leads/{{ $lead['id'] }}/quotations/{{ $selected['id'] }}/preview?locale={{ $locale }}">{{ $t('معاينة للطباعة / PDF', 'Print / PDF preview') }}</a>
                @if (! $lead['archived'] && in_array('crm.write', $permissions, true))<a class="ui-button ui-button--primary" href="/crm/leads/{{ $lead['id'] }}/quotations/{{ $selected['id'] }}/edit?locale={{ $locale }}">{{ $t('تعديل المسودة', 'Edit draft') }}</a>@endif
            </x-ui.detail-panel>
            @else<x-ui.detail-panel :title="$t('تفاصيل العرض', 'Quotation details')"><x-ui.empty-state :title="$t('اختر عرضاً لعرض التفاصيل', 'Select a quotation to view details')" /></x-ui.detail-panel>@endif
        </aside>
    </div>
</div>
@endsection

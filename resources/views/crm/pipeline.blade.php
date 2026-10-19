@php
    $stageNames = ['lead' => $t('عميل محتمل', 'Prospect'), 'contact' => $t('تواصل أولي', 'Initial contact'), 'proposal' => $t('عرض سعر', 'Proposal'), 'negotiation' => $t('تفاوض', 'Negotiation'), 'review' => $t('مراجعة داخلية', 'Internal review'), 'closed' => $t('إغلاق', 'Closed')];
    $tones = ['lead' => 'success', 'contact' => 'info', 'proposal' => 'warning', 'negotiation' => 'warning', 'review' => 'neutral', 'closed' => 'danger'];
    $canWrite = in_array('crm.write', $permissions, true);
@endphp
@extends('layouts.shell')
@section('content')
<div class="ui-stack crm-page crm-pipeline-page">
    <x-ui.summary-strip variant="cards" :items="[
        ['label' => $t('قيمة الفرص المفتوحة', 'Open opportunity value'), 'value' => $openAmount.' '.$currency, 'icon' => 'target', 'tone' => 'success'],
        ['label' => $t('عدد الصفقات', 'Opportunity count'), 'value' => (string) $total, 'icon' => 'users', 'tone' => 'info'],
        ['label' => $t('الصفقات المغلقة', 'Closed opportunities'), 'value' => (string) $columns['closed']->total(), 'icon' => 'shield'],
        ['label' => $t('تجاوزت موعد الإغلاق المتوقع', 'Past expected close date'), 'value' => (string) $overdue, 'icon' => 'clock', 'tone' => 'warning'],
    ]" />
    <x-ui.filter-bar action="/crm/pipeline">
        <input type="hidden" name="locale" value="{{ $locale }}">
        <x-ui.search-input name="q" :value="request('q')" :aria-label="$t('البحث في الصفقات', 'Search opportunities')" :placeholder="$t('البحث في الصفقات...', 'Search opportunities...')" />
        <x-ui.input name="currency" :value="$currency" maxlength="3" pattern="[A-Z]{3}" :aria-label="$t('العملة', 'Currency')" />
        <x-ui.select name="stage" :aria-label="$t('المرحلة', 'Stage')"><option value="">{{ $t('جميع المراحل', 'All stages') }}</option>@foreach ($stageNames as $key => $label)<option value="{{ $key }}" @selected(request('stage') === $key)>{{ $label }}</option>@endforeach</x-ui.select>
        <x-ui.input name="probability_min" type="number" min="0" max="100" :value="request('probability_min')" :aria-label="$t('الحد الأدنى لاحتمال الإغلاق', 'Minimum closing probability')" :placeholder="$t('احتمال الإغلاق %', 'Closing probability %')" />
        <x-ui.input name="from" type="date" :value="request('from')" :aria-label="$t('الإغلاق من تاريخ', 'Closing from date')" />
        <x-ui.input name="to" type="date" :value="request('to')" :aria-label="$t('الإغلاق إلى تاريخ', 'Closing to date')" />
        <x-ui.button type="submit" variant="secondary"><x-ui.icon name="filter" />{{ $t('تطبيق الفلاتر', 'Apply filters') }}</x-ui.button>
        @if ($canWrite)<x-ui.button data-open-dialog="opportunity-create"><x-ui.icon name="plus" />{{ $t('إضافة صفقة', 'Add opportunity') }}</x-ui.button>@endif
    </x-ui.filter-bar>
    <p class="ui-muted">{{ $t('مساحة تجريبية ببيانات اصطناعية؛ المراجعة الداخلية لا تمنح اعتماداً أو إذناً بالإرسال.', 'Synthetic workspace; internal review does not grant approval or permission to send.') }}</p>
    <section class="ui-detail-panel" aria-label="{{ $t('خط المبيعات', 'Sales pipeline') }}">
        <div class="crm-pipeline-board">
        @foreach ($stageNames as $stage => $label)
            <section class="crm-pipeline-column" aria-label="{{ $label }}">
                <header class="crm-pipeline-heading crm-pipeline-heading--{{ $tones[$stage] }}"><h2>{{ $label }}</h2><strong>{{ $columns[$stage]->total() }}</strong><small><bdi>{{ $stageTotals[$stage] }} {{ $currency }}</bdi></small></header>
                @forelse ($columns[$stage] as $opportunity)
                    <article class="crm-pipeline-card">
                        <h3>{{ $opportunity->organization ?: $opportunity->lead_name }}</h3>
                        <p>{{ $opportunity->title }}</p>
                        <div class="crm-breakdown-list__heading"><strong><bdi>{{ $opportunity->amount }} {{ $opportunity->currency }}</bdi></strong><x-ui.status-badge status="info">{{ $opportunity->probability }}%</x-ui.status-badge></div>
                        <small><bdi>{{ $opportunity->expected_close }}</bdi></small>
                        <div class="ui-inline crm-pipeline-card-actions">
                        @if ($canWrite)<a class="ui-button ui-button--ghost" href="{{ request()->fullUrlWithQuery(['selected' => $opportunity->id]) }}#opportunity-edit">{{ $t('تعديل', 'Edit') }}</a>@endif
                        <a class="ui-button ui-button--ghost" href="/crm/leads/{{ $opportunity->lead_id }}?locale={{ $locale }}">{{ $t('فتح العميل', 'Open lead') }}</a>
                        </div>
                    </article>
                @empty<p class="ui-muted">{{ $t('لا توجد صفقات', 'No opportunities') }}</p>@endforelse
                @if ($columns[$stage]->hasPages())<x-ui.list-pagination :paginator="$columns[$stage]" :locale="$locale" />@endif
            </section>
        @endforeach
        </div>
    </section>
    <div class="crm-pipeline-bottom">
    <x-ui.detail-panel :title="$t('مواعيد الإغلاق المتوقعة', 'Expected closing dates')">
        <x-ui.table :caption="$t('مواعيد الإغلاق المتوقعة', 'Expected closing dates')">
            <thead><tr><th>{{ $t('المشروع', 'Opportunity') }}</th><th>{{ $t('العميل', 'Lead') }}</th><th>{{ $t('المرحلة', 'Stage') }}</th><th>{{ $t('قيمة الصفقة', 'Amount') }}</th><th>{{ $t('تاريخ الإغلاق', 'Expected close') }}</th><th>{{ $t('الاحتمال', 'Probability') }}</th></tr></thead>
            <tbody>@forelse ($upcoming as $opportunity)<tr><td>{{ $opportunity->title }}</td><td>{{ $opportunity->organization ?: $opportunity->lead_name }}</td><td>{{ $stageNames[$opportunity->stage] }}</td><td><bdi>{{ $opportunity->amount }} {{ $currency }}</bdi></td><td><bdi>{{ $opportunity->expected_close }}</bdi></td><td>{{ $opportunity->probability }}%</td></tr>@empty<tr><td colspan="6">{{ $t('لا توجد مواعيد', 'No dates scheduled') }}</td></tr>@endforelse</tbody>
        </x-ui.table>
        <x-ui.list-pagination :paginator="$upcoming" :locale="$locale" />
    </x-ui.detail-panel>
    <x-ui.detail-panel :title="$t('فرص تجاوزت الموعد المتوقع', 'Past expected close date')">
        <ul class="crm-breakdown-list">@forelse ($overdueRows as $opportunity)<li><strong>{{ $opportunity->organization ?: $opportunity->lead_name }}</strong><div class="crm-breakdown-list__heading"><span>{{ $opportunity->title }}</span><bdi>{{ $opportunity->amount }} {{ $currency }}</bdi></div><x-ui.status-badge status="danger"><bdi>{{ $opportunity->expected_close }}</bdi></x-ui.status-badge></li>@empty<li class="ui-muted">{{ $t('لا توجد فرص متأخرة', 'No overdue opportunities') }}</li>@endforelse</ul>
    </x-ui.detail-panel>
    </div>
    @if ($canWrite)
        <x-ui.modal id="opportunity-create" :title="$t('إضافة صفقة', 'Add opportunity')" :close-label="$t('إغلاق', 'Close')">
            <div data-crm-outcome-picker data-crm-option-name="lead_id" data-crm-picker-dialog="opportunity-create">
            <form method="get" action="/crm/pipeline" class="ui-inline" data-crm-outcome-search>
                @foreach (request()->only(['locale', 'q', 'currency', 'stage', 'probability_min', 'from', 'to', 'selected', 'closing_page', 'lead_page', 'contact_page', 'proposal_page', 'negotiation_page', 'review_page', 'closed_page']) as $key => $value)
                    @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <x-ui.search-input id="opportunity-lead-search" name="lead_q" :value="request('lead_q')" maxlength="120" :aria-label="$t('البحث عن عميل', 'Search leads')" /><x-ui.button type="submit" variant="secondary">{{ $t('بحث', 'Search') }}</x-ui.button>
            </form>
            @include('crm._opportunity-form', ['opportunity' => null, 'formId' => 'create'])
            <div data-crm-outcome-pages><x-ui.list-pagination :paginator="$leadPaginator" :locale="$locale" /></div>
            <p role="alert" data-crm-outcome-error hidden>{{ $t('تعذر تحديث الخيارات. حاول مرة أخرى.', 'Could not update choices. Try again.') }}</p>
            </div>
            @include('crm._outcome-picker-script')
        </x-ui.modal>
        @if (request()->hasAny(['lead_q', 'lead_page']))
            <script>document.getElementById('opportunity-create').showModal();</script>
        @endif
        @if ($selected)<x-ui.detail-panel id="opportunity-edit" :title="$t('تعديل الصفقة', 'Edit opportunity')">@include('crm._opportunity-form', ['opportunity' => $selected, 'formId' => 'edit'])</x-ui.detail-panel>@endif
    @endif
</div>
@endsection

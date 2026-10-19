@php
    $stageNames = ['lead' => $t('عميل محتمل', 'Prospect'), 'contact' => $t('تواصل أولي', 'Initial contact'), 'proposal' => $t('عرض سعر', 'Proposal'), 'negotiation' => $t('تفاوض', 'Negotiation'), 'review' => $t('مراجعة داخلية', 'Internal review'), 'closed' => $t('إغلاق', 'Closed')];
    $tones = ['lead' => 'success', 'contact' => 'info', 'proposal' => 'warning', 'negotiation' => 'warning', 'review' => 'neutral', 'closed' => 'danger'];
    $workTypes = ['product_only' => $t('بيع منتجات', 'Product-only sale'), 'supply_installation' => $t('توريد وتركيب', 'Supply and installation'), 'maintenance' => $t('صيانة', 'Maintenance'), 'site_inspection' => $t('معاينة موقع', 'Site inspection')];
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
        <input type="hidden" name="view" value="{{ request('view', 'board') }}">
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
    <nav class="ui-inline" aria-label="{{ $t('طريقة عرض الفرص', 'Opportunity view') }}">
        <a class="ui-button {{ request('view', 'board') === 'board' ? 'ui-button--primary' : 'ui-button--ghost' }}" aria-current="{{ request('view', 'board') === 'board' ? 'page' : 'false' }}" href="{{ request()->fullUrlWithQuery(['view' => 'board', 'opportunity_page' => null]) }}">{{ $t('لوحة المراحل', 'Stage board') }}</a>
        <a class="ui-button {{ request('view', 'board') === 'directory' ? 'ui-button--primary' : 'ui-button--ghost' }}" aria-current="{{ request('view', 'board') === 'directory' ? 'page' : 'false' }}" href="{{ request()->fullUrlWithQuery(['view' => 'directory', 'opportunity_page' => null]) }}">{{ $t('قائمة الفرص', 'Opportunity directory') }}</a>
    </nav>
    @if (request('view') === 'directory')
        <x-ui.table :caption="$t('قائمة الفرص', 'Opportunity directory')">
            <thead><tr><th>{{ $t('الفرصة', 'Opportunity') }}</th><th>{{ $t('العميل', 'Lead') }}</th><th>{{ $t('المرحلة', 'Stage') }}</th><th>{{ $t('القيمة', 'Amount') }}</th></tr></thead>
            <tbody>@forelse ($directory as $opportunity)<tr><td><a href="{{ request()->fullUrlWithQuery(['selected' => $opportunity->id, 'history_page' => null]) }}#opportunity-history">{{ $opportunity->title }}</a></td><td>{{ $opportunity->organization ?: $opportunity->lead_name }}</td><td>{{ $stageNames[$opportunity->stage] }}</td><td><bdi>{{ $opportunity->amount }} {{ $opportunity->currency }}</bdi></td></tr>@empty<tr><td colspan="4">{{ $t('لا توجد فرص', 'No opportunities') }}</td></tr>@endforelse</tbody>
        </x-ui.table>
        <x-ui.list-pagination :paginator="$directory" :locale="$locale" />
    @else
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
                        @if ($canWrite && $opportunity->stage !== 'closed')<a class="ui-button ui-button--ghost" href="{{ request()->fullUrlWithQuery(['selected' => $opportunity->id]) }}#opportunity-edit">{{ $t('تعديل', 'Edit') }}</a>@endif
                        <a class="ui-button ui-button--ghost" href="{{ request()->fullUrlWithQuery(['selected' => $opportunity->id, 'history_page' => null]) }}#opportunity-history">{{ $t('السجل', 'History') }}</a>
                        <a class="ui-button ui-button--ghost" href="/crm/leads/{{ $opportunity->lead_id }}?locale={{ $locale }}">{{ $t('فتح العميل', 'Open lead') }}</a>
                        </div>
                    </article>
                @empty<p class="ui-muted">{{ $t('لا توجد صفقات', 'No opportunities') }}</p>@endforelse
                @if ($columns[$stage]->hasPages())<x-ui.list-pagination :paginator="$columns[$stage]" :locale="$locale" />@endif
            </section>
        @endforeach
        </div>
    </section>
    @endif
    @if (request('view') !== 'directory')
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
    @endif
    @if ($canWrite)
        <x-ui.modal id="opportunity-create" :title="$t('إضافة صفقة', 'Add opportunity')" :close-label="$t('إغلاق', 'Close')">
            <nav class="ui-inline" aria-label="{{ $t('مصدر الفرصة', 'Opportunity source') }}">
                <a class="ui-button {{ request('source', 'lead') === 'lead' ? 'ui-button--primary' : 'ui-button--ghost' }}" aria-current="{{ request('source', 'lead') === 'lead' ? 'page' : 'false' }}" href="{{ request()->fullUrlWithQuery(['source' => 'lead', 'lead_page' => 1, 'lead_q' => null]) }}">{{ $t('عميل محتمل', 'Lead source') }}</a>
                <a class="ui-button {{ request('source', 'lead') === 'customer' ? 'ui-button--primary' : 'ui-button--ghost' }}" aria-current="{{ request('source', 'lead') === 'customer' ? 'page' : 'false' }}" href="{{ request()->fullUrlWithQuery(['source' => 'customer', 'lead_page' => 1, 'lead_q' => null]) }}">{{ $t('عميل محول', 'Customer source') }}</a>
            </nav>
            <div data-crm-outcome-picker data-crm-option-name="{{ request('source') === 'customer' ? 'customer_id' : 'lead_id' }}" data-crm-picker-dialog="opportunity-create">
            <form method="get" action="/crm/pipeline" class="ui-inline" data-crm-outcome-search>
                @foreach (request()->only(['locale', 'source', 'view', 'opportunity_page', 'q', 'currency', 'stage', 'probability_min', 'from', 'to', 'selected', 'closing_page', 'lead_page', 'contact_page', 'proposal_page', 'negotiation_page', 'review_page', 'closed_page']) as $key => $value)
                    @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <x-ui.search-input id="opportunity-lead-search" name="lead_q" :value="request('lead_q')" maxlength="120" :aria-label="request('source') === 'customer' ? $t('البحث عن عميل محول', 'Search customers') : $t('البحث عن عميل', 'Search leads')" /><x-ui.button type="submit" variant="secondary">{{ $t('بحث', 'Search') }}</x-ui.button>
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
        @if ($selected && $selected->stage !== 'closed')
            <x-ui.detail-panel id="opportunity-edit" :title="$t('تعديل الصفقة', 'Edit opportunity')">
                @include('crm._opportunity-form', ['opportunity' => $selected, 'formId' => 'edit'])
            </x-ui.detail-panel>
            @if ($transitionOptions !== [])
                <x-ui.detail-panel :title="$t('انتقال المرحلة', 'Change stage')">
                    <p class="ui-muted">{{ $t('مسار تجريبي مقترح. الإغلاق وإعادة الفتح غير متاحين حاليًا.', 'Proposed development workflow. Closing and reopening are currently unavailable.') }}</p>
                    <form data-opportunity-transition class="ui-stack" method="post" action="/crm/pipeline/{{ $selected->id }}/transition?locale={{ $locale }}">
                        @csrf
                        <input type="hidden" name="version" value="{{ $selected->version }}">
                        <input type="hidden" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <x-ui.field id="opportunity-target-stage" :label="$t('المرحلة التالية', 'Next stage')">
                            <x-ui.select id="opportunity-target-stage" name="target_stage" required>
                                @foreach ($transitionOptions as $stage)<option value="{{ $stage }}">{{ $stageNames[$stage] }}</option>@endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field id="opportunity-transition-reason" :label="$t('السبب — مطلوب عند الرجوع', 'Reason — required when moving backwards')">
                            <x-ui.textarea id="opportunity-transition-reason" name="reason" maxlength="1000" />
                        </x-ui.field>
                        <x-ui.button type="submit">{{ $t('نقل المرحلة', 'Move opportunity') }}</x-ui.button>
                    </form>
                </x-ui.detail-panel>
            @endif
        @endif
    @endif
    @if ($history)
        <div id="opportunity-history" data-opportunity-history>
            <x-ui.detail-panel :title="$t('سجل تغييرات الفرصة', 'Opportunity history')">
                <x-ui.table :caption="$t('النسخ المحفوظة للفرصة', 'Saved opportunity versions')">
                    <thead><tr><th>{{ $t('النسخة', 'Version') }}</th><th>{{ $t('وقت الحفظ', 'Saved at') }}</th><th>{{ $t('العنوان', 'Title') }}</th><th>{{ $t('المرحلة', 'Stage') }}</th><th>{{ $t('القيمة', 'Amount') }}</th></tr></thead>
                    <tbody>
                    @forelse ($history as $entry)
                        @php($snapshot = json_decode($entry->snapshot, true, 512, JSON_THROW_ON_ERROR))
                        <tr><td>{{ $entry->version }}</td><td><bdi>{{ $entry->created_at }}</bdi></td><td>{{ $snapshot['title'] }}</td><td>{{ $stageNames[$snapshot['stage']] }}</td><td><bdi>{{ $snapshot['amount'] }} {{ $snapshot['currency'] }}</bdi></td></tr>
                    @empty
                        <tr><td colspan="5">{{ $t('لا توجد نسخ محفوظة بعد', 'No saved versions yet') }}</td></tr>
                    @endforelse
                    </tbody>
                </x-ui.table>
                <x-ui.list-pagination :paginator="$history" :locale="$locale" />
            </x-ui.detail-panel>
        </div>
    @endif
</div>
@endsection

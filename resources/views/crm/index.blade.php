@extends('layouts.shell')
@section('content')
@php
$priorityLabels = ['high' => $t('عالية', 'High'), 'normal' => $t('متوسطة', 'Normal'), 'low' => $t('منخفضة', 'Low')];
$statusLabels = ['new' => $t('جديد', 'New'), 'assigned' => $t('قيد المتابعة', 'Assigned'), 'converted' => $t('تم التحويل', 'Converted')];
$summary = [
 ['label'=>$t('إجمالي العملاء المحتملين','Total leads'),'value'=>$metrics['total'],'icon'=>'users','tone'=>'info','description'=>$t('ضمن نطاق صلاحياتك','Within your authorized scope')],
 ['label'=>$t('الجدد هذا الشهر','New this month'),'value'=>$metrics['new'],'icon'=>'plus','tone'=>'primary','description'=>$t('السجلات النشطة منذ بداية الشهر','Active records created this month')],
 ['label'=>$t('المتأخرون في المتابعة','Overdue follow-ups'),'value'=>$metrics['overdue'],'icon'=>'clock','tone'=>'danger','description'=>$t('عملاء لديهم موعد متابعة مضى','Leads with a past follow-up date')],
 ['label'=>$t('معدل التحويل','Conversion rate'),'value'=>$metrics['conversion'].'%','icon'=>'target','tone'=>'warning','description'=>$t('العملاء المحوّلون من السجلات النشطة','Converted share of active leads')],
];
@endphp
<x-ui.directory-workspace>
 <x-ui.summary-strip :items="$summary" variant="cards" />
 <x-ui.filter-bar id="crm-lead-filters" action="/crm/leads" data-tour="crm.leads.filters" :aria-label="$t('البحث والتصفية','Search and filters')">
  <input type="hidden" name="locale" value="{{ $locale }}">
  <x-ui.field id="crm-filter-q" :label="$t('بحث','Search')"><x-ui.search-input id="crm-filter-q" name="q" :value="$filters['q'] ?? ''" :placeholder="$t('البحث في العملاء المحتملين...','Search leads...')" maxlength="120" /></x-ui.field>
  <x-ui.field id="crm-filter-status" :label="$t('الحالة','Status')"><x-ui.select id="crm-filter-status" name="status"><option value="">{{ $t('الكل','All') }}</option>@foreach ($statusLabels as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</x-ui.select></x-ui.field>
  <x-ui.field id="crm-filter-source" :label="$t('المصدر','Source')"><x-ui.select id="crm-filter-source" name="source"><option value="">{{ $t('الكل','All') }}</option>@foreach ($sources as $source)<option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $source }}</option>@endforeach</x-ui.select></x-ui.field>
  <x-ui.field id="crm-filter-stage" :label="$t('المرحلة','Stage')"><x-ui.select id="crm-filter-stage" name="stage"><option value="">{{ $t('كل المراحل','All stages') }}</option>@if (filled($filters['stage'] ?? null) && !collect($stages)->contains('id',$filters['stage']))<option value="{{ $filters['stage'] }}" selected>{{ $stageLabels[$filters['stage']] ?? $t('المرحلة المحددة','Selected stage') }}</option>@endif @foreach ($stages as $stage)<option value="{{ $stage['id'] }}" @selected(($filters['stage'] ?? '') === $stage['id'])>{{ $stage['label'] }}</option>@endforeach</x-ui.select></x-ui.field>
  <x-ui.field id="crm-filter-priority" :label="$t('الأولوية','Priority')"><x-ui.select id="crm-filter-priority" name="priority"><option value="">{{ $t('الكل','All') }}</option>@foreach ($priorityLabels as $value=>$label)<option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>@endforeach</x-ui.select></x-ui.field>
  <div class="ui-inline"><x-ui.button type="submit"><x-ui.icon name="filter" />{{ $t('تطبيق','Apply filters') }}</x-ui.button><a href="/crm/leads?locale={{ $locale }}">{{ $t('مسح','Clear') }}</a></div>

 </x-ui.filter-bar>
 <section class="ui-directory-table" data-tour="crm.leads.results">
  <x-ui.directory-toolbar>
   <x-ui.icon name="filter" /><label for="crm-per-page">{{ $t('عرض','Show') }}</label><x-ui.select id="crm-per-page" name="per_page" form="crm-lead-filters">@foreach ([10,25,100] as $size)<option value="{{ $size }}" @selected($leads->perPage()===$size)>{{ $size }}</option>@endforeach</x-ui.select><strong><bdi>{{ $leads->total() }}</bdi> {{ $t('عميل محتمل','leads') }}</strong>
   <x-slot:actions>
  <details class="ui-directory-options"><summary>{{ $t('خيارات المراحل والأرشيف','Stage options and archive') }}</summary><div class="ui-inline"><x-ui.field id="crm-stage-option-search" :label="$t('ابحث في المراحل','Search stages')"><x-ui.search-input form="crm-lead-filters" id="crm-stage-option-search" name="stage_q" :value="request('stage_q')" /></x-ui.field><x-ui.field id="crm-filter-archived" :label="$t('السجلات','Records')"><x-ui.select form="crm-lead-filters" id="crm-filter-archived" name="archived"><option value="false" @selected(($filters['archived'] ?? 'false') === 'false')>{{ $t('النشطة','Active') }}</option><option value="true" @selected(($filters['archived'] ?? '') === 'true')>{{ $t('المؤرشفة','Archived') }}</option></x-ui.select></x-ui.field></div>@if ($stagePaginator->lastPage()>1)<x-ui.list-pagination :paginator="$stagePaginator" :locale="$locale" />@endif</details>
<a class="ui-button ui-button--ghost" href="/crm?locale={{ $locale }}">{{ $t('نظرة عامة','Overview') }}</a>@if (in_array('crm.manage',$permissions,true))<x-ui.button type="button" data-open-dialog="crm-create-drawer"><x-ui.icon name="plus" />{{ $t('إضافة عميل محتمل','Add lead') }}</x-ui.button><noscript><a href="/crm/leads/create?locale={{ $locale }}">{{ $t('إضافة عميل محتمل','Add lead') }}</a></noscript>@endif</x-slot:actions>
  </x-ui.directory-toolbar>
  @if ($leads->isEmpty())<x-ui.empty-state :title="$t('لا توجد نتائج','No leads found')" :description="$t('جرّب تعديل البحث أو عوامل التصفية.','Try changing the search or filters.')" />@else
  <x-ui.table :caption="$t('قائمة العملاء المحتملين ضمن نطاقك','Leads in your authorized scope')">
   <thead><tr>@foreach ([$t('الاسم','Name'),$t('الشركة','Organization'),$t('الهاتف','Phone'),$t('البريد','Email'),$t('المصدر','Source'),$t('المرحلة','Stage'),$t('المسؤول','Owner'),$t('آخر تفاعل','Last activity'),$t('المتابعة القادمة','Next follow-up'),$t('الأولوية','Priority'),$t('الحالة','Status'),$t('الإجراءات','Actions')] as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead>
   <tbody>@foreach ($leads as $lead)
   @php($status=$lead['customer_id']!==null?'converted':($lead['assignee_id']!==null?'assigned':'new'))
   <tr>
    <th scope="row"><a class="ui-directory-cell" title="{{ $lead['name'] }}" href="/crm/leads/{{ $lead['id'] }}?locale={{ $locale }}">{{ $lead['name'] }}</a></th>
    <td><span class="ui-directory-cell" title="{{ $lead['organization'] }}">{{ $lead['organization'] }}</span></td>
    <td><a class="ui-inline" href="/crm/leads/{{ $lead['id'] }}?locale={{ $locale }}#crm-lead-contact"><x-ui.icon name="phone" />{{ $t('محمي','Protected') }}</a></td>
    <td><a class="ui-inline" href="/crm/leads/{{ $lead['id'] }}?locale={{ $locale }}#crm-lead-contact"><x-ui.icon name="mail" />{{ $t('محمي','Protected') }}</a></td>
    <td><span class="ui-directory-cell" title="{{ $lead['source'] }}">{{ $lead['source'] }}</span></td>
    <td><x-ui.status-badge status="info">{{ $stageLabels[$lead['stage_id']] ?? $t('بدون مرحلة','No stage') }}</x-ui.status-badge></td>
    <td>@if ($lead['assignee_name'])<span class="ui-inline"><x-ui.avatar :name="$lead['assignee_name']" /><span class="ui-directory-cell" title="{{ $lead['assignee_name'] }}">{{ $lead['assignee_name'] }}</span></span>@else<span class="ui-muted">{{ $t('غير مسند','Unassigned') }}</span>@endif</td>
    <td><time dir="ltr">{{ $lead['last_activity'] ? \Illuminate\Support\Carbon::parse($lead['last_activity'])->format('Y/m/d H:i') : '—' }}</time></td>
    <td><time dir="ltr">{{ $lead['next_follow_up'] ? \Illuminate\Support\Carbon::parse($lead['next_follow_up'])->format('Y/m/d H:i') : '—' }}</time></td>
    <td><x-ui.status-badge :status="match($lead['priority']){'high'=>'danger','low'=>'neutral',default=>'warning'}">{{ $priorityLabels[$lead['priority']] }}</x-ui.status-badge></td>
    <td><x-ui.status-badge :status="$status==='new'?'success':'info'">{{ $statusLabels[$status] }}</x-ui.status-badge></td>
    <td><a class="ui-button ui-button--ghost ui-icon-button" href="/crm/leads/{{ $lead['id'] }}?locale={{ $locale }}" aria-label="{{ $t('تفاصيل','Details').' '.$lead['name'] }}"><x-ui.icon name="more" /></a></td>
   </tr>@endforeach</tbody>
  </x-ui.table><x-ui.list-pagination :paginator="$leads" :locale="$locale" />@endif
 </section>
 @if (in_array('crm.manage',$permissions,true))<x-slot:editor><x-ui.drawer id="crm-create-drawer" :title="$t('إضافة عميل محتمل','Add lead')" :close-label="$t('إغلاق','Close')" docked>@include('crm._quick-create')</x-ui.drawer></x-slot:editor>
 @endif
 <x-ui.page-tour id="crm-leads-list" :locale="$locale" :actor="$actor" :permissions="$permissions" />
</x-ui.directory-workspace>
@endsection



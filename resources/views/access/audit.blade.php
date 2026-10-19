@php
    $locale = ($locale ?? 'ar') === 'en' ? 'en' : 'ar';
    $t = static fn ($ar, $en) => $locale === 'ar' ? $ar : $en;
    $permissions = $permissions ?? [];

    $title = $t('سجل التدقيق', 'Audit log');
    $entries = $entries ?? [];
@endphp
@extends('layouts.shell', ['locale'=>$locale,'title'=>$title])
@section('content')
<div class="ui-stack">
    <x-ui.summary-strip :items="[['label'=>$t('إجمالي أحداث التدقيق','Total audit events'),'value'=>$summary['total'] ?? count($entries),'icon'=>'audit']]" />
    <section class="admin-section" data-tour="access.audit.filters"><form method="get" action="/access/audit" class="admin-toolbar" data-testid="audit-filters"><input type="hidden" name="locale" value="{{ $locale }}">
        <x-ui.field id="audit-search" :label="$t('البحث في السجل','Search audit log')" class="admin-search"><x-ui.search-input name="q" :value="request('q')" /></x-ui.field>
        <x-ui.field id="audit-action" :label="$t('الإجراء','Action')"><x-ui.input name="action" :value="request('action')" /></x-ui.field>
        <x-ui.field id="audit-actor" :label="$t('معرّف المنفذ','Actor ID')"><x-ui.input name="actor_id" :value="request('actor_id')" /></x-ui.field>
        <x-ui.field id="audit-subject" :label="$t('معرّف السجل','Record ID')"><x-ui.input name="subject_id" :value="request('subject_id')" /></x-ui.field>
        <x-ui.field id="audit-from" :label="$t('من تاريخ','From date')"><x-ui.date-input name="from" :value="request('from')" /></x-ui.field>
        <x-ui.field id="audit-to" :label="$t('إلى تاريخ','To date')"><x-ui.date-input name="to" :value="request('to')" /></x-ui.field>
        <x-ui.list-controls :locale="$locale" :sorts="['date'=>$t('الوقت','Time')]" />
        <x-ui.button type="submit">{{ $t('تطبيق','Apply') }}</x-ui.button>
    </form></section>
    <section class="admin-section admin-table" data-tour="access.audit.results"><div class="admin-section-heading"><h2><x-ui.icon name="audit" />{{ $t('التغييرات المسجلة','Recorded changes') }}</h2></div><x-ui.table :caption="$t('سجل تدقيق إدارة الوصول','Access management audit log')"><thead><tr><th scope="col">{{ $t('الوقت والتاريخ','Date and time') }}</th><th scope="col">{{ $t('المستخدم','Actor') }}</th><th scope="col">{{ $t('الحدث','Event') }}</th><th scope="col">{{ $t('الهدف','Target') }}</th><th scope="col">{{ $t('التفاصيل','Details') }}</th></tr></thead><tbody>
    @forelse($entries as $entry)
        @php($detail = json_encode(['event'=>$entry['action'] ?? '', 'metadata'=>$entry['metadata'] ?? []],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))
        <tr><td><bdi>{{ $entry['created_at'] ?? '—' }}</bdi></td><td><bdi>{{ $entry['actor_id'] ?? '—' }}</bdi></td><td><x-ui.status-badge status="info">{{ $entry['action'] ?? '—' }}</x-ui.status-badge></td><td><bdi>{{ $entry['subject_id'] ?? '—' }}</bdi></td><td>@if(empty($entry['metadata']))<span data-empty-metadata>—</span>@endif <x-ui.button variant="ghost" data-open-dialog="audit-detail" data-audit-detail="{{ $detail }}">{{ $t('عرض التفاصيل','View details') }}</x-ui.button></td></tr>
    @empty<tr><td colspan="5">{{ $t('لا توجد أحداث تطابق البحث.','No matching audit events.') }}</td></tr>@endforelse
    </tbody></x-ui.table><x-ui.list-pagination :paginator="$entries" :locale="$locale" /></section>
    <x-ui.drawer id="audit-detail" :title="$t('تفاصيل الحدث','Event details')" :close-label="$t('إغلاق','Close')"><div class="admin-audit-detail"><p class="ui-muted">{{ $t('بيانات الحدث المنقّحة ضمن نطاق وصولك.','Redacted event data within your access scope.') }}</p><pre data-audit-content></pre></div></x-ui.drawer>
    <x-ui.page-tour id="access-audit" :locale="$locale" :actor="$actor" :permissions="$permissions" />
</div>
@endsection

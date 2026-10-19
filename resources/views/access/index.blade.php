@php
    $locale = ($locale ?? 'ar') === 'en' ? 'en' : 'ar';
    $t = static fn ($ar, $en) => $locale === 'ar' ? $ar : $en;
    $permissions = $permissions ?? [];

    $title = $t('إدارة الوصول', 'Access management');
    $metrics = ['users' => [$t('إجمالي المستخدمين', 'Total users'),'users'], 'roles' => [$t('الأدوار المتاحة', 'Available roles'),'shield'], 'branches' => [$t('عدد الفروع', 'Branches'),'building'], 'audit' => [$t('أحداث سجل التدقيق', 'Audit events'),'audit']];
    $items = [];
    foreach ($summary ?? [] as $key => $value) { if (isset($metrics[$key])) $items[] = ['label'=>$metrics[$key][0],'value'=>$value,'icon'=>$metrics[$key][1]]; }
@endphp
@extends('layouts.shell', ['locale'=>$locale, 'title'=>$title])
@section('content')
<div class="ui-stack">
    @include('access._nav')
    <section class="admin-section admin-identity" aria-label="{{ $t('حسابك الحالي', 'Your account') }}">
        <div class="admin-identity__type"><span class="admin-icon-tile"><x-ui.icon name="users" /></span><div><p class="ui-muted">{{ $t('نوع الحساب', 'Account type') }}</p><p>{{ ($actor['is_admin'] ?? false) ? $t('مسؤول المؤسسة', 'Organization administrator') : $t('مستخدم المؤسسة', 'Organization user') }}</p></div></div>
        <div class="admin-identity__email"><span class="admin-icon-tile"><x-ui.icon name="mail" /></span><div><p class="ui-muted">{{ $t('البريد الإلكتروني', 'Email') }}</p><p><bdi>{{ $actor['email'] ?? '—' }}</bdi></p></div></div>
        <div class="admin-identity__branch"><span class="admin-icon-tile"><x-ui.icon name="building" /></span><div><p class="ui-muted">{{ $t('الفرع', 'Branch') }}</p><p><bdi>{{ $actor['branch_name'] ?? $actor['branch_id'] ?? '—' }}</bdi></p></div></div>
        <div class="admin-entity admin-identity__account"><x-ui.avatar :name="$actor['name'] ?? ''" /><div><p class="ui-muted">{{ $t('حسابك الحالي', 'Your account') }}</p><h2>{{ $actor['name'] ?? '—' }}</h2><p><bdi>{{ $actor['email'] ?? '—' }}</bdi></p><p class="ui-muted"><bdi>{{ $actor['id'] ?? '—' }}</bdi></p></div></div>
    </section>
    <x-ui.summary-strip :items="$items" variant="cards" data-testid="overview-summary" :aria-label="$t('ملخص إدارة الوصول', 'Access summary')" />
    <div class="admin-navigation-cards" data-tour="access.overview.sections">
        @foreach ([['organization.read','organization','building',$t('المؤسسة','Organization'),$t('إدارة الشركة والفروع والأقسام ضمن نطاقك.','Manage your company, branches and departments.')],['users.read','users','users',$t('المستخدمون','Users'),$t('إدارة حسابات المستخدمين وإضافة وتعديل الصلاحيات.','Manage user accounts and their access.')],['roles.read','roles','shield',$t('الأدوار والصلاحيات','Roles and permissions'),$t('إدارة الأدوار وتحديد صلاحيات الوصول للموارد.','Manage roles and resource permissions.')],['audit.read','audit','audit',$t('سجل التدقيق','Audit log'),$t('مراجعة التغييرات المسجلة لإدارة الوصول.','Review recorded access management changes.')]] as [$permission,$path,$icon,$label,$description])
            @if (in_array($permission,$permissions,true))<section class="admin-section admin-navigation-card"><span class="admin-icon-tile"><x-ui.icon :name="$icon" /></span><h2>{{ $label }}</h2><p>{{ $description }}</p><a href="/access/{{ $path }}?locale={{ $locale }}">{{ $t('فتح','Open') }} {{ $label }} <x-ui.icon name="arrow" /></a></section>@endif
        @endforeach
    </div>
    @if (in_array('audit.read',$permissions,true))
        <section class="admin-section admin-table"><div class="admin-section-heading"><h2><x-ui.icon name="audit" />{{ $t('أحدث أنشطة التدقيق','Recent audit activity') }}</h2><a href="/access/audit?locale={{ $locale }}">{{ $t('عرض سجل التدقيق الكامل','View full audit log') }}</a></div><x-ui.table :caption="$t('أحدث أنشطة التدقيق','Recent audit activity')"><thead><tr><th scope="col">{{ $t('الوقت','Time') }}</th><th scope="col">{{ $t('المستخدم','Actor') }}</th><th scope="col">{{ $t('الحدث','Event') }}</th><th scope="col">{{ $t('الهدف','Target') }}</th></tr></thead><tbody>@forelse ($auditPreview ?? [] as $entry)<tr><td><bdi>{{ $entry['created_at'] }}</bdi></td><td><bdi>{{ $entry['actor_id'] ?? '—' }}</bdi></td><td>{{ $entry['action'] }}</td><td><bdi>{{ $entry['subject_id'] }}</bdi></td></tr>@empty<tr><td colspan="4">{{ $t('لا توجد أنشطة مسجلة بعد.','No activity recorded yet.') }}</td></tr>@endforelse</tbody></x-ui.table></section>
    @endif
    <x-ui.page-tour id="access-overview" :locale="$locale" :actor="$actor" :permissions="$permissions" />
</div>
@endsection

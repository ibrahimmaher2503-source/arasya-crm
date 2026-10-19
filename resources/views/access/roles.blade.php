@php
    $locale = ($locale ?? 'ar') === 'en' ? 'en' : 'ar';
    $t = static fn ($ar, $en) => $locale === 'ar' ? $ar : $en;
    $permissions = $permissions ?? [];

    $title = $t('الأدوار والصلاحيات', 'Roles and permissions');
    $roles = $roles ?? [];
    $catalog = $catalog ?? [];
    $catalog = ($actor['is_admin'] ?? false) ? $catalog : array_values(array_intersect($catalog,$permissions));
    $canWriteRoles = in_array('roles.write',$permissions,true);
    $labels = [
        'contact.reveal' => $t('عرض بيانات تواصل العملاء', 'View customer contact details'), 'crm.assign' => $t('إسناد العملاء', 'Assign leads'), 'tasks.assign' => $t('إسناد المهام للآخرين', 'Assign tasks to others'),'organization.read'=>$t('عرض المؤسسة','View organization'),'organization.write'=>$t('إدارة المؤسسة','Manage organization'),'users.read'=>$t('عرض المستخدمين','View users'),'users.write'=>$t('إدارة المستخدمين','Manage users'),'roles.read'=>$t('عرض الأدوار','View roles'),'roles.write'=>$t('إدارة الأدوار','Manage roles'),'audit.read'=>$t('عرض سجل التدقيق','View audit log')];
@endphp
@extends('layouts.shell', ['locale'=>$locale,'title'=>$title])
@section('content')
<div class="ui-stack">
    @include('access._nav')
    <x-ui.summary-strip :items="[['label'=>$t('إجمالي الأدوار','Total roles'),'value'=>$summary['total'] ?? count($roles),'icon'=>'users'],['label'=>$t('الصلاحيات الممنوحة للأدوار','Role permission grants'),'value'=>$summary['permissions'] ?? 0,'icon'=>'shield'],['label'=>$t('الصلاحيات المتاحة لحسابك','Permissions available to you'),'value'=>count($catalog),'icon'=>'grid']]" />
    @if($canWriteRoles)
    <form method="post" action="/access/roles" class="admin-role-editor" data-testid="role-form">
        @csrf
        <section class="admin-section admin-role-details">
            <div class="admin-section-heading"><h2><x-ui.icon name="edit" />{{ $t('تفاصيل الدور','Role details') }}</h2><x-ui.button type="reset"><x-ui.icon name="plus" />{{ $t('إنشاء دور جديد','Create new role') }}</x-ui.button></div>
            <div class="ui-stack">
                <div class="admin-grid">
                    <x-ui.field id="new-role-name" :label="$t('اسم الدور','Role name')" :error="$errors->first('name')"><x-ui.input name="name" :value="old('name')" required maxlength="120" :placeholder="$t('مثال: مدير الفرع','Example: branch manager')" /></x-ui.field>
                    <div class="admin-role-scope"><span class="ui-field__label">{{ $t('نطاق الدور','Role scope') }}</span><p>{{ $t('ضمن نطاق حسابك','Within your account scope') }}</p></div>
                </div>
                <p class="ui-muted admin-role-note">{{ $t('تسري الصلاحيات ضمن نطاق المؤسسة والفرع المسموح لحسابك.','Permissions apply within your authorized company and branch scope.') }}</p>
                <section class="admin-role-summary" aria-label="{{ $t('ملخص الصلاحيات المحددة','Selected permissions summary') }}">
                    <h3><x-ui.icon name="grid" />{{ $t('ملخص الصلاحيات المحددة','Selected permissions summary') }}</h3>
                    <div class="admin-summary"><div class="admin-summary__item"><span class="admin-icon-tile"><x-ui.icon name="shield" /></span><div><span class="ui-muted">{{ $t('إجمالي الصلاحيات','Total permissions') }}</span><strong data-permission-total>{{ count(old('permissions', [])) }}</strong></div></div><div class="admin-summary__item"><span class="admin-icon-tile"><x-ui.icon name="grid" /></span><x-ui.stat-card :label="$t('مجموعات الصلاحيات المتاحة','Available permission groups')" :value="collect($catalog)->map(fn ($permission) => explode('.', $permission)[0])->unique()->count()" /></div></div>
                </section>
                <x-ui.form-actions><x-ui.button type="submit">{{ $t('حفظ الدور','Save role') }}</x-ui.button><x-ui.button type="reset" variant="secondary">{{ $t('إلغاء','Cancel') }}</x-ui.button></x-ui.form-actions>
            </div>
        </section>
        <x-erp.permission-groups :catalog="$catalog" :labels="$labels" :selected="old('permissions',[])" id="new-role" :locale="$locale" />
    </form>
    @endif
    <section class="admin-section admin-table" data-tour="access.roles.results"><div class="admin-section-heading"><h2>{{ $t('قائمة الأدوار','Role list') }}</h2><form method="get" action="/access/roles" class="admin-toolbar" data-tour="access.roles.filters"><input type="hidden" name="locale" value="{{ $locale }}"><x-ui.field id="roles-search" :label="$t('البحث في الأدوار','Search roles')"><x-ui.search-input name="q" :value="request('q')" :placeholder="$t('البحث في الأدوار...','Search roles...')" /></x-ui.field><details><summary>{{ $t('خيارات العرض','Display options') }}</summary><div class="admin-toolbar"><x-ui.list-controls :locale="$locale" :sorts="['name'=>$t('الاسم','Name')]" /></div></details><x-ui.button type="submit" variant="secondary">{{ $t('بحث','Search') }}</x-ui.button></form></div>
    <x-ui.table :caption="$t('الأدوار المتاحة','Available roles')"><thead><tr><th scope="col">{{ $t('اسم الدور','Role name') }}</th><th scope="col">{{ $t('عدد الصلاحيات','Permissions') }}</th><th scope="col">{{ $t('الصلاحيات الممنوحة','Granted permissions') }}</th><th scope="col">{{ $t('الإصدار','Version') }}</th><th scope="col">{{ $t('الإجراءات','Actions') }}</th></tr></thead><tbody>@forelse($roles as $role)<tr><th scope="row"><a href="/access/roles/{{ rawurlencode($role['id']) }}?locale={{ $locale }}">{{ $role['name'] }}</a></th><td><x-ui.status-badge status="success">{{ count($role['permissions']) }}</x-ui.status-badge></td><td>{{ implode('، ',array_map(fn($permission)=>$labels[$permission] ?? $permission,$role['permissions'])) }}</td><td><bdi>{{ $role['version'] }}</bdi></td><td><a href="/access/roles/{{ rawurlencode($role['id']) }}?locale={{ $locale }}">{{ $t('عرض وتعديل','View and edit') }}</a></td></tr>@empty<tr><td colspan="5">{{ $t('لا توجد أدوار تطابق البحث.','No matching roles.') }}</td></tr>@endforelse</tbody></x-ui.table><x-ui.list-pagination :paginator="$roles" :locale="$locale" /></section>
    <x-ui.page-tour id="access-roles" :locale="$locale" :actor="$actor" :permissions="$permissions" />
</div>
@endsection

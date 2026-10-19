@php
    $locale = ($locale ?? 'ar') === 'en' ? 'en' : 'ar';
    $t = static fn ($ar, $en) => $locale === 'ar' ? $ar : $en;
    $permissions = $permissions ?? [];

    $title = $t('المستخدمون', 'Users');
    $users = $users ?? [];
    $branchOptions = $branchOptions ?? ($branches ?? []);
    $departmentOptions = $departmentOptions ?? ($departments ?? []);
    $roleOptions = $roleOptions ?? [];
    $canWriteUsers = in_array('users.write', $permissions, true);
@endphp
@extends('layouts.shell', ['locale'=>$locale, 'title'=>$title])
@section('content')
<div class="ui-stack">
    @include('access._nav')
    <x-ui.summary-strip :items="[['label'=>$t('إجمالي المستخدمين','Total users'),'value'=>$summary['total'] ?? count($users),'icon'=>'users','tone'=>'info'],['label'=>$t('نشط','Active'),'value'=>$summary['active'] ?? 0,'icon'=>'users'],['label'=>$t('غير نشط','Inactive'),'value'=>$summary['inactive'] ?? 0,'icon'=>'shield','tone'=>'danger']]" />
    <section class="admin-section">
        <form method="get" action="/access/users" class="admin-toolbar" data-testid="users-filters" data-tour="access.users.filters">
            <input type="hidden" name="locale" value="{{ $locale }}">
            <x-ui.field id="users-search" :label="$t('البحث عن مستخدم','Search users')" class="admin-search"><x-ui.search-input name="q" :value="request('q')" :placeholder="$t('اسم، بريد إلكتروني، أو كلمة مفتاحية...','Name, email or keyword...')" /></x-ui.field>
            <x-ui.field id="users-status" :label="$t('الحالة','Status')"><x-ui.select name="status"><option value="">{{ $t('جميع الحالات','All statuses') }}</option><option value="active" @selected(request('status') === 'active')>{{ $t('نشط','Active') }}</option><option value="inactive" @selected(request('status') === 'inactive')>{{ $t('غير نشط','Inactive') }}</option></x-ui.select></x-ui.field>
            <x-ui.field id="users-branch" :label="$t('الفرع','Branch')"><x-ui.select name="branch_id"><option value="">{{ $t('جميع الفروع','All branches') }}</option>@foreach($branchOptions as $branch)<option value="{{ $branch['id'] }}" @selected(request('branch_id') === $branch['id'])>{{ $branch['name'] }}</option>@endforeach</x-ui.select></x-ui.field>
            <x-ui.field id="users-department" :label="$t('القسم','Department')"><x-ui.select name="department_id"><option value="">{{ $t('جميع الأقسام','All departments') }}</option>@foreach($departmentOptions as $department)<option value="{{ $department['id'] }}" @selected(request('department_id') === $department['id'])>{{ $department['name'] }}</option>@endforeach</x-ui.select></x-ui.field>
            @if(in_array('roles.read',$permissions,true))<x-ui.field id="users-role" :label="$t('الدور','Role')"><x-ui.select name="role_id"><option value="">{{ $t('جميع الأدوار','All roles') }}</option>@foreach($roleOptions as $role)<option value="{{ $role['id'] }}" @selected(request('role_id') === $role['id'])>{{ $role['name'] }}</option>@endforeach</x-ui.select></x-ui.field>@endif
            <x-ui.button type="submit">{{ $t('تطبيق','Apply') }}</x-ui.button>
            @if($canWriteUsers)<x-ui.button data-focus-form="user-create-form"><x-ui.icon name="plus" />{{ $t('إضافة مستخدم','Add user') }}</x-ui.button>@endif
            <details><summary>{{ $t('خيارات العرض','Display options') }}</summary><div class="admin-toolbar"><x-ui.list-controls :locale="$locale" :sorts="['name'=>$t('الاسم','Name'),'email'=>$t('البريد','Email'),'date'=>$t('تاريخ الإنشاء','Created date')]" /></div></details>
        </form>
        <x-ui.option-browser :locale="$locale" :sources="[['label'=>$t('الفروع','Branches'),'query'=>'branch_q','paginator'=>$branchOptions],['label'=>$t('الأقسام','Departments'),'query'=>'department_q','paginator'=>$departmentOptions],['label'=>$t('الأدوار','Roles'),'query'=>'role_q','paginator'=>$roleOptions]]" />
    </section>
    <div class="{{ $canWriteUsers ? 'admin-master-detail' : '' }}">
        @if($canWriteUsers)<aside class="admin-section admin-create-panel" aria-label="{{ $t('إضافة مستخدم','Add user') }}">
            <form class="ui-stack" method="post" action="/access/users" data-testid="user-form" id="user-create-form">
                @csrf
                <x-ui.form-section :title="$t('إضافة مستخدم', 'Add a user')" :description="$t('تُستخدم كلمة المرور لإنشاء الحساب ولا تظهر بعد الحفظ.', 'The password is used to create the account and is never shown after saving.')">
                    <div class="ui-grid">
                        <x-ui.field id="new-user-name" :label="$t('الاسم الكامل', 'Name')" :error="$errors->first('name')">
                            <x-ui.input id="new-user-name" name="name" :value="old('name')" autocomplete="name" required maxlength="160" :placeholder="$t('أدخل الاسم الكامل','Enter full name')" />
                        </x-ui.field>
                        <x-ui.field id="new-user-email" :label="$t('البريد الإلكتروني', 'Email address')" :error="$errors->first('email')">
                            <x-ui.input id="new-user-email" name="email" type="email" :value="old('email')" autocomplete="email" required maxlength="254" placeholder="name@example.test" />
                        </x-ui.field>
                        <x-ui.field id="new-user-password" :label="$t('كلمة المرور', 'Password')" :hint="$t('12 حرفاً على الأقل.', 'At least 12 characters.')" :error="$errors->first('password')">
                            <x-ui.input id="new-user-password" name="password" type="password" autocomplete="new-password" minlength="12" required />
                        </x-ui.field>
                        <x-ui.field id="new-user-branch" :label="$t('الفرع', 'Branch')" :error="$errors->first('branch_id')">
                            <x-ui.select id="new-user-branch" name="branch_id" required>
                                <option value="">{{ $t('اختر فرعاً', 'Choose a branch') }}</option>
                                @foreach ($branchOptions as $branch)
                                    <option value="{{ $branch['id'] }}" @selected((string) old('branch_id') === (string) ($branch['id'] ?? ''))>{{ $branch['name'] ?? $branch['id'] }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field id="new-user-department" :label="$t('القسم', 'Department')" :error="$errors->first('department_id')">
                            <x-ui.select id="new-user-department" name="department_id" required>
                                <option value="">{{ $t('اختر قسماً', 'Choose a department') }}</option>
                                @foreach ($departmentOptions as $department)
                                    <option value="{{ $department['id'] }}" @selected((string) old('department_id') === (string) ($department['id'] ?? ''))>{{ $department['name'] ?? $department['id'] }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    </div>
                </x-ui.form-section>
                <x-ui.form-actions>
                    <x-ui.button type="submit">{{ $t('إضافة المستخدم', 'Add user') }}</x-ui.button>
                </x-ui.form-actions>
            </form>

        </aside>@endif
        <section class="admin-section admin-table" data-testid="users-table" data-tour="access.users.results">
            <x-ui.table :caption="$t('قائمة المستخدمين','User list')"><thead><tr><th scope="col">{{ $t('الاسم','Name') }}</th><th scope="col">{{ $t('البريد الإلكتروني','Email') }}</th>@if(in_array('roles.read',$permissions,true))<th scope="col">{{ $t('الدور','Role') }}</th>@endif<th scope="col">{{ $t('القسم','Department') }}</th><th scope="col">{{ $t('الفرع','Branch') }}</th><th scope="col">{{ $t('الحالة','Status') }}</th><th scope="col">{{ $t('الإجراءات','Actions') }}</th></tr></thead><tbody>
            @forelse($users as $user)<tr><td><div class="admin-entity"><x-ui.avatar :name="$user['name']" /><a href="/access/users/{{ rawurlencode($user['id']) }}?locale={{ $locale }}">{{ $user['name'] }}</a></div></td><td><bdi>{{ $user['email'] }}</bdi></td>@if(in_array('roles.read',$permissions,true))<td><x-ui.status-badge status="info">{{ ($user['is_admin'] ?? false) ? $t('مسؤول المؤسسة','Administrator') : (implode('، ', $user['role_names'] ?? []) ?: $t('مستخدم','User')) }}</x-ui.status-badge></td>@endif<td>{{ $user['department_name'] ?? $user['department_id'] }}</td><td>{{ $user['branch_name'] ?? $user['branch_id'] }}</td><td><x-ui.status-badge :status="$user['active'] ? 'success' : 'danger'">{{ $user['active'] ? $t('نشط','Active') : $t('غير نشط','Inactive') }}</x-ui.status-badge></td><td><a href="/access/users/{{ rawurlencode($user['id']) }}?locale={{ $locale }}" aria-label="{{ $t('تفاصيل المستخدم','User details') }}: {{ $user['name'] }}"><x-ui.icon name="edit" /></a></td></tr>@empty<tr><td colspan="7">{{ $t('لا يوجد مستخدمون يطابقون البحث.','No matching users.') }}</td></tr>@endforelse
            </tbody></x-ui.table><x-ui.list-pagination :paginator="$users" :locale="$locale" />
        </section>
    </div>
    <x-ui.page-tour id="access-users" :locale="$locale" :actor="$actor" :permissions="$permissions" />
</div>
@endsection

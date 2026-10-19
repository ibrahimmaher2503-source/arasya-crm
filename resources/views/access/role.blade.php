@php
    $locale = ($locale ?? 'ar') === 'en' ? 'en' : 'ar';
    $t = static fn (string $arabic, string $english): string => $locale === 'ar' ? $arabic : $english;
    $title = $t('تعديل الدور', 'Edit role');
    $role = $role ?? [];
    $catalogData = is_array($catalog ?? null) ? $catalog : [];
    $catalogPermissions = array_is_list($catalogData) ? $catalogData : array_keys($catalogData);
    $catalogPermissions = array_values(array_filter($catalogPermissions, 'is_string'));
    $permissions = is_array($permissions ?? null) ? $permissions : [];
    $canWriteRoles = in_array('roles.write', $permissions, true);
    $rolePermissions = is_array($role['permissions'] ?? null) ? $role['permissions'] : [];
    $permissionLabels = [
        'contact.reveal' => $t('عرض بيانات تواصل العملاء', 'View customer contact details'), 'crm.assign' => $t('إسناد العملاء', 'Assign leads'), 'tasks.assign' => $t('إسناد المهام للآخرين', 'Assign tasks to others'),
        'organization.read' => $t('عرض المؤسسة', 'View organization'),
        'organization.write' => $t('إدارة المؤسسة', 'Manage organization'),
        'users.read' => $t('عرض المستخدمين', 'View users'),
        'users.write' => $t('إدارة المستخدمين', 'Manage users'),
        'roles.read' => $t('عرض الأدوار', 'View roles'),
        'roles.write' => $t('إدارة الأدوار', 'Manage roles'),
        'audit.read' => $t('عرض سجل التدقيق', 'View audit log'),
    ];
@endphp
@extends('layouts.shell', ['locale' => $locale, 'title' => $title])

@section('content')
    <div class="ui-stack">
        @include('access._nav', ['active' => 'role', 'locale' => $locale, 'actor' => $actor ?? [], 'permissions' => $permissions])
        <p><a href="/access/roles">{{ $t('العودة إلى الأدوار', 'Back to roles') }}</a></p>

        @if ($canWriteRoles)
            <form class="ui-stack" method="post" action="/access/roles/{{ rawurlencode((string) ($role['id'] ?? '')) }}" data-testid="role-form">
                @csrf
                <input type="hidden" name="version" value="{{ $role['version'] ?? 0 }}">
                <x-ui.form-section :title="$t('بيانات الدور', 'Role details')">
                    <x-ui.field id="role-name" :label="$t('اسم الدور', 'Role name')" :error="$errors->first('name')">
                        <x-ui.input id="role-name" name="name" :value="old('name', $role['name'] ?? '')" required maxlength="120" />
                    </x-ui.field>
                    <x-erp.permission-groups :catalog="$catalogPermissions" :labels="$permissionLabels" :selected="old('permissions', $rolePermissions)" id="role" :locale="$locale" />
                </x-ui.form-section>
                <x-ui.form-actions>
                    <x-ui.button type="submit">{{ $t('حفظ الدور', 'Save role') }}</x-ui.button>
                </x-ui.form-actions>
            </form>
        @else
            <x-ui.detail-panel :title="$role['name'] ?? $t('بيانات الدور', 'Role details')">
                <p>{{ is_array($rolePermissions) ? implode(', ', $rolePermissions) : '—' }}</p>
            </x-ui.detail-panel>
        @endif
    </div>
@endsection

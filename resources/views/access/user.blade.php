@php
    $locale = ($locale ?? 'ar') === 'en' ? 'en' : 'ar';
    $t = static fn (string $arabic, string $english): string => $locale === 'ar' ? $arabic : $english;
    $user = $user ?? [];
    $branches = $branches ?? [];
    $departments = $departments ?? [];
    $roles = $roles ?? [];
    $optionSources = [
        ['label' => $t('الفروع', 'Branches'), 'query' => 'branch_q', 'paginator' => $branches],
        ['label' => $t('الأقسام', 'Departments'), 'query' => 'department_q', 'paginator' => $departments],
        ['label' => $t('الأدوار', 'Roles'), 'query' => 'role_q', 'paginator' => $roles],
    ];
    $branchRows = $branches instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $branches->items() : $branches;
    $departmentRows = $departments instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $departments->items() : $departments;
    if (isset($user['branch_id']) && ! in_array($user['branch_id'], array_column($branchRows, 'id'), true)) {
        $branchRows[] = ['id' => $user['branch_id'], 'name' => $user['branch_name'] ?? $t('غير متاح', 'Unavailable')];
    }
    if (isset($user['department_id']) && ! in_array($user['department_id'], array_column($departmentRows, 'id'), true)) {
        $departmentRows[] = ['id' => $user['department_id'], 'name' => $user['department_name'] ?? $t('غير متاح', 'Unavailable')];
    }
    $branches = $branchRows;
    $departments = $departmentRows;
    $actor = is_array($actor ?? null) ? $actor : [];
    $catalogData = is_array($catalog ?? null) ? $catalog : [];
    $catalogPermissions = array_is_list($catalogData) ? $catalogData : array_keys($catalogData);
    $catalogPermissions = array_values(array_filter($catalogPermissions, 'is_string'));
    $permissions = is_array($permissions ?? null) ? $permissions : [];
    $canWriteUsers = in_array('users.write', $permissions, true);
    $title = $canWriteUsers ? $t('تعديل بيانات المستخدم', 'Edit user details') : $t('بيانات المستخدم', 'User details');
    $canManageUserAccess = $canWriteUsers
        && in_array('roles.write', $permissions, true)
        && in_array('roles.read', $permissions, true)
        && filled($actor['id'] ?? null)
        && filled($user['id'] ?? null)
        && (string) $actor['id'] !== (string) $user['id']
        && ($user['assignments_editable'] ?? true);
    $showAccessSection = $canManageUserAccess
        || (($user['assignments_editable'] ?? true) === false && in_array('roles.write', $permissions, true));
    $accountActive = (bool) ($user['active'] ?? false);
    $userRoleIds = is_array($user['role_ids'] ?? null) ? array_map('strval', $user['role_ids']) : [];
    $oldRoleIds = old('role_ids', old('_access_assignment') === '1' ? [] : null);
    $selectedRoleIds = is_array($oldRoleIds) ? array_map('strval', $oldRoleIds) : $userRoleIds;
    $displayedRoleIds = array_column($roles instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $roles->items() : $roles, 'id');
    $currentOverrides = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
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
@extends('layouts.shell', [
    'locale' => $locale,
    'title' => $title,
    'pageSubtitle' => $t('تحديث معلومات الحساب والتعيينات المسموح بها.', 'Update account details and permitted assignments.'),
    'pageIcon' => 'edit',
    'breadcrumbs' => [
        ['label' => $t('مركز العمل', 'Workspace'), 'url' => '/dashboard?locale='.$locale],
        ['label' => $t('المؤسسة والوصول', 'Organization and access'), 'url' => '/access?locale='.$locale],
        ['label' => $t('المستخدمون', 'Users'), 'url' => '/access/users?locale='.$locale],
        ['label' => $title],
    ],
])

@section('content')
    <style>{!! file_get_contents(resource_path('css/user-profile.css')) !!}</style>

    <div class="user-profile ui-stack">
        <div class="user-profile__back-row">
            <a class="user-profile__back-link" href="/access/users?locale={{ $locale }}">
                <x-ui.icon name="arrow" />
                <span>{{ $t('العودة إلى المستخدمين', 'Back to users') }}</span>
            </a>
        </div>

        <section class="user-profile__summary" aria-label="{{ $t('ملخص حساب المستخدم', 'User account summary') }}">
            <div class="user-profile__summary-main">
                <x-ui.avatar :name="$user['name'] ?? ''" />
                <div class="user-profile__identity">
                    <div class="user-profile__identity-heading">
                        <h2>{{ $user['name'] ?? '—' }}</h2>
                        <x-ui.status-badge :status="$accountActive ? 'success' : 'neutral'">
                            {{ $accountActive ? $t('نشط', 'Active') : $t('غير نشط', 'Inactive') }}
                        </x-ui.status-badge>
                    </div>
                    <p class="user-profile__email"><bdi dir="ltr">{{ $user['email'] ?? '—' }}</bdi></p>
                    <ul class="user-profile__locations">
                        <li><x-ui.icon name="building" /><span>{{ $user['branch_name'] ?? $t('غير متاح', 'Unavailable') }}</span></li>
                        <li><x-ui.icon name="users" /><span>{{ $user['department_name'] ?? $t('غير متاح', 'Unavailable') }}</span></li>
                    </ul>
                </div>
            </div>
            <dl class="user-profile__metadata"><div><dt><x-ui.icon name="clock" />{{ $t('تاريخ الإنشاء', 'Created at') }}</dt><dd>{{ $t('غير متاح', 'Unavailable') }}</dd></div><div><dt><x-ui.icon name="clock" />{{ $t('آخر دخول', 'Last login') }}</dt><dd>{{ $t('غير متاح', 'Unavailable') }}</dd></div></dl>
        </section>

        <nav class="user-profile__section-nav" aria-label="{{ $t('أقسام بيانات المستخدم', 'User detail sections') }}">
            <a href="#user-basic-data" aria-current="location"><x-ui.icon name="users" /><span>{{ $t('البيانات الأساسية', 'Basic information') }}</span></a>
            <a href="#user-organization"><x-ui.icon name="building" /><span>{{ $t('التبعية الإدارية', 'Organization') }}</span></a>
            @if ($showAccessSection)
                <a href="#user-access"><x-ui.icon name="shield" /><span>{{ $t('الأدوار والصلاحيات', 'Roles and permissions') }}</span></a>
            @endif
            <a href="#user-security"><x-ui.icon name="lock" /><span>{{ $t('الأمان وحالة الحساب', 'Security and account status') }}</span></a>
        </nav>

        @if ($canWriteUsers)
            <form id="user-form" class="user-profile__account-form ui-stack" method="post" action="/access/users/{{ rawurlencode((string) ($user['id'] ?? '')) }}" data-testid="user-form">
                @csrf
                <input type="hidden" name="version" value="{{ $user['version'] ?? 0 }}">

                <div class="user-profile__form-grid">
                    <section id="user-basic-data" class="user-profile__panel" aria-label="{{ $t('البيانات الأساسية', 'Basic information') }}">
                        <x-ui.icon name="audit" class="user-profile__panel-icon" />
                        <x-ui.form-section :title="$t('البيانات الأساسية', 'Basic information')" :description="$t('المعلومات الأساسية المرتبطة بحساب المستخدم.', 'Core details associated with this user account.')">
                            <div class="ui-grid">
                                <x-ui.field id="user-name" :label="$t('الاسم الكامل', 'Full name')" :error="$errors->first('name')">
                                    <x-ui.input id="user-name" name="name" :value="old('name', $user['name'] ?? '')" autocomplete="name" required maxlength="160" />
                                </x-ui.field>
                                <x-ui.field id="user-email" :label="$t('البريد الإلكتروني', 'Email address')" :error="$errors->first('email')">
                                    <x-ui.input id="user-email" name="email" type="email" :value="old('email', $user['email'] ?? '')" autocomplete="email" required maxlength="254" />
                                </x-ui.field>
                                <x-ui.field id="user-password" :label="$t('كلمة مرور جديدة', 'New password')" :hint="$t('اختياري، و12 حرفاً على الأقل عند استخدامه.', 'Optional; at least 12 characters when provided.')" :error="$errors->first('password')">
                                    <x-ui.input id="user-password" name="password" type="password" autocomplete="new-password" minlength="12" />
                                </x-ui.field>
                            </div>
                        </x-ui.form-section>
                    </section>

                    <section id="user-organization" class="user-profile__panel" aria-label="{{ $t('التبعية الإدارية', 'Organization') }}">
                        <x-ui.icon name="building" class="user-profile__panel-icon" />
                        <x-ui.form-section :title="$t('التبعية الإدارية', 'Organization')" :description="$t('حدد الفرع والقسم المرتبطين بالمستخدم.', 'Set the branch and department associated with this user.')">
                            <div class="ui-grid">
                                <x-ui.field id="user-branch" :label="$t('الفرع', 'Branch')" :error="$errors->first('branch_id')">
                                    <x-ui.select id="user-branch" name="branch_id" required>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch['id'] }}" @selected((string) old('branch_id', $user['branch_id'] ?? '') === (string) ($branch['id'] ?? ''))>{{ $branch['name'] ?? $t('غير متاح', 'Unavailable') }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                                <x-ui.field id="user-department" :label="$t('القسم', 'Department')" :error="$errors->first('department_id')">
                                    <x-ui.select id="user-department" name="department_id" required>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department['id'] }}" @selected((string) old('department_id', $user['department_id'] ?? '') === (string) ($department['id'] ?? ''))>{{ $department['name'] ?? $t('غير متاح', 'Unavailable') }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                            </div>
                        </x-ui.form-section>
                    </section>

                    <section id="user-security" class="user-profile__panel" aria-label="{{ $t('الأمان وحالة الحساب', 'Security and account status') }}">
                        <x-ui.icon name="lock" class="user-profile__panel-icon" />
                        <x-ui.form-section :title="$t('الأمان وحالة الحساب', 'Security and account status')" :description="$t('إدارة حالة الحساب وإتاحة تسجيل الدخول.', 'Manage account status and sign-in availability.')">
                            <div class="user-profile__security-grid">

                                <x-ui.field id="user-active" :label="$t('الحساب نشط', 'Account active')">
                                    <input type="hidden" name="active" value="0">
                                    <x-ui.switch id="user-active" name="active" value="1" :checked="(bool) old('active', $user['active'] ?? false)" />
                                </x-ui.field>
                            </div>
                        </x-ui.form-section>
                    </section>
                    <section class="user-profile__panel user-profile__additional" aria-labelledby="user-additional-heading">
                        <h2 id="user-additional-heading"><x-ui.icon name="help" />{{ $t('معلومات إضافية', 'Additional information') }}</h2>
                        <p class="ui-muted">{{ $t('لا تتوفر حقول إضافية لهذا الحساب حاليًا.', 'Additional fields are not currently available for this account.') }}</p>
                    </section>
                </div>

                <x-ui.form-actions class="user-profile__form-actions">
                    <x-ui.button type="submit">{{ $t('حفظ بيانات المستخدم', 'Save user details') }}</x-ui.button>
                    <a class="ui-button ui-button--secondary" href="/access/users?locale={{ $locale }}">{{ $t('إلغاء', 'Cancel') }}</a>
                </x-ui.form-actions>
            </form>
        @else
            <div class="user-profile__form-grid">
                <x-ui.detail-panel id="user-basic-data" class="user-profile__panel" :title="$t('البيانات الأساسية', 'Basic information')">
                    <x-ui.description-list :items="[
                        $t('الاسم', 'Name') => $user['name'] ?? '—',
                        $t('البريد الإلكتروني', 'Email address') => $user['email'] ?? '—',
                    ]" />
                </x-ui.detail-panel>
                <x-ui.detail-panel id="user-organization" class="user-profile__panel" :title="$t('التبعية الإدارية', 'Organization')">
                    <x-ui.description-list :items="[
                        $t('الفرع', 'Branch') => $user['branch_name'] ?? $t('غير متاح', 'Unavailable'),
                        $t('القسم', 'Department') => $user['department_name'] ?? $t('غير متاح', 'Unavailable'),
                    ]" />
                </x-ui.detail-panel>
                <x-ui.detail-panel id="user-security" class="user-profile__panel user-profile__panel--full" :title="$t('الأمان وحالة الحساب', 'Security and account status')">
                    <x-ui.status-badge :status="$accountActive ? 'success' : 'neutral'">
                        {{ $accountActive ? $t('الحساب نشط', 'Account active') : $t('الحساب غير نشط', 'Account inactive') }}
                    </x-ui.status-badge>
                </x-ui.detail-panel>
            </div>
        @endif

        <x-ui.option-browser :sources="$optionSources" :locale="$locale" />

        @if ($canManageUserAccess)
            <form id="user-access" class="user-profile__access-form ui-stack" method="post" action="/access/users/{{ rawurlencode((string) ($user['id'] ?? '')) }}/access" data-testid="user-access-form" aria-label="{{ $t('الأدوار والصلاحيات', 'Roles and permissions') }}">
                @csrf
                <input type="hidden" name="_access_assignment" value="1">
                <input type="hidden" name="version" value="{{ $user['version'] ?? 0 }}">
                @foreach (array_diff($selectedRoleIds, $displayedRoleIds) as $preservedRoleId)
                    <input type="hidden" name="role_ids[]" value="{{ $preservedRoleId }}">
                @endforeach

                <div class="user-profile__form-grid">
                    <section class="user-profile__panel" aria-label="{{ $t('الأدوار الممنوحة', 'Assigned roles') }}">
                        <x-ui.form-section :title="$t('الأدوار الممنوحة', 'Assigned roles')" :description="$t('حدد الأدوار التي ستمنح هذا المستخدم صلاحياتها.', 'Choose the roles whose permissions should be assigned to this user.')">
                            <div class="user-profile__roles-grid">
                                @forelse ($roles as $role)
                                    @php
                                        $roleId = (string) ($role['id'] ?? '');
                                    @endphp
                                    <x-ui.field :id="'assigned-role-'.$roleId" :label="$role['name'] ?? $t('دور بدون اسم', 'Unnamed role')">
                                        <x-ui.checkbox :id="'assigned-role-'.$roleId" name="role_ids[]" :value="$roleId" :checked="in_array($roleId, $selectedRoleIds, true)" />
                                    </x-ui.field>
                                @empty
                                    <p class="ui-muted">{{ $t('لا توجد أدوار متاحة في نطاقك.', 'No roles are available in your scope.') }}</p>
                                @endforelse
                            </div>
                        </x-ui.form-section>
                    </section>

                    @if ($catalogPermissions !== [])
                        <section class="user-profile__panel" aria-label="{{ $t('الصلاحيات المباشرة', 'Direct permissions') }}">
                            <x-ui.form-section :title="$t('صلاحيات مباشرة', 'Direct permissions')" :description="$t('اختر الوراثة أو السماح أو المنع لكل صلاحية.', 'Choose inherit, allow, or deny for each permission.')">
                                <div class="user-profile__permissions-grid">
                                    @foreach ($catalogPermissions as $permission)
                                        @php
                                            $override = $currentOverrides[$permission] ?? null;
                                            $overrideValue = is_bool($override) ? ($override ? '1' : '0') : (in_array((string) $override, ['1', '0'], true) ? (string) $override : 'inherit');
                                            $oldOverride = old('permissions', [])[$permission] ?? null;
                                            $overrideValue = in_array($oldOverride, ['inherit', '1', '0'], true) ? $oldOverride : $overrideValue;
                                            $label = $permissionLabels[$permission] ?? ($catalogData[$permission] ?? $permission);
                                        @endphp
                                        <x-ui.field :id="'permission-'.str_replace('.', '-', $permission)" :label="$label">
                                            <x-ui.select :id="'permission-'.str_replace('.', '-', $permission)" :name="'permissions['.$permission.']'">
                                                <option value="inherit" @selected($overrideValue === 'inherit')>{{ $t('استخدام صلاحيات الدور', 'Inherit from roles') }}</option>
                                                <option value="1" @selected($overrideValue === '1')>{{ $t('السماح', 'Allow') }}</option>
                                                <option value="0" @selected($overrideValue === '0')>{{ $t('المنع', 'Deny') }}</option>
                                            </x-ui.select>
                                        </x-ui.field>
                                    @endforeach
                                </div>
                            </x-ui.form-section>
                        </section>
                    @endif
                </div>

                <x-ui.form-actions class="user-profile__form-actions">
                    <x-ui.button type="submit">{{ $t('حفظ الأدوار والصلاحيات', 'Save roles and permissions') }}</x-ui.button>
                </x-ui.form-actions>
            </form>
        @elseif (($user['assignments_editable'] ?? true) === false && in_array('roles.write', $permissions, true))
            <section id="user-access" class="user-profile__access-warning" aria-label="{{ $t('الأدوار والصلاحيات', 'Roles and permissions') }}">
                <x-ui.alert variant="warning">{{ $t('يتطلب تعديل وصول هذا المستخدم مسؤولاً يستطيع إدارة جميع صلاحياته الحالية.', 'Editing this user’s access requires an administrator who can manage all existing assignments.') }}</x-ui.alert>
            </section>
        @endif

    </div>
    <script>
        (() => {
            const links = document.querySelectorAll('.user-profile__section-nav a');
            const markLocation = () => {
                const selected = Array.from(links).some(link => link.hash === location.hash) ? location.hash : '#user-basic-data';
                links.forEach(link => {
                    if (link.hash === selected) link.setAttribute('aria-current', 'location');
                    else link.removeAttribute('aria-current');
                });
            };
            window.addEventListener('hashchange', markLocation);
            markLocation();
        })();
    </script>
@endsection

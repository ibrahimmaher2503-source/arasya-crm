@props(['actor' => [], 'permissions' => [], 'locale' => 'ar'])
@php
    $t = static fn (string $arabic, string $english): string => $locale === 'ar' ? $arabic : $english;
    $hasAccess = collect(['organization.read', 'users.read', 'roles.read', 'audit.read'])->contains(fn (string $permission): bool => in_array($permission, $permissions, true));
    $fullActorName = is_array($actor) && filled($actor['name'] ?? null) ? $actor['name'] : $t('مستخدم النظام', 'System user');
    $actorName = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::words($fullActorName, 2, ''), 24);
    $currentPath = request()->getPathInfo();
    $actorInitial = mb_substr($actorName, 0, 1, 'UTF-8');
@endphp
        <aside class="dashboard-sidebar" data-shared-sidebar aria-label="{{ $t('التنقل الرئيسي', 'Primary navigation') }}">
            <div class="sidebar-head">
                <button class="sidebar-collapse" type="button" data-sidebar-collapse aria-expanded="true" aria-controls="primary-navigation" aria-label="{{ $t('تبديل عرض القائمة', 'Toggle navigation width') }}">«</button>
                <a class="dashboard-brand" href="/dashboard" aria-label="{{ $t('أراسيــا، لوحة المتابعة', 'Arasya dashboard') }}">
                    <svg viewBox="0 0 42 42" aria-hidden="true" focusable="false">
                        <path d="M21 37V14m-4 23 2-19m6 19-2-19M21 15C10 5 4 13 4 22c6-7 10-8 17-7Zm0 0C32 5 38 13 38 22c-6-7-10-8-17-7ZM21 14C9 3 7 7 6 12c6-2 10-1 15 2Zm0 0C33 3 35 7 36 12c-6-2-10-1-15 2ZM21 14c-6-8-4-11 0-12 4 1 6 4 0 12Z" />
                    </svg>
                    <span class="brand-copy"><strong>{{ $t('أراسيــا', 'Arasya') }}</strong><small>My Arasya</small></span>
                </a>
                <button class="sidebar-close" type="button" data-nav-toggle aria-controls="primary-navigation" aria-expanded="false" aria-label="{{ $t('إغلاق القائمة', 'Close navigation') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m15 18-6-6 6-6M20 18l-6-6 6-6" /></svg>
                </button>
            </div>

            <label class="sidebar-search">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.5 4.5" /></svg>
                <span class="visually-hidden">{{ $t('ابحث في القائمة', 'Search navigation') }}</span>
                <input type="search" data-nav-search placeholder="{{ $t('بحث في القائمة...', 'Search navigation...') }}" autocomplete="off">
                <span class="search-empty" data-search-empty hidden>{{ $t('لا توجد نتائج', 'No results') }}</span>
            </label>

            <nav id="primary-navigation" class="primary-navigation" aria-label="{{ $t('الأقسام', 'Sections') }}">
                @php
                    $groups = [
                        ['مركز العمل', 'Workspace', 'grid', [
                            ['لوحة التحكم', 'Dashboard', '/dashboard', null],
                            ['المهام اليومية', 'Daily tasks', '/tasks', 'tasks.read'],
                            ['التنبيهات', 'Notifications', null, null],
                        ]],
                        ['إدارة علاقات العملاء', 'CRM', 'users', [
                            ['نظرة عامة', 'Overview', '/crm', 'crm.read'],
                            ['العملاء المحتملون', 'Leads', '/crm/leads', 'crm.read'],
                            ['العملاء والحسابات', 'Customers and accounts', null, null],
                            ['الصفقات وخط المبيعات', 'Deals and pipeline', app()->environment(['local', 'testing']) && config('sales.synthetic_drafts') ? '/crm/pipeline' : null, 'crm.read'],
                            ['المتابعات والأنشطة', 'Follow-ups and activities', null, null],
                        ]],
                        ['المؤسسة والوصول', 'Organization and access', 'building', [
                            ['نظرة عامة', 'Overview', '/access', 'access'],
                            ['المؤسسة', 'Organization', '/access/organization', 'organization.read'],
                            ['المستخدمون', 'Users', '/access/users', 'users.read'],
                            ['الأدوار والصلاحيات', 'Roles and permissions', '/access/roles', 'roles.read'],
                            ['سجل التدقيق', 'Audit log', '/access/audit', 'audit.read'],
                        ]],
                        ['الموظفون', 'Employees', 'users', [
                            ['دليل الموظفين', 'Employee directory', '/employees', 'employees.read'],
                            ['إضافة موظف', 'Add employee', '/employees/create', 'employees.write'],
                            ['فهرس الملفات الوظيفية', 'Employee profile index', null, null],
                            ['العمال والمشرفون', 'Workers and supervisors', '/employees/workers', 'employees.read'],
                        ]],
                        ['المهام والمؤشرات', 'Tasks and performance', 'audit', [
                            ['المهام', 'Tasks', '/tasks', 'tasks.read'],
                            ['الأهداف', 'Goals', null, null],
                            ['مؤشرات الأداء', 'Performance indicators', null, null],
                            ['لوحة التنفيذي', 'Executive dashboard', null, null],
                        ]],
                        ['المشاريع والجودة', 'Projects and quality', 'grid', [
                            ['المشاريع', 'Projects', null, null], ['التركيب والتنفيذ', 'Installation', null, null],
                            ['الجودة', 'Quality', null, null], ['المهام المرتبطة', 'Linked tasks', null, null],
                        ]],
                        ['المخزون والتسليم', 'Inventory and delivery', 'building', [
                            ['المخزون', 'Inventory', null, null], ['المستودعات', 'Warehouses', null, null],
                            ['التسليم', 'Delivery', null, null], ['سندات الاستلام', 'Receipts', null, null],
                        ]],
                        ['المالية', 'Finance', 'grid', [
                            ['القيود', 'Entries', null, null], ['المصروفات', 'Expenses', null, null],
                            ['التحصيلات', 'Collections', null, null], ['التعويضات', 'Reimbursements', null, null],
                        ]],
                        ['المستندات', 'Documents', 'audit', [['المستندات', 'Documents', null, null]]],
                        ['التقارير', 'Reports', 'grid', [['التقارير', 'Reports', null, null]]],
                        ['المساعد الذكي', 'AI assistant', 'help', [['المساعد الذكي', 'AI assistant', null, null]]],
                        ['الإعدادات', 'Settings', 'shield', [['الإعدادات', 'Settings', null, null]]],
                    ];
                    // Only the most specific delivered destination is active; tasks have one canonical home.
                    $activeDestination = null;
                    foreach ($groups as $group) {
                        foreach ($group[3] as $item) {
                            if ($item[2] && ($currentPath === $item[2] || (!in_array($item[2], ['/dashboard', '/access', '/crm'], true) && str_starts_with($currentPath, $item[2].'/'))) && strlen($item[2]) > strlen($activeDestination ?? '')) $activeDestination = $item[2];
                        }
                    }
                @endphp
                <ul class="nav-list">
                    @foreach ($groups as $groupIndex => [$arabic, $english, $icon, $items])
                        @php
                            $items = array_values(array_filter($items, fn ($item) => ($item[2] !== '/employees/create' || in_array('employees.read', $permissions, true)) && ($item[2] === null || $item[3] === null || ($item[3] === 'access' ? $hasAccess : in_array($item[3], $permissions, true)))));
                            $groupActive = collect($items)->contains(fn ($item) => $item[2] !== null && $item[2] === $activeDestination) && !($groupIndex === 0 && $activeDestination === '/tasks');
                        @endphp
                        @if ($items !== [])
                        <li data-nav-item data-nav-text="{{ $t($arabic, $english).' '.collect($items)->map(fn ($item) => $t($item[0], $item[1]))->implode(' ') }}">
                            <details class="nav-group" @if ($groupActive) open @endif>
                                <summary class="nav-link{{ $groupActive ? ' is-current' : '' }}" aria-label="{{ $t($arabic, $english) }}"><x-ui.icon :name="$icon" /><span>{{ $t($arabic, $english) }}</span>@if (collect($items)->every(fn ($item) => $item[2] === null))<small class="nav-soon">{{ $t('قريبًا', 'Soon') }}</small>@endif<span class="nav-chevron" aria-hidden="true">⌄</span></summary>
                                <ul class="nav-children">
                                    @foreach ($items as [$ar, $en, $destination, $permission])
                                        @php($activeItem = $groupActive && $destination !== null && $destination === $activeDestination)
                                        <li class="nav-subitem">
                                            @if ($destination !== null)
                                                <a class="nav-link{{ $activeItem ? ' is-current' : '' }}" href="{{ $destination }}?locale={{ $locale }}" @if ($activeItem) aria-current="page" @endif>{{ $t($ar, $en) }}</a>
                                            @else
                                                <button class="nav-link nav-disabled" type="button" disabled><span>{{ $t($ar, $en) }}</span><small>{{ $t('قريبًا', 'Soon') }}</small></button>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        </li>
                        @endif
                    @endforeach
                </ul>
            </nav>

            <div class="sidebar-account">
                <div class="account-summary">
                    <span class="account-avatar" aria-hidden="true">{{ $actorInitial }}</span>
                    <span class="account-copy"><strong title="{{ $fullActorName }}">{{ $actorName }}</strong><small>{{ $t('مستخدم النظام', 'System user') }}</small></span>
                    <svg class="account-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m7 10 5 5 5-5" /></svg>
                </div>
                <form method="post" action="/logout">
                    @csrf
                    <button class="logout-button" type="submit">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 17l5-5-5-5m5 5H3m9-9h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" /></svg>
                        {{ $t('تسجيل الخروج', 'Sign out') }}
                    </button>
                </form>
            </div>
        </aside>

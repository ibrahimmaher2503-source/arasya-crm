@php
    $locale = ($locale ?? 'ar') === 'en' ? 'en' : 'ar';
    $designSystem = (bool) ($designSystem ?? false);
    $authShell = (bool) ($authShell ?? false);
    $applicationShell = !$designSystem && isset($actor);
    $navigationPermissions = $permissions ?? [];
    if ($applicationShell && $actor instanceof \App\Modules\Identity\Domain\SEC01\ActorAccess) {
        $navigationPermissions = array_values(array_unique(array_merge($actor->inheritedPermissions, array_keys(array_filter($actor->overrides)))));
        $navigationPermissions = array_values(array_filter($navigationPermissions, fn ($permission) => ($actor->overrides[$permission] ?? true) !== false));
    }
    $current = $active ?? ($designSystem ? request()->query('screen', 'catalog') : 'overview');
    $copy = $locale === 'ar' ? [
        'shell_title' => __('ui.shell_title', [], 'ar'),
        'skip_to_content' => __('ui.skip_to_content', [], 'ar'),
        'primary_navigation' => __('ui.primary_navigation', [], 'ar'),
        'workspace' => __('ui.workspace', [], 'ar'),
        'overview' => __('ui.overview', [], 'ar'),
        'shell_note' => __('ui.shell_note', [], 'ar'),
        'locale' => __('ui.locale', [], 'ar'),
        'error_heading' => __('ui.error_heading', [], 'ar'),
        'page_content' => __('ui.page_content', [], 'ar'),
        'contact_email' => __('ui.contact_email', [], 'ar'),
        'reference_number' => __('ui.reference_number', [], 'ar'),
        'design_system' => 'نظام التصميم',
        'catalog' => 'المكونات',
        'dashboard' => 'لوحة المتابعة',
        'orders' => 'الطلبات',
        'order' => 'تفاصيل الطلب',
        'form' => 'النماذج',
        'approvals' => 'الموافقات',
        'toggle_theme' => 'تبديل المظهر',
        'switch_language' => 'Switch to English',
        'toggle_navigation' => 'فتح أو إغلاق القائمة',
    ] : [
        'shell_title' => 'Arasya workspace',
        'skip_to_content' => 'Skip to content',
        'primary_navigation' => 'Primary navigation',
        'workspace' => 'Workspace',
        'overview' => 'Overview',
        'shell_note' => 'A clear and accessible workspace.',
        'locale' => 'English',
        'error_heading' => 'We could not complete that request',
        'page_content' => 'Page content',
        'contact_email' => 'Email',
        'reference_number' => 'Reference number',
        'design_system' => 'Design system',
        'catalog' => 'Component catalog',
        'dashboard' => 'Dashboard',
        'orders' => 'Orders',
        'order' => 'Order details',
        'form' => 'Forms',
        'approvals' => 'Approvals',
        'toggle_theme' => 'Toggle theme',
        'switch_language' => 'التبديل إلى العربية',
        'toggle_navigation' => 'Toggle navigation',
    ];
                        $section = request()->segment(2) ?? 'index';
                        $pageIcons = ['users' => 'users', 'roles' => 'shield', 'organization' => 'building', 'audit' => 'audit'];
                        $subtitles = $locale === 'ar' ? ['index' => 'إدارة حسابات المستخدمين والأدوار والصلاحيات وسجل التدقيق', 'organization' => 'إدارة بيانات المؤسسة والفروع والأقسام والأدوار الافتراضية', 'users' => 'إدارة مستخدمي المؤسسة وصلاحياتهم وأدوارهم', 'roles' => 'إدارة أدوار المستخدمين والصلاحيات ضمن نطاقك', 'audit' => 'مراجعة الأنشطة والتغييرات المسجلة ضمن نطاقك'] : ['index' => 'Manage accounts, roles, permissions and audit activity', 'organization' => 'Manage your organization, branches, departments and default roles', 'users' => 'Manage organization users, permissions and roles', 'roles' => 'Manage roles and permissions in your scope', 'audit' => 'Review recorded activity and changes in your scope'];
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? $copy['shell_title'] }}</title>
    @foreach (['design-system.css', 'ui-forms.css', 'ui-data.css', 'ui-feedback.css', 'admin.css', 'crm.css'] as $stylesheet)
        @php($stylePath = resource_path('css/'.$stylesheet))
        @if (is_file($stylePath))
            <style>{!! file_get_contents($stylePath) !!}</style>
        @endif
    @endforeach
    @if ($authShell)
        <style>{!! file_get_contents(resource_path('css/auth.css')) !!}</style>
    @endif
    @if ($applicationShell)
        <style>.dashboard-shell { background: var(--color-sidebar); min-height: 100vh; display: grid; grid-template-columns: var(--sidebar-width) minmax(0, 1fr); } @media (max-width: 850px) { .dashboard-shell { display: block; } } @scope (.dashboard-shell) { {!! str_replace('.dashboard-shell', ':scope', file_get_contents(resource_path('css/dashboard.css'))) !!} }</style>
    @endif
</head>
<body @class(['dashboard-body' => $applicationShell])>
    <a class="skip-link" href="#main-content">{{ $copy['skip_to_content'] }}</a>
    @if ($authShell)
        @yield('content')
    @else
    <div class="{{ $applicationShell ? 'dashboard-shell' : 'shell' }}" @if ($applicationShell) data-dashboard-shell @endif>
        @if ($applicationShell)
            <x-erp.application-sidebar :actor="$actor" :permissions="$navigationPermissions" :locale="$locale" />
        @else
        <aside class="sidebar" aria-label="{{ $copy['primary_navigation'] }}">
            <div class="sidebar-head">
                <a class="brand" href="{{ $designSystem ? '/dev/design-system?screen=catalog&locale='.$locale : '#main-content' }}" aria-label="{{ $copy['shell_title'] }}">
                    <span class="brand-mark" aria-hidden="true">ع</span>
                    <span>{{ $copy['shell_title'] }}</span>
                </a>
                <button class="nav-toggle" type="button" data-nav-toggle aria-controls="primary-navigation" aria-expanded="true" aria-label="{{ $copy['toggle_navigation'] }}">
                    <span class="u-visually-hidden">{{ $copy['toggle_navigation'] }}</span>
                    <span aria-hidden="true">☰</span>
                </button>
            </div>
            <nav id="primary-navigation" aria-label="{{ $copy['primary_navigation'] }}">
                <p class="nav-caption">{{ $designSystem ? $copy['design_system'] : $copy['workspace'] }}</p>
                <ul>
                    @if ($designSystem)
                        @foreach (['catalog', 'dashboard', 'orders', 'order', 'form', 'approvals'] as $screen)
                            <li>
                                <a href="/dev/design-system?screen={{ $screen }}&amp;locale={{ $locale }}" @if ($current === $screen) aria-current="page" @endif>
                                    <span class="nav-symbol" aria-hidden="true">{{ ['catalog' => '◈', 'dashboard' => '▦', 'orders' => '≡', 'order' => '▤', 'form' => '▧', 'approvals' => '✓'][$screen] }}</span>
                                    {{ $copy[$screen] }}
                                </a>
                            </li>
                        @endforeach
                    @elseif (request()->is('access', 'access/*', 'employees', 'employees/*') && isset($actor, $permissions))
                        @foreach ([['/access', null, 'المؤسسة والوصول', 'Organization and access'], ['/access/organization', 'organization.read', 'المؤسسة', 'Organization'], ['/access/users', 'users.read', 'المستخدمون', 'Users'], ['/access/roles', 'roles.read', 'الأدوار', 'Roles'], ['/access/audit', 'audit.read', 'سجل التدقيق', 'Audit log'], ['/employees', 'employees.read', 'الموظفون', 'Employees'], ['/employees/workers', 'employees.read', 'العمال التابعون لي', 'My workers']] as [$destination, $permission, $arabic, $english])
                            @if (($permission === null || in_array($permission, $permissions, true)) && (!str_starts_with($destination, '/employees') || \Illuminate\Support\Facades\Route::has('employees.index')))
                                <li><a href="{{ $destination }}" @if (request()->getPathInfo() === $destination || ($destination !== '/access' && str_starts_with(request()->getPathInfo(), $destination.'/'))) aria-current="page" @endif>{{ $locale === 'ar' ? $arabic : $english }}</a></li>
                            @endif
                        @endforeach
                    @else
                        <li>
                            <a href="#main-content" @if ($current === 'overview') aria-current="page" @endif>
                                <span class="nav-symbol" aria-hidden="true">◈</span>
                                {{ $copy['overview'] }}
                            </a>
                        </li>
                    @endif
                </ul>
            </nav>
            <p class="sidebar-note">{{ $copy['shell_note'] }}</p>
        </aside>
        @endif

        <div class="workspace{{ $applicationShell ? ' dashboard-main' : '' }}">
            <header class="{{ $applicationShell ? 'dashboard-topbar admin-topbar' : 'topbar' }}">
                @if ($applicationShell)
                    <button class="mobile-menu" type="button" data-nav-toggle aria-controls="primary-navigation" aria-expanded="false" aria-label="{{ $locale === 'ar' ? 'فتح القائمة' : 'Open navigation' }}">☰</button>
                @endif
                @if ($applicationShell)
                    @if (request()->is('crm', 'crm/*') && in_array('crm.read', $navigationPermissions, true))
                        <form class="admin-topbar-search" action="/crm/leads" method="get" role="search">
                            <x-ui.icon name="search" />
                            <input type="hidden" name="locale" value="{{ $locale }}">
                            <x-ui.input type="search" name="q" :aria-label="$locale === 'ar' ? 'البحث في العملاء المحتملين' : 'Search leads'" :placeholder="$locale === 'ar' ? 'البحث في العملاء المحتملين والشركات...' : 'Search leads and companies...'" maxlength="120" />
                        </form>
                    @elseif (in_array('users.read', $navigationPermissions, true))
                        <form class="admin-topbar-search" action="/access/users" method="get" role="search">
                            <x-ui.icon name="search" />
                            <input type="hidden" name="locale" value="{{ $locale }}">
                            <x-ui.input type="search" name="q" :aria-label="$locale === 'ar' ? 'البحث عن مستخدم' : 'Search users'" :placeholder="$locale === 'ar' ? 'البحث عن مستخدم بالاسم أو البريد الإلكتروني...' : 'Search users by name or email...'" />
                        </form>
                    @endif
                @else
                    <p>{{ $copy['workspace'] }}</p>
                @endif
                <div class="topbar-actions">
                    @if ($applicationShell)
                        <x-ui.icon-button :label="$copy['toggle_theme']" data-theme-toggle><x-ui.icon name="sun" /></x-ui.icon-button>
                        <a class="admin-profile admin-profile--link" href="{{ route('help.index', ['locale' => $locale]) }}" aria-label="{{ $locale === 'ar' ? 'مركز المساعدة' : 'Help Center' }}" title="{{ $locale === 'ar' ? 'مركز المساعدة' : 'Help Center' }}"><x-ui.icon name="help" /></a>
                        <details class="admin-profile"><summary><x-ui.avatar :name="is_array($actor) ? ($actor['name'] ?? '') : ''" /><span><strong>{{ is_array($actor) ? \Illuminate\Support\Str::words($actor['name'] ?? '', 2, '') : '' }}</strong><small>{{ $locale === 'ar' ? 'حسابك الحالي' : 'Your account' }}</small></span><span aria-hidden="true">⌄</span></summary><div class="admin-profile-menu"><form action="/logout" method="post">@csrf<x-ui.button type="submit" variant="ghost">{{ $locale === 'ar' ? 'تسجيل الخروج' : 'Sign out' }}</x-ui.button></form></div></details>
                    @else
                        <span class="locale-chip" lang="{{ $locale }}">{{ $copy['locale'] }}</span>
                    @endif
                    @if ($designSystem)
                        <button class="preview-control" type="button" data-testid="theme-toggle" aria-label="{{ $copy['toggle_theme'] }}">{{ $copy['toggle_theme'] }}</button>
                        <button class="preview-control" type="button" data-testid="direction-toggle" aria-label="{{ $copy['switch_language'] }}">{{ $copy['switch_language'] }}</button>
                    @endif
                </div>
            </header>
            <main id="main-content" class="main-content" tabindex="-1">
                @if ($applicationShell)

                    <x-ui.page-header :title="$title ?? $copy['overview']" :subtitle="$pageSubtitle ?? (request()->is('access', 'access/*') ? ($subtitles[$section] ?? '') : '')" :icon="$pageIcon ?? $pageIcons[$section] ?? 'grid'" :breadcrumbs="$breadcrumbs ?? [['label' => $locale === 'ar' ? 'مركز العمل' : 'Workspace', 'url' => '/dashboard'], ['label' => $locale === 'ar' ? 'المؤسسة والوصول' : 'Organization and access', 'url' => '/access'], ['label' => $title ?? $copy['overview']]]" />
                @else
                <div class="page-heading">
                    <div>
                        <p class="eyebrow">{{ $designSystem ? $copy['design_system'] : $copy['workspace'] }}</p>
                        <h1>{{ $title ?? $copy['overview'] }}</h1>
                    </div>
                </div>
                @endif

                @if ($errors->any())
                    <section class="feedback" role="alert" aria-labelledby="error-heading">
                        <h2 id="error-heading">{{ $copy['error_heading'] }}</h2>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="content-region" aria-label="{{ $copy['page_content'] }}">
                    @hasSection('content')
                        @yield('content')
                    @else
                        {{ $content ?? '' }}
                    @endif
                    @isset($email)<p>{{ $copy['contact_email'] }}: <bdi dir="ltr">{{ $email }}</bdi></p>@endisset
                    @isset($number)<p>{{ $copy['reference_number'] }}: <bdi dir="ltr">{{ $number }}</bdi></p>@endisset
                </section>
            </main>
        </div>
    </div>
    @endif
    @if ($applicationShell)
        <script>
            document.querySelectorAll('[data-dashboard-shell] [data-nav-toggle], [data-dashboard-shell] [data-theme-toggle]').forEach(button => button.addEventListener('click', event => event.stopPropagation()));
            {!! file_get_contents(resource_path('js/dashboard.js')) !!}
        </script>
    @endif
    @php($scriptPath = resource_path('js/design-system.js'))
    @if (is_file($scriptPath))
        <script>{!! file_get_contents($scriptPath) !!}</script>
    @endif
</body>
</html>

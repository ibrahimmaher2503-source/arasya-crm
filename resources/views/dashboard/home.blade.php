@php
    $locale = ($locale ?? 'ar') === 'en' ? 'en' : 'ar';
    $t = static fn (string $arabic, string $english): string => $locale === 'ar' ? $arabic : $english;
    $actor = is_array($actor ?? null) ? $actor : [];
    $permissions = is_array($permissions ?? null)
        ? $permissions
        : (is_array($actor['permissions'] ?? null) ? $actor['permissions'] : []);
    $hasAccess = collect(['organization.read', 'users.read', 'roles.read', 'audit.read'])
        ->contains(fn (string $permission): bool => in_array($permission, $permissions, true));
    $fullActorName = filled($actor['name'] ?? null) ? $actor['name'] : $t('أحمد محمد', 'Ahmed Mohamed');
    $actorName = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::words($fullActorName, 2, ''), 24);
    $actorInitial = mb_substr($actorName, 0, 1, 'UTF-8');
    $title = $t('لوحة المتابعة', 'Dashboard');
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title }} · {{ $t('أراسيــا', 'Arasya') }}</title>
    @foreach (['design-system.css', 'dashboard.css'] as $stylesheet)
        @php($stylePath = resource_path('css/'.$stylesheet))
        @if (is_file($stylePath))
            <style>{!! file_get_contents($stylePath) !!}</style>
        @endif
    @endforeach
</head>
<body class="dashboard-body">
    <a class="dashboard-skip-link" href="#main-content">{{ $t('تجاوز إلى المحتوى', 'Skip to content') }}</a>
    <div class="dashboard-shell" data-dashboard-shell>
        <x-erp.application-sidebar :actor="$actor" :permissions="$permissions" :locale="$locale" />

        <div class="dashboard-main">
            <header class="dashboard-topbar">
                <button class="mobile-menu" type="button" data-nav-toggle aria-controls="primary-navigation" aria-expanded="false" aria-label="{{ $t('فتح القائمة', 'Open navigation') }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <div class="topbar-date">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" /></svg>
                    <time datetime="{{ now()->toDateString() }}">{{ $locale === 'ar' ? now()->locale('ar')->translatedFormat('l، j F Y') : now()->format('l, F j, Y') }}</time>
                </div>
                <label class="top-search">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.5 4.5" /></svg>
                    <span class="visually-hidden">{{ $t('ابحث في القائمة', 'Search navigation') }}</span>
                    <input type="search" data-nav-search placeholder="{{ $t('ابحث في النظام...', 'Search the workspace...') }}" autocomplete="off" aria-keyshortcuts="Control+K">
                    <kbd>Ctrl K</kbd>
                </label>
                <div class="topbar-actions">
                    <button class="top-icon-button" type="button" data-theme-toggle aria-label="{{ $t('تبديل المظهر', 'Toggle theme') }}" aria-pressed="false">
                        <svg class="icon-sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4" /><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
                        <svg class="icon-moon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.5 15.4A8.7 8.7 0 0 1 8.6 3.5 8.8 8.8 0 1 0 20.5 15.4Z" /></svg>
                    </button>
                    <div class="popover-wrap">
                        <button class="top-icon-button has-count" type="button" data-popover-trigger="notification-popover" aria-controls="notification-popover" aria-expanded="false" aria-label="{{ $t('الإشعارات، 3 غير مقروءة', 'Notifications, 3 unread') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12h4" /></svg><span class="notification-count" aria-hidden="true">3</span>
                        </button>
                        <div class="top-popover" id="notification-popover" data-popover hidden>
                            <h2>{{ $t('الإشعارات', 'Notifications') }}</h2>
                            <ul>
                                <li><span class="popover-dot is-danger"></span><span><strong>{{ $t('3 فواتير مستحقة', '3 invoices due') }}</strong><small>{{ $t('تحتاج إلى متابعة', 'Needs follow-up') }}</small></span></li>
                                <li><span class="popover-dot is-warning"></span><span><strong>{{ $t('طلب شراء بانتظار الموافقة', 'Purchase order awaiting approval') }}</strong><small><bdi dir="ltr">#PR-2026-015</bdi></small></span></li>
                                <li><span class="popover-dot is-success"></span><span><strong>{{ $t('تقرير الشهر جاهز', 'Monthly report is ready') }}</strong><small>{{ $t('سبتمبر 2026', 'September 2026') }}</small></span></li>
                            </ul>
                        </div>
                    </div>
                    <div class="popover-wrap">
                        <button class="top-icon-button" type="button" data-popover-trigger="message-popover" aria-controls="message-popover" aria-expanded="false" aria-label="{{ $t('الرسائل، رسالتان جديدتان', 'Messages, two new') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 4h16a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-9l-6 3v-3H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" /><path d="M7 9h10M7 13h7" /></svg>
                        </button>
                        <div class="top-popover" id="message-popover" data-popover hidden>
                            <h2>{{ $t('الرسائل', 'Messages') }}</h2>
                            <ul>
                                <li><span class="message-avatar">{{ $t('م', 'M') }}</span><span><strong>{{ $t('مها العتيبي', 'Maha Al-Otaibi') }}</strong><small>{{ $t('تم تحديث بيانات الفرع.', 'The branch details were updated.') }}</small></span></li>
                                <li><span class="message-avatar">{{ $t('ع', 'A') }}</span><span><strong>{{ $t('عمر سالم', 'Omar Salem') }}</strong><small>{{ $t('أرسلت ملخص الطلبات.', 'I sent the order summary.') }}</small></span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>

            <main id="main-content" class="dashboard-content" tabindex="-1">
                <section class="dashboard-hero" data-tour="dashboard.heading" aria-labelledby="welcome-heading">
                    <div class="hero-copy">
                        <span class="demo-badge">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 19V5m0 14h17M7 15l4-4 3 3 6-7" /></svg>
                            {{ $t('بيانات تجريبية', 'Demo data') }}
                        </span>
                        <h1 id="welcome-heading" title="{{ $fullActorName }}">{{ $t('مرحباً', 'Welcome') }} {{ $actorName }}</h1>
                        <p>{{ $t('هنا نظرة سريعة على أداء أعمالك اليوم', 'Here is a quick look at your business today') }}</p>
                        <span class="company-chip">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 21h18M5 21V6l7-3 7 3v15M9 8h1m4 0h1m-6 4h1m4 0h1m-5 9v-5h4v5" /></svg>
                            {{ $t('شركة القمر للتجارة', 'Al-Qamar Trading Co.') }}
                        </span>
                    </div>
                </section>

                <section class="kpi-grid" data-tour="dashboard.summary" aria-label="{{ $t('مؤشرات الأداء', 'Key figures') }}">
                    <article class="kpi-card">
                        <span class="kpi-icon kpi-icon--red"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 2h8l5 5v15H6ZM14 2v6h5M9 12h7M9 16h7" /></svg></span>
                        <div class="kpi-copy"><h2>{{ $t('المستحقات', 'Payables') }}</h2><strong><bdi>684,200</bdi> <small>{{ $t('ر.س', 'SAR') }}</small></strong><span class="kpi-change is-down">15.3% <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m3 6 5 5 5-5" /></svg></span><p>{{ $t('مقارنة بالشهر الماضي', 'Compared with last month') }}</p></div>
                    </article>
                    <article class="kpi-card">
                        <span class="kpi-icon kpi-icon--green"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><ellipse cx="12" cy="5" rx="8.5" ry="3" /><path d="M3.5 5v14c0 1.7 3.8 3 8.5 3s8.5-1.3 8.5-3V5m-17 5c0 1.7 3.8 3 8.5 3s8.5-1.3 8.5-3m-17 5c0 1.7 3.8 3 8.5 3s8.5-1.3 8.5-3" /></svg></span>
                        <div class="kpi-copy"><h2>{{ $t('المخزون الحالي', 'Current stock') }}</h2><strong><bdi>3,420</bdi> <small>{{ $t('صنف', 'items') }}</small></strong><span class="kpi-change is-up">5.7% <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m3 10 5-5 5 5" /></svg></span><p>{{ $t('عن الشهر الماضي', 'Compared with last month') }}</p></div>
                    </article>
                    <article class="kpi-card">
                        <span class="kpi-icon kpi-icon--orange"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 2 9 5-9 5-9-5 9-5Zm-9 5v10l9 5 9-5V7m-9 5v10" /></svg></span>
                        <div class="kpi-copy"><h2>{{ $t('إجمالي المشتريات', 'Total purchases') }}</h2><strong><bdi>892,300</bdi> <small>{{ $t('ر.س', 'SAR') }}</small></strong><span class="kpi-change is-down">8.2% <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m3 6 5 5 5-5" /></svg></span><p>{{ $t('مقارنة بالشهر الماضي', 'Compared with last month') }}</p></div>
                    </article>
                    <article class="kpi-card">
                        <span class="kpi-icon kpi-icon--green"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3h2l3 12h10l3-9H6M9 21h.01M18 21h.01" /></svg></span>
                        <div class="kpi-copy"><h2>{{ $t('إجمالي المبيعات', 'Total sales') }}</h2><strong><bdi>1,248,500</bdi> <small>{{ $t('ر.س', 'SAR') }}</small></strong><span class="kpi-change is-up">12.5% <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m3 10 5-5 5 5" /></svg></span><p>{{ $t('مقارنة بالشهر الماضي', 'Compared with last month') }}</p></div>
                    </article>
                </section>

                <section class="analytics-grid" aria-label="{{ $t('تحليلات الأعمال', 'Business analytics') }}">
                    <article class="panel tasks-panel">
                        <div class="panel-heading"><h2>{{ $t('المهام والتنبيهات', 'Tasks and alerts') }}</h2>@if (in_array('tasks.read', $permissions, true))<a href="/tasks">{{ $t('عرض الكل', 'View all') }}</a>@endif</div>
                        @if (in_array('tasks.read', $permissions, true))
                            <ul class="task-list">
                                <li><a href="/tasks"><span class="task-icon task-icon--red"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 3h9l5 5v13H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm8 0v6h6M8 13h8m-8 4h8" /></svg></span><span class="task-copy"><strong>{{ $t('3 فواتير مستحقة', '3 invoices due') }}</strong><small>{{ $t('تحتاج إلى متابعة', 'Needs follow-up') }}</small></span><svg class="task-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 18 6-6-6-6" /></svg></a></li>
                                <li><a href="/tasks"><span class="task-icon task-icon--yellow"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg></span><span class="task-copy"><strong>{{ $t('موافقة على طلب شراء', 'Purchase order approval') }}</strong><small><bdi dir="ltr">#PR-2026-015</bdi></small></span><svg class="task-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 18 6-6-6-6" /></svg></a></li>
                                <li><a href="/tasks"><span class="task-icon task-icon--red"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 2 9 5-9 5-9-5 9-5Zm-9 5v10l9 5 9-5V7m-9 5v10" /></svg></span><span class="task-copy"><strong>{{ $t('مخزون منخفض', 'Low stock') }}</strong><small>{{ $t('5 أصناف في الحد الأدنى', '5 items at minimum') }}</small></span><svg class="task-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 18 6-6-6-6" /></svg></a></li>
                                <li><a href="/tasks"><span class="task-icon task-icon--green"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3h2l3 12h10l3-9H6M9 21h.01M18 21h.01" /></svg></span><span class="task-copy"><strong>{{ $t('تقرير شهري جاهز', 'Monthly report ready') }}</strong><small>{{ $t('لشهر سبتمبر 2026', 'For September 2026') }}</small></span><svg class="task-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 18 6-6-6-6" /></svg></a></li>
                            </ul>
                        @else
                            <p class="panel-empty">{{ $t('لا تتوفر لديك صلاحية عرض المهام.', 'You do not have permission to view tasks.') }}</p>
                        @endif
                    </article>

                    <article class="panel category-panel">
                        <div class="panel-heading"><h2>{{ $t('توزيع المبيعات حسب الفئة', 'Sales by category') }}</h2><label class="period-control"><span class="visually-hidden">{{ $t('فترة توزيع المبيعات', 'Sales distribution period') }}</span><select data-donut-period><option value="current">{{ $t('هذا الشهر', 'This month') }}</option><option value="previous">{{ $t('الشهر الماضي', 'Last month') }}</option></select></label></div>
                        <div class="donut-content">
                            <div class="donut-chart">
                                <svg viewBox="0 0 220 220" role="img" aria-labelledby="donut-title donut-description">
                                    <title id="donut-title">{{ $t('توزيع المبيعات حسب الفئة', 'Sales by category') }}</title>
                                    <desc id="donut-description">{{ $t('بيانات تجريبية موزعة على خمس فئات.', 'Demo data across five categories.') }}</desc>
                                    <circle class="donut-track" cx="110" cy="110" r="76" />
                                    <circle class="donut-segment donut-segment--one" cx="110" cy="110" r="76" pathLength="100" stroke-dasharray="34 66" stroke-dashoffset="0" />
                                    <circle class="donut-segment donut-segment--two" cx="110" cy="110" r="76" pathLength="100" stroke-dasharray="21 79" stroke-dashoffset="-35" />
                                    <circle class="donut-segment donut-segment--three" cx="110" cy="110" r="76" pathLength="100" stroke-dasharray="17 83" stroke-dashoffset="-57" />
                                    <circle class="donut-segment donut-segment--four" cx="110" cy="110" r="76" pathLength="100" stroke-dasharray="14 86" stroke-dashoffset="-75" />
                                    <circle class="donut-segment donut-segment--five" cx="110" cy="110" r="76" pathLength="100" stroke-dasharray="9 91" stroke-dashoffset="-90" />
                                    <text class="donut-total" x="110" y="106" text-anchor="middle" data-donut-total data-current="1.25M" data-previous="1.18M">1.25M</text>
                                    <text class="donut-caption" x="110" y="130" text-anchor="middle">{{ $t('إجمالي المبيعات', 'Total sales') }}</text>
                                    <text class="donut-currency" x="110" y="150" text-anchor="middle">{{ $t('ر.س', 'SAR') }}</text>
                                </svg>
                            </div>
                            <ul class="category-legend">
                                <li><span>{{ $t('الأجهزة والمعدات', 'Equipment') }}</span><b data-donut-share data-current="35%" data-previous="32%">35%</b><i class="legend-dot legend-dot--one"></i></li>
                                <li><span>{{ $t('مواد البناء', 'Building materials') }}</span><b data-donut-share data-current="22%" data-previous="24%">22%</b><i class="legend-dot legend-dot--two"></i></li>
                                <li><span>{{ $t('المنتجات الجاهزة', 'Finished products') }}</span><b data-donut-share data-current="18%" data-previous="17%">18%</b><i class="legend-dot legend-dot--three"></i></li>
                                <li><span>{{ $t('الخدمات', 'Services') }}</span><b data-donut-share data-current="15%" data-previous="16%">15%</b><i class="legend-dot legend-dot--four"></i></li>
                                <li><span>{{ $t('أخرى', 'Other') }}</span><b data-donut-share data-current="10%" data-previous="11%">10%</b><i class="legend-dot legend-dot--five"></i></li>
                            </ul>
                        </div>
                    </article>

                    <article class="panel sales-panel">
                        <div class="panel-heading sales-heading"><h2>{{ $t('المبيعات والمشتريات', 'Sales and purchases') }}</h2><label class="period-control"><span class="visually-hidden">{{ $t('الفترة الزمنية للمخطط', 'Chart period') }}</span><select data-line-period><option value="current">{{ $t('آخر 6 أشهر', 'Last 6 months') }}</option><option value="previous">{{ $t('الفترة السابقة', 'Previous period') }}</option></select></label></div>
                        <div class="chart-tabs" role="group" aria-label="{{ $t('اختر سلسلة الرسم', 'Choose chart series') }}">
                            <button type="button" data-series-choice="purchases" aria-pressed="false">{{ $t('المشتريات', 'Purchases') }}</button>
                            <button type="button" data-series-choice="sales" aria-pressed="true">{{ $t('المبيعات', 'Sales') }}</button>
                        </div>
                        <div class="line-chart-wrap">
                            <svg class="line-chart" viewBox="0 0 600 270" role="img" aria-labelledby="line-title line-description">
                                <title id="line-title">{{ $t('المبيعات والمشتريات خلال آخر 6 أشهر', 'Sales and purchases over the last six months') }}</title>
                                <desc id="line-description" data-line-description>{{ $t('مقارنة تجريبية للمبيعات والمشتريات من أبريل إلى سبتمبر.', 'Demo sales and purchases comparison from April through September.') }}</desc>
                                <g class="chart-grid" aria-hidden="true"><path d="M48 38H580M48 82H580M48 126H580M48 170H580M48 214H580" /></g>
                                <g class="chart-axis-labels" aria-hidden="true"><text x="42" y="42" text-anchor="end">400K</text><text x="42" y="86" text-anchor="end">300K</text><text x="42" y="130" text-anchor="end">200K</text><text x="42" y="174" text-anchor="end">100K</text><text x="42" y="218" text-anchor="end">0</text></g>
                                <g data-period-graphic="current">
                                    <g data-series-graphic="sales"><path class="sales-area" d="M58 163 157 113 256 132 355 83 454 97 553 48V214H58Z"/><path class="sales-line" d="M58 163 157 113 256 132 355 83 454 97 553 48"/><g class="sales-points"><circle cx="58" cy="163" r="5"/><circle cx="157" cy="113" r="5"/><circle cx="256" cy="132" r="5"/><circle cx="355" cy="83" r="5"/><circle cx="454" cy="97" r="5"/><circle cx="553" cy="48" r="5"/></g></g>
                                    <g data-series-graphic="purchases"><path class="purchase-area" d="M58 184 157 151 256 171 355 145 454 145 553 129V214H58Z"/><path class="purchase-line" d="M58 184 157 151 256 171 355 145 454 145 553 129"/><g class="purchase-points"><circle cx="58" cy="184" r="5"/><circle cx="157" cy="151" r="5"/><circle cx="256" cy="171" r="5"/><circle cx="355" cy="145" r="5"/><circle cx="454" cy="145" r="5"/><circle cx="553" cy="129" r="5"/></g></g>
                                </g>
                                <g data-period-graphic="previous" hidden>
                                    <g data-series-graphic="sales"><path class="sales-area" d="M58 177 157 145 256 152 355 107 454 118 553 70V214H58Z"/><path class="sales-line" d="M58 177 157 145 256 152 355 107 454 118 553 70"/><g class="sales-points"><circle cx="58" cy="177" r="5"/><circle cx="157" cy="145" r="5"/><circle cx="256" cy="152" r="5"/><circle cx="355" cy="107" r="5"/><circle cx="454" cy="118" r="5"/><circle cx="553" cy="70" r="5"/></g></g>
                                    <g data-series-graphic="purchases"><path class="purchase-area" d="M58 193 157 161 256 180 355 158 454 149 553 141V214H58Z"/><path class="purchase-line" d="M58 193 157 161 256 180 355 158 454 149 553 141"/><g class="purchase-points"><circle cx="58" cy="193" r="5"/><circle cx="157" cy="161" r="5"/><circle cx="256" cy="180" r="5"/><circle cx="355" cy="158" r="5"/><circle cx="454" cy="149" r="5"/><circle cx="553" cy="141" r="5"/></g></g>
                                </g>
                                <g class="chart-months" aria-hidden="true"><text x="58" y="245" text-anchor="middle">{{ $t('أبريل', 'Apr') }}</text><text x="157" y="245" text-anchor="middle">{{ $t('مايو', 'May') }}</text><text x="256" y="245" text-anchor="middle">{{ $t('يونيو', 'Jun') }}</text><text x="355" y="245" text-anchor="middle">{{ $t('يوليو', 'Jul') }}</text><text x="454" y="245" text-anchor="middle">{{ $t('أغسطس', 'Aug') }}</text><text x="553" y="245" text-anchor="middle">{{ $t('سبتمبر', 'Sep') }}</text></g>
                            </svg>
                        </div>
                        <div class="chart-legend"><span><i class="legend-line legend-line--sales"></i>{{ $t('المبيعات', 'Sales') }}</span><span><i class="legend-line legend-line--purchases"></i>{{ $t('المشتريات', 'Purchases') }}</span></div>
                    </article>
                </section>

                <section class="tables-grid" data-tour="dashboard.records" aria-label="{{ $t('بيانات المتابعة', 'Dashboard records') }}">
                    <article class="panel clients-panel">
                        <div class="panel-heading"><h2>{{ $t('أفضل العملاء', 'Top clients') }}</h2><span class="demo-table-label">{{ $t('بيانات تجريبية', 'Demo data') }}</span></div>
                        <div class="table-scroll" role="region" aria-label="{{ $t('جدول أفضل العملاء', 'Top clients table') }}" tabindex="0">
                            <table>
                                <caption class="visually-hidden">{{ $t('أفضل العملاء حسب إجمالي المشتريات', 'Top clients by total purchases') }}</caption>
                                <thead><tr><th scope="col">#</th><th scope="col">{{ $t('العميل', 'Client') }}</th><th scope="col">{{ $t('إجمالي المشتريات', 'Total purchases') }}</th><th scope="col">{{ $t('عدد الطلبات', 'Orders') }}</th></tr></thead>
                                <tbody>
                                    <tr><td>1</td><td><span class="client-name"><i class="client-mark client-mark--one">A</i>{{ $t('شركة القمر للتجارة', 'Al-Qamar Trading Co.') }}</span></td><td><bdi>456,200</bdi> {{ $t('ر.س', 'SAR') }}</td><td><bdi>28</bdi></td></tr>
                                    <tr><td>2</td><td><span class="client-name"><i class="client-mark client-mark--two">ن</i>{{ $t('مؤسسة الإبداع', 'Al-Ebdaa Establishment') }}</span></td><td><bdi>328,900</bdi> {{ $t('ر.س', 'SAR') }}</td><td><bdi>19</bdi></td></tr>
                                    <tr><td>3</td><td><span class="client-name"><i class="client-mark client-mark--three">ش</i>{{ $t('شركة الشرق الأوسط', 'Middle East Co.') }}</span></td><td><bdi>285,400</bdi> {{ $t('ر.س', 'SAR') }}</td><td><bdi>17</bdi></td></tr>
                                    <tr><td>4</td><td><span class="client-name"><i class="client-mark client-mark--four">أ</i>{{ $t('مؤسسة الأمل', 'Al-Amal Foundation') }}</span></td><td><bdi>192,700</bdi> {{ $t('ر.س', 'SAR') }}</td><td><bdi>12</bdi></td></tr>
                                    <tr><td>5</td><td><span class="client-name"><i class="client-mark client-mark--five">ر</i>{{ $t('فرع الرياض', 'Riyadh Branch') }}</span></td><td><bdi>168,300</bdi> {{ $t('ر.س', 'SAR') }}</td><td><bdi>9</bdi></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="panel orders-panel">
                        <div class="panel-heading"><h2>{{ $t('أحدث الطلبات', 'Recent orders') }}</h2><span class="demo-table-label">{{ $t('بيانات تجريبية', 'Demo data') }}</span></div>
                        <div class="table-scroll" role="region" aria-label="{{ $t('جدول أحدث الطلبات', 'Recent orders table') }}" tabindex="0">
                            <table>
                                <caption class="visually-hidden">{{ $t('أحدث الطلبات المسجلة', 'Most recent orders') }}</caption>
                                <thead><tr><th scope="col">{{ $t('رقم الطلب', 'Order number') }}</th><th scope="col">{{ $t('العميل', 'Client') }}</th><th scope="col">{{ $t('القيمة', 'Value') }}</th><th scope="col">{{ $t('الحالة', 'Status') }}</th><th scope="col">{{ $t('التاريخ', 'Date') }}</th></tr></thead>
                                <tbody>
                                    <tr><td><bdi dir="ltr">#SO-2026-015</bdi></td><td>{{ $t('شركة القمر للتجارة', 'Al-Qamar Trading Co.') }}</td><td><bdi>125,000</bdi> {{ $t('ر.س', 'SAR') }}</td><td><span class="status-pill is-complete">{{ $t('مكتمل', 'Complete') }}</span></td><td><bdi dir="ltr">2026-10-17</bdi></td></tr>
                                    <tr><td><bdi dir="ltr">#SO-2026-014</bdi></td><td>{{ $t('مؤسسة الإبداع', 'Al-Ebdaa Establishment') }}</td><td><bdi>89,500</bdi> {{ $t('ر.س', 'SAR') }}</td><td><span class="status-pill is-progress">{{ $t('قيد التنفيذ', 'In progress') }}</span></td><td><bdi dir="ltr">2026-10-16</bdi></td></tr>
                                    <tr><td><bdi dir="ltr">#SO-2026-013</bdi></td><td>{{ $t('شركة الشرق الأوسط', 'Middle East Co.') }}</td><td><bdi>245,300</bdi> {{ $t('ر.س', 'SAR') }}</td><td><span class="status-pill is-paused">{{ $t('معلق', 'On hold') }}</span></td><td><bdi dir="ltr">2026-10-16</bdi></td></tr>
                                    <tr><td><bdi dir="ltr">#SO-2026-012</bdi></td><td>{{ $t('مؤسسة الأمل', 'Al-Amal Foundation') }}</td><td><bdi>67,200</bdi> {{ $t('ر.س', 'SAR') }}</td><td><span class="status-pill is-complete">{{ $t('مكتمل', 'Complete') }}</span></td><td><bdi dir="ltr">2026-10-15</bdi></td></tr>
                                    <tr><td><bdi dir="ltr">#SO-2026-011</bdi></td><td>{{ $t('فرع الرياض', 'Riyadh Branch') }}</td><td><bdi>182,400</bdi> {{ $t('ر.س', 'SAR') }}</td><td><span class="status-pill is-cancelled">{{ $t('ملغي', 'Cancelled') }}</span></td><td><bdi dir="ltr">2026-10-14</bdi></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>
                <x-ui.page-tour id="dashboard" :locale="$locale" :autostart="true" :actor="$actor" :permissions="$permissions" />
            </main>
        </div>
    </div>
    <script>
        {!! file_get_contents(resource_path('js/dashboard.js')) !!}
    </script>
</body>
</html>


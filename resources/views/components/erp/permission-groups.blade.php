@props(['catalog' => [], 'selected' => [], 'labels' => [], 'id' => 'permissions', 'locale' => 'ar'])
@php
    $t = static fn ($ar, $en) => $locale === 'ar' ? $ar : $en;
    $permissions = array_is_list($catalog) ? $catalog : array_keys($catalog);
    $groups = collect($permissions)->filter(fn ($value) => is_string($value))->groupBy(fn ($permission) => explode('.', $permission)[0]);
    $names = ['organization' => $t('المؤسسة', 'Organization'), 'users' => $t('إدارة المستخدمين', 'User management'), 'roles' => $t('الأدوار والصلاحيات', 'Roles and permissions'), 'audit' => $t('سجل التدقيق', 'Audit log'), 'employees' => $t('الموارد البشرية', 'Employees'), 'employee' => $t('خصوصية الموظفين', 'Employee privacy'), 'workers' => $t('العمال', 'Workers'), 'tasks' => $t('المهام', 'Tasks'), 'crm' => $t('إدارة العملاء', 'CRM'), 'contact' => $t('بيانات الاتصال', 'Contact data'), 'cycles' => $t('دورات العمل', 'Work cycles')];
@endphp
<div {{ $attributes->merge(['class' => 'admin-permissions', 'data-permission-groups' => '']) }}>
    <div class="admin-section-heading"><h2><x-ui.icon name="shield" />{{ $t('الصلاحيات', 'Permissions') }}</h2><x-ui.field :id="$id.'-search'" class="admin-search" :label="$t('البحث في الصلاحيات', 'Search permissions')"><x-ui.search-input :id="$id.'-search'" data-permission-search autocomplete="off" :placeholder="$t('البحث في الصلاحيات...', 'Search permissions...')" /></x-ui.field></div>
    <p class="admin-permission-count" aria-live="polite"><strong data-permission-count>{{ count(array_intersect($permissions, $selected)) }}</strong> {{ $t('صلاحية محددة', 'permissions selected') }}</p>
    @foreach ($groups as $group => $entries)
        <details class="admin-permission-group" data-permission-group>
            <summary><x-ui.icon :name="['organization'=>'building','users'=>'users','audit'=>'audit','employees'=>'users','crm'=>'grid','tasks'=>'audit'][$group] ?? 'shield'" /><span class="admin-permission-state" aria-hidden="true" data-permission-state></span><strong>{{ $names[$group] ?? $group }}</strong><span class="admin-count">{{ count($entries) }}</span><span class="admin-disclosure" aria-hidden="true">‹</span></summary>
            <fieldset><legend class="u-visually-hidden">{{ $names[$group] ?? $group }}</legend>
                <div class="ui-inline"><x-ui.button variant="ghost" data-permission-select="all">{{ $t('تحديد الكل', 'Select all') }}</x-ui.button><x-ui.button variant="ghost" data-permission-select="none">{{ $t('مسح التحديد', 'Clear selection') }}</x-ui.button></div>
                @foreach ($entries as $permission)
                    <label class="admin-permission-option" data-permission-option><x-ui.checkbox :id="$id.'-'.str_replace('.', '-', $permission)" name="permissions[]" :value="$permission" :checked="in_array($permission, $selected, true)" /><span>{{ $labels[$permission] ?? (is_string($catalog[$permission] ?? null) ? $catalog[$permission] : $permission) }}</span></label>
                @endforeach
            </fieldset>
        </details>
    @endforeach
</div>

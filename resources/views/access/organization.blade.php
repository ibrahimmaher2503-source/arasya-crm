@php
    $locale = ($locale ?? 'ar') === 'en' ? 'en' : 'ar';
    $t = static fn (string $arabic, string $english): string => $locale === 'ar' ? $arabic : $english;
    $title = $t('المؤسسة', 'Organization');
    $company = $data['company'] ?? [];
    $branches = $data['branches'] ?? [];
    $departments = $data['departments'] ?? [];
    $branchOptions = $branchOptions ?? $branches;
    $roleOptions = $roleOptions ?? ($roles ?? []);
    $summary = is_array($summary ?? null) ? $summary : [];
    $actor = is_array($actor ?? null) ? $actor : [];
    $permissions = is_array($permissions ?? null) ? $permissions : [];
    $canWriteOrganization = in_array('organization.write', $permissions, true);
    $canManageCompany = $canWriteOrganization && ($actor['is_admin'] ?? false) === true;
    $canCreateBranch = $canManageCompany;
    $canAssignDepartmentRoles = $canWriteOrganization
        && in_array('roles.write', $permissions, true)
        && in_array('roles.read', $permissions, true);
    $branchCount = $summary['branches'] ?? (method_exists($branches, 'total') ? $branches->total() : count($branches));
    $departmentCount = $summary['departments'] ?? (method_exists($departments, 'total') ? $departments->total() : count($departments));
    $filters = is_array($filters ?? null) ? $filters : request()->only(['q', 'status', 'branch_id']);
    $searchQuery = is_string($filters['q'] ?? null) ? $filters['q'] : '';
    $statusFilter = in_array($filters['status'] ?? '', ['active', 'inactive'], true) ? $filters['status'] : '';
    $branchFilter = is_string($filters['branch_id'] ?? null) ? $filters['branch_id'] : '';
@endphp
@extends('layouts.shell', ['locale' => $locale, 'title' => $title])

@section('content')
    <div class="ui-stack">
        @include('access._nav', ['active' => 'organization', 'locale' => $locale, 'actor' => $actor, 'permissions' => $permissions])

        <x-ui.summary-strip class="admin-organization-summary" :aria-label="$t('ملخص المؤسسة', 'Organization overview')" :items="[['label'=>$t('اسم الشركة','Company name'),'value'=>$company['name'] ?? '—','icon'=>'building','tone'=>'info','description'=>$company['id'] ?? '—'],['label'=>$t('عدد الفروع','Branches'),'value'=>$branchCount,'icon'=>'building'],['label'=>$t('عدد الأقسام','Departments'),'value'=>$departmentCount,'icon'=>'users']]" />

        <section class="admin-section" aria-labelledby="company-details-title">
            <div class="admin-section-heading">
                <div>
                    <h2 id="company-details-title"><x-ui.icon name="building" />{{ $t('بيانات المؤسسة', 'Company details') }}</h2>
                </div>
            </div>
            @if ($canManageCompany)
                <form class="admin-company-form" method="post" action="/access/organization" data-testid="company-form">
                    @csrf
                    <input type="hidden" name="version" value="{{ $company['version'] ?? 0 }}">
                    <x-ui.field id="company-name" :label="$t('اسم الشركة', 'Company name')" :error="$errors->first('name')">
                        <x-ui.input id="company-name" name="name" :value="old('name', $company['name'] ?? '')" required maxlength="160" />
                    </x-ui.field>
                    <x-ui.field id="company-identifier" :label="$t('رمز المؤسسة', 'Company ID')"><x-ui.input id="company-identifier" :value="$company['id'] ?? '—'" readonly dir="ltr" /></x-ui.field>
                    <div class="admin-company-actions"><p class="ui-muted">{{ $t('اسم الشركة هو البيان القابل للتعديل هنا.', 'The company name is the editable company detail.') }}</p><x-ui.button type="submit"><x-ui.icon name="building" />{{ $t('حفظ بيانات الشركة', 'Save company details') }}</x-ui.button></div>
                </form>
            @else
                <x-ui.description-list :items="[$t('اسم الشركة', 'Company name') => $company['name'] ?? '—']" />
            @endif
        </section>

        <section class="admin-section admin-directory-controls" data-tour="access.organization.filters"><form class="admin-toolbar" role="search" method="get" action="/access/organization">
            <input type="hidden" name="locale" value="{{ $locale }}">
            <x-ui.field id="organization-search" class="admin-search" :label="$t('البحث بالاسم أو الرمز', 'Search by name or code')">
                <x-ui.input id="organization-search" name="q" type="search" :value="$searchQuery" :placeholder="$t('البحث في الفروع والأقسام...', 'Search branches and departments...')" />
            </x-ui.field>
            <x-ui.field id="organization-status" :label="$t('الحالة', 'Status')">
                <x-ui.select id="organization-status" name="status">
                    <option value="" @selected($statusFilter === '')>{{ $t('جميع الحالات', 'All statuses') }}</option>
                    <option value="active" @selected($statusFilter === 'active')>{{ $t('نشط', 'Active') }}</option>
                    <option value="inactive" @selected($statusFilter === 'inactive')>{{ $t('غير نشط', 'Inactive') }}</option>
                </x-ui.select>
            </x-ui.field>
            <x-ui.field id="organization-branch-filter" :label="$t('الفرع', 'Branch')">
                <x-ui.select id="organization-branch-filter" name="branch_id">
                    <option value="" @selected($branchFilter === '')>{{ $t('جميع الفروع', 'All branches') }}</option>
                    @foreach ($branchOptions as $branchOption)
                        <option value="{{ $branchOption['id'] }}" @selected($branchFilter === (string) ($branchOption['id'] ?? ''))>{{ $branchOption['name'] ?? $branchOption['id'] }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.button type="submit">{{ $t('تطبيق', 'Apply filters') }}</x-ui.button>
            <a class="ui-button ui-button--ghost" href="/access/organization?locale={{ $locale }}">{{ $t('مسح التصفية', 'Clear filters') }}</a>
        </form>
        <x-ui.option-browser :locale="$locale" :sources="[['label'=>$t('الفروع','Branches'),'query'=>'branch_q','paginator'=>$branchOptions],['label'=>$t('الأدوار','Roles'),'query'=>'role_q','paginator'=>$roleOptions]]" /></section>

        <section class="admin-section admin-table" aria-labelledby="branches-title" data-tour="access.organization.results">
            <div class="admin-section-heading">
                <div>
                    <h2 id="branches-title"><x-ui.icon name="building" />{{ $t('الفروع', 'Branches') }}</h2>
                </div>
                @if ($canCreateBranch)
                    <x-ui.button data-open-dialog="branch-create"><x-ui.icon name="plus" />{{ $t('إضافة فرع', 'Add branch') }}</x-ui.button>
                @endif
            </div>
            <x-ui.table :caption="$t('فروع الشركة', 'Company branches')">
                <thead>
                    <tr>
                        <th scope="col">{{ $t('اسم الفرع', 'Branch name') }}</th>
                        <th scope="col">{{ $t('الرمز', 'Code') }}</th>
                        <th scope="col">{{ $t('الحالة', 'Status') }}</th>
                        <th scope="col">{{ $t('الإصدار', 'Version') }}</th>
                        @if ($canWriteOrganization)<th scope="col">{{ $t('الإجراءات', 'Actions') }}</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <th scope="row">{{ $branch['name'] ?? '—' }}</th>
                            <td><bdi dir="ltr">{{ $branch['code'] ?? '—' }}</bdi></td>
                            <td>
                                <x-ui.status-badge :status="($branch['active'] ?? false) ? 'success' : 'neutral'">
                                    {{ ($branch['active'] ?? false) ? $t('نشط', 'Active') : $t('غير نشط', 'Inactive') }}
                                </x-ui.status-badge>
                            </td>
                            <td><bdi dir="ltr">{{ $branch['version'] ?? 0 }}</bdi></td>
                            @if ($canWriteOrganization)
                                <td>
                                    <x-ui.button variant="secondary" data-open-dialog="branch-edit" data-edit-record data-record="{{ json_encode($branch, JSON_UNESCAPED_UNICODE) }}" data-form-action="{{ '/access/branches/'.rawurlencode((string) ($branch['id'] ?? '')) }}">
                                        {{ $t('تعديل الفرع', 'Edit branch') }}
                                    </x-ui.button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canWriteOrganization ? 5 : 4 }}">{{ $t('لا توجد فروع تطابق الاختيار.', 'No branches match the current filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$branches" :locale="$locale" />
        </section>

        <section class="admin-section admin-table" aria-labelledby="departments-title">
            <div class="admin-section-heading">
                <div>
                    <h2 id="departments-title"><x-ui.icon name="users" />{{ $t('الأقسام', 'Departments') }}</h2>
                </div>
                @if ($canWriteOrganization)
                    <x-ui.button data-open-dialog="department-create"><x-ui.icon name="plus" />{{ $t('إضافة قسم', 'Add department') }}</x-ui.button>
                @endif
            </div>
            <x-ui.table :caption="$t('أقسام الشركة', 'Company departments')">
                <thead>
                    <tr>
                        <th scope="col">{{ $t('اسم القسم', 'Department name') }}</th>
                        <th scope="col">{{ $t('الرمز', 'Code') }}</th>
                        <th scope="col">{{ $t('الفرع', 'Branch') }}</th>
                        <th scope="col">{{ $t('الحالة', 'Status') }}</th>
                        <th scope="col">{{ $t('الإصدار', 'Version') }}</th>
                        @if ($canWriteOrganization)<th scope="col">{{ $t('الإجراءات', 'Actions') }}</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <th scope="row">{{ $department['name'] ?? '—' }}</th>
                            <td><bdi dir="ltr">{{ $department['code'] ?? '—' }}</bdi></td>
                            <td>{{ $department['branch_name'] ?? $department['branch_id'] ?? '—' }}</td>
                            <td>
                                <x-ui.status-badge :status="($department['active'] ?? false) ? 'success' : 'neutral'">
                                    {{ ($department['active'] ?? false) ? $t('نشط', 'Active') : $t('غير نشط', 'Inactive') }}
                                </x-ui.status-badge>
                            </td>
                            <td><bdi dir="ltr">{{ $department['version'] ?? 0 }}</bdi></td>
                            @if ($canWriteOrganization)
                                <td>
                                    <div class="ui-inline">
                                        <x-ui.button variant="secondary" data-open-dialog="department-edit" data-edit-record data-record="{{ json_encode($department, JSON_UNESCAPED_UNICODE) }}" data-form-action="{{ '/access/departments/'.rawurlencode((string) ($department['id'] ?? '')) }}">
                                            {{ $t('تعديل القسم', 'Edit department') }}
                                        </x-ui.button>
                                        @if ($canAssignDepartmentRoles && ($department['roles_editable'] ?? true) && (($actor['is_admin'] ?? false) || ($department['id'] ?? null) !== ($actor['department_id'] ?? null)))
                                            <x-ui.button variant="ghost" data-testid="department-roles-{{ $department['id'] }}" data-open-dialog="department-roles" data-edit-record data-record="{{ json_encode($department, JSON_UNESCAPED_UNICODE) }}" data-form-action="{{ '/access/departments/'.rawurlencode((string) ($department['id'] ?? '')).'/roles' }}">
                                                {{ $t('الأدوار الافتراضية ('.count($department['role_ids'] ?? []).')', 'Default roles ('.count($department['role_ids'] ?? []).')') }}
                                            </x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canWriteOrganization ? 6 : 5 }}">{{ $t('لا توجد أقسام تطابق الاختيار.', 'No departments match the current filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
            <x-ui.list-pagination :paginator="$departments" :locale="$locale" />
        </section>

        @if ($canCreateBranch)
            <x-ui.modal id="branch-create" :title="$t('إضافة فرع', 'Add a branch')" :close-label="$t('إغلاق', 'Close')">
                <form class="ui-stack" method="post" action="/access/branches" data-testid="branch-form">
                    @csrf
                    <x-ui.field id="new-branch-name" :label="$t('اسم الفرع', 'Branch name')" :error="$errors->first('name')">
                        <x-ui.input id="new-branch-name" name="name" :value="old('name')" required maxlength="160" />
                    </x-ui.field>
                    <x-ui.field id="new-branch-code" :label="$t('رمز الفرع', 'Branch code')" :error="$errors->first('code')">
                        <x-ui.input id="new-branch-code" name="code" :value="old('code')" required maxlength="40" />
                    </x-ui.field>
                    <x-ui.form-actions>
                        <x-ui.button type="submit">{{ $t('إضافة الفرع', 'Add branch') }}</x-ui.button>
                        <x-ui.button variant="secondary" data-close-dialog>{{ $t('إلغاء', 'Cancel') }}</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.modal>
        @endif

        @if ($canWriteOrganization)
            <x-ui.modal id="branch-edit" :title="$t('تعديل الفرع', 'Edit branch')" :close-label="$t('إغلاق', 'Close')">
                <form class="ui-stack" method="post" action="" data-record-form>
                    @csrf
                    <input type="hidden" name="version" value="0">
                    <x-ui.field id="edit-branch-name" :label="$t('اسم الفرع', 'Branch name')" :error="$errors->first('name')">
                        <x-ui.input id="edit-branch-name" name="name" required maxlength="160" />
                    </x-ui.field>
                    <x-ui.field id="edit-branch-code" :label="$t('رمز الفرع', 'Branch code')" :error="$errors->first('code')">
                        <x-ui.input id="edit-branch-code" name="code" required maxlength="40" />
                    </x-ui.field>
                    <x-ui.field id="edit-branch-active" :label="$t('نشط', 'Active')">
                        <input type="hidden" name="active" value="0">
                        <x-ui.checkbox id="edit-branch-active" name="active" value="1" />
                    </x-ui.field>
                    <x-ui.form-actions>
                        <x-ui.button type="submit">{{ $t('حفظ الفرع', 'Save branch') }}</x-ui.button>
                        <x-ui.button variant="secondary" data-close-dialog>{{ $t('إلغاء', 'Cancel') }}</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.modal>
        @endif

        @if ($canWriteOrganization)
            <x-ui.modal id="department-create" :title="$t('إضافة قسم', 'Add a department')" :close-label="$t('إغلاق', 'Close')">
                <form class="ui-stack" method="post" action="/access/departments" data-testid="department-form">
                    @csrf
                    <x-ui.field id="new-department-name" :label="$t('اسم القسم', 'Department name')" :error="$errors->first('name')">
                        <x-ui.input id="new-department-name" name="name" :value="old('name')" required maxlength="160" />
                    </x-ui.field>
                    <x-ui.field id="new-department-code" :label="$t('رمز القسم', 'Department code')" :error="$errors->first('code')">
                        <x-ui.input id="new-department-code" name="code" :value="old('code')" required maxlength="40" />
                    </x-ui.field>
                    <x-ui.field id="new-department-branch" :label="$t('الفرع', 'Branch')" :error="$errors->first('branch_id')">
                        <x-ui.select id="new-department-branch" name="branch_id" required>
                            <option value="">{{ $t('اختر فرعاً', 'Choose a branch') }}</option>
                            @foreach ($branchOptions as $branchOption)
                                <option value="{{ $branchOption['id'] }}" @selected((string) old('branch_id') === (string) ($branchOption['id'] ?? ''))>{{ $branchOption['name'] ?? $branchOption['id'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.form-actions>
                        <x-ui.button type="submit">{{ $t('إضافة القسم', 'Add department') }}</x-ui.button>
                        <x-ui.button variant="secondary" data-close-dialog>{{ $t('إلغاء', 'Cancel') }}</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.modal>

            <x-ui.modal id="department-edit" :title="$t('تعديل القسم', 'Edit department')" :close-label="$t('إغلاق', 'Close')">
                <form class="ui-stack" method="post" action="" data-record-form>
                    @csrf
                    <input type="hidden" name="version" value="0">
                    <x-ui.field id="edit-department-name" :label="$t('اسم القسم', 'Department name')" :error="$errors->first('name')">
                        <x-ui.input id="edit-department-name" name="name" required maxlength="160" />
                    </x-ui.field>
                    <x-ui.field id="edit-department-code" :label="$t('رمز القسم', 'Department code')" :error="$errors->first('code')">
                        <x-ui.input id="edit-department-code" name="code" required maxlength="40" />
                    </x-ui.field>
                    <x-ui.field id="edit-department-branch" :label="$t('الفرع', 'Branch')">
                        <x-ui.select id="edit-department-branch" name="branch_id" required>
                            @foreach ($branchOptions as $branchOption)
                                <option value="{{ $branchOption['id'] }}">{{ $branchOption['name'] ?? $branchOption['id'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field id="edit-department-active" :label="$t('نشط', 'Active')">
                        <input type="hidden" name="active" value="0">
                        <x-ui.checkbox id="edit-department-active" name="active" value="1" />
                    </x-ui.field>
                    <x-ui.form-actions>
                        <x-ui.button type="submit">{{ $t('حفظ القسم', 'Save department') }}</x-ui.button>
                        <x-ui.button variant="secondary" data-close-dialog>{{ $t('إلغاء', 'Cancel') }}</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.modal>
        @endif

        @if ($canAssignDepartmentRoles)
            <x-ui.modal id="department-roles" :title="$t('الأدوار الافتراضية للقسم', 'Department default roles')" :close-label="$t('إغلاق', 'Close')">
                <p>{{ $t('يرث مستخدمو القسم الأدوار المحددة. استثناء المنع الفردي يتغلب على الصلاحية الموروثة، وإزالة الدور تسري على الجلسات الحالية.', 'Department users inherit selected roles. Individual denials override inherited permissions, and removal applies to current sessions.') }}</p>
                <form class="ui-stack" method="post" action="" data-record-form data-testid="department-roles-form">
                    @csrf
                    <input type="hidden" name="version" value="0">
                    <div data-preserved-role-values></div>
                    <fieldset>
                        <legend>{{ $t('الأدوار', 'Roles') }}</legend>
                        @foreach ($roleOptions as $role)
                            <label class="ui-inline" for="department-role-{{ $role['id'] }}">
                                <x-ui.checkbox id="department-role-{{ $role['id'] }}" name="role_ids[]" :value="$role['id']" />
                                {{ $role['name'] }}
                            </label>
                        @endforeach
                    </fieldset>
                    <x-ui.form-actions>
                        <x-ui.button type="submit" variant="secondary">{{ $t('حفظ الأدوار', 'Save roles') }}</x-ui.button>
                        <x-ui.button variant="ghost" data-close-dialog>{{ $t('إلغاء', 'Cancel') }}</x-ui.button>
                    </x-ui.form-actions>
                </form>
            </x-ui.modal>
        @endif

        <x-ui.page-tour id="access-organization" :locale="$locale" :actor="$actor" :permissions="$permissions" />
    </div>
@endsection

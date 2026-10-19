@php
    $t = static fn (string $ar, string $en): string => $locale === 'ar' ? $ar : $en;
    $manageAll = in_array('employees.manage_all', $permissions, true);
    $private = in_array('employee.private', $permissions, true);
    $canLinkUser = in_array('users.read', $permissions, true);
    $employee = $employee ?? [];
    $workerDefaults = $workerDefaults ?? [];
    $workerCreate = (bool) ($workerCreate ?? false);
    $editing = isset($employee['id']);
    $canLinkUser = $canLinkUser && (! $editing || ($employee['can_assign_account'] ?? false));
    $branchValue = old('branch_id', $workerDefaults['branch_id'] ?? $employee['branch_id'] ?? '');
    $departmentValue = old('department_id', $workerDefaults['department_id'] ?? $employee['department_id'] ?? '');
    $managerValue = old('manager_id', $workerDefaults['manager_id'] ?? $employee['manager_id'] ?? '');
    $userValue = old('user_id', $employee['user_id'] ?? '');
    $hireDateValue = old('hire_date', $employee['hire_date'] ?? '');
    $statusValue = old('status', $employee['status'] ?? 'active');
    $backPath = $editing ? '/employees/'.rawurlencode((string) $employee['id']).'?locale='.$locale : ($workerCreate ? '/employees/workers?locale='.$locale : '/employees?locale='.$locale);
    $formPath = $editing ? '/employees/'.rawurlencode((string) $employee['id']) : '/employees';
    $formQuery = ($workerCreate && ! $manageAll ? 'worker=1&' : '').'locale='.$locale;
    $statusLabels = [
        'active' => $t('نشط', 'Active'),
        'on_leave' => $t('في إجازة', 'On leave'),
        'suspended' => $t('موقوف', 'Suspended'),
        'terminated' => $t('انتهت الخدمة', 'Terminated'),
    ];
    $statusVariants = ['active' => 'success', 'on_leave' => 'info', 'suspended' => 'warning', 'terminated' => 'danger'];
    $workspaceTitle = $editing
        ? ($employee['name'] ?? $t('ملف الموظف', 'Employee profile'))
        : ($workerCreate ? $t('إضافة عامل', 'Add worker') : $t('إضافة موظف', 'Add employee'));
@endphp
@extends('layouts.shell')
@section('content')
<style>{!! file_get_contents(resource_path('css/employee-profile.css')) !!}</style>
<div class="ui-stack employee-form" data-testid="employee-form-workspace">
    <section class="admin-section employee-form__intro" aria-label="{{ $t('سياق ملف الموظف', 'Employee workspace context') }}">
        <div class="employee-form__identity">
            <x-ui.avatar :name="$workspaceTitle" class="employee-form__avatar" />
            <div class="employee-form__identity-copy">
                <p class="employee-profile__eyebrow">{{ $editing ? $t('تعديل بيانات الموظف', 'Edit employee details') : $t('ملف موظف جديد', 'New employee record') }}</p>
                <h2>{{ $workspaceTitle }}</h2>
                @if($editing)
                    <p><bdi>{{ $employee['code'] ?? '—' }}</bdi>@if(filled($employee['role'] ?? null)) <span aria-hidden="true">·</span> {{ $employee['role'] }}@endif</p>
                    <x-ui.status-badge :status="$statusVariants[$statusValue] ?? 'neutral'">{{ $statusLabels[$statusValue] ?? $t('غير محدد', 'Unknown') }}</x-ui.status-badge>
                @else
                    <p>{{ $workerCreate ? $t('تُحفظ بيانات العامل ضمن نطاق المشرف المحدد.', 'The worker is created within the assigned supervisor scope.') : $t('أدخل بيانات الموظف المتاحة ضمن صلاحياتك.', 'Enter the employee details available to your permissions.') }}</p>
                @endif
            </div>
        </div>
    </section>

    @if($manageAll && !empty($optionSources))
        <x-ui.option-browser :sources="$optionSources" :locale="$locale" />
    @endif

    <form method="post" action="{{ $formPath }}?{{ $formQuery }}" class="ui-stack employee-form__form">
        @csrf
        @if($editing)
            <input type="hidden" name="version" value="{{ $employee['version'] }}">
        @endif

        @if($workerCreate && ! $manageAll)
            <input type="hidden" name="branch_id" value="{{ $branchValue }}">
            <input type="hidden" name="department_id" value="{{ $departmentValue }}">
            <input type="hidden" name="manager_id" value="{{ $managerValue }}">
            <input type="hidden" name="user_id" value="">
            @foreach(['branch_id' => $t('نطاق الفرع', 'Branch scope'), 'department_id' => $t('نطاق القسم', 'Department scope'), 'manager_id' => $t('المشرف المباشر', 'Supervisor'), 'user_id' => $t('حساب الدخول', 'Login account')] as $field => $label)
                @if($errors->first($field))
                    <x-ui.validation-message :id="'employee-'.$field.'-error'">{{ $label }}: {{ $errors->first($field) }}</x-ui.validation-message>
                @endif
            @endforeach
        @endif

        <div @class(['admin-grid employee-form__layout' => $manageAll || $workerCreate, 'ui-stack employee-form__layout--single' => ! $manageAll && ! $workerCreate])>
            <div class="ui-stack employee-form__column">
                <x-ui.form-section class="admin-section employee-form__section" :title="$t('البيانات الأساسية', 'Basic details')">
                    <div class="ui-grid">
                        @if($manageAll || $workerCreate)
                            <x-ui.field id="employee-code" :label="$t('رقم الموظف', 'Employee code')" :error="$errors->first('code')">
                                <x-ui.input id="employee-code" name="code" :value="old('code', $employee['code'] ?? '')" :error="$errors->first('code')" required maxlength="64" />
                            </x-ui.field>
                        @endif
                        <x-ui.field id="employee-name" :label="$t('الاسم', 'Name')" :error="$errors->first('name')">
                            <x-ui.input id="employee-name" name="name" :value="old('name', $employee['name'] ?? '')" :error="$errors->first('name')" required maxlength="200" autocomplete="name" />
                        </x-ui.field>
                        <x-ui.field id="employee-role" :label="$t('المسمى الوظيفي', 'Job title')" :error="$errors->first('role')">
                            <x-ui.input id="employee-role" name="role" :value="old('role', $employee['role'] ?? '')" :error="$errors->first('role')" required maxlength="200" />
                        </x-ui.field>
                    </div>
                </x-ui.form-section>

                @if($private)
                    <x-ui.form-section class="admin-section employee-form__section" :title="$t('معلومات الاتصال الخاصة', 'Private contact details')" :description="$t('لا تظهر هذه البيانات إلا لمن لديه صلاحية الاطلاع عليها.', 'These details are shown only to people with permission to view them.')">
                        <div class="ui-grid">
                            <x-ui.field id="employee-phone" :label="$t('الهاتف', 'Phone')" :error="$errors->first('phone')">
                                <x-ui.input id="employee-phone" name="phone" :value="old('phone', $employee['phone'] ?? '')" :error="$errors->first('phone')" autocomplete="tel" maxlength="64" />
                            </x-ui.field>
                            <x-ui.field id="employee-email" :label="$t('البريد الإلكتروني', 'Email')" :error="$errors->first('email')">
                                <x-ui.input id="employee-email" name="email" type="email" :value="old('email', $employee['email'] ?? '')" :error="$errors->first('email')" autocomplete="email" maxlength="254" />
                            </x-ui.field>
                            <x-ui.field id="employee-private-notes" :label="$t('ملاحظات خاصة', 'Private notes')" :error="$errors->first('private_notes')">
                                <x-ui.textarea id="employee-private-notes" name="private_notes" :error="$errors->first('private_notes')" maxlength="4000">{{ old('private_notes', $employee['private_notes'] ?? '') }}</x-ui.textarea>
                            </x-ui.field>
                        </div>
                    </x-ui.form-section>
                @endif
            </div>

            @if($manageAll || $workerCreate)
                <div class="ui-stack employee-form__column">
                    <x-ui.form-section class="admin-section employee-form__section" :title="$t('المعلومات الوظيفية', 'Employment details')">
                        <div class="ui-grid">
                            <x-ui.field id="employee-hire-date" :label="$t('تاريخ التعيين', 'Hire date')" :error="$errors->first('hire_date')">
                                <x-ui.date-input id="employee-hire-date" name="hire_date" :value="$hireDateValue" :error="$errors->first('hire_date')" required />
                            </x-ui.field>
                            @if($workerCreate && ! $manageAll)
                                <input type="hidden" name="status" value="active">
                                <div class="ui-field">
                                    <span class="ui-field__label">{{ $t('الحالة الوظيفية', 'Employment status') }}</span>
                                    <x-ui.status-badge status="success">{{ $t('نشط', 'Active') }}</x-ui.status-badge>
                                    @if($errors->first('status'))
                                        <x-ui.validation-message id="employee-status-error">{{ $errors->first('status') }}</x-ui.validation-message>
                                    @endif
                                </div>
                            @elseif(($employee['status'] ?? null) === 'terminated')
                                <input type="hidden" name="status" value="{{ $statusValue }}">
                                <div class="employee-form__terminated-status">
                                    <x-ui.status-badge status="danger">{{ $statusLabels[$employee['status'] ?? 'terminated'] ?? $t('انتهت الخدمة', 'Terminated') }}</x-ui.status-badge>
                                    <p class="ui-muted">{{ $t('تغيير الحالة وإنهاء الخدمة غير متاحين في هذا النموذج.', 'Status changes and offboarding are not available in this form.') }}</p>
                                </div>
                                @if($errors->first('status'))
                                    <x-ui.validation-message id="employee-status-error">{{ $errors->first('status') }}</x-ui.validation-message>
                                @endif
                            @else
                                <x-ui.field id="employee-status" :label="$t('الحالة الوظيفية', 'Employment status')" :error="$errors->first('status')">
                                    <x-ui.select id="employee-status" name="status" :error="$errors->first('status')">
                                        @foreach(['active' => $t('نشط', 'Active'), 'on_leave' => $t('في إجازة', 'On leave'), 'suspended' => $t('موقوف', 'Suspended')] as $value => $label)
                                            <option value="{{ $value }}" @selected((string) $statusValue === $value)>{{ $label }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                            @endif
                        </div>
                    </x-ui.form-section>

                    @if($manageAll)
                        <x-ui.form-section class="admin-section employee-form__section" :title="$t('الفرع والقسم والمشرف', 'Branch, department and supervisor')">
                            <div class="ui-grid">
                                @foreach([
                                    'branch_id' => [$t('الفرع', 'Branch'), $branches ?? [], $branchValue, true],
                                    'department_id' => [$t('القسم', 'Department'), $departments ?? [], $departmentValue, true],
                                    'manager_id' => [$t('المشرف المباشر', 'Supervisor'), $managers ?? [], $managerValue, false],
                                ] as $field => [$label, $options, $selectedValue, $required])
                                    <x-ui.field :id="'employee-'.str_replace('_', '-', $field)" :label="$label" :error="$errors->first($field)">
                                        <x-ui.select :id="'employee-'.str_replace('_', '-', $field)" :name="$field" :required="$required" :error="$errors->first($field)">
                                            <option value="">{{ $required ? $t('اختر', 'Choose') : $t('بدون مشرف', 'No supervisor') }}</option>
                                            @foreach($options as $option)
                                                <option value="{{ $option['id'] }}" @selected((string) $selectedValue === (string) $option['id'])>{{ $option['name'] }}</option>
                                            @endforeach
                                        </x-ui.select>
                                    </x-ui.field>
                                @endforeach
                            </div>
                        </x-ui.form-section>

                        @if($canLinkUser)
                            <x-ui.form-section class="admin-section employee-form__section" :title="$t('حساب الدخول المرتبط', 'Linked login account')" :description="$t('يمكن ربط حساب موجود. إنشاء حساب جديد يتم من إدارة المستخدمين.', 'Link an existing account. Create accounts from user management.')">
                                <x-ui.field id="employee-user-id" :label="$t('حساب المستخدم (اختياري)', 'User account (optional)')" :error="$errors->first('user_id')">
                                    <x-ui.select id="employee-user-id" name="user_id" :error="$errors->first('user_id')">
                                        <option value="">{{ $t('بدون حساب دخول', 'No login account') }}</option>
                                        @foreach(($users ?? []) as $user)
                                            <option value="{{ $user['id'] }}" @selected((string) $userValue === (string) $user['id'])>{{ $user['name'] }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                            </x-ui.form-section>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        <x-ui.form-actions class="employee-form__actions">
            <x-ui.button type="submit">{{ $t('حفظ الموظف', 'Save employee') }}</x-ui.button>
            <a class="ui-button ui-button--secondary" href="{{ $backPath }}">{{ $t('إلغاء', 'Cancel') }}</a>
        </x-ui.form-actions>
    </form>
</div>
@endsection

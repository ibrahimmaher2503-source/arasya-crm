<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

final class EmployeeProfilePresentationTest extends TestCase
{
    public function test_overview_presents_employee_identity_and_truthful_profile_summary_without_loading_history(): void
    {
        $html = $this->renderProfile();

        foreach ([
            'Synthetic employee',
            'Field engineer',
            'EMP-0001',
            'South Branch',
            'Operations',
            'Synthetic supervisor',
            '2025-03-12',
            'Employee summary',
            'Direct reports',
            'data-testid="employee-profile-summary"',
            'data-report-count="2"',
            'Overview',
            'Workers',
            'Work evidence',
            'Audit history',
            '/employees/00000000000000000000000001/edit?locale=en',
            '/employees?locale=en',
        ] as $expected) {
            self::assertStringContainsString($expected, $html);
        }

        self::assertStringNotContainsString('AUDIT-OVERVIEW-SENTINEL', $html);
        self::assertStringNotContainsString('EVIDENCE-OVERVIEW-SENTINEL', $html);
    }

    public function test_overview_counts_array_report_fixtures_without_assuming_a_paginator(): void
    {
        $html = $this->renderProfile([
            'reports' => [
                ['id' => 'report-one', 'name' => 'First report'],
                ['id' => 'report-two', 'name' => 'Second report'],
                ['id' => 'report-three', 'name' => 'Third report'],
            ],
        ]);

        self::assertStringContainsString('data-report-count="3"', $html);
    }

    public function test_missing_report_data_is_not_presented_as_zero(): void
    {
        $html = $this->renderProfile(['reports' => null]);

        preg_match('/<dd data-testid="employee-report-count"[^>]*>(.*?)<\/dd>/s', $html, $matches);
        self::assertSame('Unavailable', trim(strip_tags($matches[1] ?? '')));
        self::assertStringNotContainsString('data-report-count="0"', $html);
    }

    public function test_private_contact_and_account_identity_require_their_read_permissions(): void
    {
        $html = $this->renderProfile([
            'permissions' => ['employees.read'],
            'employee' => [
                'account_visible' => false,
                'user_id' => 'ACCOUNT-ID-PRIVATE-SENTINEL',
                'user_name' => 'PRIVATE-USERNAME-SENTINEL',
                'phone' => 'PRIVATE-PHONE-SENTINEL',
                'email' => 'PRIVATE-EMAIL-SENTINEL',
                'private_notes' => 'PRIVATE-NOTE-SENTINEL',
            ],
        ]);

        self::assertStringContainsString('Account information restricted', $html);
        foreach ([
            'ACCOUNT-ID-PRIVATE-SENTINEL',
            'PRIVATE-USERNAME-SENTINEL',
            'PRIVATE-PHONE-SENTINEL',
            'PRIVATE-EMAIL-SENTINEL',
            'PRIVATE-NOTE-SENTINEL',
            'Private contact details',
        ] as $hiddenValue) {
            self::assertStringNotContainsString($hiddenValue, $html);
        }
    }

    public function test_public_employee_values_are_html_escaped(): void
    {
        $html = $this->renderProfile([
            'employee' => [
                'name' => '<script>alert("profile")</script>',
                'branch_name' => '<img src=x onerror=alert(1)>',
            ],
        ]);

        self::assertStringContainsString('&lt;script&gt;alert(&quot;profile&quot;)&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('<script>alert("profile")</script>', $html);
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
    }

    public function test_editor_form_keeps_post_csrf_version_old_input_errors_and_authorized_fields(): void
    {
        $this->setOldInput([
            'name' => 'Old <employee>',
            'status' => 'on_leave',
            'branch_id' => 'BRANCH-OLD',
            'user_id' => 'USER-2',
        ]);
        $errors = $this->errors(['name' => 'Please correct the employee name.']);
        $html = $this->renderForm(['errors' => $errors]);

        foreach ([
            'data-testid="employee-form-workspace"',
            'Basic details',
            'Employment details',
            'Branch, department and supervisor',
            'Private contact details',
            'Linked login account',
            'method="post"',
            'action="/employees/00000000000000000000000001?locale=en"',
            'name="_token"',
            'name="version" value="3"',
            'Save employee',
            'Cancel',
            'Please correct the employee name.',
            'value="Old &lt;employee&gt;"',
        ] as $expected) {
            self::assertStringContainsString($expected, $html);
        }

        self::assertMatchesRegularExpression('/<option value="BRANCH-OLD"[^>]*selected/', $html);
        self::assertMatchesRegularExpression('/<option value="USER-2"[^>]*selected/', $html);
        self::assertMatchesRegularExpression('/<option value="on_leave"[^>]*selected/', $html);
    }

    public function test_supervisor_worker_form_keeps_hidden_scope_and_hides_private_and_account_controls(): void
    {
        $html = $this->renderForm([
            'permissions' => ['employees.read', 'employees.write'],
            'employee' => [],
            'workerCreate' => true,
            'workerDefaults' => [
                'branch_id' => 'BRANCH-WORKER',
                'department_id' => 'DEPARTMENT-WORKER',
                'manager_id' => 'MANAGER-WORKER',
            ],
        ]);

        self::assertStringContainsString('action="/employees?worker=1&amp;locale=en"', $html);
        self::assertStringContainsString('name="branch_id" value="BRANCH-WORKER"', $html);
        self::assertStringContainsString('name="department_id" value="DEPARTMENT-WORKER"', $html);
        self::assertStringContainsString('name="manager_id" value="MANAGER-WORKER"', $html);
        self::assertStringContainsString('name="user_id" value=""', $html);
        self::assertStringContainsString('name="status" value="active"', $html);
        self::assertStringContainsString('Active', $html);
        self::assertStringNotContainsString('name="phone"', $html);
        self::assertStringNotContainsString('name="email"', $html);
        self::assertStringNotContainsString('name="private_notes"', $html);
        self::assertStringNotContainsString('id="employee-user-id"', $html);
    }

    public function test_terminated_employee_status_remains_read_only_in_the_edit_form(): void
    {
        $this->setOldInput(['status' => 'terminated']);
        $html = $this->renderForm([
            'employee' => ['status' => 'terminated'],
        ]);

        self::assertStringContainsString('name="status" value="terminated"', $html);
        self::assertStringContainsString('Terminated', $html);
        self::assertStringContainsString('Status changes and offboarding are not available in this form.', $html);
        self::assertStringNotContainsString('id="employee-status"', $html);
    }

    /** @param array<string, mixed> $overrides */
    private function renderProfile(array $overrides = []): string
    {
        $report = static fn (string $id, string $name): array => [
            'id' => $id,
            'name' => $name,
            'code' => 'REPORT-'.$id,
            'role' => 'Technician',
            'branch_name' => 'South Branch',
            'department_name' => 'Operations',
            'status' => 'active',
        ];

        $data = [
            'actor' => ['id' => '00000000000000000000000090', 'name' => 'Synthetic viewer'],
            'permissions' => ['employees.read', 'employees.write', 'employee.private', 'users.read', 'tasks.read', 'audit.read'],
            'locale' => 'en',
            'title' => 'Employee details',
            'pageSubtitle' => 'Synthetic employee profile',
            'breadcrumbs' => [],
            'employee' => [
                'id' => '00000000000000000000000001',
                'name' => 'Synthetic employee',
                'code' => 'EMP-0001',
                'role' => 'Field engineer',
                'branch_id' => '00000000000000000000000011',
                'branch_name' => 'South Branch',
                'department_id' => '00000000000000000000000012',
                'department_name' => 'Operations',
                'manager_id' => '00000000000000000000000013',
                'manager_name' => 'Synthetic supervisor',
                'hire_date' => '2025-03-12',
                'status' => 'active',
                'version' => 3,
                'user_id' => 'ACCOUNT-ID-SHOULD-NOT-RENDER',
                'account_visible' => true,
                'can_assign_account' => true,
                'user_name' => 'synthetic.account',
                'phone' => '+1-555-0100',
                'email' => 'employee@example.test',
                'private_notes' => 'Synthetic private note',
            ],
            'reports' => new LengthAwarePaginator([
                $report('00000000000000000000000021', 'First report'),
                $report('00000000000000000000000022', 'Second report'),
            ], 2, 10, 1, ['path' => '/employees/00000000000000000000000001']),
            'profileTab' => 'overview',
            'evidence' => [['note' => 'EVIDENCE-OVERVIEW-SENTINEL']],
            'evidenceLinks' => [],
            'auditEntries' => [['action' => 'AUDIT-OVERVIEW-SENTINEL']],
            'auditLinks' => [],
            'tasks' => [],
            'errors' => $this->errors(),
        ];

        if (array_key_exists('employee', $overrides)) {
            $data['employee'] = array_replace($data['employee'], $overrides['employee']);
            $overrides['employee'] = $data['employee'];
        }

        return view('employees.show', array_replace($data, $overrides))->render();
    }

    /** @param array<string, mixed> $overrides */
    private function renderForm(array $overrides = []): string
    {
        $data = [
            'actor' => ['id' => '00000000000000000000000090', 'name' => 'Synthetic editor'],
            'permissions' => ['employees.read', 'employees.write', 'employees.manage_all', 'employee.private', 'users.read'],
            'locale' => 'en',
            'title' => 'Edit employee',
            'pageSubtitle' => 'Update the employee details you are authorized to manage.',
            'breadcrumbs' => [],
            'employee' => [
                'id' => '00000000000000000000000001',
                'name' => 'Synthetic employee',
                'code' => 'EMP-0001',
                'role' => 'Field engineer',
                'branch_id' => 'BRANCH-ORIGINAL',
                'department_id' => 'DEPARTMENT-ORIGINAL',
                'manager_id' => 'MANAGER-ORIGINAL',
                'hire_date' => '2025-03-12',
                'status' => 'active',
                'version' => 3,
                'user_id' => 'USER-1',
                'can_assign_account' => true,
                'phone' => '+1-555-0100',
                'email' => 'employee@example.test',
                'private_notes' => 'Synthetic private note',
            ],
            'workerCreate' => false,
            'workerDefaults' => [],
            'branches' => [
                ['id' => 'BRANCH-ORIGINAL', 'name' => 'Original branch'],
                ['id' => 'BRANCH-OLD', 'name' => 'Old input branch'],
            ],
            'departments' => [
                ['id' => 'DEPARTMENT-ORIGINAL', 'name' => 'Original department'],
                ['id' => 'DEPARTMENT-OLD', 'name' => 'Old input department'],
            ],
            'managers' => [
                ['id' => 'MANAGER-ORIGINAL', 'name' => 'Original supervisor'],
            ],
            'users' => [
                ['id' => 'USER-1', 'name' => 'Original linked account'],
                ['id' => 'USER-2', 'name' => 'Old input linked account'],
            ],
            'optionSources' => [],
            'errors' => $this->errors(),
        ];

        if (array_key_exists('employee', $overrides)) {
            $data['employee'] = $overrides['employee'] === []
                ? []
                : array_replace($data['employee'], $overrides['employee']);
            $overrides['employee'] = $data['employee'];
        }
        if (isset($overrides['workerDefaults'])) {
            $data['workerDefaults'] = array_replace($data['workerDefaults'], $overrides['workerDefaults']);
        }

        return view('employees.form', array_replace($data, $overrides))->render();
    }

    /** @param array<string, string> $messages */
    private function errors(array $messages = []): ViewErrorBag
    {
        $errors = new ViewErrorBag;
        if ($messages !== []) {
            $errors->put('default', new MessageBag($messages));
        }

        return $errors;
    }

    /** @param array<string, mixed> $input */
    private function setOldInput(array $input): void
    {
        $session = app('session')->driver();
        $session->start();
        $session->put('_token', 'synthetic-csrf-token');
        $session->put('_old_input', $input);
        app('request')->setLaravelSession($session);
    }
}

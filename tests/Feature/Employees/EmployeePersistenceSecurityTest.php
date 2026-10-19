<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Modules\Employees\Application\EMP01\EmployeeDirectory;
use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use App\Modules\Tasks\Application\TASK01\TaskCycle;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;
use Throwable;

final class EmployeePersistenceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_and_revoked_sessions_cannot_read_employee_data(): void
    {
        $org = $this->organization();
        $userId = $this->user($org, 'Reader', 'branch_a', 'department_a', ['employees.read']);
        $unprivileged = $this->user($org, 'Unprivileged', 'branch_a', 'department_a');
        $employeeId = $this->employee($org, 'EMP-READ', 'Private Worker');

        $this->assertRejected(
            fn () => $this->directory()->find($this->requestFor($unprivileged), $employeeId),
            AccessDeniedHttpException::class,
        );

        $request = Request::create('/employees');
        $this->assertRejected(
            fn () => $this->directory()->find($request, $employeeId),
            AuthenticationException::class,
        );

        $inactiveUser = $this->user($org, 'Inactive Reader', 'branch_a', 'department_a', ['employees.read']);
        $inactiveRequest = $this->requestFor($inactiveUser);
        DB::table('access_users')->where('id', $inactiveUser)->update(['active' => false]);
        $this->assertRejected(
            fn () => $this->directory()->find($inactiveRequest, $employeeId),
            AuthenticationException::class,
        );

        $missingUser = $this->user($org, 'Deleted Reader', 'branch_a', 'department_a', ['employees.read']);
        $missingRequest = $this->requestFor($missingUser);
        DB::table('access_user_roles')->where('user_id', $missingUser)->delete();
        DB::table('access_user_permissions')->where('user_id', $missingUser)->delete();
        DB::table('access_users')->where('id', $missingUser)->delete();
        $this->assertRejected(
            fn () => $this->directory()->find($missingRequest, $employeeId),
            AuthenticationException::class,
        );

        $request = $this->requestFor($userId);
        DB::table('access_users')->where('id', $userId)->update(['session_version' => 1]);

        $this->assertRejected(
            fn () => $this->directory()->find($request, $employeeId),
            AuthenticationException::class,
        );
    }

    public function test_supervisor_listing_is_branch_department_and_assignment_scoped_and_masks_private_fields(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'Supervisor', 'branch_a', 'department_a', ['employees.read']);
        $supervisorProfile = $this->employee($org, 'EMP-SUP', 'Supervisor', userId: $supervisor);
        $assigned = $this->employee($org, 'EMP-ASSIGNED', 'Assigned Worker', managerId: $supervisorProfile);
        $unassigned = $this->employee($org, 'EMP-OTHER', 'Other Worker');
        $otherBranch = $this->employee($org, 'EMP-BRANCH', 'Other Branch Worker', 'branch_b', 'department_b');
        $otherCompany = $this->employee($org, 'EMP-COMPANY', 'Other Company Worker', 'branch_c', 'department_c', companyId: $org['company_b']);

        $result = $this->directory()->listing($this->requestFor($supervisor));
        $json = $this->encoded($result);

        self::assertStringContainsString($supervisorProfile, $json);
        self::assertStringContainsString($assigned, $json);
        self::assertStringNotContainsString($unassigned, $json);
        self::assertStringNotContainsString($otherBranch, $json);
        self::assertStringNotContainsString($otherCompany, $json);
        self::assertStringNotContainsString('SECRET-PHONE-', $json);
        self::assertStringNotContainsString('SECRET-EMAIL-', $json);
        self::assertStringNotContainsString('SECRET-NOTE-', $json);

        $details = $this->encoded($this->directory()->find($this->requestFor($supervisor), $assigned));
        self::assertStringNotContainsString('SECRET-PHONE-EMP-ASSIGNED', $details);
        self::assertStringNotContainsString('SECRET-EMAIL-EMP-ASSIGNED', $details);
        self::assertStringNotContainsString('SECRET-NOTE-EMP-ASSIGNED', $details);
    }

    public function test_manage_all_is_company_wide_but_does_not_grant_private_field_access(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Employee Admin', 'branch_a', 'department_a', ['employees.read', 'employees.manage_all']);
        $sameCompanyOtherBranch = $this->employee($org, 'EMP-WIDE', 'Other Branch Worker', 'branch_b', 'department_b');
        $otherCompany = $this->employee($org, 'EMP-FOREIGN', 'Foreign Company Worker', 'branch_c', 'department_c', companyId: $org['company_b']);

        $result = $this->directory()->listing($this->requestFor($admin));
        $json = $this->encoded($result);

        self::assertStringContainsString($sameCompanyOtherBranch, $json);
        self::assertStringNotContainsString($otherCompany, $json);
        self::assertStringNotContainsString('SECRET-PHONE-EMP-WIDE', $json);
        self::assertStringNotContainsString('SECRET-EMAIL-EMP-WIDE', $json);
        self::assertStringNotContainsString('SECRET-NOTE-EMP-WIDE', $json);
    }

    public function test_self_link_and_supervisor_assignment_cannot_be_forged_in_request_input(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'Supervisor', 'branch_a', 'department_a', ['employees.read']);
        $supervisorProfile = $this->employee($org, 'EMP-MGR', 'Supervisor', userId: $supervisor);
        $worker = $this->employee($org, 'EMP-WORKER', 'Assigned Worker', managerId: $supervisorProfile);
        $attacker = $this->user($org, 'Unassigned Reader', 'branch_a', 'department_a', ['employees.read']);
        $outOfScopeSelfLink = $this->employee(
            $org,
            'EMP-SELF-OUTSIDE',
            'Out of Scope Self Link',
            'branch_b',
            'department_b',
            userId: $attacker,
        );

        $spoofedRequest = $this->requestFor($attacker, [
            'actor_id' => $supervisor,
            'manager_id' => $supervisorProfile,
            'employee_id' => $worker,
        ]);

        $this->assertRejected(
            fn () => $this->directory()->find($spoofedRequest, $worker),
            NotFoundHttpException::class,
        );
        $this->assertRejected(
            fn () => $this->directory()->find($this->requestFor($attacker), $outOfScopeSelfLink),
            NotFoundHttpException::class,
        );
    }

    public function test_private_permission_is_reloaded_for_each_read_and_controls_phone_email_and_notes(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Private Reader', 'branch_a', 'department_a', ['employees.read', 'employees.manage_all']);
        $employeeId = $this->employee($org, 'EMP-PRIVATE', 'Private Worker');
        $request = $this->requestFor($admin);

        $masked = $this->encoded($this->directory()->find($request, $employeeId));
        self::assertStringNotContainsString('SECRET-PHONE-EMP-PRIVATE', $masked);
        self::assertStringNotContainsString('SECRET-EMAIL-EMP-PRIVATE', $masked);
        self::assertStringNotContainsString('SECRET-NOTE-EMP-PRIVATE', $masked);

        $this->grant($admin, ['employee.private']);
        $revealed = $this->directory()->find($request, $employeeId);
        $revealedJson = $this->encoded($revealed);
        self::assertStringContainsString('SECRET-PHONE-EMP-PRIVATE', $revealedJson);
        self::assertStringContainsString('SECRET-EMAIL-EMP-PRIVATE', $revealedJson);
        self::assertStringContainsString('SECRET-NOTE-EMP-PRIVATE', $revealedJson);

        DB::table('access_role_permissions')->where('role_id', $this->roleId($admin))->where('permission', 'employee.private')->delete();
        $maskedAgain = $this->encoded($this->directory()->find($request, $employeeId));
        self::assertStringNotContainsString('SECRET-PHONE-EMP-PRIVATE', $maskedAgain);
        self::assertStringNotContainsString('SECRET-EMAIL-EMP-PRIVATE', $maskedAgain);
        self::assertStringNotContainsString('SECRET-NOTE-EMP-PRIVATE', $maskedAgain);
    }

    public function test_supervisor_can_update_authorized_details_but_cannot_create_or_change_worker_authority(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'Supervisor', 'branch_a', 'department_a', ['employees.read', 'employees.write', 'employee.private']);
        $otherSupervisor = $this->employee($org, 'EMP-MGR-2', 'Other Supervisor');
        $supervisorProfile = $this->employee($org, 'EMP-MGR', 'Supervisor', userId: $supervisor);
        $worker = $this->employee($org, 'EMP-REPORT', 'Worker', managerId: $supervisorProfile);
        $request = $this->requestFor($supervisor);

        $this->directory()->save($request, [
            'name' => 'Updated Worker',
            'role' => 'Senior Technician',
            'phone' => '+201000000000',
            'email' => 'updated-worker@example.test',
            'version' => 0,
        ], $worker);

        $this->assertProfile($worker, [
            'name' => 'Updated Worker',
            'role' => 'Senior Technician',
            'phone' => '+201000000000',
            'email' => 'updated-worker@example.test',
            'version' => 1,
        ]);

        $this->assertRejected(
            fn () => $this->directory()->save($request, $this->newProfileData($org, 'EMP-NO-CREATE'), null),
            AccessDeniedHttpException::class,
        );

        foreach ([
            'branch_id' => $org['branch_b'],
            'department_id' => $org['department_b'],
            'manager_id' => $otherSupervisor,
            'user_id' => $this->user($org, 'Injected Account', 'branch_a', 'department_a'),
            'status' => 'terminated',
        ] as $field => $value) {
            $this->assertRejected(
                fn () => $this->directory()->save($request, [$field => $value, 'version' => 1], $worker),
                ValidationException::class,
                AccessDeniedHttpException::class,
            );
        }

        $this->assertProfile($worker, [
            'name' => 'Updated Worker',
            'branch_id' => $org['branch_a'],
            'department_id' => $org['department_a'],
            'manager_id' => $supervisorProfile,
            'user_id' => null,
            'status' => 'active',
            'version' => 1,
        ]);
    }

    public function test_only_manage_all_writers_can_create_or_admin_update_and_audit_uses_the_session_actor(): void
    {
        $org = $this->organization();
        $reader = $this->user($org, 'Read Only', 'branch_a', 'department_a', ['employees.read']);
        $writer = $this->user($org, 'Limited Writer', 'branch_a', 'department_a', ['employees.read', 'employees.write']);
        $admin = $this->user($org, 'Employee Admin', 'branch_a', 'department_a', ['employees.read', 'employees.write', 'employees.manage_all']);
        $target = $this->employee($org, 'EMP-ADMIN-TARGET', 'Target');

        $this->assertRejected(
            fn () => $this->directory()->save($this->requestFor($writer), $this->newProfileData($org, 'EMP-LIMITED'), null),
            AccessDeniedHttpException::class,
        );
        $this->assertRejected(
            fn () => $this->directory()->save($this->requestFor($writer), ['name' => 'Unauthorized', 'version' => 0], $target),
            AccessDeniedHttpException::class,
        );
        $this->assertRejected(
            fn () => $this->directory()->save($this->requestFor($reader), ['name' => 'Missing Write Grant', 'version' => 0], $target),
            AccessDeniedHttpException::class,
        );

        $this->directory()->save($this->requestFor($admin), $this->newProfileData($org, 'EMP-ADMIN-CREATED'), null);
        $createdId = (string) DB::table('employee_profiles')->where('code', 'EMP-ADMIN-CREATED')->value('id');
        self::assertNotSame('', $createdId);
        $this->assertProfile($createdId, ['name' => 'EMP-ADMIN-CREATED Worker']);

        $this->directory()->save($this->requestFor($admin), ['name' => 'Admin Updated', 'version' => 0], $target);
        $this->assertProfile($target, ['name' => 'Admin Updated', 'version' => 1]);

        self::assertSame($admin, (string) DB::table('employee_audit')->where('employee_id', $target)->latest('created_at')->value('actor_id'));

        DB::table('access_role_permissions')->where('role_id', $this->roleId($admin))->where('permission', 'employees.write')->delete();
        $this->assertRejected(
            fn () => $this->directory()->save($this->requestFor($admin), ['name' => 'After Revocation', 'version' => 1], $target),
            AccessDeniedHttpException::class,
        );
        $this->assertProfile($target, ['name' => 'Admin Updated', 'version' => 1]);
    }

    public function test_account_links_must_be_same_scope_and_unique(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Employee Admin', 'branch_a', 'department_a', ['employees.write', 'employees.manage_all']);
        $foreignScopeUser = $this->user($org, 'Foreign Scope Account', 'branch_b', 'department_b');
        $linkedUser = $this->user($org, 'Linked Account', 'branch_a', 'department_a');
        $this->employee($org, 'EMP-LINKED', 'Linked Employee', userId: $linkedUser);

        $foreignLink = $this->newProfileData($org, 'EMP-CROSS-LINK');
        $foreignLink['user_id'] = $foreignScopeUser;
        $this->assertRejected(
            fn () => $this->directory()->save($this->requestFor($admin), $foreignLink, null),
            ValidationException::class,
        );

        $duplicateLink = $this->newProfileData($org, 'EMP-DUPLICATE-LINK');
        $duplicateLink['user_id'] = $linkedUser;
        $this->assertRejected(
            fn () => $this->directory()->save($this->requestFor($admin), $duplicateLink, null),
            ValidationException::class,
        );
    }

    public function test_profiles_cannot_be_created_in_inactive_branches_or_departments(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Employee Admin', 'branch_a', 'department_a', ['employees.write', 'employees.manage_all']);
        $directory = $this->directory();
        $request = $this->requestFor($admin);
        $beforeProfiles = DB::table('employee_profiles')->where('company_id', $org['company_a'])->count();
        $beforeAudit = DB::table('employee_audit')->where('company_id', $org['company_a'])->count();

        DB::table('access_branches')->where('id', $org['branch_b'])->update(['active' => false]);
        $inactiveBranchData = $this->newProfileData($org, 'EMP-INACTIVE-BRANCH');
        $inactiveBranchData['branch_id'] = $org['branch_b'];
        $inactiveBranchData['department_id'] = $org['department_b'];
        $this->assertRejected(
            fn () => $directory->save($request, $inactiveBranchData),
            ValidationException::class,
        );
        self::assertSame($beforeProfiles, DB::table('employee_profiles')->where('company_id', $org['company_a'])->count());
        self::assertSame($beforeAudit, DB::table('employee_audit')->where('company_id', $org['company_a'])->count());

        DB::table('access_branches')->where('id', $org['branch_b'])->update(['active' => true]);
        DB::table('access_departments')->where('id', $org['department_b'])->update(['active' => false]);
        $inactiveDepartmentData = $this->newProfileData($org, 'EMP-INACTIVE-DEPARTMENT');
        $inactiveDepartmentData['branch_id'] = $org['branch_b'];
        $inactiveDepartmentData['department_id'] = $org['department_b'];
        $this->assertRejected(
            fn () => $directory->save($request, $inactiveDepartmentData),
            ValidationException::class,
        );
        self::assertSame($beforeProfiles, DB::table('employee_profiles')->where('company_id', $org['company_a'])->count());
        self::assertSame($beforeAudit, DB::table('employee_audit')->where('company_id', $org['company_a'])->count());
    }

    public function test_new_links_to_inactive_accounts_are_rejected_without_profile_or_audit_rows(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Employee Admin', 'branch_a', 'department_a', ['employees.write', 'employees.manage_all']);
        $inactiveAccount = $this->user($org, 'Inactive Account', 'branch_a', 'department_a');
        DB::table('access_users')->where('id', $inactiveAccount)->update(['active' => false]);
        $data = $this->newProfileData($org, 'EMP-INACTIVE-ACCOUNT');
        $data['user_id'] = $inactiveAccount;
        $beforeProfiles = DB::table('employee_profiles')->where('company_id', $org['company_a'])->count();
        $beforeAudit = DB::table('employee_audit')->where('company_id', $org['company_a'])->count();

        $this->assertRejected(
            fn () => $this->directory()->save($this->requestFor($admin), $data),
            ValidationException::class,
        );

        self::assertSame($beforeProfiles, DB::table('employee_profiles')->where('company_id', $org['company_a'])->count());
        self::assertSame($beforeAudit, DB::table('employee_audit')->where('company_id', $org['company_a'])->count());
    }

    public function test_existing_inactive_account_link_can_be_retained_during_profile_edit(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Employee Admin', 'branch_a', 'department_a', ['employees.write', 'employees.manage_all']);
        $linkedAccount = $this->user($org, 'Linked Employee Account', 'branch_a', 'department_a');
        $employeeId = $this->employee($org, 'EMP-INACTIVE-LINK-RETAINED', 'Before Edit', userId: $linkedAccount);
        DB::table('access_users')->where('id', $linkedAccount)->update(['active' => false]);

        $this->directory()->save($this->requestFor($admin), ['name' => 'After Edit', 'version' => 0], $employeeId);

        $this->assertProfile($employeeId, ['name' => 'After Edit', 'user_id' => $linkedAccount, 'version' => 1]);
    }

    public function test_manager_links_reject_self_cycles_and_cross_scope_assignments(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Employee Admin', 'branch_a', 'department_a', ['employees.write', 'employees.manage_all']);
        $manager = $this->employee($org, 'EMP-MANAGER', 'Manager');
        $worker = $this->employee($org, 'EMP-CYCLE-WORKER', 'Worker', managerId: $manager);
        $foreignManager = $this->employee($org, 'EMP-FOREIGN-MANAGER', 'Foreign Manager', 'branch_b', 'department_b');
        $request = $this->requestFor($admin);

        foreach ([
            [$worker, $worker],
            [$manager, $worker],
            [$worker, $foreignManager],
        ] as [$employeeId, $managerId]) {
            $this->assertRejected(
                fn () => $this->directory()->save($request, ['manager_id' => $managerId, 'version' => 0], $employeeId),
                ValidationException::class,
            );
        }

        $this->assertProfile($worker, ['manager_id' => $manager, 'version' => 0]);
        $this->assertProfile($manager, ['manager_id' => null, 'version' => 0]);
    }

    public function test_unknown_fields_and_stale_versions_are_rejected_without_mutation(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Employee Admin', 'branch_a', 'department_a', ['employees.write', 'employees.manage_all']);
        $employeeId = $this->employee($org, 'EMP-VALIDATION', 'Original Name');
        $request = $this->requestFor($admin);

        foreach (['unexpected' => 'value', 'actor_id' => $this->user($org, 'Spoofed Actor', 'branch_a', 'department_a')] as $field => $value) {
            $this->assertRejected(
                fn () => $this->directory()->save($request, [$field => $value, 'version' => 0], $employeeId),
                ValidationException::class,
            );
        }

        $this->assertRejected(
            fn () => $this->directory()->save($request, ['name' => 'Stale Update', 'version' => 42], $employeeId),
            ConflictHttpException::class,
        );

        $this->assertProfile($employeeId, ['name' => 'Original Name', 'version' => 0]);
    }

    public function test_private_notes_require_private_grant_and_audit_never_stores_sensitive_text(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Employee Admin', 'branch_a', 'department_a', ['employees.write', 'employees.manage_all']);
        $employeeId = $this->employee($org, 'EMP-NOTES', 'Notes Worker');
        $request = $this->requestFor($admin);
        $privateNote = 'CONFIDENTIAL-HR-NOTE-DO-NOT-AUDIT';

        $this->assertRejected(
            fn () => $this->directory()->save($request, ['private_notes' => $privateNote, 'version' => 0], $employeeId),
            AccessDeniedHttpException::class,
            ValidationException::class,
        );

        $this->grant($admin, ['employee.private']);
        $this->directory()->save($request, [
            'private_notes' => $privateNote,
            'phone' => '+201000000001',
            'email' => 'private-change@example.test',
            'version' => 0,
        ], $employeeId);

        $this->assertProfile($employeeId, ['private_notes' => $privateNote, 'version' => 1]);
        $audit = (string) DB::table('employee_audit')->where('employee_id', $employeeId)->latest('created_at')->value('metadata');
        self::assertStringNotContainsString($privateNote, $audit);
        self::assertStringNotContainsString('+201000000001', $audit);
        self::assertStringNotContainsString('private-change@example.test', $audit);
    }

    public function test_evidence_uses_the_session_actor_and_audits_no_free_text(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'Supervisor', 'branch_a', 'department_a', [
            'employees.read', 'employees.evidence.write', 'tasks.read', 'tasks.write',
        ]);
        $supervisorProfile = $this->employee($org, 'EMP-EVIDENCE-SUP', 'Supervisor', userId: $supervisor);
        $worker = $this->employee($org, 'EMP-EVIDENCE-WORKER', 'Worker', managerId: $supervisorProfile);
        $request = $this->requestFor($supervisor);
        $jobId = $this->createTask($request, 'Assigned field work');
        $note = 'CONFIDENTIAL-FIELD-NOTE-DO-NOT-AUDIT';

        $this->directory()->recordEvidence($request, $worker, ['job_id' => $jobId, 'note' => $note]);

        $evidence = DB::table('employee_evidence')->where('employee_id', $worker)->first();
        self::assertNotNull($evidence);
        self::assertSame($org['company_a'], (string) $evidence->company_id);
        self::assertSame($jobId, (string) $evidence->job_id);
        self::assertSame($supervisor, (string) $evidence->actor_id);
        self::assertSame($note, (string) $evidence->note);

        $audit = DB::table('employee_audit')->where('employee_id', $worker)->latest('created_at')->first();
        self::assertNotNull($audit);
        self::assertSame($supervisor, (string) $audit->actor_id);
        self::assertStringNotContainsString($note, (string) $audit->metadata);

        $this->assertRejected(
            fn () => $this->directory()->recordEvidence($request, $worker, [
                'job_id' => $jobId,
                'note' => 'Spoofed actor must be rejected',
                'actor_id' => $this->user($org, 'Spoofed Evidence Actor', 'branch_a', 'department_a'),
            ]),
            ValidationException::class,
        );

        DB::table('access_role_permissions')->where('role_id', $this->roleId($supervisor))->where('permission', 'employees.evidence.write')->delete();
        $this->assertRejected(
            fn () => $this->directory()->recordEvidence($request, $worker, ['job_id' => $jobId, 'note' => 'After permission revocation']),
            AccessDeniedHttpException::class,
        );
        self::assertSame(1, DB::table('employee_evidence')->where('employee_id', $worker)->count());
    }

    public function test_evidence_rejects_missing_other_owner_and_cross_scope_task_links(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'Supervisor', 'branch_a', 'department_a', [
            'employees.read', 'employees.evidence.write', 'tasks.read', 'tasks.write',
        ]);
        $supervisorProfile = $this->employee($org, 'EMP-LINK-SUP', 'Supervisor', userId: $supervisor);
        $worker = $this->employee($org, 'EMP-LINK-WORKER', 'Worker', managerId: $supervisorProfile);
        $otherOwner = $this->user($org, 'Other Task Owner', 'branch_a', 'department_a', ['tasks.read', 'tasks.write']);
        $otherOwnerTask = $this->createTask($this->requestFor($otherOwner), 'Another worker task');
        $crossScopeTask = $this->insertTask($org['company_a'], $org['department_b'], $supervisor, 'Different department task');
        $request = $this->requestFor($supervisor);
        $beforeEvidence = DB::table('employee_evidence')->count();
        $beforeAudit = DB::table('employee_audit')->count();

        foreach ([
            (string) Str::ulid(),
            $otherOwnerTask,
            $crossScopeTask,
        ] as $jobId) {
            $this->assertRejected(
                fn () => $this->directory()->recordEvidence($request, $worker, [
                    'job_id' => $jobId,
                    'note' => 'Must not be recorded',
                ]),
                NotFoundHttpException::class,
            );
        }

        self::assertSame($beforeEvidence, DB::table('employee_evidence')->count());
        self::assertSame($beforeAudit, DB::table('employee_audit')->count());
    }

    public function test_terminated_workers_cannot_receive_evidence_or_audit_rows(): void
    {
        $org = $this->organization();
        $supervisor = $this->user($org, 'Supervisor', 'branch_a', 'department_a', [
            'employees.read', 'employees.evidence.write', 'tasks.read', 'tasks.write',
        ]);
        $supervisorProfile = $this->employee($org, 'EMP-TERMINATED-SUP', 'Supervisor', userId: $supervisor);
        $worker = $this->employee($org, 'EMP-TERMINATED-WORKER', 'Terminated Worker', managerId: $supervisorProfile);
        $request = $this->requestFor($supervisor);
        $jobId = $this->createTask($request, 'Work for active worker');
        DB::table('employee_profiles')->where('id', $worker)->update(['status' => 'terminated']);
        $beforeEvidence = DB::table('employee_evidence')->count();
        $beforeAudit = DB::table('employee_audit')->count();

        $this->assertRejected(
            fn () => $this->directory()->recordEvidence($request, $worker, [
                'job_id' => $jobId,
                'note' => 'Must not be accepted after termination',
            ]),
            ConflictHttpException::class,
        );

        self::assertSame($beforeEvidence, DB::table('employee_evidence')->count());
        self::assertSame($beforeAudit, DB::table('employee_audit')->count());
    }

    /** @return array<string, string> */
    private function organization(): array
    {
        $companyA = $this->id();
        $companyB = $this->id();
        DB::table('access_companies')->insert([
            ['id' => $companyA, 'name' => 'Employee Test Company A', 'version' => 0],
            ['id' => $companyB, 'name' => 'Employee Test Company B', 'version' => 0],
        ]);

        $ids = [
            'branch_a' => $this->id(),
            'branch_b' => $this->id(),
            'branch_c' => $this->id(),
            'department_a' => $this->id(),
            'department_b' => $this->id(),
            'department_c' => $this->id(),
        ];
        foreach ([
            [$ids['branch_a'], $companyA, 'BR-A'],
            [$ids['branch_b'], $companyA, 'BR-B'],
            [$ids['branch_c'], $companyB, 'BR-C'],
        ] as [$branchId, $companyId, $code]) {
            DB::table('access_branches')->insert([
                'id' => $branchId,
                'company_id' => $companyId,
                'name' => $code,
                'code' => $code,
                'active' => true,
                'version' => 0,
            ]);
        }
        foreach ([
            [$ids['department_a'], $companyA, $ids['branch_a'], 'DEPT-A'],
            [$ids['department_b'], $companyA, $ids['branch_b'], 'DEPT-B'],
            [$ids['department_c'], $companyB, $ids['branch_c'], 'DEPT-C'],
        ] as [$departmentId, $companyId, $branchId, $code]) {
            DB::table('access_departments')->insert([
                'id' => $departmentId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => $code,
                'code' => $code,
                'active' => true,
                'version' => 0,
            ]);
        }

        return ['company_a' => $companyA, 'company_b' => $companyB] + $ids;
    }

    /** @param list<string> $permissions */
    private function user(array $org, string $name, string $branch, string $department, array $permissions = []): string
    {
        $id = $this->id();
        $companyId = $branch === 'branch_c' ? $org['company_b'] : $org['company_a'];
        DB::table('access_users')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'branch_id' => $org[$branch],
            'department_id' => $org[$department],
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.$id.'@example.test',
            'password' => Hash::make('employee-test-password'),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($permissions !== []) {
            $roleId = $this->id();
            DB::table('access_roles')->insert([
                'id' => $roleId,
                'company_id' => $companyId,
                'name' => 'Employee Test Role '.$roleId,
                'version' => 0,
            ]);
            foreach ($permissions as $permission) {
                DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
            }
            DB::table('access_user_roles')->insert(['user_id' => $id, 'role_id' => $roleId]);
        }

        return $id;
    }

    private function roleId(string $userId): string
    {
        return (string) DB::table('access_user_roles')->where('user_id', $userId)->value('role_id');
    }

    /** @param list<string> $permissions */
    private function grant(string $userId, array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insertOrIgnore([
                'role_id' => $this->roleId($userId),
                'permission' => $permission,
            ]);
        }
    }

    private function employee(
        array $org,
        string $code,
        string $name,
        string $branch = 'branch_a',
        string $department = 'department_a',
        ?string $managerId = null,
        ?string $userId = null,
        ?string $companyId = null,
    ): string {
        $id = $this->id();
        DB::table('employee_profiles')->insert([
            'id' => $id,
            'company_id' => $companyId ?? ($branch === 'branch_c' ? $org['company_b'] : $org['company_a']),
            'branch_id' => $org[$branch],
            'department_id' => $org[$department],
            'manager_id' => $managerId,
            'user_id' => $userId,
            'code' => $code,
            'name' => $name,
            'role' => 'Technician',
            'hire_date' => '2026-01-01',
            'status' => 'active',
            'phone' => 'SECRET-PHONE-'.$code,
            'email' => 'SECRET-EMAIL-'.$code.'@example.test',
            'private_notes' => 'SECRET-NOTE-'.$code,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @return array<string, mixed> */
    private function newProfileData(array $org, string $code): array
    {
        return [
            'code' => $code,
            'name' => $code.' Worker',
            'role' => 'Technician',
            'branch_id' => $org['branch_a'],
            'department_id' => $org['department_a'],
            'manager_id' => null,
            'user_id' => null,
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ];
    }

    private function requestFor(string $userId, array $input = []): Request
    {
        $request = Request::create('/employees', 'POST', $input);
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->replace([
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ]);

        return $request;
    }

    private function directory(): EmployeeDirectory
    {
        return app(EmployeeDirectory::class);
    }

    private function createTask(Request $request, string $title): string
    {
        $task = app(TaskCycle::class)->create(
            app(RuntimeIdentity::class)->fromRequest($request),
            ['title' => $title, 'priority' => 'normal'],
            $this->id(),
        );

        return (string) $task['id'];
    }

    private function insertTask(string $companyId, string $departmentId, string $ownerId, string $title): string
    {
        $id = $this->id();
        DB::table('tasks_cycle')->insert([
            'id' => $id,
            'entity_id' => $companyId,
            'department_id' => $departmentId,
            'owner_id' => $ownerId,
            'assignee_id' => $ownerId,
            'title' => $title,
            'priority' => 'normal',
            'due_at' => null,
            'status' => 'pending',
            'version' => 0,
            'create_key_hash' => hash('sha256', $id),
            'create_payload_hash' => hash('sha256', $title),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function assertProfile(string $employeeId, array $expected): void
    {
        $profile = (array) DB::table('employee_profiles')->where('id', $employeeId)->first();
        foreach ($expected as $field => $value) {
            self::assertSame($value, $profile[$field] ?? null, 'Unexpected employee_profiles.'.$field.' value.');
        }
    }

    private function assertRejected(callable $operation, string ...$exceptionTypes): Throwable
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            foreach ($exceptionTypes as $exceptionType) {
                if ($exception instanceof $exceptionType) {
                    self::assertInstanceOf($exceptionType, $exception);

                    return $exception;
                }
            }

            throw $exception;
        }

        throw new AssertionFailedError('Expected the operation to be rejected.');
    }

    private function encoded(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }
}

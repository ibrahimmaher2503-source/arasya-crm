<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Modules\Employees\Application\EMP01\EmployeeDirectory;
use App\Modules\Employees\Domain\EMP01\EmployeeStatus;
use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use App\Modules\Tasks\Application\TASK01\TaskCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;
use Tests\TestCase;

final class EmployeePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_persists_and_reads_back_versioned_employee_changes_with_audit(): void
    {
        self::assertTrue(class_exists(EmployeeDirectory::class), 'The employee persistence service must be available.');
        $world = $this->seedWorld();
        $request = $this->requestFor($world['actor_id']);
        $directory = app(EmployeeDirectory::class);
        $manager = $directory->save($request, $this->employeeData($world, 'MGR-001', 'Supervisor', null, $world['actor_id']));
        $data = array_replace($this->employeeData($world, 'WRK-001', 'Field Worker', $manager['id'], null), [
            'phone' => '+201000000001',
            'email' => 'private.worker@example.test',
            'private_notes' => 'Synthetic private note before update',
        ]);

        $created = $directory->save($request, $data);
        $listed = $directory->listing($request);
        $reloaded = $directory->find($request, $created['id']);
        $listedProfile = collect($listed)->firstWhere('id', $created['id']);

        self::assertSame($created['id'], $reloaded['id']);
        self::assertNotNull($listedProfile);
        self::assertSame($created['id'], $listedProfile['id']);
        self::assertSame($world['company_id'], $reloaded['company_id']);
        self::assertSame($world['branch_id'], $reloaded['branch_id']);
        self::assertSame($world['department_id'], $reloaded['department_id']);
        self::assertSame($manager['id'], $reloaded['manager_id']);
        self::assertNull($reloaded['user_id']);
        self::assertSame(0, $reloaded['version']);

        $updated = $directory->save($request, array_replace($data, [
            'name' => 'Field Worker Updated',
            'phone' => '+201000000009',
            'email' => 'private.worker.updated@example.test',
            'private_notes' => 'Synthetic private note after update',
            'version' => $reloaded['version'],
        ]), $created['id']);

        self::assertSame($created['id'], $updated['id']);
        self::assertSame(1, $updated['version']);
        self::assertSame('Field Worker Updated', $directory->find($request, $created['id'])['name']);

        $audit = DB::table('employee_audit')->where('employee_id', $created['id'])->get();
        self::assertCount(2, $audit);
        self::assertSame([$world['actor_id']], array_values(array_unique($audit->pluck('actor_id')->all())));

        $createdMetadata = $this->auditMetadata($audit->firstWhere('action', 'employee.created'));
        $updatedMetadata = $this->auditMetadata($audit->firstWhere('action', 'employee.updated'));
        self::assertArrayHasKey('changes', $updatedMetadata);
        self::assertSame(['before' => 'Field Worker', 'after' => 'Field Worker Updated'], $updatedMetadata['changes']['name']);
        self::assertArrayNotHasKey('phone', $updatedMetadata['changes']);
        self::assertArrayNotHasKey('email', $updatedMetadata['changes']);
        self::assertArrayNotHasKey('private_notes', $updatedMetadata['changes']);
        self::assertContains('employees.write', $updatedMetadata['effective_permissions']);
        self::assertSame('web', $updatedMetadata['origin']);
        self::assertSame('accepted', $updatedMetadata['outcome']);
        self::assertSame([], $updatedMetadata['approval_path']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $updatedMetadata['correlation_id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $createdMetadata['correlation_id']);
        self::assertNotSame($createdMetadata['correlation_id'], $updatedMetadata['correlation_id']);

        $canonicalAudit = DB::table('access_audit')
            ->where('subject_id', $created['id'])
            ->where('action', 'employee.updated')
            ->first();
        self::assertNotNull($canonicalAudit);
        $canonicalMetadata = $this->auditMetadata($canonicalAudit);
        self::assertSame([$world['role_id']], $canonicalMetadata['effective_role_ids']);
        self::assertSame($updatedMetadata['correlation_id'], $canonicalMetadata['correlation_id']);
        foreach ([$updatedMetadata, $canonicalMetadata] as $metadata) {
            $serialized = json_encode($metadata, JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString('+201000000001', $serialized);
            self::assertStringNotContainsString('+201000000009', $serialized);
            self::assertStringNotContainsString('private.worker@example.test', $serialized);
            self::assertStringNotContainsString('private.worker.updated@example.test', $serialized);
            self::assertStringNotContainsString('Synthetic private note before update', $serialized);
            self::assertStringNotContainsString('Synthetic updated private note', $serialized);
            self::assertStringNotContainsString('Synthetic private note after update', $serialized);
        }
    }

    public function test_status_transitions_persist_outbox_intents_at_the_new_aggregate_version(): void
    {
        $world = $this->seedWorld();
        $request = $this->requestFor($world['actor_id']);
        $directory = app(EmployeeDirectory::class);
        $manager = $directory->save($request, $this->employeeData($world, 'MGR-006', 'Supervisor', null, $world['actor_id']));
        $employee = $directory->save($request, $this->employeeData($world, 'WRK-006', 'Status Worker', $manager['id'], null));

        $onLeave = $directory->save($request, [
            ...$this->employeeData($world, 'WRK-006', 'Status Worker', $manager['id'], null),
            'status' => EmployeeStatus::OnLeave->value,
            'version' => 0,
        ], $employee['id']);
        self::assertSame(EmployeeStatus::OnLeave->value, $onLeave['status']);
        self::assertSame(1, $onLeave['version']);

        $active = $directory->save($request, [
            ...$this->employeeData($world, 'WRK-006', 'Status Worker', $manager['id'], null),
            'version' => 1,
        ], $employee['id']);
        self::assertSame(EmployeeStatus::Active->value, $active['status']);
        self::assertSame(2, $active['version']);

        $updates = DB::table('employee_audit')
            ->where('employee_id', $employee['id'])
            ->where('action', 'employee.updated')
            ->get();
        self::assertCount(2, $updates);
        $intentsByStatus = [];
        foreach ($updates as $auditRow) {
            $metadata = $this->auditMetadata($auditRow);
            self::assertArrayHasKey('outbox_intent', $metadata);
            $intent = $metadata['outbox_intent'];
            self::assertSame('employee.status_changed', $intent['event_type']);
            self::assertSame($employee['id'], $intent['aggregate_id']);
            self::assertSame($world['actor_id'], $intent['payload']['actor_id']);
            $intentsByStatus[$intent['payload']['to']] = $intent;
        }

        self::assertSame(1, $intentsByStatus[EmployeeStatus::OnLeave->value]['aggregate_version']);
        self::assertSame(2, $intentsByStatus[EmployeeStatus::Active->value]['aggregate_version']);
    }

    public function test_unsupported_terminated_transition_does_not_change_profile_or_audit_history(): void
    {
        $world = $this->seedWorld();
        $request = $this->requestFor($world['actor_id']);
        $directory = app(EmployeeDirectory::class);
        $manager = $directory->save($request, $this->employeeData($world, 'MGR-007', 'Supervisor', null, $world['actor_id']));
        $employee = $directory->save($request, $this->employeeData($world, 'WRK-007', 'Offboarding Worker', $manager['id'], null));
        $onLeave = $directory->save($request, [
            ...$this->employeeData($world, 'WRK-007', 'Offboarding Worker', $manager['id'], null),
            'status' => EmployeeStatus::OnLeave->value,
            'version' => 0,
        ], $employee['id']);
        $employeeAuditCount = DB::table('employee_audit')->where('employee_id', $employee['id'])->count();
        $accessAuditCount = DB::table('access_audit')->where('company_id', $world['company_id'])->count();

        try {
            $directory->save($request, [
                ...$this->employeeData($world, 'WRK-007', 'Offboarding Worker', $manager['id'], null),
                'status' => EmployeeStatus::Terminated->value,
                'version' => $onLeave['version'],
            ], $employee['id']);
            self::fail('Termination must wait for the offboarding workflow.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('status', $exception->errors());
        }

        $persisted = DB::table('employee_profiles')->where('id', $employee['id'])->first();
        self::assertSame(EmployeeStatus::OnLeave->value, $persisted->status);
        self::assertSame(1, (int) $persisted->version);
        self::assertSame($employeeAuditCount, DB::table('employee_audit')->where('employee_id', $employee['id'])->count());
        self::assertSame($accessAuditCount, DB::table('access_audit')->where('company_id', $world['company_id'])->count());
    }

    public function test_supervisor_reads_assigned_worker_while_private_fields_remain_masked(): void
    {
        $world = $this->seedWorld();
        $supervisorId = $this->seedUser($world, ['employees.read'], 'supervisor@example.test');
        $directory = app(EmployeeDirectory::class);
        $manager = $directory->save(
            $this->requestFor($world['actor_id']),
            $this->employeeData($world, 'MGR-002', 'Supervisor', null, $supervisorId),
        );
        $worker = $directory->save(
            $this->requestFor($world['actor_id']),
            array_replace($this->employeeData($world, 'WRK-002', 'Assigned Worker', $manager['id'], null), [
                'phone' => '+201000000002',
                'email' => 'worker@example.test',
                'private_notes' => 'Synthetic confidential note',
            ]),
        );

        $supervisorRequest = $this->requestFor($supervisorId);
        $visible = $directory->find($supervisorRequest, $worker['id']);
        $visibleFromList = collect($directory->listing($supervisorRequest))->firstWhere('id', $worker['id']);

        self::assertSame($manager['id'], $visible['manager_id']);
        self::assertNotNull($visibleFromList);
        self::assertNull($visible['phone'] ?? null);
        self::assertNull($visible['email'] ?? null);
        self::assertNull($visible['private_notes'] ?? null);
    }

    public function test_duplicate_employee_codes_and_account_links_do_not_create_profiles(): void
    {
        $world = $this->seedWorld();
        $request = $this->requestFor($world['actor_id']);
        $directory = app(EmployeeDirectory::class);
        $manager = $directory->save($request, $this->employeeData($world, 'MGR-003', 'Supervisor', null, $world['actor_id']));
        $linkedUserId = $this->seedUser($world, [], 'linked@example.test');
        $directory->save($request, $this->employeeData($world, 'WRK-003', 'Linked Worker', $manager['id'], $linkedUserId));

        try {
            $directory->save($request, $this->employeeData($world, 'WRK-003', 'Duplicate Code', $manager['id'], null));
            self::fail('A duplicate employee code must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('code', $exception->errors());
        }

        try {
            $directory->save($request, $this->employeeData($world, 'WRK-004', 'Duplicate Account', $manager['id'], $linkedUserId));
            self::fail('An access account must link to at most one employee profile.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('user_id', $exception->errors());
        }

        self::assertSame(2, DB::table('employee_profiles')->where('company_id', $world['company_id'])->count());
    }

    public function test_non_login_worker_evidence_uses_a_persisted_authorized_task_and_real_actor(): void
    {
        $world = $this->seedWorld();
        $request = $this->requestFor($world['actor_id']);
        $directory = app(EmployeeDirectory::class);
        $manager = $directory->save($request, $this->employeeData($world, 'MGR-004', 'Supervisor', null, $world['actor_id']));
        $worker = $directory->save($request, $this->employeeData($world, 'WRK-005', 'Non-login Worker', $manager['id'], null));
        $actor = app(RuntimeIdentity::class)->fromRequest($request);
        $job = app(TaskCycle::class)->create($actor, [
            'title' => 'Synthetic repair assignment',
            'priority' => 'normal',
        ], 'employee-evidence-'.Str::ulid());

        $directory->recordEvidence($request, $worker['id'], [
            'job_id' => $job['id'],
            'note' => 'Synthetic completed repair evidence',
        ]);

        $evidence = DB::table('employee_evidence')->where('employee_id', $worker['id'])->first();
        self::assertNotNull($evidence);
        self::assertSame($world['company_id'], $evidence->company_id);
        self::assertSame($job['id'], $evidence->job_id);
        self::assertSame($world['actor_id'], $evidence->actor_id);
        self::assertSame('Synthetic completed repair evidence', $evidence->note);
        self::assertNull(DB::table('employee_profiles')->where('id', $worker['id'])->value('user_id'));
        self::assertArrayNotHasKey('job_id', $directory->find($request, $worker['id']));
        self::assertDatabaseHas('tasks_cycle', ['id' => $job['id'], 'owner_id' => $world['actor_id']]);
    }

    /** @return array{company_id: string, branch_id: string, department_id: string, actor_id: string, role_id: string} */
    private function seedWorld(): array
    {
        $companyId = (string) Str::ulid();
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        $actorId = (string) Str::ulid();
        $this->seedOrganization($companyId, $branchId, $departmentId);
        $roleId = $this->seedUserRecord($companyId, $branchId, $departmentId, $actorId, 'employee.owner@example.test', [
            'employees.read',
            'employees.write',
            'employees.manage_all',
            'employees.evidence.write',
            'employee.private',
            'tasks.read',
            'tasks.write',
        ]);

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'actor_id' => $actorId,
            'role_id' => $roleId,
        ];
    }

    private function seedOrganization(string $companyId, string $branchId, string $departmentId): void
    {
        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Synthetic Company', 'version' => 0]);
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Synthetic Branch',
            'code' => 'branch-'.strtolower(substr($branchId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Synthetic Department',
            'code' => 'dept-'.strtolower(substr($departmentId, -6)),
            'active' => true,
            'version' => 0,
        ]);
    }

    /** @param list<string> $permissions */
    private function seedUser(array $world, array $permissions, string $email): string
    {
        $id = (string) Str::ulid();
        $this->seedUserRecord($world['company_id'], $world['branch_id'], $world['department_id'], $id, $email, $permissions);

        return $id;
    }

    /** @param list<string> $permissions */
    private function seedUserRecord(
        string $companyId,
        string $branchId,
        string $departmentId,
        string $userId,
        string $email,
        array $permissions,
    ): string {
        $roleId = (string) Str::ulid();
        $now = now()->toDateTimeString();
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $companyId,
            'name' => 'Synthetic role '.$roleId,
            'version' => 0,
        ]);
        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'name' => 'Synthetic Employee '.$userId,
            'email' => $email,
            'password' => Hash::make('unused-synthetic-password'),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);

        return $roleId;
    }

    private function requestFor(string $userId): Request
    {
        $session = app('session')->driver();
        $session->flush();
        $session->put('access_user_id', $userId);
        $session->put('access_session_version', 0);
        $request = Request::create('/employees', 'GET');
        $request->setLaravelSession($session);

        return $request;
    }

    /** @return array<string, mixed> */
    private function employeeData(array $world, string $code, string $name, ?string $managerId, ?string $userId): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'role' => 'Field Technician',
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'manager_id' => $managerId,
            'user_id' => $userId,
            'hire_date' => '2026-01-15',
            'status' => EmployeeStatus::Active->value,
            'phone' => null,
            'email' => null,
            'private_notes' => null,
            'version' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function auditMetadata(stdClass $audit): array
    {
        $metadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($metadata);

        return $metadata;
    }
}

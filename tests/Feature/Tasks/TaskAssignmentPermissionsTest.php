<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class TaskAssignmentPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_granted_actor_can_assign_to_a_department_role_recipient_who_can_read_and_start_the_task(): void
    {
        $org = $this->organization();
        $owner = $this->user($org, 'home', 'Task owner', ['tasks.read', 'tasks.write', 'tasks.assign']);
        $assignee = $this->user($org, 'home', 'Department assignee');
        $this->grantDepartmentRole($org['home'], 'Task recipients', ['tasks.read', 'tasks.write']);
        $peer = $this->user($org, 'home', 'Unassigned peer', ['tasks.read', 'tasks.write']);

        $created = $this->sessionAs($owner)->postJson('/tasks', $this->taskInput($assignee, 'assign-to-recipient'))
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id']]);
        $taskId = (string) $created->json('data.id');

        $this->assertDatabaseHas('tasks_cycle', [
            'id' => $taskId,
            'entity_id' => $org['home']['company_id'],
            'department_id' => $org['home']['department_id'],
            'owner_id' => $owner,
            'assignee_id' => $assignee,
        ]);
        $this->assertDatabaseHas('tasks_cycle_audit', [
            'task_id' => $taskId,
            'actor_id' => $owner,
            'request_key' => 'assign-to-recipient',
        ]);

        $this->sessionAs($assignee)->getJson('/tasks/'.$taskId)
            ->assertOk()
            ->assertJsonPath('data.assignee_id', $assignee);
        $this->sessionAs($assignee)->postJson('/tasks/'.$taskId.'/start', [
            'idempotency_key' => 'recipient-starts-task',
        ])->assertOk();

        $this->sessionAs($peer)->getJson('/tasks/'.$taskId)
            ->assertNotFound()
            ->assertJsonMissingPath('data.id');
        $this->sessionAs($peer)->postJson('/tasks/'.$taskId.'/start', [
            'idempotency_key' => 'peer-cannot-start-task',
        ])->assertNotFound();

        $this->assertDatabaseHas('tasks_cycle_audit', [
            'task_id' => $taskId,
            'actor_id' => $assignee,
            'request_key' => 'recipient-starts-task',
        ]);
        self::assertSame('in_progress', DB::table('tasks_cycle')->where('id', $taskId)->value('status'));
        self::assertSame(2, DB::table('tasks_cycle_audit')->where('task_id', $taskId)->count());
    }

    public function test_self_assignment_works_without_tasks_assign_but_assigning_a_peer_is_forbidden(): void
    {
        $org = $this->organization();
        $owner = $this->user($org, 'home', 'Task owner without assign grant', ['tasks.read', 'tasks.write']);
        $assignee = $this->user($org, 'home', 'Eligible alternate assignee', ['tasks.read', 'tasks.write']);

        $this->sessionAs($owner)->postJson('/tasks', $this->taskInput($owner, 'self-assignment-without-assign'))
            ->assertCreated();
        $this->sessionAs($owner)->postJson('/tasks', $this->taskInput($assignee, 'peer-assignment-without-assign'))
            ->assertForbidden();

        self::assertSame(1, DB::table('tasks_cycle')->count());
        self::assertSame(1, DB::table('tasks_cycle_audit')->count());
    }

    public function test_an_explicit_assign_denial_blocks_peer_assignment_without_blocking_self_assignment(): void
    {
        $org = $this->organization();
        $owner = $this->user($org, 'home', 'Task owner with denied assign', ['tasks.read', 'tasks.write', 'tasks.assign']);
        $assignee = $this->user($org, 'home', 'Eligible alternate assignee', ['tasks.read', 'tasks.write']);
        $this->denyPermission($owner, 'tasks.assign');

        $this->sessionAs($owner)->postJson('/tasks', $this->taskInput($owner, 'self-assignment-with-denial'))
            ->assertCreated();
        $this->sessionAs($owner)->postJson('/tasks', $this->taskInput($assignee, 'denied-peer-assignment'))
            ->assertForbidden();

        self::assertSame(1, DB::table('tasks_cycle')->count());
        self::assertSame(1, DB::table('tasks_cycle_audit')->count());
    }

    public function test_ineligible_assignees_receive_generic_validation_errors_without_task_or_audit_writes(): void
    {
        $org = $this->organization();
        $owner = $this->user($org, 'home', 'Task owner', ['tasks.read', 'tasks.write', 'tasks.assign']);
        $targets = [
            'inactive' => $this->user($org, 'home', 'Inactive assignee', ['tasks.read', 'tasks.write'], active: false),
            'foreign-company' => $this->user($org, 'other_company', 'Foreign assignee', ['tasks.read', 'tasks.write']),
            'other-branch' => $this->user($org, 'other_branch', 'Other branch assignee', ['tasks.read', 'tasks.write']),
            'other-department' => $this->user($org, 'other_department', 'Other department assignee', ['tasks.read', 'tasks.write']),
            'missing-read' => $this->user($org, 'home', 'Assignee without read', ['tasks.write']),
            'missing-write' => $this->user($org, 'home', 'Assignee without write', ['tasks.read']),
            'explicitly-denied' => $this->user($org, 'home', 'Assignee with denied write', ['tasks.read', 'tasks.write']),
        ];
        $this->denyPermission($targets['explicitly-denied'], 'tasks.write');

        foreach ($targets as $case => $targetId) {
            $response = $this->sessionAs($owner)->postJson('/tasks', $this->taskInput($targetId, 'invalid-assignee-'.$case));
            $response->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
            self::assertStringNotContainsString($targetId, $response->getContent());
            self::assertSame(0, DB::table('tasks_cycle')->count(), $case);
            self::assertSame(0, DB::table('tasks_cycle_audit')->count(), $case);
        }
    }

    public function test_create_replay_reuses_the_same_assignee_and_conflicts_when_the_assignee_changes(): void
    {
        $org = $this->organization();
        $owner = $this->user($org, 'home', 'Task owner', ['tasks.read', 'tasks.write', 'tasks.assign']);
        $firstAssignee = $this->user($org, 'home', 'First eligible assignee', ['tasks.read', 'tasks.write']);
        $secondAssignee = $this->user($org, 'home', 'Second eligible assignee', ['tasks.read', 'tasks.write']);
        $payload = $this->taskInput($firstAssignee, 'same-assignment-create-key');

        $first = $this->sessionAs($owner)->postJson('/tasks', $payload)->assertCreated();
        $replay = $this->sessionAs($owner)->postJson('/tasks', $payload)->assertCreated();
        self::assertSame($first->json('data.id'), $replay->json('data.id'));

        $this->sessionAs($owner)->postJson('/tasks', [
            ...$payload,
            'assignee_id' => $secondAssignee,
        ])->assertConflict();

        $this->assertDatabaseHas('tasks_cycle', [
            'id' => $first->json('data.id'),
            'owner_id' => $owner,
            'assignee_id' => $firstAssignee,
        ]);
        self::assertSame(1, DB::table('tasks_cycle')->count());
        self::assertSame(1, DB::table('tasks_cycle_audit')->count());
    }

    public function test_task_detail_keeps_the_current_assignee_selected_outside_the_requested_search_and_page(): void
    {
        $org = $this->organization();
        $owner = $this->user($org, 'home', 'Task owner', ['tasks.read', 'tasks.write', 'tasks.assign']);
        $currentAssignee = $this->user($org, 'home', 'Current selected assignee', ['tasks.read', 'tasks.write']);
        $taskId = $this->seedTask($org['home'], $owner, $currentAssignee);

        $response = $this->sessionAs($owner)->get('/tasks/'.$taskId.'?locale=en&assignee_q=No+matching+name&assignee_page=999');
        $response->assertOk()->assertSeeText('Current selected assignee');

        $this->assertSelectedAssigneeOption($response, $currentAssignee, 'Current selected assignee');
    }

    public function test_malformed_old_assignee_input_does_not_replace_the_current_task_assignee(): void
    {
        $org = $this->organization();
        $owner = $this->user($org, 'home', 'Task owner', ['tasks.read', 'tasks.write', 'tasks.assign']);
        $currentAssignee = $this->user($org, 'home', 'Current selected assignee', ['tasks.read', 'tasks.write']);
        $taskId = $this->seedTask($org['home'], $owner, $currentAssignee);

        $response = $this->sessionAs($owner)
            ->withSession(['_old_input' => ['assignee_id' => ['malformed']]])
            ->get('/tasks/'.$taskId.'?locale=en');
        $response->assertOk();

        $this->assertSelectedAssigneeOption($response, $currentAssignee, 'Current selected assignee');
    }

    public function test_task_create_defaults_the_assignment_to_the_current_actor(): void
    {
        $org = $this->organization();
        $owner = $this->user($org, 'home', 'Task owner', ['tasks.read', 'tasks.write', 'tasks.assign']);

        $response = $this->sessionAs($owner)->get('/tasks/create?locale=en');
        $response->assertOk();

        $this->assertSelectedAssigneeOption($response, $owner, 'Me');
    }

    public function test_owner_can_reassign_an_existing_task_and_access_moves_to_the_new_recipient(): void
    {
        $org = $this->organization();
        $owner = $this->user($org, 'home', 'Task owner', ['tasks.read', 'tasks.write', 'tasks.assign']);
        $oldAssignee = $this->user($org, 'home', 'Old task assignee', ['tasks.read', 'tasks.write']);
        $newAssignee = $this->user($org, 'home', 'New task assignee', ['tasks.read', 'tasks.write']);
        $taskId = $this->seedTask($org['home'], $owner, $oldAssignee);

        $this->sessionAs($owner)->postJson('/tasks/'.$taskId.'/assign', [
            'assignee_id' => $newAssignee,
            'expected_version' => 0,
            'idempotency_key' => 'owner-reassigns-task',
        ])->assertOk();

        $this->assertDatabaseHas('tasks_cycle', [
            'id' => $taskId,
            'owner_id' => $owner,
            'assignee_id' => $newAssignee,
            'version' => 1,
        ]);
        $this->assertDatabaseHas('tasks_cycle_audit', [
            'task_id' => $taskId,
            'actor_id' => $owner,
            'action' => 'assign',
            'request_key' => 'owner-reassigns-task',
        ]);
        $payload = json_decode((string) DB::table('tasks_cycle_audit')->where('task_id', $taskId)->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($newAssignee, $payload['assignee_id'] ?? null);

        $this->sessionAs($newAssignee)->getJson('/tasks/'.$taskId)->assertOk();
        $this->sessionAs($oldAssignee)->getJson('/tasks/'.$taskId)->assertNotFound();
        $this->sessionAs($owner)->getJson('/tasks/'.$taskId)->assertOk();
        self::assertSame(1, DB::table('tasks_cycle_audit')->where('task_id', $taskId)->count());
    }

    public function test_current_assignee_without_tasks_read_can_reassign_while_the_creator_remains_the_owner(): void
    {
        $org = $this->organization();
        $owner = $this->user($org, 'home', 'Task creator', ['tasks.read', 'tasks.write']);
        $currentAssignee = $this->user($org, 'home', 'Current task assignee', ['tasks.write', 'tasks.assign']);
        $nextAssignee = $this->user($org, 'home', 'Next task assignee', ['tasks.read', 'tasks.write']);
        $taskId = $this->seedTask($org['home'], $owner, $currentAssignee);

        $this->sessionAs($currentAssignee)->getJson('/tasks/'.$taskId)->assertForbidden();
        $this->sessionAs($currentAssignee)->postJson('/tasks/'.$taskId.'/assign', [
            'assignee_id' => $nextAssignee,
            'expected_version' => 0,
            'idempotency_key' => 'current-assignee-reassigns-task',
        ])->assertOk();

        $this->assertDatabaseHas('tasks_cycle', [
            'id' => $taskId,
            'owner_id' => $owner,
            'assignee_id' => $nextAssignee,
            'version' => 1,
        ]);
        $this->sessionAs($owner)->getJson('/tasks/'.$taskId)->assertOk();
        $this->sessionAs($nextAssignee)->getJson('/tasks/'.$taskId)->assertOk();
        $this->sessionAs($currentAssignee)->getJson('/tasks/'.$taskId)->assertForbidden();
    }

    public function test_reassignment_requires_assign_permission_and_rejects_unrelated_peers(): void
    {
        $org = $this->organization();
        $ownerWithoutGrant = $this->user($org, 'home', 'Owner without assign', ['tasks.read', 'tasks.write']);
        $currentAssignee = $this->user($org, 'home', 'Current assignee', ['tasks.read', 'tasks.write']);
        $peer = $this->user($org, 'home', 'Unrelated peer with assign', ['tasks.read', 'tasks.write', 'tasks.assign']);
        $target = $this->user($org, 'home', 'Eligible replacement', ['tasks.read', 'tasks.write']);
        $taskId = $this->seedTask($org['home'], $ownerWithoutGrant, $currentAssignee);
        $payload = [
            'assignee_id' => $target,
            'expected_version' => 0,
            'idempotency_key' => 'unauthorized-reassignment',
        ];

        $this->sessionAs($ownerWithoutGrant)->postJson('/tasks/'.$taskId.'/assign', $payload)->assertForbidden();
        $this->sessionAs($peer)->postJson('/tasks/'.$taskId.'/assign', $payload)->assertNotFound();

        $this->assertDatabaseHas('tasks_cycle', [
            'id' => $taskId,
            'owner_id' => $ownerWithoutGrant,
            'assignee_id' => $currentAssignee,
            'version' => 0,
        ]);
        self::assertSame(0, DB::table('tasks_cycle_audit')->where('task_id', $taskId)->count());
    }

    public function test_reassignment_rejects_cross_scope_targets_and_stale_versions_without_writes(): void
    {
        $org = $this->organization();
        $owner = $this->user($org, 'home', 'Task owner', ['tasks.read', 'tasks.write', 'tasks.assign']);
        $currentAssignee = $this->user($org, 'home', 'Current assignee', ['tasks.read', 'tasks.write']);
        $crossScopeTarget = $this->user($org, 'other_branch', 'Cross branch target', ['tasks.read', 'tasks.write']);
        $eligibleTarget = $this->user($org, 'home', 'Eligible target', ['tasks.read', 'tasks.write']);
        $taskId = $this->seedTask($org['home'], $owner, $currentAssignee);

        $crossScope = $this->sessionAs($owner)->postJson('/tasks/'.$taskId.'/assign', [
            'assignee_id' => $crossScopeTarget,
            'expected_version' => 0,
            'idempotency_key' => 'cross-scope-reassignment',
        ]);
        $crossScope->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
        self::assertStringNotContainsString($crossScopeTarget, $crossScope->getContent());

        $this->sessionAs($owner)->postJson('/tasks/'.$taskId.'/assign', [
            'assignee_id' => $eligibleTarget,
            'expected_version' => 1,
            'idempotency_key' => 'stale-reassignment',
        ])->assertConflict();

        $this->assertDatabaseHas('tasks_cycle', [
            'id' => $taskId,
            'assignee_id' => $currentAssignee,
            'version' => 0,
        ]);
        self::assertSame(0, DB::table('tasks_cycle_audit')->where('task_id', $taskId)->count());
    }

    public function test_reassignment_replay_returns_success_once_and_changed_target_conflicts(): void
    {
        $org = $this->organization();
        $owner = $this->user($org, 'home', 'Task owner', ['tasks.read', 'tasks.write', 'tasks.assign']);
        $currentAssignee = $this->user($org, 'home', 'Current assignee', ['tasks.read', 'tasks.write']);
        $firstTarget = $this->user($org, 'home', 'First replacement', ['tasks.read', 'tasks.write']);
        $otherTarget = $this->user($org, 'home', 'Other replacement', ['tasks.read', 'tasks.write']);
        $taskId = $this->seedTask($org['home'], $owner, $currentAssignee);
        $payload = [
            'assignee_id' => $firstTarget,
            'expected_version' => 0,
            'idempotency_key' => 'replay-reassignment-key',
        ];

        $this->sessionAs($owner)->postJson('/tasks/'.$taskId.'/assign', $payload)->assertOk();
        $this->sessionAs($owner)->postJson('/tasks/'.$taskId.'/assign', $payload)->assertOk();
        $this->sessionAs($owner)->postJson('/tasks/'.$taskId.'/assign', [
            ...$payload,
            'assignee_id' => $otherTarget,
        ])->assertConflict();

        $this->assertDatabaseHas('tasks_cycle', [
            'id' => $taskId,
            'owner_id' => $owner,
            'assignee_id' => $firstTarget,
            'version' => 1,
        ]);
        self::assertSame(1, DB::table('tasks_cycle_audit')->where('task_id', $taskId)->count());
    }

    /** @return array<string, array{company_id: string, branch_id: string, department_id: string}> */
    private function organization(): array
    {
        $companies = ['home' => $this->id(), 'other' => $this->id()];
        foreach ($companies as $key => $id) {
            DB::table('access_companies')->insert(['id' => $id, 'name' => 'Task assignment company '.$key, 'version' => 0]);
        }

        $branches = [
            'home' => ['company' => 'home', 'id' => $this->id()],
            'other_branch' => ['company' => 'home', 'id' => $this->id()],
            'other_company' => ['company' => 'other', 'id' => $this->id()],
        ];
        foreach ($branches as $key => $branch) {
            DB::table('access_branches')->insert([
                'id' => $branch['id'],
                'company_id' => $companies[$branch['company']],
                'name' => 'Task assignment branch '.$key,
                'code' => 'task-'.$key,
                'active' => true,
                'version' => 0,
            ]);
        }

        $departmentRows = [
            'home' => ['company' => 'home', 'branch' => 'home'],
            'other_department' => ['company' => 'home', 'branch' => 'home'],
            'other_branch' => ['company' => 'home', 'branch' => 'other_branch'],
            'other_company' => ['company' => 'other', 'branch' => 'other_company'],
        ];
        $scopes = [];
        foreach ($departmentRows as $key => $department) {
            $departmentId = $this->id();
            DB::table('access_departments')->insert([
                'id' => $departmentId,
                'company_id' => $companies[$department['company']],
                'branch_id' => $branches[$department['branch']]['id'],
                'name' => 'Task assignment department '.$key,
                'code' => 'task-'.$key,
                'active' => true,
                'version' => 0,
            ]);
            $scopes[$key] = [
                'company_id' => $companies[$department['company']],
                'branch_id' => $branches[$department['branch']]['id'],
                'department_id' => $departmentId,
            ];
        }

        return $scopes;
    }

    /** @param list<string> $permissions */
    private function user(array $org, string $scope, string $name, array $permissions = [], bool $active = true): string
    {
        $id = $this->id();
        $location = $org[$scope];
        DB::table('access_users')->insert([
            'id' => $id,
            'company_id' => $location['company_id'],
            'branch_id' => $location['branch_id'],
            'department_id' => $location['department_id'],
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.$id.'@example.test',
            'password' => Hash::make('unused-task-assignment-password'),
            'active' => $active,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($permissions !== []) {
            $this->grantUserRole($location['company_id'], $id, $name, $permissions);
        }

        return $id;
    }

    /** @param list<string> $permissions */
    private function grantUserRole(string $companyId, string $userId, string $name, array $permissions): void
    {
        $roleId = $this->createRole($companyId, $name, $permissions);
        DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);
    }

    /** @param array{company_id: string, branch_id: string, department_id: string} $scope
     * @param  list<string>  $permissions
     */
    private function grantDepartmentRole(array $scope, string $name, array $permissions): void
    {
        $roleId = $this->createRole($scope['company_id'], $name, $permissions);
        DB::table('access_department_roles')->insert([
            'company_id' => $scope['company_id'],
            'branch_id' => $scope['branch_id'],
            'department_id' => $scope['department_id'],
            'role_id' => $roleId,
        ]);
    }

    /** @param list<string> $permissions */
    private function createRole(string $companyId, string $name, array $permissions): string
    {
        $roleId = $this->id();
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $companyId,
            'name' => $name.' role '.$roleId,
            'version' => 0,
        ]);
        DB::table('access_role_permissions')->insert(array_map(
            static fn (string $permission): array => ['role_id' => $roleId, 'permission' => $permission],
            $permissions,
        ));

        return $roleId;
    }

    private function denyPermission(string $userId, string $permission): void
    {
        DB::table('access_user_permissions')->insert([
            'user_id' => $userId,
            'permission' => $permission,
            'allowed' => false,
        ]);
    }

    /** @param array{company_id: string, branch_id: string, department_id: string} $scope */
    private function seedTask(array $scope, string $ownerId, string $assigneeId): string
    {
        $taskId = $this->id();
        DB::table('tasks_cycle')->insert([
            'id' => $taskId,
            'entity_id' => $scope['company_id'],
            'department_id' => $scope['department_id'],
            'owner_id' => $ownerId,
            'assignee_id' => $assigneeId,
            'title' => 'Seeded task for reassignment',
            'priority' => 'normal',
            'due_at' => '2026-10-20 12:00:00',
            'status' => 'pending',
            'version' => 0,
            'create_key_hash' => hash('sha256', 'create-'.$taskId),
            'create_payload_hash' => hash('sha256', 'payload-'.$taskId),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $taskId;
    }

    private function sessionAs(string $userId): self
    {
        return $this->withSession([
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ]);
    }

    /** @return array{title: string, priority: string, due_at: string, assignee_id: string, idempotency_key: string} */
    private function taskInput(string $assigneeId, string $requestKey): array
    {
        return [
            'title' => 'Follow up with an assigned customer',
            'priority' => 'normal',
            'due_at' => '2026-10-20T12:00:00Z',
            'assignee_id' => $assigneeId,
            'idempotency_key' => $requestKey,
        ];
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }

    private function assertSelectedAssigneeOption(TestResponse $response, string $assigneeId, string $label): void
    {
        $xpath = $this->htmlXPath($response);
        $options = $xpath->query('//select[@name="assignee_id"]/option[@selected]');
        self::assertNotFalse($options);
        self::assertSame(1, $options->length, 'The assignee selector should have exactly one selected option.');

        $option = $options->item(0);
        self::assertNotNull($option);
        self::assertSame($assigneeId, $option->getAttribute('value'));
        self::assertSame($label, trim($option->textContent));
    }

    private function htmlXPath(TestResponse $response): DOMXPath
    {
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }
}

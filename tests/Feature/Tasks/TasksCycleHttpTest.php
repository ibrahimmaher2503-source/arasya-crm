<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class TasksCycleHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_tasks_can_be_listed_and_loaded_as_json(): void
    {
        $taskId = $this->createTask();

        $this->withSession($this->actorSession())->getJson('/tasks')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'title', 'owner_id', 'assignee_id', 'priority', 'due_at', 'status', 'version',
                ]],
            ]);

        $detail = $this->withSession($this->actorSession())->getJson("/tasks/{$taskId}");
        $detail->assertOk()->assertJsonStructure([
            'data' => [
                'id', 'title', 'owner_id', 'assignee_id', 'priority', 'due_at', 'status', 'version',
                'evidence', 'audit',
            ],
        ]);
        $this->assertIsArray($detail->json('data.evidence'));
        $this->assertIsArray($detail->json('data.audit'));
        $detail->assertJsonPath('data.id', $taskId)->assertJsonCount(1, 'data.audit');
    }

    public function test_creation_requires_a_key_valid_priority_and_self_assignment_and_forces_the_owner(): void
    {
        $this->withSession($this->actorSession())->postJson('/tasks', $this->createPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        $this->withSession($this->actorSession())->postJson('/tasks', $this->createPayload([
            'idempotency_key' => 'invalid-priority',
            'priority' => 'urgent',
        ]))->assertUnprocessable()->assertJsonValidationErrors('priority');

        $this->withSession($this->actorSession())->postJson('/tasks', $this->createPayload([
            'idempotency_key' => 'other-assignee',
            'assignee_id' => 'another-actor',
        ]))->assertUnprocessable()->assertJsonValidationErrors('assignee_id');

        $payload = $this->createPayload([
            'idempotency_key' => 'create-task-1',
            'owner_id' => 'spoofed-owner',
        ]);
        unset($payload['assignee_id']);

        $response = $this->withSession($this->actorSession())->postJson('/tasks', $payload)
            ->assertCreated()->assertJsonStructure(['data' => ['id']]);

        $this->assertDatabaseHas('tasks_cycle', [
            'id' => $response->json('data.id'),
            'entity_id' => 'entity-1',
            'department_id' => 'department-1',
            'owner_id' => 'actor-1',
            'assignee_id' => 'actor-1',
        ]);
        $this->assertSame(1, DB::table('tasks_cycle')->count());
    }

    public function test_create_replay_reuses_the_task_and_changed_payload_conflicts(): void
    {
        $payload = $this->createPayload([
            'title' => 'Original title',
            'idempotency_key' => 'create-replay-key',
        ]);

        $first = $this->withSession($this->actorSession())->postJson('/tasks', $payload)->assertCreated();
        $replay = $this->withSession($this->actorSession())->postJson('/tasks', $payload)->assertCreated();
        $this->assertSame($first->json('data.id'), $replay->json('data.id'));

        $this->withSession($this->actorSession())->postJson('/tasks', [
            ...$payload,
            'title' => 'Changed title',
        ])->assertConflict();

        $this->assertSame(1, DB::table('tasks_cycle')->count());
        $this->assertSame(1, DB::table('tasks_cycle_audit')->count());
    }

    public function test_create_retries_a_transient_mariadb_deadlock_without_duplicate_task_or_audit(): void
    {
        $connection = DB::connection();
        $connection->rollBack(); // Exercise the application transaction as the outer transaction.

        $injected = false;
        DB::listen(static function (QueryExecuted $query) use (&$injected): void {
            $sql = strtolower(ltrim($query->sql));
            if ($injected || ! str_starts_with($sql, 'insert') || ! str_contains($sql, 'tasks_cycle')) {
                return;
            }

            $injected = true;
            $deadlock = new \PDOException('Deadlock found when trying to get lock', 40001);
            $deadlock->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];
            throw new QueryException($query->connectionName, $query->sql, $query->bindings, $deadlock);
        });

        try {
            $this->withSession($this->actorSession())->postJson('/tasks', $this->createPayload([
                'idempotency_key' => 'deadlock-retry-key',
            ]))->assertCreated();

            $this->assertTrue($injected);
            $this->assertSame(1, DB::table('tasks_cycle')->count());
            $this->assertSame(1, DB::table('tasks_cycle_audit')->count());
        } finally {
            DB::table('tasks_cycle_audit')->delete();
            DB::table('tasks_cycle_evidence')->delete();
            DB::table('tasks_cycle')->delete();
            $connection->beginTransaction();
        }
    }

    public function test_task_can_complete_full_lifecycle_with_persisted_audits_and_private_evidence_download(): void
    {
        $taskId = $this->createTask();

        $this->withSession($this->actorSession())->postJson("/tasks/{$taskId}/start", [
            'idempotency_key' => 'start-task-1',
        ])->assertOk();

        $contents = 'synthetic task evidence';
        $this->uploadEvidence($taskId, UploadedFile::fake()->createWithContent('proof.txt', $contents), 'evidence-task-1')
            ->assertOk();

        $this->withSession($this->actorSession())->postJson("/tasks/{$taskId}/complete", [
            'idempotency_key' => 'complete-task-1',
        ])->assertOk();

        $detail = $this->withSession($this->actorSession())->getJson("/tasks/{$taskId}");
        $detail->assertOk()->assertJsonPath('data.status', 'completed')
            ->assertJsonCount(1, 'data.evidence')
            ->assertJsonCount(4, 'data.audit')
            ->assertJsonStructure([
                'data' => [
                    'evidence' => [['id', 'name', 'mime', 'size']],
                ],
            ]);
        $detail->assertJsonPath('data.evidence.0.name', 'proof.txt');

        foreach (['create-task-1', 'start-task-1', 'evidence-task-1', 'complete-task-1'] as $requestKey) {
            $this->assertDatabaseHas('tasks_cycle_audit', [
                'task_id' => $taskId,
                'actor_id' => 'actor-1',
                'request_key' => $requestKey,
            ]);
        }

        $evidenceId = $detail->json('data.evidence.0.id');
        $path = DB::table('tasks_cycle_evidence')->where('id', $evidenceId)->value('path');
        $this->assertIsString($path);
        Storage::disk('local')->assertExists($path);

        $download = $this->withSession($this->actorSession())
            ->get("/tasks/{$taskId}/evidence/{$evidenceId}");
        $download->assertOk()->assertHeader('Content-Disposition');
        $this->assertSame($contents, $download->streamedContent());
    }

    public function test_evidence_download_rejects_a_tampered_path_to_another_private_file(): void
    {
        $taskId = $this->createTask();
        $this->withSession($this->actorSession())->postJson("/tasks/{$taskId}/start", [
            'idempotency_key' => 'start-before-path-tampering',
        ])->assertOk();
        $this->uploadEvidence($taskId, UploadedFile::fake()->createWithContent('proof.txt', 'owned evidence'), 'path-tampering-evidence')
            ->assertOk();

        $evidenceId = DB::table('tasks_cycle_evidence')->where('task_id', $taskId)->value('id');
        $otherPrivatePath = 'task-evidence/another-task/private.txt';
        $secret = 'unrelated private task evidence';
        Storage::disk('local')->put($otherPrivatePath, $secret);
        DB::table('tasks_cycle_evidence')->where('id', $evidenceId)->update(['path' => $otherPrivatePath]);

        $this->withSession($this->actorSession())
            ->get("/tasks/{$taskId}/evidence/{$evidenceId}")
            ->assertNotFound()
            ->assertDontSee($secret);
    }

    public function test_evidence_cannot_be_added_while_task_is_pending(): void
    {
        $taskId = $this->createTask();

        $this->uploadEvidence($taskId, UploadedFile::fake()->createWithContent('proof.txt', 'premature evidence'), 'pending-evidence')
            ->assertConflict();

        $this->assertSame(0, DB::table('tasks_cycle_evidence')->where('task_id', $taskId)->count());
        $this->assertSame(1, DB::table('tasks_cycle_audit')->where('task_id', $taskId)->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_evidence_file_is_removed_when_audit_transaction_fails_after_storage(): void
    {
        $taskId = $this->createTask();
        $this->withSession($this->actorSession())->postJson("/tasks/{$taskId}/start", [
            'idempotency_key' => 'start-before-audit-failure',
        ])->assertOk();

        $injected = false;
        DB::listen(static function (QueryExecuted $query) use (&$injected): void {
            $sql = strtolower(ltrim($query->sql));
            if ($injected || ! str_starts_with($sql, 'insert') || ! str_contains($sql, 'tasks_cycle_audit')) {
                return;
            }

            $injected = true;
            throw new \RuntimeException('Synthetic audit transaction failure.');
        });
        $this->withoutExceptionHandling();

        try {
            $this->uploadEvidence($taskId, UploadedFile::fake()->createWithContent('proof.txt', 'rollback evidence'), 'audit-failure-evidence');
            $this->fail('The injected audit failure should escape the request.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic audit transaction failure.', $exception->getMessage());
        }

        $this->assertTrue($injected);
        $this->assertSame(0, DB::table('tasks_cycle_evidence')->where('task_id', $taskId)->count());
        $this->assertSame(2, DB::table('tasks_cycle_audit')->where('task_id', $taskId)->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_idempotent_replay_does_not_duplicate_state_and_reusing_key_for_another_action_conflicts(): void
    {
        $taskId = $this->createTask();

        $this->withSession($this->actorSession())->postJson("/tasks/{$taskId}/start", [
            'idempotency_key' => 'shared-action-key',
        ])->assertOk();
        $version = DB::table('tasks_cycle')->where('id', $taskId)->value('version');

        $this->withSession($this->actorSession())->postJson("/tasks/{$taskId}/start", [
            'idempotency_key' => 'shared-action-key',
        ])->assertOk();

        $this->withSession($this->actorSession())->postJson("/tasks/{$taskId}/complete", [
            'idempotency_key' => 'shared-action-key',
        ])->assertConflict();

        $this->assertSame($version, DB::table('tasks_cycle')->where('id', $taskId)->value('version'));
        $this->assertSame(2, DB::table('tasks_cycle_audit')->where('task_id', $taskId)->count());
        $this->assertSame('in_progress', DB::table('tasks_cycle')->where('id', $taskId)->value('status'));
    }

    public function test_completion_without_required_state_or_evidence_conflicts_without_mutating_or_auditing(): void
    {
        $taskId = $this->createTask();

        $this->withSession($this->actorSession())->postJson("/tasks/{$taskId}/complete", [
            'idempotency_key' => 'complete-too-early',
        ])->assertConflict();
        $this->assertSame(1, DB::table('tasks_cycle_audit')->where('task_id', $taskId)->count());

        $this->withSession($this->actorSession())->postJson("/tasks/{$taskId}/start", [
            'idempotency_key' => 'start-without-evidence',
        ])->assertOk();
        $version = DB::table('tasks_cycle')->where('id', $taskId)->value('version');

        $this->withSession($this->actorSession())->postJson("/tasks/{$taskId}/complete", [
            'idempotency_key' => 'complete-without-evidence',
        ])->assertConflict();

        $this->assertSame('in_progress', DB::table('tasks_cycle')->where('id', $taskId)->value('status'));
        $this->assertSame($version, DB::table('tasks_cycle')->where('id', $taskId)->value('version'));
        $this->assertSame(2, DB::table('tasks_cycle_audit')->where('task_id', $taskId)->count());
    }

    public function test_mismatched_evidence_replay_rolls_back_rows_and_stored_file(): void
    {
        $taskId = $this->createTask();
        $this->withSession($this->actorSession())->postJson("/tasks/{$taskId}/start", [
            'idempotency_key' => 'start-before-evidence',
        ])->assertOk();

        $this->uploadEvidence($taskId, UploadedFile::fake()->createWithContent('proof.txt', 'first proof'), 'same-evidence-key')
            ->assertOk();
        $storedFiles = Storage::disk('local')->allFiles();
        $this->assertCount(1, $storedFiles);

        $this->uploadEvidence($taskId, UploadedFile::fake()->createWithContent('replacement.txt', 'different proof'), 'same-evidence-key')
            ->assertConflict();

        $this->assertSame(1, DB::table('tasks_cycle_evidence')->where('task_id', $taskId)->count());
        $this->assertSame(3, DB::table('tasks_cycle_audit')->where('task_id', $taskId)->count());
        $this->assertSame($storedFiles, Storage::disk('local')->allFiles());
    }

    public function test_unsupported_and_oversized_evidence_are_rejected_without_persistence(): void
    {
        $taskId = $this->createTask();
        $this->withSession($this->actorSession())->postJson("/tasks/{$taskId}/start", [
            'idempotency_key' => 'start-before-invalid-evidence',
        ])->assertOk();

        $this->uploadEvidence($taskId, UploadedFile::fake()->create('large.txt', 5121, 'text/plain'), 'too-large-evidence')
            ->assertUnprocessable()->assertJsonValidationErrors('evidence');
        $this->uploadEvidence($taskId, UploadedFile::fake()->create('program.exe', 1, 'application/octet-stream'), 'unsupported-evidence')
            ->assertUnprocessable()->assertJsonValidationErrors('evidence');

        $this->assertSame(0, DB::table('tasks_cycle_evidence')->where('task_id', $taskId)->count());
        $this->assertSame(2, DB::table('tasks_cycle_audit')->where('task_id', $taskId)->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    private function createTask(array $overrides = []): string
    {
        $response = $this->withSession($this->actorSession())->postJson('/tasks', $this->createPayload([
            'idempotency_key' => 'create-task-1',
            ...$overrides,
        ]))->assertCreated()->assertJsonStructure(['data' => ['id']]);

        return (string) $response->json('data.id');
    }

    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Follow up with customer',
            'priority' => 'normal',
            'due_at' => '2026-10-20T12:00:00+00:00',
            'assignee_id' => 'actor-1',
        ], $overrides);
    }

    private function uploadEvidence(string $taskId, UploadedFile $file, string $requestKey): TestResponse
    {
        return $this->withSession($this->actorSession())
            ->withHeader('Accept', 'application/json')
            ->post("/tasks/{$taskId}/evidence", [
                'evidence' => $file,
                'idempotency_key' => $requestKey,
            ]);
    }

    private function actorSession(): array
    {
        return [
            'actor_id' => 'actor-1',
            'entity_id' => 'entity-1',
            'department_id' => 'department-1',
            'permissions' => ['tasks.read', 'tasks.write', 'tasks.assist'],
        ];
    }
}

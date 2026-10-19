<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class TasksCycleSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['tasks.read', 'tasks.write', 'tasks.assist'];

    public function test_missing_identity_and_permissions_are_denied_without_writes(): void
    {
        $auditCount = DB::table('tasks_cycle_audit')->count();

        $this->postJson('/tasks', $this->taskInput())->assertUnauthorized();
        $this->getJson('/tasks')->assertUnauthorized();

        $this->withSession($this->identity(permissions: ['tasks.write']))
            ->getJson('/tasks')
            ->assertForbidden();
        $this->withSession($this->identity(permissions: ['tasks.read']))
            ->postJson('/tasks', $this->taskInput())
            ->assertForbidden();

        self::assertSame(0, DB::table('tasks_cycle')->count());
        self::assertSame($auditCount, DB::table('tasks_cycle_audit')->count());
    }

    public function test_creation_uses_session_scope_and_ignores_forged_owner_and_scope_fields(): void
    {
        $session = $this->identity();
        $id = $this->withSession($session)
            ->postJson('/tasks', $this->taskInput([
                'owner_id' => 'forged-owner',
                'entity_id' => 'forged-entity',
                'department_id' => 'forged-department',
            ]))
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id']])
            ->json('data.id');

        self::assertIsString($id);
        $this->withSession($session)->getJson("/tasks/{$id}")->assertOk();

        $this->withSession($this->identity(
            actor: 'forged-owner',
            entity: 'forged-entity',
            department: 'forged-department',
        ))->getJson("/tasks/{$id}")
            ->assertNotFound()
            ->assertJsonMissingPath('data.id');
    }

    public function test_record_scope_denials_do_not_leak_the_task_or_append_audit(): void
    {
        $id = $this->withSession($this->identity(actor: 'owner'))
            ->postJson('/tasks', $this->taskInput())
            ->assertCreated()
            ->json('data.id');
        $auditCount = DB::table('tasks_cycle_audit')->count();

        $outsiders = [
            $this->identity(actor: 'owner', entity: 'other-entity'),
            $this->identity(actor: 'owner', department: 'other-department'),
            $this->identity(actor: 'bystander'),
        ];

        foreach ($outsiders as $identity) {
            $response = $this->withSession($identity)->getJson("/tasks/{$id}");
            $response->assertNotFound()->assertJsonMissingPath('data.id');

            $this->withSession($identity)
                ->postJson("/tasks/{$id}/start", ['idempotency_key' => 'denied-start'])
                ->assertNotFound();
        }

        // Replaying a denied command with the same key must remain side-effect free.
        $this->withSession($outsiders[2])
            ->postJson("/tasks/{$id}/start", ['idempotency_key' => 'denied-start'])
            ->assertNotFound();

        self::assertSame($auditCount, DB::table('tasks_cycle_audit')->count());
        $this->withSession($this->identity(actor: 'owner'))
            ->getJson("/tasks/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_creation_requires_an_idempotency_key_and_rejects_assigning_another_actor(): void
    {
        $this->withSession($this->identity())
            ->postJson('/tasks', $this->taskInput(['idempotency_key' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        $this->withSession($this->identity())
            ->postJson('/tasks', $this->taskInput(['assignee_id' => 'another-actor']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');

        self::assertSame(0, DB::table('tasks_cycle')->count());
        self::assertSame(0, DB::table('tasks_cycle_audit')->count());
    }

    public function test_tasks_routes_are_hidden_and_mutations_are_blocked_in_production(): void
    {
        app()->detectEnvironment(static fn (): string => 'production');

        $this->withSession($this->identity())
            ->getJson('/tasks')
            ->assertNotFound();
        $this->withSession($this->identity())
            ->postJson('/tasks', $this->taskInput())
            ->assertStatus(419);

        self::assertSame(0, DB::table('tasks_cycle')->count());
        self::assertSame(0, DB::table('tasks_cycle_audit')->count());
    }

    public function test_private_evidence_download_is_scoped_and_never_exposes_storage_paths(): void
    {
        Storage::fake('local');
        $id = $this->withSession($this->identity())
            ->postJson('/tasks', $this->taskInput())
            ->assertCreated()
            ->json('data.id');
        $this->withSession($this->identity())
            ->postJson("/tasks/{$id}/start", ['idempotency_key' => 'evidence-scope-start-key'])
            ->assertOk();

        $this->withSession($this->identity())
            ->withHeader('Accept', 'application/json')
            ->post("/tasks/{$id}/evidence", [
                'evidence' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
                'idempotency_key' => 'upload-proof-key',
            ])
            ->assertSuccessful();

        $evidenceId = $this->withSession($this->identity())
            ->getJson("/tasks/{$id}")
            ->assertOk()
            ->json('data.evidence.0.id');
        self::assertIsString($evidenceId);

        $download = $this->withSession($this->identity())
            ->get("/tasks/{$id}/evidence/{$evidenceId}")
            ->assertOk();

        self::assertSame('application/octet-stream', $download->headers->get('Content-Type'));
        self::assertSame('nosniff', $download->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('attachment', strtolower($download->headers->get('Content-Disposition', '')));
        self::assertStringContainsString('private', strtolower($download->headers->get('Cache-Control', '')));

        $auditCount = DB::table('tasks_cycle_audit')->count();
        $outsiders = [
            $this->identity(actor: 'owner', entity: 'other-entity'),
            $this->identity(actor: 'owner', department: 'other-department'),
            $this->identity(actor: 'bystander'),
        ];

        foreach ($outsiders as $identity) {
            $denied = $this->withSession($identity)
                ->getJson("/tasks/{$id}/evidence/{$evidenceId}")
                ->assertNotFound()
                ->assertJsonMissingPath('data.id')
                ->assertJsonMissingPath('data.path');
            self::assertStringNotContainsString('storage', strtolower($denied->getContent()));
        }

        self::assertSame($auditCount, DB::table('tasks_cycle_audit')->count());
    }

    /** @return array<string, mixed> */
    private function identity(
        string $actor = 'owner',
        string $entity = 'entity-a',
        string $department = 'department-a',
        array $permissions = self::PERMISSIONS,
    ): array {
        return [
            'actor_id' => $actor,
            'entity_id' => $entity,
            'department_id' => $department,
            'permissions' => $permissions,
        ];
    }

    /** @return array<string, mixed> */
    private function taskInput(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Call the customer',
            'priority' => 'normal',
            'due_at' => '2026-10-18T10:00:00Z',
            'idempotency_key' => 'create-task-key',
        ], $overrides);
    }
}

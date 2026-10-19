<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Modules\Identity\Domain\SEC01\ActorAccess;
use App\Modules\Tasks\Application\TASK01\TaskTransitionPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

final class TasksCycleAssistantTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['tasks.read', 'tasks.write', 'tasks.assist'];

    public function test_task_local_preview_is_read_only_and_requires_assist_and_write_permissions(): void
    {
        $id = $this->createTask();
        $auditCount = DB::table('tasks_cycle_audit')->count();

        $this->withSession($this->identity(permissions: ['tasks.read', 'tasks.assist']))
            ->postJson("/tasks/{$id}/assistant/preview")
            ->assertForbidden();
        $this->withSession($this->identity(permissions: ['tasks.read', 'tasks.write']))
            ->postJson("/tasks/{$id}/assistant/preview")
            ->assertForbidden();

        $preview = $this->withSession($this->identity())
            ->postJson("/tasks/{$id}/assistant/preview")
            ->assertOk()
            ->assertJsonPath('data.task_id', $id)
            ->assertJsonPath('data.action', 'start')
            ->json('data');

        self::assertIsString($preview['token']);
        self::assertIsInt($preview['version']);
        self::assertSame($auditCount, DB::table('tasks_cycle_audit')->count());
        $this->withSession($this->identity())
            ->getJson("/tasks/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_http_confirmation_stays_blocked_without_a_governed_shared_gateway(): void
    {
        $id = $this->createTask();
        $preview = $this->withSession($this->identity())
            ->postJson("/tasks/{$id}/assistant/preview")
            ->assertOk()
            ->json('data');
        $auditCount = DB::table('tasks_cycle_audit')->count();

        $this->withSession($this->identity())
            ->postJson("/tasks/{$id}/assistant/confirm", [
                'token' => $preview['token'],
                'confirmed' => true,
            ])
            ->assertStatus(503)
            ->assertJsonPath('code', 'shared_ai_gateway_blocked');

        self::assertSame($auditCount, DB::table('tasks_cycle_audit')->count());
        $this->withSession($this->identity())
            ->getJson("/tasks/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_direct_preview_and_confirm_reauthorize_and_replay_the_same_receipt(): void
    {
        $id = $this->createTask();
        $actor = new ActorAccess('owner', 'entity-a', 'department-a', self::PERMISSIONS);
        $previewHandler = app(TaskTransitionPreview::class);
        $auditCount = DB::table('tasks_cycle_audit')->count();

        $preview = $previewHandler->preview($actor, $id);

        self::assertSame($id, $preview['task_id']);
        self::assertSame('start', $preview['action']);
        self::assertIsString($preview['token']);
        self::assertSame($auditCount, DB::table('tasks_cycle_audit')->count());

        $receipt = $previewHandler->confirm($actor, $id, $preview['token'], true);
        $auditCountAfterConfirm = DB::table('tasks_cycle_audit')->count();
        $replay = $previewHandler->confirm($actor, $id, $preview['token'], true);

        self::assertSame($id, $receipt['task_id']);
        self::assertSame('in_progress', $receipt['status']);
        self::assertSame($receipt['receipt_id'], $replay['receipt_id']);
        self::assertSame($auditCountAfterConfirm, DB::table('tasks_cycle_audit')->count());
        $this->withSession($this->identity())
            ->getJson("/tasks/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_unconfirmed_and_tampered_previews_are_rejected_without_mutation(): void
    {
        $id = $this->createTask();
        $actor = new ActorAccess('owner', 'entity-a', 'department-a', self::PERMISSIONS);
        $previewHandler = app(TaskTransitionPreview::class);
        $preview = $previewHandler->preview($actor, $id);
        $auditCount = DB::table('tasks_cycle_audit')->count();
        $tampered = ($preview['token'][0] === 'a' ? 'b' : 'a').substr($preview['token'], 1);

        $this->assertHandlerStatus(422, fn () => $previewHandler->confirm($actor, $id, $preview['token'], false));
        $this->assertHandlerStatus(422, fn () => $previewHandler->confirm($actor, $id, $tampered, true));

        self::assertSame($auditCount, DB::table('tasks_cycle_audit')->count());
        $this->withSession($this->identity())
            ->getJson("/tasks/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_direct_confirmation_rechecks_actor_scope_and_revoked_permissions(): void
    {
        $id = $this->createTask();
        $actor = new ActorAccess('owner', 'entity-a', 'department-a', self::PERMISSIONS);
        $previewHandler = app(TaskTransitionPreview::class);
        $preview = $previewHandler->preview($actor, $id);
        $auditCount = DB::table('tasks_cycle_audit')->count();

        $this->assertHandlerStatus(404, fn () => $previewHandler->confirm(
            new ActorAccess('other-actor', 'entity-a', 'department-a', self::PERMISSIONS),
            $id,
            $preview['token'],
            true,
        ));
        $this->assertHandlerStatus(404, fn () => $previewHandler->confirm(
            new ActorAccess('owner', 'other-entity', 'department-a', self::PERMISSIONS),
            $id,
            $preview['token'],
            true,
        ));
        $this->assertHandlerStatus(403, fn () => $previewHandler->confirm(
            new ActorAccess('owner', 'entity-a', 'department-a', ['tasks.read', 'tasks.assist']),
            $id,
            $preview['token'],
            true,
        ));

        self::assertSame($auditCount, DB::table('tasks_cycle_audit')->count());
        $this->withSession($this->identity())
            ->getJson("/tasks/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_expired_preview_is_rejected_after_five_minutes_without_mutation(): void
    {
        $id = $this->createTask();
        $actor = new ActorAccess('owner', 'entity-a', 'department-a', self::PERMISSIONS);
        $previewHandler = app(TaskTransitionPreview::class);
        $preview = $previewHandler->preview($actor, $id);
        $auditCount = DB::table('tasks_cycle_audit')->count();

        $this->travel(6)->minutes();

        $this->assertHandlerStatus(409, fn () => $previewHandler->confirm($actor, $id, $preview['token'], true));

        self::assertSame($auditCount, DB::table('tasks_cycle_audit')->count());
        $this->withSession($this->identity())
            ->getJson("/tasks/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_stale_preview_after_manual_start_is_rejected_without_a_second_audit(): void
    {
        $id = $this->createTask();
        $actor = new ActorAccess('owner', 'entity-a', 'department-a', self::PERMISSIONS);
        $previewHandler = app(TaskTransitionPreview::class);
        $preview = $previewHandler->preview($actor, $id);

        $this->withSession($this->identity())
            ->postJson("/tasks/{$id}/start", ['idempotency_key' => 'manual-start-key'])
            ->assertOk();
        $auditCount = DB::table('tasks_cycle_audit')->count();

        $this->withSession($this->identity())
            ->postJson("/tasks/{$id}/assistant/preview")
            ->assertStatus(409);
        $this->assertHandlerStatus(409, fn () => $previewHandler->confirm($actor, $id, $preview['token'], true));

        self::assertSame($auditCount, DB::table('tasks_cycle_audit')->count());
        $this->withSession($this->identity())
            ->getJson("/tasks/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
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

    private function createTask(): string
    {
        return $this->withSession($this->identity())
            ->postJson('/tasks', [
                'title' => 'Call the customer',
                'priority' => 'normal',
                'due_at' => '2026-10-18T10:00:00Z',
                'idempotency_key' => 'create-assistant-task',
            ])
            ->assertCreated()
            ->json('data.id');
    }

    /** @param callable(): mixed $operation */
    private function assertHandlerStatus(int $status, callable $operation): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            self::assertSame($status, $exception->status);

            return;
        } catch (HttpExceptionInterface $exception) {
            self::assertSame($status, $exception->getStatusCode());

            return;
        }

        self::fail("Expected the task preview handler to reject with HTTP {$status}.");
    }
}

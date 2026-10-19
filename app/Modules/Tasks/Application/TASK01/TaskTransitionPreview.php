<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Application\TASK01;

use App\Modules\Identity\Domain\SEC01\ActorAccess;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TaskTransitionPreview
{
    public function __construct(private TaskCycle $tasks) {}

    /** @return array<string, mixed> */
    public function preview(ActorAccess $actor, string $id): array
    {
        $this->requireAssistantPermission($actor);
        $task = $this->tasks->find($actor, $id);
        if ($task['status'] !== 'pending') {
            throw new ConflictHttpException('Only a pending task can be previewed for start.');
        }

        $claims = [
            'task_id' => $id,
            'actor_id' => $actor->actorId,
            'entity_id' => $actor->entityId,
            'department_id' => $actor->departmentId,
            'action' => 'start',
            'version' => $task['version'],
            'nonce' => (string) Str::ulid(),
            'expires_at' => now()->addMinutes(5)->getTimestamp(),
        ];

        return [
            'task_id' => $id,
            'action' => 'start',
            'from' => $task['status'],
            'to' => 'in_progress',
            'version' => $task['version'],
            'expires_at' => date(DATE_ATOM, $claims['expires_at']),
            'token' => Crypt::encryptString(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ];
    }

    /** @return array<string, mixed> */
    public function confirm(ActorAccess $actor, string $id, string $token, bool $confirmed): array
    {
        if (! $confirmed) {
            throw ValidationException::withMessages(['confirmed' => 'Explicit confirmation is required.']);
        }
        $this->requireAssistantPermission($actor);

        try {
            $claims = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['token' => 'The preview token is invalid.']);
        }
        if (! is_array($claims)
            || ($claims['task_id'] ?? null) !== $id
            || ($claims['actor_id'] ?? null) !== $actor->actorId
            || ($claims['entity_id'] ?? null) !== $actor->entityId
            || ($claims['department_id'] ?? null) !== $actor->departmentId) {
            throw new NotFoundHttpException('Task not found.');
        }
        if (($claims['action'] ?? null) !== 'start'
            || ! is_int($claims['version'] ?? null)
            || ! is_string($claims['nonce'] ?? null)
            || trim($claims['nonce']) === ''
            || ! is_int($claims['expires_at'] ?? null)) {
            throw ValidationException::withMessages(['token' => 'The preview token is invalid.']);
        }
        if ($claims['expires_at'] <= now()->getTimestamp()) {
            throw new ConflictHttpException('The preview token has expired.');
        }

        return ['task_id' => $id, ...$this->tasks->start($actor, $id, 'assistant-'.$claims['nonce'], $claims['version'])];
    }

    private function requireAssistantPermission(ActorAccess $actor): void
    {
        $this->tasks->assertAssistantAccess($actor);
    }
}

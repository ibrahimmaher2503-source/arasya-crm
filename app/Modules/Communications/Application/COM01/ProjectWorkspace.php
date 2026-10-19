<?php

declare(strict_types=1);

namespace App\Modules\Communications\Application\COM01;

use App\Modules\Communications\Domain\COM01\WorkspaceItem;
use App\Modules\Communications\Domain\COM01\WorkspaceProjection;
use App\Shared\Outbox\InMemoryOutboxStore;
use App\Shared\Outbox\OutboxEvent;

final class ProjectWorkspace
{
    public function __construct(private ?InMemoryOutboxStore $outbox = null) {}

    /** @param list<WorkspaceItem> $items */
    public function project(string $entityId, string $employeeId, array $items): WorkspaceProjection
    {
        $visible = array_values(array_filter($items, static fn (WorkspaceItem $item): bool => $item->entityId === $entityId && $item->employeeId === $employeeId));

        return new WorkspaceProjection($entityId, $employeeId, $visible);
    }

    public function publish(WorkspaceItem $item, string $actorId, string $requestId): WorkspaceMutationResult
    {
        if (trim($actorId) === '') {
            return new WorkspaceMutationResult(false, 'invalid_actor', $item);
        }
        if (trim($requestId) === '') {
            return new WorkspaceMutationResult(false, 'invalid_request', $item);
        }
        if ($this->outbox !== null) {
            $this->outbox->append(new OutboxEvent(
                'workspace-item-'.$item->id,
                'workspace.item.published',
                1,
                'WorkspaceItem',
                $item->id,
                1,
                [
                    'item_id' => $item->id,
                    'entity_id' => $item->entityId,
                    'employee_id' => $item->employeeId,
                    'kind' => $item->kind,
                    'actor_id' => $actorId,
                    'request_id' => $requestId,
                    'occurred_at_utc' => $item->occurredAtUtc->format('c'),
                ],
            ));
        }

        return new WorkspaceMutationResult(true, 'accepted', $item);
    }
}

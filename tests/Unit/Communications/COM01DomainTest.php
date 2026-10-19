<?php

declare(strict_types=1);

namespace Tests\Unit\Communications;

use App\Modules\Communications\Application\COM01\ProjectWorkspace;
use App\Modules\Communications\Domain\COM01\WorkspaceItem;
use App\Shared\Outbox\InMemoryOutboxStore;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class COM01DomainTest extends TestCase
{
    public function test_workspace_projection_contains_only_employee_entity_items_and_normalizes_utc(): void
    {
        $items = [
            new WorkspaceItem('entity-1', 'employee-1', 'task-1', 'assigned_work', 'Call customer', null, new DateTimeImmutable('2026-01-10T10:00:00+02:00')),
            new WorkspaceItem('entity-1', 'employee-1', 'target-1', 'target', 'Monthly sales', '1000.00', new DateTimeImmutable('2026-01-10T11:00:00+02:00')),
            new WorkspaceItem('entity-1', 'employee-2', 'message-2', 'internal_message', 'Other employee', null),
            new WorkspaceItem('entity-2', 'employee-1', 'approval-2', 'approval', 'Other entity', null),
        ];

        $projection = (new ProjectWorkspace)->project('entity-1', 'employee-1', $items);

        self::assertSame(['task-1', 'target-1'], array_map(static fn (WorkspaceItem $item): string => $item->id, $projection->items));
        self::assertSame('2026-01-10T08:00:00+00:00', $projection->items[0]->occurredAtUtc->format('c'));
        self::assertSame('1000.00', $projection->items[1]->amount);
    }

    public function test_valid_workspace_mutation_emits_safe_audit_outbox_intent(): void
    {
        $store = new InMemoryOutboxStore;
        $service = new ProjectWorkspace($store);
        $item = new WorkspaceItem('entity-1', 'employee-1', 'approval-1', 'approval', 'Approve quote', null);

        $result = $service->publish($item, 'employee-1', 'request-1');

        self::assertTrue($result->accepted);
        self::assertCount(1, $store->pending());
        self::assertSame('workspace.item.published', $store->pending()[0]->eventType);
        self::assertSame('approval-1', $store->pending()[0]->payload['item_id']);
    }

    public function test_invalid_workspace_mutation_preserves_state_and_emits_no_intent(): void
    {
        $store = new InMemoryOutboxStore;
        $service = new ProjectWorkspace($store);
        $item = new WorkspaceItem('entity-1', 'employee-1', 'task-1', 'assigned_work', 'Call customer');

        $result = $service->publish($item, '', 'request-1');

        self::assertFalse($result->accepted);
        self::assertSame('invalid_actor', $result->reason);
        self::assertSame($item, $result->item);
        self::assertSame([], $store->pending());
    }

    public function test_workspace_item_rejects_unknown_kind_and_invalid_money(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WorkspaceItem('entity-1', 'employee-1', 'x', 'secret', 'Hidden', '10', new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }
}

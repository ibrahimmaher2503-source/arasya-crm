<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Modules\Communications\Domain\COM01\WorkspaceItem;
use App\Modules\Communications\Infrastructure\COM01\WorkspaceAccessDiagnosticAdapter;
use DateTimeImmutable;
use Tests\TestCase;

final class COM01AccessTest extends TestCase
{
    public function test_workspace_is_employee_and_entity_scoped(): void
    {
        $item = new WorkspaceItem('entity-1', 'employee-1', 'item-1', 'internal_message', 'Follow up', null, new DateTimeImmutable('2026-01-01T00:00:00+03:00'));
        $result = (new WorkspaceAccessDiagnosticAdapter)->inspect('entity-1', 'employee-1', ['employee-1'], [$item]);
        self::assertSame('allow', $result['decision']);
        self::assertCount(1, $result['workspace']->items);
        self::assertSame('2025-12-31T21:00:00+00:00', $result['workspace']->items[0]->occurredAtUtc->format('c'));
    }

    public function test_other_assignment_and_revocation_are_denied(): void
    {
        $item = new WorkspaceItem('entity-1', 'employee-1', 'item-1', 'internal_message', 'Follow up', null, new DateTimeImmutable('2026-01-01'));
        $adapter = new WorkspaceAccessDiagnosticAdapter;
        self::assertSame(['decision' => 'deny', 'code' => 'access_denied', 'workspace' => null], $adapter->inspect('entity-1', 'employee-2', ['employee-1'], [$item]));
        self::assertSame(['decision' => 'deny', 'code' => 'access_denied', 'workspace' => null], $adapter->inspect('entity-1', 'employee-1', ['employee-1'], [$item], true));
    }
}

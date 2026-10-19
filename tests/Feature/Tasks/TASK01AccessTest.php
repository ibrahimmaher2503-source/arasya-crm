<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Modules\Tasks\Domain\TASK01\TaskRecord;
use App\Modules\Tasks\Domain\TASK01\TaskStatus;
use App\Modules\Tasks\Infrastructure\TASK01\TaskAccessDiagnosticAdapter;
use DateTimeImmutable;
use Tests\TestCase;

final class TASK01AccessTest extends TestCase
{
    public function test_visible_tasks_are_scoped_and_overdue_state_is_preserved(): void
    {
        $task = new TaskRecord('task-1', 'entity-1', 'actor-1', null, 'dept-1', 'Follow up', TaskStatus::Pending, new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $result = (new TaskAccessDiagnosticAdapter)->inspect('entity-1', 'actor-1', null, [$task], ['task-1'], new DateTimeImmutable('2026-01-02T00:00:00Z'));
        self::assertSame('allow', $result['decision']);
        self::assertTrue($result['tasks'][0]->isOverdue(new DateTimeImmutable('2026-01-02T00:00:00Z')));
    }

    public function test_other_assignment_and_revocation_fail_closed(): void
    {
        $task = new TaskRecord('task-1', 'entity-1', 'actor-1', null, 'dept-1', 'Follow up', TaskStatus::Pending, new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $adapter = new TaskAccessDiagnosticAdapter;
        self::assertSame('deny', $adapter->inspect('entity-1', 'actor-2', null, [$task], [], new DateTimeImmutable)['decision']);
        self::assertSame(['decision' => 'deny', 'code' => 'access_denied', 'tasks' => []], $adapter->inspect('entity-1', 'actor-1', null, [$task], ['task-1'], new DateTimeImmutable, true));
    }
}

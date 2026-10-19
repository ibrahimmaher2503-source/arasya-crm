<?php

declare(strict_types=1);

namespace Tests\Unit\Tasks;

use App\Modules\Tasks\Application\TASK01\ManageTasks;
use App\Modules\Tasks\Domain\TASK01\TaskRecord;
use App\Modules\Tasks\Domain\TASK01\TaskStatus;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class TASK01DomainTest extends TestCase
{
    public function test_personal_and_manager_tasks_are_scoped_and_overdue_is_derived_in_utc(): void
    {
        $now = new DateTimeImmutable('2026-01-10T12:00:00+02:00');
        $records = [
            new TaskRecord('task-1', 'entity-1', 'employee-1', 'manager-1', 'sales', 'Call', TaskStatus::Pending, new DateTimeImmutable('2026-01-10T08:00:00Z')),
            new TaskRecord('task-2', 'entity-1', 'employee-2', 'manager-1', 'sales', 'Other', TaskStatus::Pending, new DateTimeImmutable('2026-01-10T08:00:00Z')),
            new TaskRecord('task-3', 'entity-2', 'employee-1', 'manager-1', 'sales', 'Other entity', TaskStatus::Pending, $now),
            new TaskRecord('task-4', 'entity-1', 'employee-1', null, 'sales', 'Done', TaskStatus::Completed, $now),
        ];

        $result = (new ManageTasks)->visibleFor('entity-1', 'employee-1', 'manager-1', $records, $now);

        self::assertSame(['task-1', 'task-4'], array_map(static fn (TaskRecord $task): string => $task->id, $result));
        self::assertTrue($result[0]->isOverdue($now));
        self::assertFalse($result[1]->isOverdue($now));
        self::assertSame('2026-01-10T08:00:00+00:00', $result[0]->dueAtUtc->format('c'));
    }

    public function test_valid_transition_reschedule_and_evidence_return_audit_intents(): void
    {
        $task = new TaskRecord('task-1', 'entity-1', 'employee-1', 'manager-1', 'sales', 'Call', TaskStatus::Pending, new DateTimeImmutable('2026-01-10T08:00:00Z'));
        $service = new ManageTasks;
        $when = new DateTimeImmutable('2026-01-10T12:00:00+02:00');

        $started = $service->transition($task, TaskStatus::InProgress, 'employee-1', 'req-1', $when);
        $rescheduled = $service->reschedule($started->task, new DateTimeImmutable('2026-01-12T15:00:00+03:00'), 'manager-1', 'req-2', $when);
        $evidenced = $service->attachEvidence($rescheduled->task, 'photo-1', 'site-photo', 'employee-1', 'req-3', $when);

        self::assertTrue($started->accepted);
        self::assertTrue($rescheduled->accepted);
        self::assertTrue($evidenced->accepted);
        self::assertNotNull($evidenced->auditOutboxIntent);
        self::assertSame('2026-01-12T12:00:00+00:00', $evidenced->task->dueAtUtc->format('c'));
        self::assertSame('photo-1', $evidenced->task->evidence[0]->id);
    }

    public function test_invalid_transition_preserves_state_and_emits_no_intent(): void
    {
        $task = new TaskRecord('task-1', 'entity-1', 'employee-1', null, 'sales', 'Call', TaskStatus::Completed, new DateTimeImmutable('2026-01-10T08:00:00Z'));
        $result = (new ManageTasks)->transition($task, TaskStatus::InProgress, 'employee-1', 'req-1', new DateTimeImmutable('now', new DateTimeZone('UTC')));

        self::assertFalse($result->accepted);
        self::assertSame('invalid_transition', $result->reason);
        self::assertSame($task, $result->task);
        self::assertNull($result->auditOutboxIntent);
    }
}

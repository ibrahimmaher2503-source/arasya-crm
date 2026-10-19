<?php

declare(strict_types=1);

namespace Tests\Unit\Employees;

use App\Modules\Employees\Application\EMP01\ReadScopedEmployeeProfiles;
use App\Modules\Employees\Domain\EMP01\EmployeeProfile;
use App\Modules\Employees\Domain\EMP01\EmployeeStatus;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class EMP01DomainTest extends TestCase
{
    public function test_scoped_profile_read_model_contains_required_fields_and_masks_private_notes_by_default(): void
    {
        $profile = new EmployeeProfile(
            'entity-1', 'employee-1', 'Alice', 'sales', 'sales', 'manager-1',
            new DateTimeImmutable('2026-01-02T04:00:00+04:00'), EmployeeStatus::Active,
            ['contract.pdf'], ['q1: strong'], 'salary discussion',
        );

        $result = (new ReadScopedEmployeeProfiles)->query('entity-1', [$profile], ['employee-1']);

        self::assertCount(1, $result);
        self::assertSame('2026-01-02T00:00:00+00:00', $result[0]['hire_date_utc']);
        self::assertSame('active', $result[0]['status']);
        self::assertArrayNotHasKey('private_notes', $result[0]);
        self::assertSame(['contract.pdf'], $result[0]['documents']);
        self::assertSame(['q1: strong'], $result[0]['evaluations']);
    }

    public function test_scope_is_fail_closed_and_private_notes_require_field_grant(): void
    {
        $profile = new EmployeeProfile(
            'entity-1', 'employee-1', 'Alice', 'sales', 'sales', null,
            new DateTimeImmutable('2026-01-02', new DateTimeZone('UTC')), EmployeeStatus::Active,
            [], [], 'restricted',
        );
        $other = new EmployeeProfile(
            'entity-2', 'employee-2', 'Bob', 'sales', 'sales', null,
            new DateTimeImmutable('2026-01-02', new DateTimeZone('UTC')), EmployeeStatus::Active,
        );

        self::assertSame([], (new ReadScopedEmployeeProfiles)->query('entity-1', [$profile, $other], []));
        self::assertArrayHasKey('private_notes', (new ReadScopedEmployeeProfiles)->query('entity-1', [$profile], ['employee-1'], true)[0]);
        self::assertSame([], (new ReadScopedEmployeeProfiles)->query('entity-2', [$profile], ['employee-1']));
    }

    public function test_invalid_status_transition_preserves_profile_and_valid_transition_emits_safe_intent(): void
    {
        $profile = new EmployeeProfile(
            'entity-1', 'employee-1', 'Alice', 'sales', 'sales', null,
            new DateTimeImmutable('2026-01-02', new DateTimeZone('UTC')), EmployeeStatus::Active,
        );

        $invalid = $profile->transitionStatus(EmployeeStatus::Active, 'actor-1', 'request-1', new DateTimeImmutable('2026-01-02T00:00:00Z'));
        self::assertFalse($invalid->accepted);
        self::assertSame(EmployeeStatus::Active, $invalid->profile->status);
        self::assertNull($invalid->intent);

        $valid = $profile->transitionStatus(EmployeeStatus::OnLeave, 'actor-1', 'request-2', new DateTimeImmutable('2026-01-02T00:00:00Z'));
        self::assertTrue($valid->accepted);
        self::assertSame(EmployeeStatus::OnLeave, $valid->profile->status);
        self::assertNotNull($valid->intent);
        self::assertSame('employee.status_changed', $valid->intent->eventType);
        self::assertSame('employee-1', $valid->intent->aggregateId);
        self::assertSame([
            'employee_id' => 'employee-1',
            'from' => 'active',
            'to' => 'on_leave',
            'actor_id' => 'actor-1',
            'occurred_at_utc' => '2026-01-02T00:00:00+00:00',
        ], $valid->intent->payload);
    }
}

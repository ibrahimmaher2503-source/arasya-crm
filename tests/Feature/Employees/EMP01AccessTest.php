<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Modules\Employees\Domain\EMP01\EmployeeProfile;
use App\Modules\Employees\Domain\EMP01\EmployeeStatus;
use App\Modules\Employees\Infrastructure\EMP01\EmployeeAccessDiagnosticAdapter;
use DateTimeImmutable;
use Tests\TestCase;

final class EMP01AccessTest extends TestCase
{
    public function test_profile_projection_is_entity_scoped_and_masks_private_notes(): void
    {
        $profile = new EmployeeProfile('entity-1', 'employee-1', 'A', 'Manager', 'dept-1', null, new DateTimeImmutable('2026-01-01'), EmployeeStatus::Active, ['doc-1'], ['eval-1'], 'private');
        $result = (new EmployeeAccessDiagnosticAdapter)->inspect('entity-1', [$profile], ['employee-1']);
        self::assertSame('allow', $result['decision']);
        self::assertArrayNotHasKey('private_notes', $result['profiles'][0]);
        self::assertSame('Manager', $result['profiles'][0]['role']);
    }

    public function test_revoked_or_other_entity_access_fails_closed(): void
    {
        $profile = new EmployeeProfile('entity-1', 'employee-1', 'A', 'Manager', 'dept-1', null, new DateTimeImmutable('2026-01-01'), EmployeeStatus::Active);
        $adapter = new EmployeeAccessDiagnosticAdapter;
        self::assertSame([], $adapter->inspect('entity-2', [$profile], ['employee-1'])['profiles']);
        self::assertSame(['decision' => 'deny', 'code' => 'access_denied', 'profiles' => []], $adapter->inspect('entity-1', [$profile], ['employee-1'], true));
    }
}

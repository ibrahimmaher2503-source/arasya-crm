<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Application\SEC01\AuthorizeScopedRecord;
use App\Modules\Identity\Domain\SEC01\ActorAccess;
use App\Modules\Identity\Domain\SEC01\ScopedPermissionGuard;
use App\Modules\Identity\Domain\SEC01\ScopedRecord;
use PHPUnit\Framework\TestCase;

final class SEC01DomainTest extends TestCase
{
    private AuthorizeScopedRecord $authorization;

    protected function setUp(): void
    {
        $this->authorization = new AuthorizeScopedRecord(new ScopedPermissionGuard);
    }

    public function test_inherited_permission_allows_an_assigned_record_in_scope(): void
    {
        self::assertTrue($this->allows(
            $this->actor(inheritedPermissions: ['crm.lead.read']),
            $this->record(assignedActorId: 'employee-1'),
        ));
    }

    public function test_explicit_employee_deny_overrides_an_inherited_allow(): void
    {
        self::assertFalse($this->allows(
            $this->actor(inheritedPermissions: ['crm.lead.read'], overrides: ['crm.lead.read' => false]),
            $this->record(assignedActorId: 'employee-1'),
        ));
    }

    public function test_explicit_employee_allow_overrides_an_absent_inherited_grant(): void
    {
        self::assertTrue($this->allows(
            $this->actor(overrides: ['crm.lead.read' => true]),
            $this->record(assignedActorId: 'employee-1'),
        ));
    }

    public function test_absent_and_non_exact_permissions_are_denied(): void
    {
        self::assertFalse($this->allows($this->actor(), $this->record(assignedActorId: 'employee-1')));
        self::assertFalse($this->allows(
            $this->actor(inheritedPermissions: ['crm.*']),
            $this->record(assignedActorId: 'employee-1'),
        ));
    }

    public function test_entity_and_department_scope_are_required_even_with_an_explicit_allow(): void
    {
        $actor = $this->actor(overrides: ['crm.lead.read' => true]);

        self::assertFalse($this->allows($actor, $this->record(entityId: 'entity-2', assignedActorId: 'employee-1')));
        self::assertFalse($this->allows($actor, $this->record(departmentId: 'department-2', assignedActorId: 'employee-1')));
    }

    public function test_owner_and_assignee_can_access_but_another_actor_cannot(): void
    {
        $actor = $this->actor(inheritedPermissions: ['crm.lead.read']);

        self::assertTrue($this->allows($actor, $this->record(ownerId: 'employee-1')));
        self::assertTrue($this->allows($actor, $this->record(assignedActorId: 'employee-1')));
        self::assertFalse($this->allows($actor, $this->record(ownerId: 'employee-2', assignedActorId: 'employee-3')));
    }

    public function test_unassigned_records_are_denied(): void
    {
        self::assertFalse($this->allows(
            $this->actor(inheritedPermissions: ['crm.lead.read']),
            $this->record(),
        ));
    }

    public function test_blank_actor_permission_or_scope_is_denied(): void
    {
        self::assertFalse($this->allows($this->actor(actorId: ' '), $this->record(assignedActorId: 'employee-1')));
        self::assertFalse($this->authorization->allows(
            $this->actor(inheritedPermissions: ['crm.lead.read']),
            '',
            $this->record(assignedActorId: 'employee-1'),
        ));
        self::assertFalse($this->allows(
            $this->actor(inheritedPermissions: ['crm.lead.read']),
            $this->record(recordId: '', assignedActorId: 'employee-1'),
        ));
    }

    public function test_privileged_sounding_identity_has_no_implicit_bypass(): void
    {
        foreach (['super-administrator', 'executive'] as $identity) {
            self::assertFalse($this->allows(
                $this->actor(actorId: $identity, inheritedPermissions: []),
                $this->record(ownerId: $identity),
            ));
        }
    }

    public function test_malformed_permission_data_fails_closed(): void
    {
        $actor = new ActorAccess('employee-1', 'entity-1', 'department-1', [], ['crm.lead.read' => 'yes']);

        self::assertFalse($this->allows($actor, $this->record(assignedActorId: 'employee-1')));
        self::assertFalse($this->allows(
            new ActorAccess('employee-1', 'entity-1', 'department-1', ['']),
            $this->record(assignedActorId: 'employee-1'),
        ));
    }

    /** @param list<string> $inheritedPermissions @param array<string, bool> $overrides */
    private function actor(
        string $actorId = 'employee-1',
        array $inheritedPermissions = [],
        array $overrides = [],
    ): ActorAccess {
        return new ActorAccess($actorId, 'entity-1', 'department-1', $inheritedPermissions, $overrides);
    }

    private function record(
        string $recordId = 'lead-1',
        string $entityId = 'entity-1',
        string $departmentId = 'department-1',
        ?string $ownerId = null,
        ?string $assignedActorId = null,
    ): ScopedRecord {
        return new ScopedRecord($recordId, $entityId, $departmentId, $ownerId, $assignedActorId);
    }

    private function allows(ActorAccess $actor, ScopedRecord $record): bool
    {
        return $this->authorization->allows($actor, 'crm.lead.read', $record);
    }
}

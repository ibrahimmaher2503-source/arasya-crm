<?php

namespace Tests\Feature\VerticalIntegration;

use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use App\Modules\Identity\Domain\SEC01\ScopedPermissionGuard;
use App\Modules\Identity\Domain\SEC01\ScopedRecord;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AuthorizationContractTest extends TestCase
{
    #[Test]
    public function session_identity_is_used_and_caller_supplied_identity_is_ignored(): void
    {
        $identity = new RuntimeIdentity;
        $actor = $identity->fromSession([
            'actor_id' => 'actor-1',
            'entity_id' => 'entity-1',
            'department_id' => 'branch-1',
            'permissions' => ['records.read'],
        ]);

        $this->assertSame('actor-1', $actor->actorId);
        $this->assertFalse((new ScopedPermissionGuard)->allows(
            $actor,
            'records.read',
            new ScopedRecord('record-1', 'entity-1', 'branch-1', 'actor-evil', null),
        ));
    }
}

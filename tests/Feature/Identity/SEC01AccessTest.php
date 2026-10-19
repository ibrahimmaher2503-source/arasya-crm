<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Infrastructure\SEC01\PolicyDiagnosticAdapter;
use Tests\TestCase;

final class SEC01AccessTest extends TestCase
{
    public function test_local_cli_returns_only_sanitized_policy_decision_for_synthetic_fixture(): void
    {
        [$status, $output] = $this->runDiagnostic($this->fixture(
            inheritedPermissions: ['crm.lead.read'],
            assignedActorId: 'employee-1',
        ));

        self::assertSame(0, $status);
        self::assertSame(['decision' => 'allow', 'code' => 'policy_allowed'], json_decode($output, true));
        self::assertStringNotContainsString('employee-1', $output);
        self::assertStringNotContainsString('lead-1', $output);
        self::assertStringNotContainsString('crm.lead.read', $output);
    }

    public function test_cli_denies_other_owner_unassigned_and_cross_scope_fixtures(): void
    {
        $denied = [
            $this->fixture(inheritedPermissions: ['crm.lead.read'], ownerId: 'employee-2'),
            $this->fixture(inheritedPermissions: ['crm.lead.read']),
            $this->fixture(inheritedPermissions: ['crm.lead.read'], assignedActorId: 'employee-1', recordEntityId: 'entity-2'),
            $this->fixture(inheritedPermissions: ['crm.lead.read'], assignedActorId: 'employee-1', recordDepartmentId: 'department-2'),
        ];

        foreach ($denied as $fixture) {
            [$status, $output] = $this->runDiagnostic($fixture);

            self::assertSame(0, $status);
            self::assertSame(['decision' => 'deny', 'code' => 'policy_denied'], json_decode($output, true));
        }
    }

    public function test_inherited_grant_explicit_override_and_revocation_are_reflected_on_each_invocation(): void
    {
        $adapter = new PolicyDiagnosticAdapter;
        $fixture = $this->fixture(inheritedPermissions: ['crm.lead.read'], assignedActorId: 'employee-1');

        self::assertSame(['decision' => 'allow', 'code' => 'policy_allowed'], $adapter->evaluate($fixture));
        $fixture['actor']['overrides'] = ['crm.lead.read' => false];
        self::assertSame(['decision' => 'deny', 'code' => 'policy_denied'], $adapter->evaluate($fixture));
        $fixture['actor']['inheritedPermissions'] = [];
        $fixture['actor']['overrides'] = [];
        self::assertSame(['decision' => 'deny', 'code' => 'policy_denied'], $adapter->evaluate($fixture));
    }

    public function test_explicit_allow_does_not_bypass_scope_and_unknown_or_malformed_input_fails_closed(): void
    {
        $adapter = new PolicyDiagnosticAdapter;
        $fixture = $this->fixture(assignedActorId: 'employee-1');
        $fixture['actor']['overrides'] = ['crm.lead.read' => true];
        $fixture['record']['entityId'] = 'entity-2';

        self::assertSame(['decision' => 'deny', 'code' => 'policy_denied'], $adapter->evaluate($fixture));

        $fixture['secretGrant'] = 'must-not-echo';
        self::assertSame(['decision' => 'deny', 'code' => 'invalid_diagnostic_input'], $adapter->evaluate($fixture));
        self::assertSame(
            ['decision' => 'deny', 'code' => 'invalid_diagnostic_input'],
            $adapter->evaluate(['actor' => ['actorId' => ['malformed']]]),
        );
    }

    public function test_malformed_cli_request_emits_localized_generic_error_without_fixture_details(): void
    {
        [$status, $output] = $this->runDiagnostic(['actorId' => 'employee-secret']);

        self::assertSame(2, $status);
        self::assertSame("تعذّر تقييم الطلب. استخدم fixture تشخيصية صحيحة.\n", $output);
        self::assertStringNotContainsString('employee-secret', $output);
        self::assertStringNotContainsString('Trace', $output);
    }

    /** @return array{0:int,1:string} */
    private function runDiagnostic(array $fixture): array
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, base_path('app/Modules/Identity/Infrastructure/SEC01/policy-diagnostic.php')],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );

        self::assertIsResource($process);
        fwrite($pipes[0], json_encode($fixture, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $status = proc_close($process);

        self::assertSame('', $error);

        return [$status, $output];
    }

    private function fixture(
        array $inheritedPermissions = [],
        ?string $ownerId = null,
        ?string $assignedActorId = null,
        string $recordEntityId = 'entity-1',
        string $recordDepartmentId = 'department-1',
    ): array {
        return [
            'permission' => 'crm.lead.read',
            'actor' => [
                'actorId' => 'employee-1',
                'entityId' => 'entity-1',
                'departmentId' => 'department-1',
                'inheritedPermissions' => $inheritedPermissions,
                'overrides' => [],
            ],
            'record' => [
                'recordId' => 'lead-1',
                'entityId' => $recordEntityId,
                'departmentId' => $recordDepartmentId,
                'ownerId' => $ownerId,
                'assignedActorId' => $assignedActorId,
            ],
        ];
    }
}

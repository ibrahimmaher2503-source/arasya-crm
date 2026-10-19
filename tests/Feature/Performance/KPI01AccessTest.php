<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Modules\Performance\Domain\KPI01\TargetDefinition;
use App\Modules\Performance\Domain\KPI01\TargetOwnerType;
use App\Modules\Performance\Infrastructure\KPI01\TargetAccessDiagnosticAdapter;
use DateTimeImmutable;
use Tests\TestCase;

final class KPI01AccessTest extends TestCase
{
    public function test_owner_scoped_projection_preserves_dimensions(): void
    {
        $target = new TargetDefinition('target-1', 'collections', 'egp', 'monthly', '100.00', '50.00', TargetOwnerType::Person, 'employee-1', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-02-01'));
        $result = (new TargetAccessDiagnosticAdapter)->inspect('actor-1', ['employee-1'], [$target]);
        self::assertSame('allow', $result['decision']);
        self::assertSame('collections', $result['targets'][0]->metric);
    }

    public function test_other_owner_and_revoked_access_fail_closed(): void
    {
        $target = new TargetDefinition('target-1', 'collections', 'egp', 'monthly', '100.00', '50.00', TargetOwnerType::Person, 'employee-1', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-02-01'));
        $adapter = new TargetAccessDiagnosticAdapter;
        self::assertSame([], $adapter->inspect('actor-1', ['employee-2'], [$target])['targets']);
        self::assertFalse($adapter->inspect('actor-1', ['employee-1'], [$target], true)['decision'] === 'allow');
    }
}

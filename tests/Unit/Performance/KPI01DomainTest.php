<?php

declare(strict_types=1);

namespace Tests\Unit\Performance;

use App\Modules\Performance\Application\KPI01\DefineTarget;
use App\Modules\Performance\Domain\KPI01\TargetDefinition;
use App\Modules\Performance\Domain\KPI01\TargetOwnerType;
use App\Modules\Performance\Domain\KPI01\TargetStatus;
use App\Modules\Performance\Domain\KPI01\TargetTransitionGuard;
use App\Shared\Outbox\InMemoryOutboxStore;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class KPI01DomainTest extends TestCase
{
    public function test_target_preserves_metric_dimensions_and_owner_scope(): void
    {
        $target = new TargetDefinition(
            'target-1', 'revenue', 'USD', 'monthly', '1000.00', '25.00',
            TargetOwnerType::Department, 'department-sales',
            new DateTimeImmutable('2026-01-01 00:00:00+02:00'),
            new DateTimeImmutable('2026-02-01 00:00:00+02:00'),
        );

        self::assertSame(TargetOwnerType::Department, $target->ownerType);
        self::assertSame('department-sales', $target->ownerId);
        self::assertSame('2025-12-31T22:00:00+00:00', $target->periodStartUtc->format('c'));
        self::assertSame('2026-01-31T22:00:00+00:00', $target->periodEndUtc->format('c'));
        self::assertSame('USD', $target->unit);
        self::assertSame('1000.00', $target->threshold);
        self::assertSame('25.00', $target->weight);
    }

    public function test_person_and_department_owners_are_explicitly_distinct_and_mutation_emits_intent(): void
    {
        $store = new InMemoryOutboxStore;
        $target = new TargetDefinition(
            'target-2', 'calls', 'count', 'weekly', '20.00', '10.00',
            TargetOwnerType::Person, 'person-7',
            new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-01-08', new DateTimeZone('UTC')),
        );

        $defined = new DefineTarget($store)->execute($target);

        self::assertSame($target, $defined);
        self::assertCount(1, $store->pending());
        self::assertSame('kpi.target.defined', $store->pending()[0]->eventType);
        self::assertSame('person', $store->pending()[0]->payload['owner_type']);
    }

    public function test_invalid_transition_returns_original_state(): void
    {
        $target = new TargetDefinition(
            'target-3', 'revenue', 'USD', 'monthly', '1.00', '1.00',
            TargetOwnerType::Person, 'person-3',
            new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-02-01', new DateTimeZone('UTC')),
        );

        $result = new TargetTransitionGuard()->propose($target, TargetStatus::Archived);

        self::assertFalse($result->accepted);
        self::assertSame('transition_not_allowed', $result->reason);
        self::assertSame($target, $result->target);
    }

    public function test_target_rejects_invalid_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TargetDefinition(
            'target-4', 'revenue', 'USD', 'monthly', '1', '101.00',
            TargetOwnerType::Department, 'department-sales',
            new DateTimeImmutable('2026-02-01', new DateTimeZone('UTC')),
            new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC')),
        );
    }
}

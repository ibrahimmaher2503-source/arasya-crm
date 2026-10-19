<?php

declare(strict_types=1);

namespace Tests\Unit\Organization;

use App\Modules\Organization\Application\CORE02\ProjectRecordLinkage;
use App\Modules\Organization\Domain\CORE02\RecordIdentity;
use App\Modules\Organization\Domain\CORE02\RecordKind;
use App\Modules\Organization\Domain\CORE02\RecordLink;
use App\Modules\Organization\Domain\CORE02\RecordLinkageGuard;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CORE02DomainTest extends TestCase
{
    private ProjectRecordLinkage $projector;

    protected function setUp(): void
    {
        $this->projector = new ProjectRecordLinkage(new RecordLinkageGuard);
    }

    public function test_all_prd_kinds_have_typed_stable_identities(): void
    {
        foreach (RecordKind::cases() as $kind) {
            $identity = new RecordIdentity($kind, $kind->value.'-1', 'entity-1');
            $model = $this->projector->project($identity, [], [$identity]);

            self::assertNotNull($model, $kind->value);
            self::assertSame($identity, $model->record);
            self::assertSame($kind, $model->record->kind);
        }

        self::assertCount(10, RecordKind::cases());
    }

    public function test_valid_links_require_exact_catalog_identity_and_entity(): void
    {
        $customer = new RecordIdentity(RecordKind::Customer, 'cust-1', 'entity-1');
        $project = new RecordIdentity(RecordKind::Project, 'project-1', 'entity-1');
        $link = new RecordLink($project);

        $model = $this->projector->project($customer, [$link], [$customer, $project]);

        self::assertNotNull($model);
        self::assertSame([$link], $model->links);
    }

    public function test_unresolved_wrong_kind_and_cross_entity_links_fail_closed(): void
    {
        $customer = new RecordIdentity(RecordKind::Customer, 'customer-1', 'entity-1');
        $project = new RecordIdentity(RecordKind::Project, 'target-1', 'entity-1');
        $wrongKind = new RecordIdentity(RecordKind::Contract, 'target-1', 'entity-1');
        $otherEntity = new RecordIdentity(RecordKind::Project, 'target-1', 'entity-2');

        self::assertNull($this->projector->project($customer, [new RecordLink($project)], [$customer]));
        self::assertNull($this->projector->project($customer, [new RecordLink($project)], [$customer, $wrongKind]));
        self::assertNull($this->projector->project($customer, [new RecordLink($otherEntity)], [$customer, $otherEntity]));
        self::assertNull($this->projector->project($customer, [], [$wrongKind]));
    }

    public function test_duplicate_or_conflicting_catalog_identities_invalidate_snapshot(): void
    {
        $customer = new RecordIdentity(RecordKind::Customer, 'same-id', 'entity-1');
        $duplicate = new RecordIdentity(RecordKind::Customer, 'same-id', 'entity-1');
        $conflict = new RecordIdentity(RecordKind::Lead, 'same-id', 'entity-1');

        self::assertNull($this->projector->project($customer, [], [$customer, $duplicate]));
        self::assertNull($this->projector->project($customer, [], [$customer, $conflict]));
        self::assertNull($this->projector->project($customer, [], [$customer, 'free-text-id']));
    }

    public function test_malformed_or_empty_identity_ids_throw_and_fail_closed(): void
    {
        foreach (['', ' ', 'bad identity', 'id/with/slash', str_repeat('x', 129)] as $badId) {
            try {
                new RecordIdentity(RecordKind::Customer, $badId, 'entity-1');
                self::fail('Malformed record IDs must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

        try {
            new RecordIdentity(RecordKind::Customer, 'customer-1', '');
            self::fail('Malformed entity IDs must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }
    }

    public function test_projection_is_immutable_and_replay_is_exact(): void
    {
        $customer = new RecordIdentity(RecordKind::Customer, 'customer-1', 'entity-1');
        $project = new RecordIdentity(RecordKind::Project, 'project-1', 'entity-1');
        $sourceLinks = [new RecordLink($project)];
        $catalog = [$customer, $project];

        $first = $this->projector->project($customer, $sourceLinks, $catalog);
        $replay = $this->projector->project($customer, $sourceLinks, $catalog);
        $denied = $this->projector->project($customer, [new RecordLink(new RecordIdentity(RecordKind::Contract, 'missing', 'entity-1'))], $catalog);

        self::assertNotNull($first);
        self::assertNotNull($replay);
        self::assertSame($first->record, $replay->record);
        self::assertSame($first->links, $replay->links);
        self::assertNull($denied);
        self::assertSame([$customer, $project], $catalog);
        self::assertCount(1, $sourceLinks);
    }

    public function test_empty_and_oversized_catalogs_fail_closed(): void
    {
        $customer = new RecordIdentity(RecordKind::Customer, 'customer-1', 'entity-1');

        self::assertNull($this->projector->project($customer, [], []));
        self::assertNull($this->projector->project($customer, [], array_fill(0, 10001, $customer)));
    }
}

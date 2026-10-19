<?php

declare(strict_types=1);

namespace Tests\Unit\Organization;

use App\Modules\Organization\Application\CORE01\LinkOrganizationReferences;
use App\Modules\Organization\Domain\CORE01\OrganizationReference;
use App\Modules\Organization\Domain\CORE01\OrganizationReferenceGuard;
use PHPUnit\Framework\TestCase;

final class CORE01DomainTest extends TestCase
{
    private LinkOrganizationReferences $linker;

    protected function setUp(): void
    {
        $this->linker = new LinkOrganizationReferences(new OrganizationReferenceGuard);
    }

    public function test_references_in_the_same_entity_can_be_linked(): void
    {
        self::assertTrue($this->linker->allowsLink(
            new OrganizationReference('department', 'dept-1', 'entity-1'),
            new OrganizationReference('branch', 'branch-1', 'entity-1'),
        ));
    }

    public function test_cross_entity_reference_links_are_denied(): void
    {
        self::assertFalse($this->linker->allowsLink(
            new OrganizationReference('department', 'dept-1', 'entity-1'),
            new OrganizationReference('branch', 'branch-1', 'entity-2'),
        ));
    }

    public function test_blank_and_unknown_scope_data_fail_closed(): void
    {
        self::assertFalse($this->linker->allowsLink(
            new OrganizationReference('department', ' ', 'entity-1'),
            new OrganizationReference('branch', 'branch-1', 'entity-1'),
        ));
        self::assertFalse($this->linker->allowsLink(
            new OrganizationReference('not-a-core-reference', 'ref-1', 'entity-1'),
            new OrganizationReference('branch', 'branch-1', 'entity-1'),
        ));
        self::assertFalse($this->linker->allowsLink(
            new OrganizationReference('entity', 'another-entity', 'entity-1'),
            new OrganizationReference('department', 'dept-1', 'entity-1'),
        ));
    }

    public function test_reference_match_requires_exact_kind_identity_and_entity_scope(): void
    {
        $department = new OrganizationReference('department', 'dept-1', 'entity-1');

        self::assertTrue($this->linker->matches('department', 'dept-1', 'entity-1', $department));
        self::assertFalse($this->linker->matches('department', 'dept-2', 'entity-1', $department));
        self::assertFalse($this->linker->matches('branch', 'dept-1', 'entity-1', $department));
        self::assertFalse($this->linker->matches('department', 'dept-1', 'entity-2', $department));
        self::assertFalse($this->linker->matches(' ', 'dept-1', 'entity-1', $department));
    }

    public function test_all_core01_reference_kinds_are_supported_within_entity_scope(): void
    {
        foreach ([
            'entity',
            'department',
            'branch',
            'warehouse',
            'site',
            'currency',
            'unit',
            'status',
            'expense_category',
            'document_numbering',
            'approval_policy',
        ] as $kind) {
            $reference = new OrganizationReference($kind, $kind.'-1', 'entity-1');

            self::assertTrue($this->linker->matches($kind, $kind.'-1', 'entity-1', $reference), $kind);
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use App\Modules\CRM\Application\CRM02\QueryAssignedLeads;
use App\Modules\CRM\Domain\CRM01\LeadRecord;
use PHPUnit\Framework\TestCase;

final class CRM02DomainTest extends TestCase
{
    public function test_query_is_entity_and_assignment_scoped(): void
    {
        $r = (new QueryAssignedLeads)->execute('a', 'e1', ['l1'], [new LeadRecord('e1', 'l1', 'A', 'Org', 'x', null, true), new LeadRecord('e1', 'l2', 'B', 'Other', 'x', 'b@example.test', true), new LeadRecord('e2', 'l3', 'C', 'X', 'x', null, true)]);
        self::assertSame([['id' => 'l1', 'name' => 'A', 'organization' => 'Org']], $r->toArray());
    }

    public function test_empty_assignment_returns_no_contact_data(): void
    {
        $r = (new QueryAssignedLeads)->execute('a', 'e1', [], [new LeadRecord('e1', 'l1', 'A', 'Org', 'x', 'secret@example.test', true)]);
        self::assertSame([], $r->toArray());
    }
}

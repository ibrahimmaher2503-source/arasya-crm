<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Modules\CRM\Domain\CRM01\LeadRecord;
use App\Modules\CRM\Infrastructure\CRM02\AssignedLeadAccessDiagnosticAdapter;
use Tests\TestCase;

final class CRM02AccessTest extends TestCase
{
    public function test_assigned_leads_are_exposed_without_contacts(): void
    {
        $r = (new AssignedLeadAccessDiagnosticAdapter)->query('u', 'e1', ['l1'], [new LeadRecord('e1', 'l1', 'A', 'Org', 'x', 'a@example.test', true)]);
        self::assertSame('allow', $r['decision']);
        self::assertSame([['id' => 'l1', 'name' => 'A', 'organization' => 'Org']], $r['items']);
    }

    public function test_revoked_access_is_denied(): void
    {
        self::assertSame(['decision' => 'deny', 'code' => 'access_denied', 'items' => []], (new AssignedLeadAccessDiagnosticAdapter)->query('u', 'e1', ['l1'], [], true));
    }
}

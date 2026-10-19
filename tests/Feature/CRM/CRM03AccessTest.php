<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Modules\CRM\Domain\CRM03\LeadTaxonomyHistory;
use App\Modules\CRM\Infrastructure\CRM03\LeadTaxonomyAccessDiagnosticAdapter;
use Tests\TestCase;

final class CRM03AccessTest extends TestCase
{
    public function test_assigned_lead_taxonomy_history_is_projected_and_appended(): void
    {
        $history = new LeadTaxonomyHistory('l1', [['stage' => 'new', 'outcome' => 'uncontacted', 'version' => 1]]);
        $result = (new LeadTaxonomyAccessDiagnosticAdapter)->record('u1', 'e1', 'e1', 'l1', $history, 'qualified', 'interested');
        self::assertSame('allow', $result['decision']);
        self::assertSame(2, $result['history']->entries[1]['version']);
    }

    public function test_other_entity_or_revoked_access_is_denied(): void
    {
        $history = new LeadTaxonomyHistory('l1', [['stage' => 'new', 'outcome' => 'uncontacted', 'version' => 1]]);
        self::assertSame('deny', (new LeadTaxonomyAccessDiagnosticAdapter)->record('u1', 'other', 'e1', 'l1', $history, 'qualified', 'interested')['decision']);
        self::assertSame('deny', (new LeadTaxonomyAccessDiagnosticAdapter)->record('u1', 'e1', 'e1', 'l1', $history, 'qualified', 'interested', true)['decision']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use App\Modules\CRM\Application\CRM03\RecordLeadTaxonomy;
use App\Modules\CRM\Domain\CRM03\LeadTaxonomyHistory;
use PHPUnit\Framework\TestCase;

final class CRM03DomainTest extends TestCase
{
    public function test_stage_and_outcome_history_are_separate_and_immutable(): void
    {
        $h = (new RecordLeadTaxonomy)->execute(new LeadTaxonomyHistory('l1', [['stage' => 'new', 'outcome' => 'uncontacted', 'version' => 1]]), 'qualified', 'interested');
        self::assertSame('qualified', $h->entries[1]['stage']);
        self::assertSame('interested', $h->entries[1]['outcome']);
        self::assertCount(1, (new LeadTaxonomyHistory('l1', [['stage' => 'new', 'outcome' => 'uncontacted', 'version' => 1]]))->entries);
    }

    public function test_invalid_history_order_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LeadTaxonomyHistory('l1', [['stage' => 'new', 'outcome' => 'x', 'version' => 2], ['stage' => 'old', 'outcome' => 'y', 'version' => 1]]);
    }
}

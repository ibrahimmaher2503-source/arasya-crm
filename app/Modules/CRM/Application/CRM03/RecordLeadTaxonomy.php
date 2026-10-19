<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\CRM03;

use App\Modules\CRM\Domain\CRM03\LeadTaxonomyHistory;

final class RecordLeadTaxonomy
{
    public function execute(LeadTaxonomyHistory $history, string $stage, string $outcome): LeadTaxonomyHistory
    {
        return $history->append($stage, $outcome);
    }
}

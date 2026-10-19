<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\CRM01;

use App\Modules\CRM\Domain\CRM01\LeadHistoryEntry;

final readonly class LeadImportItem
{
    /** @param list<LeadHistoryEntry> $archiveHistory */
    public function __construct(public string $classification, public bool $consentGranted, public array $archiveHistory) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\CRM01;

final readonly class LeadImportPreview
{
    /** @param list<LeadImportItem> $items */
    public function __construct(public array $items) {}
}

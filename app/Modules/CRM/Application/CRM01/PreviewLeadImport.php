<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\CRM01;

use App\Modules\CRM\Domain\CRM01\LeadRecord;

final class PreviewLeadImport
{
    /**
     * @param  array<array-key, mixed>  $rows
     * @param  list<LeadRecord>  $existing
     */
    public function preview(string $entityId, array $rows, array $existing): LeadImportPreview
    {
        $known = [];
        foreach ($existing as $lead) {
            if ($lead->entityId === $entityId && $lead->duplicateKey() !== null) {
                $known[$lead->duplicateKey()] = $lead;
            }
        }
        $items = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $email = isset($row['email']) && is_string($row['email']) ? strtolower(trim($row['email'])) : null;
            $duplicate = $email !== null && isset($known[$email]);
            $lead = $duplicate ? $known[$email] : null;
            $items[] = new LeadImportItem($duplicate ? 'duplicate' : 'new', (bool) ($row['consent'] ?? false), $lead === null ? [] : $lead->history);
        }

        return new LeadImportPreview($items);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\CRM01;

use App\Modules\CRM\Application\CRM01\PreviewLeadImport;
use App\Modules\CRM\Domain\CRM01\LeadRecord;

/** Read-only synthetic adapter; authorization is derived from its server-side arguments. */
final class LeadAccessDiagnosticAdapter
{
    /**
     * @param  list<string>  $authorizedLeadIds
     * @param  array<array-key, mixed>  $rows
     * @param  list<LeadRecord>  $existing
     * @return array{decision: string, code: string, items: list<mixed>}
     */
    public function preview(string $actorId, string $entityId, array $authorizedLeadIds, array $rows, array $existing, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '' || trim($entityId) === '' || $authorizedLeadIds === []) {
            return ['decision' => 'deny', 'code' => 'access_denied', 'items' => []];
        }

        $allowed = array_fill_keys($authorizedLeadIds, true);
        $scoped = array_values(array_filter(
            $existing,
            static fn (LeadRecord $lead): bool => $lead->entityId === $entityId && isset($allowed[$lead->id]),
        ));
        $preview = (new PreviewLeadImport)->preview($entityId, $rows, $scoped);

        return ['decision' => 'allow', 'code' => 'preview_ready', 'items' => $preview->items];
    }
}

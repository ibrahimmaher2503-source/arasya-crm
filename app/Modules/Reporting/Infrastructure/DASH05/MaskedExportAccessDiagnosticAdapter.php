<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\DASH05;

use App\Modules\Reporting\Application\DASH05\BuildMaskedExport;

final class MaskedExportAccessDiagnosticAdapter
{
    /**
     * @param  list<string>  $filters
     * @param  list<array<string, string>>  $sourceRows
     * @param  list<string>  $maskedFields
     * @return array{decision: string, code: string, export?: array{rows: list<array<string, string>>, sourceEntityId: string, audit: array{actorId: string, filters: list<string>}}}
     */
    public function build(string $actorId, string $entityId, string $assignedEntityId, array $filters, array $sourceRows, array $maskedFields, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '' || $entityId !== $assignedEntityId) {
            return ['decision' => 'deny', 'code' => 'access_denied'];
        }

        return ['decision' => 'allow', 'code' => 'export_ready', 'export' => (new BuildMaskedExport)->execute($entityId, $actorId, $filters, $sourceRows, $maskedFields)];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\DASH05;

use InvalidArgumentException;

final class BuildMaskedExport
{
    /**
     * @param  list<string>  $filters
     * @param  list<array<string, string>>  $sourceRows
     * @param  list<string>  $maskedFields
     * @return array{rows: list<array<string, string>>, sourceEntityId: string, audit: array{actorId: string, filters: list<string>}}
     */
    public function execute(string $entityId, string $actorId, array $filters, array $sourceRows, array $maskedFields): array
    {
        if (trim($entityId) === '' || trim($actorId) === '') {
            throw new InvalidArgumentException('Export context is required.');
        } $rows = [];
        foreach ($sourceRows as $row) {
            if (($row['entityId'] ?? null) !== $entityId) {
                throw new InvalidArgumentException('Export scope mismatch.');
            }$rows[] = array_diff_key($row, array_flip(['entityId', ...$maskedFields]));
        }

        return ['rows' => $rows, 'sourceEntityId' => $entityId, 'audit' => ['actorId' => $actorId, 'filters' => $filters]];
    }
}

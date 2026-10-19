<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\CORE04;

use App\Modules\Organization\Domain\CORE04\SearchRecord;
use App\Modules\Organization\Domain\CORE04\SearchResult;

final class SearchScopedRecords
{
    /**
     * @param  array<array-key, mixed>  $records
     * @param  array<array-key, mixed>  $authorizedRecordIds
     */
    public function query(string $entityId, string $term, array $records, array $authorizedRecordIds): SearchResult
    {
        if (trim($entityId) === '') {
            return new SearchResult([]);
        }

        $authorized = [];
        foreach ($authorizedRecordIds as $id) {
            if (is_string($id) && $id !== '') {
                $authorized[$id] = true;
            }
        }

        $normalizedTerm = strtolower(trim($term));
        $visible = [];
        foreach ($records as $record) {
            if (! $record instanceof SearchRecord
                || $record->entityId !== $entityId
                || ! isset($authorized[$record->id])
                || $record->contactHidden
                || ! $record->matches($normalizedTerm)
            ) {
                continue;
            }
            $visible[] = $record;
        }

        return new SearchResult($visible);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE02;

final class RecordLinkageGuard
{
    private const int MAX_CATALOG_RECORDS = 10000;

    /**
     * @param  array<array-key, mixed>  $catalog  Explicit identity snapshot; no persistence is consulted.
     * @param  array<array-key, mixed>  $links  Requested typed links.
     */
    public function project(RecordIdentity $record, array $links, array $catalog): ?RecordLinkageReadModel
    {
        if (count($catalog) > self::MAX_CATALOG_RECORDS || count($links) > self::MAX_CATALOG_RECORDS) {
            return null;
        }

        $known = [];
        $idsByEntity = [];
        foreach ($catalog as $candidate) {
            if (! $candidate instanceof RecordIdentity) {
                return null;
            }

            $scopedKey = $candidate->entityId."\0".$candidate->id;
            if (isset($idsByEntity[$scopedKey])) {
                return null;
            }
            $idsByEntity[$scopedKey] = true;
            $known[$this->identityKey($candidate)] = true;
        }

        if (! isset($known[$this->identityKey($record)])) {
            return null;
        }

        $projectedLinks = [];
        foreach ($links as $link) {
            if (! $link instanceof RecordLink
                || $link->target->entityId !== $record->entityId
                || ! isset($known[$this->identityKey($link->target)])) {
                return null;
            }

            $projectedLinks[] = $link;
        }

        return new RecordLinkageReadModel($record, $projectedLinks);
    }

    private function identityKey(RecordIdentity $identity): string
    {
        return $identity->entityId."\0".$identity->kind->value."\0".$identity->id;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE04;

use JsonSerializable;

final readonly class SearchResult implements JsonSerializable
{
    /** @param list<SearchRecord> $records */
    public function __construct(public array $records)
    {
        $this->count = count($records);
    }

    public readonly int $count;

    /** @return array{count: int, records: list<SearchRecord>} */
    public function jsonSerialize(): array
    {
        return ['count' => $this->count, 'records' => $this->records];
    }
}

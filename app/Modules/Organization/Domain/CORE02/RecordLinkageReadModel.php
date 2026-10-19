<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE02;

final readonly class RecordLinkageReadModel
{
    /** @param list<RecordLink> $links */
    public function __construct(
        public RecordIdentity $record,
        public array $links,
    ) {}
}

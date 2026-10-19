<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\CORE02;

use App\Modules\Organization\Domain\CORE02\RecordIdentity;
use App\Modules\Organization\Domain\CORE02\RecordLinkageGuard;
use App\Modules\Organization\Domain\CORE02\RecordLinkageReadModel;

final readonly class ProjectRecordLinkage
{
    public function __construct(private RecordLinkageGuard $guard) {}

    /**
     * @param  array<array-key, mixed>  $catalog
     * @param  array<array-key, mixed>  $links
     */
    public function project(RecordIdentity $record, array $links, array $catalog): ?RecordLinkageReadModel
    {
        return $this->guard->project($record, $links, $catalog);
    }
}

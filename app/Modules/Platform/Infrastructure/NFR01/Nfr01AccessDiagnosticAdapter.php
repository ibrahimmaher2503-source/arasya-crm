<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\NFR01;

use App\Modules\Platform\Domain\NFR01\Nfr01AccessContext;
use App\Modules\Platform\Domain\NFR01\Nfr01Guard;
use App\Modules\Platform\Domain\NFR01\Nfr01Resource;

/** Read-only boundary that returns only the guard's sanitized decision. */
final class Nfr01AccessDiagnosticAdapter
{
    /**
     * @param  list<string>  $requestedFields
     * @return array{allowed: bool, reason: string, fields: array<string, mixed>}
     */
    public function inspect(Nfr01AccessContext $context, Nfr01Resource $resource, array $requestedFields): array
    {
        $decision = (new Nfr01Guard)->authorize($context, $resource, $requestedFields);

        return ['allowed' => $decision->allowed, 'reason' => $decision->reason, 'fields' => $decision->visibleFields];
    }
}

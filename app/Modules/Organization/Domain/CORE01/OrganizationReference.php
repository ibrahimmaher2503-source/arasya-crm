<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE01;

final readonly class OrganizationReference
{
    public function __construct(
        public string $kind,
        public string $referenceId,
        public string $entityId,
    ) {}
}

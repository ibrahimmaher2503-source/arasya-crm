<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\CORE01;

use App\Modules\Organization\Domain\CORE01\OrganizationReference;
use App\Modules\Organization\Domain\CORE01\OrganizationReferenceGuard;

final readonly class LinkOrganizationReferences
{
    public function __construct(private OrganizationReferenceGuard $guard) {}

    public function allowsLink(OrganizationReference $source, OrganizationReference $target): bool
    {
        return $this->guard->allowsLink($source, $target);
    }

    public function matches(
        string $kind,
        string $referenceId,
        string $entityId,
        OrganizationReference $reference,
    ): bool {
        return $this->guard->matches($kind, $referenceId, $entityId, $reference);
    }
}

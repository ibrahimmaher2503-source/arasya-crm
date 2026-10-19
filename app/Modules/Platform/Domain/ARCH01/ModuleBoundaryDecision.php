<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\ARCH01;

final readonly class ModuleBoundaryDecision
{
    public const string DENIED = 'denied';

    public const string OWNED = 'owned';

    public function __construct(
        public bool $allowed,
        public string $route,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\NFR01;

final readonly class Nfr01Decision
{
    /** @param array<string, mixed> $visibleFields */
    public function __construct(
        public bool $allowed,
        public string $reason,
        public array $visibleFields,
    ) {}
}

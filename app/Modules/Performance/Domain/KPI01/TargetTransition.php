<?php

declare(strict_types=1);

namespace App\Modules\Performance\Domain\KPI01;

final readonly class TargetTransition
{
    public function __construct(
        public bool $accepted,
        public string $reason,
        public TargetDefinition $target,
    ) {}
}

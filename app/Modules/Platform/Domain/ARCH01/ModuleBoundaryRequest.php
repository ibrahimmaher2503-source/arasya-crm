<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\ARCH01;

final readonly class ModuleBoundaryRequest
{
    public function __construct(
        public string $sourceModule,
        public string $sourceEntityId,
        public string $targetModule,
        public string $targetEntityId,
        public string $targetTable,
        public string $operation,
        public ?string $boundaryContractId = null,
    ) {}
}

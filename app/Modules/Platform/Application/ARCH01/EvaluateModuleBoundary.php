<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\ARCH01;

use App\Modules\Platform\Domain\ARCH01\ModuleBoundaryDecision;
use App\Modules\Platform\Domain\ARCH01\ModuleBoundaryGuard;
use App\Modules\Platform\Domain\ARCH01\ModuleBoundaryRequest;

final readonly class EvaluateModuleBoundary
{
    public function __construct(private ModuleBoundaryGuard $guard) {}

    public function evaluate(ModuleBoundaryRequest $request): ModuleBoundaryDecision
    {
        return $this->guard->decide($request);
    }
}

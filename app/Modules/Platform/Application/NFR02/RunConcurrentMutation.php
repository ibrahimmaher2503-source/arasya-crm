<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\NFR02;

use App\Modules\Platform\Domain\NFR02\ConcurrentMutationHarness;

final class RunConcurrentMutation
{
    public function __construct(private ConcurrentMutationHarness $harness) {}

    public function execute(string $expectedVersion, string $actualVersion, callable $mutation): string
    {
        return $this->harness->run($expectedVersion, $actualVersion, $mutation);
    }
}

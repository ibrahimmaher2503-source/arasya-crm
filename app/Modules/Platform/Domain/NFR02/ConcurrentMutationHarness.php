<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\NFR02;

use RuntimeException;

final class ConcurrentMutationHarness
{
    /** @param callable(string): void $mutation */
    public function run(string $expectedVersion, string $actualVersion, callable $mutation): string
    {
        if ($expectedVersion !== $actualVersion) {
            throw new RuntimeException('concurrent mutation conflict');
        } $mutation($expectedVersion);

        return 'committed';
    }
}

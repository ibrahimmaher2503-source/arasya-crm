<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\NFR02;

use App\Modules\Platform\Application\NFR02\RunConcurrentMutation;
use App\Modules\Platform\Domain\NFR02\ConcurrentMutationHarness;
use Throwable;

final class ConcurrentMutationDiagnosticAdapter
{
    /** @return array{decision:string,code:string,error?:string} */
    public function mutate(string $expectedVersion, string $actualVersion): array
    {
        try {
            (new RunConcurrentMutation(new ConcurrentMutationHarness))->execute($expectedVersion, $actualVersion, static function (string $version): void {});

            return ['decision' => 'allow', 'code' => 'committed'];
        } catch (Throwable $e) {
            return ['decision' => 'deny', 'code' => 'concurrent_conflict', 'error' => $e->getMessage()];
        }
    }
}

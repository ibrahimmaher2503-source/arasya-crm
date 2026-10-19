<?php

declare(strict_types=1);

namespace App\Modules\Platform\Presentation\ARCH01;

use App\Modules\Platform\Infrastructure\ARCH01\InspectArchitecturePolicy;

final readonly class InspectArchitecturePolicyCli
{
    public function __construct(private InspectArchitecturePolicy $inspector) {}

    /** @param list<string> $arguments */
    public function run(array $arguments, callable $writeOutput, callable $writeError): int
    {
        $encoded = false;
        $intent = null;

        foreach ($arguments as $argument) {
            if ($argument === '--base64' && ! $encoded) {
                $encoded = true;

                continue;
            }

            if ($intent !== null) {
                return $this->deny($writeOutput, $writeError);
            }

            $intent = $argument;
        }

        if ($intent === null) {
            return $this->deny($writeOutput, $writeError);
        }

        if ($encoded) {
            $decoded = base64_decode($intent, true);
            if ($decoded === false || base64_encode($decoded) !== $intent) {
                return $this->deny($writeOutput, $writeError);
            }
            $intent = $decoded;
        }

        if (! $this->inspector->inspect($intent)) {
            return $this->deny($writeOutput, $writeError);
        }

        $writeOutput('{"decision":"allowed","code":"ARCH01_ALLOWED"}');

        return 0;
    }

    private function deny(callable $writeOutput, callable $writeError): int
    {
        $writeOutput('{"decision":"denied","code":"ARCH01_DENIED"}');
        $writeError('تم رفض فحص السياسة؛ راجع صيغة الطلب المسموح بها.');

        return 1;
    }
}

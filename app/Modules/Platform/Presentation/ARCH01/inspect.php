<?php

declare(strict_types=1);

use App\Modules\Platform\Application\ARCH01\EvaluateModuleBoundary;
use App\Modules\Platform\Domain\ARCH01\ModuleBoundaryGuard;
use App\Modules\Platform\Domain\ARCH01\ProviderSdkDependencyGuard;
use App\Modules\Platform\Infrastructure\ARCH01\InspectArchitecturePolicy;
use App\Modules\Platform\Presentation\ARCH01\InspectArchitecturePolicyCli;

$root = dirname(__DIR__, 5);
require $root.'/vendor/autoload.php';

$cli = new InspectArchitecturePolicyCli(new InspectArchitecturePolicy(
    new EvaluateModuleBoundary(new ModuleBoundaryGuard),
    new ProviderSdkDependencyGuard,
));

exit($cli->run(
    array_slice($argv, 1),
    static function (string $line): int {
        $written = fwrite(STDOUT, $line.PHP_EOL);

        return $written === false ? 0 : $written;
    },
    static function (string $line): int {
        $written = fwrite(STDERR, $line.PHP_EOL);

        return $written === false ? 0 : $written;
    },
));

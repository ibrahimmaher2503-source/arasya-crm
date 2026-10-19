<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\DASH03;

use App\Modules\Reporting\Domain\DASH03\AuthorizedDrillDown;
use DateTimeImmutable;

final class BuildAuthorizedDrillDown
{
    public function execute(string $entityId, string $metricKey, string $calculation, string $source, DateTimeImmutable $freshAt, string $permittedAction): AuthorizedDrillDown
    {
        return new AuthorizedDrillDown($entityId, $metricKey, $calculation, $source, $freshAt, $permittedAction);
    }
}

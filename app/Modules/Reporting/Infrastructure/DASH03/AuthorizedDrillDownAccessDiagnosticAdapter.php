<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Infrastructure\DASH03;

use App\Modules\Reporting\Application\DASH03\BuildAuthorizedDrillDown;
use DateTimeImmutable;

final class AuthorizedDrillDownAccessDiagnosticAdapter
{
    /** @return array{decision:string,code:string,drill_down?:object} */
    public function build(string $actorId, string $entityId, string $assignedEntityId, string $metricKey, string $calculation, string $source, DateTimeImmutable $freshAt, string $permittedAction, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '' || $entityId !== $assignedEntityId) {
            return ['decision' => 'deny', 'code' => 'access_denied'];
        }

        return ['decision' => 'allow', 'code' => 'drill_down_ready', 'drill_down' => (new BuildAuthorizedDrillDown)->execute($entityId, $metricKey, $calculation, $source, $freshAt, $permittedAction)];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\CRM06;

use App\Modules\CRM\Domain\CRM06\ConversionIdentity;

final class ConversionIdentityAccessDiagnosticAdapter
{
    /** @return array{decision:string,code:string,identity?:ConversionIdentity} */
    public function inspect(string $actorId, string $entityId, string $assignedEntityId, ConversionIdentity $identity, bool $revoked = false): array
    {
        if ($revoked || trim($actorId) === '' || trim($entityId) === '' || $entityId !== $assignedEntityId) {
            return ['decision' => 'deny', 'code' => 'access_denied'];
        }

        return ['decision' => 'allow', 'code' => 'conversion_ready', 'identity' => $identity];
    }
}

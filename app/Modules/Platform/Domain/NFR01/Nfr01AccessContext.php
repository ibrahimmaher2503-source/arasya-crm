<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\NFR01;

use InvalidArgumentException;

final readonly class Nfr01AccessContext
{
    /** @param list<string> $permissions */
    public function __construct(
        public string $actorId,
        public string $entityId,
        public array $permissions,
        public bool $secureTransport = false,
        public bool $secretStoreConfigured = false,
        public bool $revoked = false,
        public int $rateLimitRemaining = 1,
        public bool $serviceAccount = false,
    ) {
        if (trim($actorId) === '' || trim($entityId) === '') {
            throw new InvalidArgumentException('Access context identity is required.');
        }
        if ($rateLimitRemaining < 0) {
            throw new InvalidArgumentException('Rate limit remaining cannot be negative.');
        }
        foreach ($permissions as $permission) {
            if (trim($permission) === '') {
                throw new InvalidArgumentException('Permission names must be non-empty.');
            }
        }
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}

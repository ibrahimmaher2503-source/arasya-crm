<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE03;

/** Explicit fixture policy; it does not define production workflow rules. */
final readonly class TransitionPolicy
{
    private bool $valid;

    /** @param array<array-key, mixed> $allowedEdges from => to => required permission */
    public function __construct(public array $allowedEdges)
    {
        $valid = $allowedEdges !== [];
        foreach ($allowedEdges as $from => $destinations) {
            if (! is_string($from) || trim($from) === '' || ! is_array($destinations) || $destinations === []) {
                $valid = false;

                continue;
            }
            foreach ($destinations as $to => $permission) {
                if (! is_string($to) || trim($to) === '' || ! is_string($permission) || trim($permission) === '') {
                    $valid = false;
                }
            }
        }
        $this->valid = $valid;
    }

    public function permissionFor(string $from, string $to): ?string
    {
        if (! $this->valid || trim($from) === '' || trim($to) === '') {
            return null;
        }
        $destinations = $this->allowedEdges[$from] ?? null;
        if (! is_array($destinations)) {
            return null;
        }
        $permission = $destinations[$to] ?? null;

        return is_string($permission) && trim($permission) !== '' ? $permission : null;
    }
}

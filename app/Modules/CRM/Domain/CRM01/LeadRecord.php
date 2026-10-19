<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\CRM01;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class LeadRecord
{
    /** @param list<LeadHistoryEntry> $history */
    public function __construct(
        public string $entityId,
        public string $id,
        public string $name,
        public string $organization,
        public string $source,
        public ?string $email,
        public bool $consentGranted,
        public bool $archived = false,
        public array $history = [],
    ) {
        foreach ([$entityId, $id, $name, $organization, $source] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Lead identifiers and names must be non-empty.');
            }
        }
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Lead email is invalid.');
        }
    }

    public function duplicateKey(): ?string
    {
        return $this->email === null ? null : strtolower(trim($this->email));
    }

    public function archivedAtUtc(): ?DateTimeImmutable
    {
        foreach (array_reverse($this->history) as $entry) {
            if ($entry->action === 'archived') {
                return $entry->occurredAtUtc;
            }
        }

        return null;
    }
}

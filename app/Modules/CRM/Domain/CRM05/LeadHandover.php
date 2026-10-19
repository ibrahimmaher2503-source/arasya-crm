<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\CRM05;

use InvalidArgumentException;

final readonly class LeadHandover
{
    public function __construct(public string $leadId, public string $ownerId, public string $recipientId, public bool $accepted = false)
    {
        foreach ([$leadId, $ownerId, $recipientId] as $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException('Handover fields are required.');
            }
        }
    }

    public function accept(string $actorId): self
    {
        if ($actorId !== $this->recipientId) {
            throw new InvalidArgumentException('Only the recipient may accept.');
        }

        return new self($this->leadId, $this->recipientId, $this->recipientId, true);
    }
}

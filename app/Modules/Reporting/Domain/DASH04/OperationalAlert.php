<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\DASH04;

use InvalidArgumentException;

final readonly class OperationalAlert
{
    /** @param list<string> $events */
    public function __construct(public string $id, public string $entityId, public string $severity, public string $ownerId, public string $status = 'open', public array $events = [])
    {
        foreach ([$id, $entityId, $severity, $ownerId] as $v) {
            if (trim($v) === '') {
                throw new InvalidArgumentException('Alert fields are required.');
            }
        }if (! in_array($status, ['open', 'acknowledged', 'escalated', 'resolved'], true)) {
            throw new InvalidArgumentException('Alert status is invalid.');
        }
    }

    public function transition(string $status, string $event): self
    {
        if (trim($event) === '') {
            throw new InvalidArgumentException('Alert event is required.');
        }if (in_array($event, $this->events, true)) {
            return $this;
        }

        return new self($this->id, $this->entityId, $this->severity, $this->ownerId, $status, [...$this->events, $event]);
    }
}

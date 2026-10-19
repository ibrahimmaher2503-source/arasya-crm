<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE03;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class TransitionHistoryEntry
{
    public DateTimeImmutable $occurredAtUtc;

    public function __construct(
        public string $from,
        public string $to,
        public string $actorId,
        public string $correlationId,
        public string $requestId,
        public string $entityId,
        public string $subjectId,
        public int $sourceVersion,
        DateTimeImmutable $occurredAt,
    ) {
        if (trim($from) === '' || trim($to) === '' || trim($actorId) === '' || trim($correlationId) === ''
            || trim($requestId) === '' || trim($entityId) === '' || trim($subjectId) === '' || $sourceVersion < 0) {
            throw new InvalidArgumentException('Transition history context must be non-empty.');
        }

        $this->occurredAtUtc = $occurredAt->setTimezone(new DateTimeZone('UTC'));
    }
}

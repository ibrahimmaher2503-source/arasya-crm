<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\SEC03;

use DateTimeImmutable;
use DateTimeZone;

final readonly class ApprovalHistoryEntry
{
    public DateTimeImmutable $occurredAtUtc;

    public function __construct(
        public ApprovalStatus $from,
        public ApprovalStatus $to,
        public string $actorId,
        public string $requestId,
        public string $entityId,
        public string $subjectId,
        public string $reason,
        DateTimeImmutable $occurredAt,
        public ?string $delegateId = null,
        public int $sourceVersion = 0,
    ) {
        if (trim($actorId) === '' || trim($requestId) === '' || trim($entityId) === '' || trim($subjectId) === '') {
            throw new \InvalidArgumentException('Approval history context is required.');
        }
        $this->occurredAtUtc = $occurredAt->setTimezone(new DateTimeZone('UTC'));
    }
}

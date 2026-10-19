<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\SEC03;

final readonly class ApprovalRequestState
{
    /** @var list<ApprovalHistoryEntry> */
    public array $history;

    /** @param list<ApprovalHistoryEntry> $history */
    public function __construct(
        public string $entityId,
        public string $subjectId,
        public string $requesterId,
        public string $requestId,
        public ApprovalStatus $status,
        public int $version = 0,
        array $history = [],
    ) {
        if (trim($entityId) === '' || trim($subjectId) === '' || trim($requesterId) === '' || trim($requestId) === '' || $version < 0) {
            throw new \InvalidArgumentException('Approval request identity is required.');
        }
        foreach ($history as $index => $entry) {
            if ($entry->entityId !== $entityId || $entry->subjectId !== $subjectId || $entry->sourceVersion !== $index) {
                throw new \InvalidArgumentException('Approval history is not contiguous.');
            }
            if ($index > 0 && $history[$index - 1]->to !== $entry->from) {
                throw new \InvalidArgumentException('Approval history transition is inconsistent.');
            }
        }
        if ($history !== [] && end($history)->to !== $status) {
            throw new \InvalidArgumentException('Approval status does not match history.');
        }
        if (count($history) !== $version) {
            throw new \InvalidArgumentException('Approval version does not match history.');
        }
        $this->history = $history;
    }

    public static function pending(string $entityId, string $subjectId, string $requesterId, string $requestId): self
    {
        return new self($entityId, $subjectId, $requesterId, $requestId, ApprovalStatus::Pending);
    }
}

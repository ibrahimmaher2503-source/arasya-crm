<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE03;

use InvalidArgumentException;

final readonly class WorkflowState
{
    /** @var list<TransitionHistoryEntry> */
    public array $history;

    /** @param array<array-key, mixed> $history */
    public function __construct(
        public string $entityId,
        public string $subjectId,
        public string $status,
        public int $version,
        array $history = [],
    ) {
        if (trim($entityId) === '' || trim($subjectId) === '' || trim($status) === '' || $version < 0) {
            throw new InvalidArgumentException('Malformed workflow state.');
        }

        if (! array_is_list($history)) {
            throw new InvalidArgumentException('Malformed transition history.');
        }
        $previousStatus = null;
        $previousVersion = null;
        $requestIds = [];
        foreach ($history as $entry) {
            if (! $entry instanceof TransitionHistoryEntry
                || $entry->entityId !== $entityId
                || $entry->subjectId !== $subjectId
                || ($previousStatus !== null && $entry->from !== $previousStatus)
                || ($previousVersion !== null && $entry->sourceVersion !== $previousVersion + 1)
                || isset($requestIds[$entry->requestId])
            ) {
                throw new InvalidArgumentException('Malformed transition history.');
            }
            $requestIds[$entry->requestId] = true;
            $previousStatus = $entry->to;
            $previousVersion = $entry->sourceVersion;
        }
        if ($previousStatus !== null
            && ($previousStatus !== $status || $version === 0 || $previousVersion !== $version - 1)
        ) {
            throw new InvalidArgumentException('Workflow status/version must match its history suffix.');
        }
        $this->history = $history;
    }
}

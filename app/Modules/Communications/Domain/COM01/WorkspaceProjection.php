<?php

declare(strict_types=1);

namespace App\Modules\Communications\Domain\COM01;

use InvalidArgumentException;
use JsonSerializable;

final readonly class WorkspaceProjection implements JsonSerializable
{
    /** @param list<WorkspaceItem> $items */
    public function __construct(
        public string $entityId,
        public string $employeeId,
        public array $items,
    ) {
        if (trim($entityId) === '' || trim($employeeId) === '') {
            throw new InvalidArgumentException('Workspace projection scope is malformed.');
        }
        foreach ($items as $item) {
            if ($item->entityId !== $entityId || $item->employeeId !== $employeeId) {
                throw new InvalidArgumentException('Workspace item is outside projection scope.');
            }
        }
    }

    /** @return array{entity_id: string, employee_id: string, items: list<WorkspaceItem>} */
    public function jsonSerialize(): array
    {
        return ['entity_id' => $this->entityId, 'employee_id' => $this->employeeId, 'items' => $this->items];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Performance\Domain\KPI04;

use InvalidArgumentException;

final readonly class AchievementHistory
{
    /** @param list<array{targetId:string,value:string,recordedAtUtc:string}> $entries */
    public function __construct(public string $entityId, public array $entries)
    {
        if (trim($entityId) === '') {
            throw new InvalidArgumentException('Entity is required.');
        }foreach ($entries as $entry) {
            if (trim($entry['targetId']) === '' || trim($entry['value']) === '' || trim($entry['recordedAtUtc']) === '') {
                throw new InvalidArgumentException('Achievement history is incomplete.');
            }
        }
    }

    public function append(string $targetId, string $value, string $recordedAtUtc): self
    {
        return new self($this->entityId, [...$this->entries, ['targetId' => $targetId, 'value' => $value, 'recordedAtUtc' => $recordedAtUtc]]);
    }
}

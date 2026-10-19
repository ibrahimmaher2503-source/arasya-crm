<?php

declare(strict_types=1);

namespace App\Modules\Communications\Domain\COM01;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;

final readonly class WorkspaceItem implements JsonSerializable
{
    public DateTimeImmutable $occurredAtUtc;

    /** @var list<string> */
    private const KINDS = [
        'assigned_work', 'calendar', 'target', 'approval', 'announcement',
        'internal_message', 'customer_communication', 'action_history',
    ];

    public function __construct(
        public string $entityId,
        public string $employeeId,
        public string $id,
        public string $kind,
        public string $label,
        public ?string $amount = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        foreach ([$entityId, $employeeId, $id, $kind, $label] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Workspace item fields must be non-empty.');
            }
        }
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException('Workspace item kind is not supported.');
        }
        if ($amount !== null && preg_match('/\A\d+\.\d{2}\z/D', $amount) !== 1) {
            throw new InvalidArgumentException('Money must be a decimal with two places.');
        }
        $this->occurredAtUtc = ($occurredAt ?? new DateTimeImmutable)->setTimezone(new DateTimeZone('UTC'));
    }

    /** @return array<string, string|null> */
    public function jsonSerialize(): array
    {
        return [
            'entity_id' => $this->entityId,
            'employee_id' => $this->employeeId,
            'id' => $this->id,
            'kind' => $this->kind,
            'label' => $this->label,
            'amount' => $this->amount,
            'occurred_at_utc' => $this->occurredAtUtc->format('c'),
        ];
    }
}

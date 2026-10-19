<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE04;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;

final readonly class SearchRecord implements JsonSerializable
{
    public DateTimeImmutable $occurredAtUtc;

    public function __construct(
        public string $entityId,
        public string $id,
        public string $kind,
        public string $label,
        private string $searchableText,
        public bool $contactHidden,
        public ?string $amount = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        foreach ([$entityId, $id, $kind, $label] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Malformed search record.');
            }
        }
        if ($amount !== null && preg_match('/\A\d+\.\d{2}\z/D', $amount) !== 1) {
            throw new InvalidArgumentException('Money must be a decimal with two places.');
        }
        $this->occurredAtUtc = ($occurredAt ?? new DateTimeImmutable)->setTimezone(new DateTimeZone('UTC'));
    }

    public function matches(string $term): bool
    {
        return $term === ''
            || str_contains(strtolower($this->kind), $term)
            || str_contains(strtolower($this->label), $term)
            || str_contains(strtolower($this->searchableText), $term);
    }

    /** @return array{id: string, entity_id: string, kind: string, label: string, amount: ?string, occurred_at_utc: string} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'entity_id' => $this->entityId,
            'kind' => $this->kind,
            'label' => $this->label,
            'amount' => $this->amount,
            'occurred_at_utc' => $this->occurredAtUtc->format('c'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

use JsonSerializable;

final readonly class OutboxEvent implements JsonSerializable
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public int $eventVersion,
        public string $aggregateType,
        public string $aggregateId,
        public int $aggregateVersion,
        public array $payload,
    ) {
        foreach ([$eventId, $eventType, $aggregateType, $aggregateId] as $value) {
            if (trim($value) === '') {
                throw new OutboxException('Outbox event identifiers must be non-empty.');
            }
        }
        if ($eventVersion < 1 || $aggregateVersion < 1) {
            throw new OutboxException('Outbox event metadata is invalid.');
        }
        $this->assertSafePayload($payload);
    }

    /** @param array<mixed> $payload */
    private function assertSafePayload(array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (preg_match('/(?:secret|token|password|api[_-]?key|credential)/i', (string) $key) === 1) {
                throw new OutboxException('Outbox payload contains a prohibited secret field.');
            }
            if (is_array($value)) {
                $this->assertSafePayload($value);
            }
        }
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'event_version' => $this->eventVersion,
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'aggregate_version' => $this->aggregateVersion,
            'payload' => $this->payload,
        ];
    }
}

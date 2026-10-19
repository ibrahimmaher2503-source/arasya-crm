<?php

declare(strict_types=1);

namespace App\Shared\Outbox;

/** Synthetic/local store for the development reliability seam. */
final class InMemoryOutboxStore
{
    /** @var array<string, OutboxEvent> */
    private array $events = [];

    /** @var array<string, true> */
    private array $published = [];

    /** @var array<string, true> */
    private array $receiptKeys = [];

    /** @var list<array{event_id: string, reason: string, attempts: int}> */
    private array $deadLetters = [];

    public function append(OutboxEvent $event): void
    {
        if (isset($this->events[$event->eventId])) {
            if ($this->events[$event->eventId] !== $event) {
                throw new OutboxException('Outbox event ID already exists with different content.');
            }

            return;
        }
        $this->events[$event->eventId] = $event;
    }

    /** @return list<OutboxEvent> */
    public function pending(): array
    {
        return array_values(array_filter(
            $this->events,
            fn (OutboxEvent $event): bool => ! isset($this->published[$event->eventId]),
        ));
    }

    /** @return list<OutboxEvent> */
    public function claimForPublish(): array
    {
        return $this->pending();
    }

    public function acknowledgePublished(string $eventId): void
    {
        $this->event($eventId);
        $this->published[$eventId] = true;
    }

    public function recordReceipt(string $consumerId, string $eventId): bool
    {
        if (trim($consumerId) === '') {
            throw new OutboxException('Consumer ID must be non-empty.');
        }
        $this->event($eventId);
        $key = $consumerId.':'.$eventId;
        if (isset($this->receiptKeys[$key])) {
            return false;
        }
        $this->receiptKeys[$key] = true;

        return true;
    }

    public function hasReceipt(string $consumerId, string $eventId): bool
    {
        return isset($this->receiptKeys[$consumerId.':'.$eventId]);
    }

    public function recordFailure(string $eventId, string $reason): void
    {
        $this->event($eventId);
        $safe = preg_replace('/\b(?:token|secret|password|api[_-]?key|credential)\s*=\s*[^\s]+/i', '', trim($reason)) ?? 'delivery failed';
        $safe = trim(substr($safe, 0, 200));
        $attempts = 1;
        foreach ($this->deadLetters as $entry) {
            if ($entry['event_id'] === $eventId) {
                $attempts = $entry['attempts'] + 1;
            }
        }
        $this->deadLetters[] = ['event_id' => $eventId, 'reason' => $safe, 'attempts' => $attempts];
    }

    /** @return list<array{event_id: string, reason: string, attempts: int}> */
    public function deadLetters(): array
    {
        return $this->deadLetters;
    }

    /** @return list<array{consumer_id: string, event_id: string}> */
    public function receipts(): array
    {
        $receipts = [];
        foreach (array_keys($this->receiptKeys) as $key) {
            [$consumer, $eventId] = explode(':', $key, 2);
            $receipts[] = ['consumer_id' => $consumer, 'event_id' => $eventId];
        }

        return $receipts;
    }

    public function replay(string $eventId, bool $authorized): OutboxEvent
    {
        if (! $authorized) {
            throw new OutboxException('Outbox replay requires explicit authorization.');
        }

        return $this->event($eventId);
    }

    private function event(string $eventId): OutboxEvent
    {
        if (! isset($this->events[$eventId])) {
            throw new OutboxException('Outbox event was not found.');
        }

        return $this->events[$eventId];
    }
}

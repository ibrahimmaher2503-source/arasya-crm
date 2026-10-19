<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\ARCH02;

use App\Shared\Outbox\InMemoryOutboxStore;
use App\Shared\Outbox\OutboxEvent;

final class OutboxRecoveryContract
{
    public function __construct(private InMemoryOutboxStore $store) {}

    public function recordCommitted(OutboxEvent $event): void
    {
        $this->store->append($event);
    }

    /** @param callable(): void $effect */
    public function deliver(string $consumerId, string $eventId, callable $effect): string
    {
        if ($this->store->hasReceipt($consumerId, $eventId)) {
            return 'duplicate';
        }

        try {
            $effect();
            $this->store->recordReceipt($consumerId, $eventId);

            return 'processed';
        } catch (\Throwable $exception) {
            $this->store->recordFailure($eventId, $exception->getMessage());

            return 'failed';
        }
    }

    public function replay(string $eventId, bool $authorized): OutboxEvent
    {
        return $this->store->replay($eventId, $authorized);
    }

    /** @param callable(): void $effect */
    public function replayAndDeliver(string $consumerId, string $eventId, bool $authorized, callable $effect): string
    {
        $this->replay($eventId, $authorized);

        return $this->deliver($consumerId, $eventId, $effect);
    }
}

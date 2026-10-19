<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox;

use App\Shared\Outbox\InMemoryOutboxStore;
use App\Shared\Outbox\OutboxEvent;
use App\Shared\Outbox\OutboxException;
use PHPUnit\Framework\TestCase;

final class OutboxReliabilityTest extends TestCase
{
    public function test_committed_event_survives_publisher_crash_and_replays_with_same_id(): void
    {
        $store = new InMemoryOutboxStore;
        $event = $this->event('event-1');
        $store->append($event);

        self::assertSame([$event], $store->pending());
        self::assertSame([$event], $store->claimForPublish());
        self::assertSame([$event], $store->pending());

        $store->acknowledgePublished($event->eventId);
        self::assertSame([], $store->pending());
        self::assertSame('event-1', $store->replay($event->eventId, true)->eventId);
    }

    public function test_duplicate_delivery_creates_one_consumer_receipt(): void
    {
        $store = new InMemoryOutboxStore;
        $event = $this->event('event-2');
        $store->append($event);

        self::assertTrue($store->recordReceipt('consumer-a', $event->eventId));
        self::assertFalse($store->recordReceipt('consumer-a', $event->eventId));
        self::assertCount(1, $store->receipts());
    }

    public function test_failure_is_sanitized_and_replay_requires_explicit_authorization(): void
    {
        $store = new InMemoryOutboxStore;
        $event = $this->event('event-3');
        $store->append($event);
        $store->recordFailure($event->eventId, 'provider timeout token=secret-value');

        self::assertSame('provider timeout', $store->deadLetters()[0]['reason']);
        $this->expectException(OutboxException::class);
        $store->replay($event->eventId, false);
    }

    public function test_secret_payload_keys_are_rejected(): void
    {
        $this->expectException(OutboxException::class);
        new OutboxEvent('event-4', 'fixture.created', 1, 'Entity', 'entity-1', 1, ['api_token' => 'secret']);
    }

    private function event(string $id): OutboxEvent
    {
        return new OutboxEvent($id, 'fixture.created', 1, 'Fixture', 'fixture-1', 1, ['fixture_id' => 'fixture-1']);
    }
}

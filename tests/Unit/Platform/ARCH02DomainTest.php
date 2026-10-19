<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Domain\ARCH02\OutboxRecoveryContract;
use App\Shared\Outbox\InMemoryOutboxStore;
use App\Shared\Outbox\OutboxEvent;
use App\Shared\Outbox\OutboxException;
use PHPUnit\Framework\TestCase;

final class ARCH02DomainTest extends TestCase
{
    public function test_commit_then_crash_keeps_event_and_replay_is_authorized(): void
    {
        $store = new InMemoryOutboxStore;
        $contract = new OutboxRecoveryContract($store);
        $event = $this->event('event-arch02-1');

        $contract->recordCommitted($event);
        self::assertSame($event, $contract->replay($event->eventId, true));
        self::assertCount(1, $store->pending());

        $this->expectException(OutboxException::class);
        $contract->replay($event->eventId, false);
    }

    public function test_consumer_receipt_prevents_duplicate_business_effect(): void
    {
        $store = new InMemoryOutboxStore;
        $contract = new OutboxRecoveryContract($store);
        $event = $this->event('event-arch02-2');
        $contract->recordCommitted($event);
        $effects = 0;

        self::assertSame('processed', $contract->deliver('consumer-a', $event->eventId, static function () use (&$effects): void {
            $effects++;
        }));
        self::assertSame('duplicate', $contract->deliver('consumer-a', $event->eventId, static function () use (&$effects): void {
            $effects++;
        }));
        self::assertSame(1, $effects);
    }

    public function test_failed_consumer_is_recorded_with_safe_error_and_can_be_replayed(): void
    {
        $store = new InMemoryOutboxStore;
        $contract = new OutboxRecoveryContract($store);
        $event = $this->event('event-arch02-3');
        $contract->recordCommitted($event);

        self::assertSame('failed', $contract->deliver('consumer-a', $event->eventId, static function (): void {
            throw new OutboxException('provider timeout token=secret');
        }));
        self::assertSame('provider timeout', $store->deadLetters()[0]['reason']);
        self::assertSame('processed', $contract->replayAndDeliver('consumer-a', $event->eventId, true, static function (): void {}));
    }

    private function event(string $id): OutboxEvent
    {
        return new OutboxEvent($id, 'fixture.created', 1, 'Fixture', 'fixture-1', 1, ['fixture_id' => 'fixture-1']);
    }
}

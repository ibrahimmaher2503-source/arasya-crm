<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use App\Modules\CRM\Application\CRM01\PreviewLeadImport;
use App\Modules\CRM\Domain\CRM01\LeadLifecycle;
use App\Modules\CRM\Domain\CRM01\LeadRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CRM01DomainTest extends TestCase
{
    public function test_import_preview_marks_duplicates_consent_and_archive_history(): void
    {
        $existing = new LeadRecord('entity-1', 'lead-1', 'Acme', 'Acme Ltd', 'SALES', 'alice@example.test', true);
        $preview = (new PreviewLeadImport)->preview('entity-1', [
            ['id' => 'incoming-1', 'name' => 'Alice', 'organization' => 'Acme', 'email' => ' ALICE@EXAMPLE.TEST ', 'consent' => false],
            ['id' => 'incoming-2', 'name' => 'Bob', 'organization' => 'Beta', 'email' => 'bob@example.test', 'consent' => true],
        ], [$existing]);

        self::assertSame(['duplicate', 'new'], array_map(static fn ($item): string => $item->classification, $preview->items));
        self::assertFalse($preview->items[0]->consentGranted);
        self::assertTrue($preview->items[1]->consentGranted);
        self::assertSame([], $preview->items[0]->archiveHistory);
        self::assertSame([], $preview->items[1]->archiveHistory);
    }

    public function test_archive_emits_audit_outbox_intent_and_invalid_transition_preserves_state(): void
    {
        $lead = new LeadRecord('entity-1', 'lead-1', 'Alice', 'Acme', 'WEB', 'alice@example.test', true);
        $when = new DateTimeImmutable('2026-10-17T12:00:00+03:00');
        $archived = (new LeadLifecycle)->archive($lead, 'entity-1', 'actor-1', 'request-1', $when);

        self::assertTrue($archived->lead->archived);
        self::assertCount(1, $archived->lead->history);
        self::assertNotNull($archived->auditOutboxIntent);
        self::assertSame('lead.archived', $archived->auditOutboxIntent->eventType);
        self::assertSame('2026-10-17T09:00:00+00:00', $archived->lead->history[0]->occurredAtUtc->format('c'));

        $denied = (new LeadLifecycle)->archive($archived->lead, 'other-entity', 'actor-1', 'request-2', $when);
        self::assertFalse($denied->accepted);
        self::assertSame($archived->lead, $denied->lead);
        self::assertNull($denied->auditOutboxIntent);
    }

    public function test_lead_rejects_empty_scope_and_invalid_money_if_present(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LeadRecord('', 'lead-1', 'Alice', 'Acme', 'WEB', null, false);
    }
}

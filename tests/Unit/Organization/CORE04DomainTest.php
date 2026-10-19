<?php

declare(strict_types=1);

namespace Tests\Unit\Organization;

use App\Modules\Organization\Application\CORE04\SearchScopedRecords;
use App\Modules\Organization\Domain\CORE04\SearchRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CORE04DomainTest extends TestCase
{
    public function test_query_and_count_exclude_unauthorized_and_hidden_contacts_without_enumeration(): void
    {
        $records = [
            new SearchRecord('entity-1', 'visible-1', 'customer', 'Visible customer', 'alice@example.test', false),
            new SearchRecord('entity-1', 'hidden-1', 'customer', 'Hidden customer', 'bob@example.test', true),
            new SearchRecord('entity-2', 'other-1', 'customer', 'Other entity', 'alice@example.test', false),
        ];

        $result = (new SearchScopedRecords)->query('entity-1', 'customer', $records, ['visible-1', 'hidden-1']);

        self::assertSame(1, $result->count);
        self::assertSame(['visible-1'], array_map(static fn (SearchRecord $record): string => $record->id, $result->records));
        self::assertStringNotContainsString('bob@example.test', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function test_scope_and_authorization_are_fail_closed_for_malformed_inputs(): void
    {
        $record = new SearchRecord('entity-1', 'record-1', 'contract', 'Contract', 'safe text', false);

        self::assertSame(0, (new SearchScopedRecords)->query('entity-2', 'contract', [$record], ['record-1'])->count);
        self::assertSame(0, (new SearchScopedRecords)->query('entity-1', 'contract', [$record], [])->count);
        self::assertSame(0, (new SearchScopedRecords)->query('entity-1', 'secret', [$record], ['record-1'])->count);
    }

    public function test_result_normalizes_money_and_timestamp_without_mutating_source(): void
    {
        $record = new SearchRecord(
            'entity-1',
            'record-1',
            'order',
            'Order',
            'order text',
            false,
            '12.30',
            new DateTimeImmutable('2026-01-02T04:00:00+04:00'),
        );

        $result = (new SearchScopedRecords)->query('entity-1', 'order', [$record], ['record-1']);

        self::assertSame('12.30', $result->records[0]->amount);
        self::assertSame('2026-01-02T00:00:00+00:00', $result->records[0]->occurredAtUtc->format('c'));
        self::assertSame('12.30', $record->amount);
        self::assertSame('2026-01-02T00:00:00+00:00', $record->occurredAtUtc->format('c'));
    }
}

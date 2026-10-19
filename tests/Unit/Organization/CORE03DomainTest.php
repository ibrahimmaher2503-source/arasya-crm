<?php

declare(strict_types=1);

namespace Tests\Unit\Organization;

use App\Modules\Organization\Application\CORE03\ProposeTransition;
use App\Modules\Organization\Domain\CORE03\TransitionGuard;
use App\Modules\Organization\Domain\CORE03\TransitionHistoryEntry;
use App\Modules\Organization\Domain\CORE03\TransitionPolicy;
use App\Modules\Organization\Domain\CORE03\WorkflowState;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CORE03DomainTest extends TestCase
{
    private function guard(): ProposeTransition
    {
        return new ProposeTransition(new TransitionGuard(new TransitionPolicy(['draft' => ['review' => 'fixture.transition']])));
    }

    private function state(): WorkflowState
    {
        return new WorkflowState('entity-1', 'record-1', 'draft', 0);
    }

    public function test_fixture_edge_proposes_immutable_versioned_state_and_utc_audit_intent(): void
    {
        $source = $this->state();
        $result = $this->guard()->propose(
            $source,
            'entity-1',
            'review',
            0,
            ['fixture.transition'],
            true,
            'actor-1',
            'correlation-1',
            'request-1',
            new DateTimeImmutable('2026-01-02T04:00:00+04:00'),
        );

        self::assertTrue($result->accepted);
        self::assertSame('review', $result->state->status);
        self::assertSame(1, $result->state->version);
        self::assertCount(1, $result->state->history);
        self::assertSame('2026-01-02T00:00:00+00:00', $result->state->history[0]->occurredAtUtc->format('c'));
        self::assertSame($result->state->history[0], $result->auditOutboxIntent);
        self::assertSame('draft', $source->status);
        self::assertSame([], $source->history);
    }

    public function test_forbidden_unknown_entity_permission_validation_and_stale_requests_preserve_source(): void
    {
        $guard = $this->guard();
        $source = $this->state();
        $when = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $cases = [
            $guard->propose($source, 'entity-1', 'published', 0, ['fixture.transition'], true, 'actor', 'corr', 'r1', $when),
            $guard->propose($source, 'entity-1', 'unknown', 0, ['fixture.transition'], true, 'actor', 'corr', 'r2', $when),
            $guard->propose($source, 'entity-2', 'review', 0, ['fixture.transition'], true, 'actor', 'corr', 'r3', $when),
            $guard->propose($source, 'entity-1', 'review', 0, [], true, 'actor', 'corr', 'r4', $when),
            $guard->propose($source, 'entity-1', 'review', 0, ['fixture.transition'], false, 'actor', 'corr', 'r5', $when),
            $guard->propose($source, 'entity-1', 'review', 1, ['fixture.transition'], true, 'actor', 'corr', 'r6', $when),
        ];

        foreach ($cases as $result) {
            self::assertFalse($result->accepted);
            self::assertSame($source, $result->state);
            self::assertNull($result->auditOutboxIntent);
        }
        self::assertSame('draft', $source->status);
        self::assertSame([], $source->history);
    }

    public function test_matching_replay_requires_original_scope_version_permission_and_validation_without_new_intent(): void
    {
        $guard = $this->guard();
        $source = $this->state();
        $when = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $first = $guard->propose($source, 'entity-1', 'review', 0, ['fixture.transition'], true, 'actor', 'corr', 'request', $when);
        $replay = $guard->propose($first->state, 'entity-1', 'review', 0, ['fixture.transition'], true, 'actor', 'corr', 'request', $when);

        self::assertTrue($first->accepted);
        self::assertTrue($replay->accepted);
        self::assertTrue($replay->replayed);
        self::assertSame($first->state, $replay->state);
        self::assertNull($replay->auditOutboxIntent);

        $invalidReplays = [
            $guard->propose($first->state, 'other-entity', 'review', 0, ['fixture.transition'], true, 'actor', 'corr', 'request', $when),
            $guard->propose($first->state, 'entity-1', 'review', 999, ['fixture.transition'], true, 'actor', 'corr', 'request', $when),
            $guard->propose($first->state, 'entity-1', 'review', 0, [], true, 'actor', 'corr', 'request', $when),
            $guard->propose($first->state, 'entity-1', 'review', 0, ['fixture.transition'], false, 'actor', 'corr', 'request', $when),
            $guard->propose($first->state, 'entity-1', 'other', 0, ['fixture.transition'], true, 'actor', 'corr', 'request', $when),
        ];
        foreach ($invalidReplays as $denied) {
            self::assertFalse($denied->accepted);
            self::assertSame($first->state, $denied->state);
            self::assertNull($denied->auditOutboxIntent);
        }
    }

    public function test_denial_preserves_nonempty_history_and_malformed_fixture_policy_fails_closed(): void
    {
        $guard = $this->guard();
        $accepted = $guard->propose($this->state(), 'entity-1', 'review', 0, ['fixture.transition'], true, 'actor', 'corr', 'first', new DateTimeImmutable('2026-01-01T00:00:00Z'));
        $denied = $guard->propose($accepted->state, 'entity-1', 'published', 1, ['fixture.transition'], true, 'actor', 'corr', 'second', new DateTimeImmutable('2026-01-02T00:00:00Z'));
        $malformed = new TransitionGuard(new TransitionPolicy(['draft' => 'not-an-edge-map']));
        $malformedSource = $this->state();
        $malformedResult = $malformed->propose($malformedSource, 'entity-1', 'review', 0, ['fixture.transition'], true, 'actor', 'corr', 'third', new DateTimeImmutable('2026-01-01T00:00:00Z'));

        self::assertFalse($denied->accepted);
        self::assertSame($accepted->state, $denied->state);
        self::assertCount(1, $denied->state->history);
        self::assertFalse($malformedResult->accepted);
        self::assertSame($malformedSource, $malformedResult->state);
    }

    public function test_history_requires_contiguous_suffix_versions_unique_requests_and_matching_snapshot(): void
    {
        $when = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $first = new TransitionHistoryEntry('draft', 'review', 'actor', 'corr', 'r1', 'entity-1', 'record-1', 4, $when);
        $second = new TransitionHistoryEntry('review', 'approved', 'actor', 'corr', 'r2', 'entity-1', 'record-1', 5, $when);
        $valid = new WorkflowState('entity-1', 'record-1', 'approved', 6, [$first, $second]);
        self::assertSame([$first, $second], $valid->history);

        $invalidSnapshots = [
            ['review', 6, [$first]],
            ['approved', 7, [$first, $second]],
            ['approved', 6, [$first, new TransitionHistoryEntry('review', 'approved', 'actor', 'corr', 'r2', 'entity-1', 'record-1', 7, $when)]],
            ['approved', 6, [$first, new TransitionHistoryEntry('review', 'approved', 'actor', 'corr', 'r1', 'entity-1', 'record-1', 5, $when)]],
            ['approved', 6, [$first, new TransitionHistoryEntry('draft', 'approved', 'actor', 'corr', 'r2', 'entity-1', 'record-1', 5, $when)]],
        ];
        foreach ($invalidSnapshots as [$status, $version, $history]) {
            try {
                new WorkflowState('entity-1', 'record-1', $status, $version, $history);
                self::fail('Inconsistent history snapshots must be rejected.');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_older_request_replays_from_valid_multievent_suffix_without_new_intent(): void
    {
        $guard = new ProposeTransition(new TransitionGuard(new TransitionPolicy([
            'draft' => ['review' => 'fixture.review'],
            'review' => ['approved' => 'fixture.approve'],
        ])));
        $when = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $draft = $this->state();
        $review = $guard->propose($draft, 'entity-1', 'review', 0, ['fixture.review'], true, 'actor', 'corr', 'r1', $when)->state;
        $approved = $guard->propose($review, 'entity-1', 'approved', 1, ['fixture.approve'], true, 'actor', 'corr', 'r2', $when)->state;
        $replay = $guard->propose($approved, 'entity-1', 'review', 0, ['fixture.review'], true, 'actor', 'corr', 'r1', $when);

        self::assertTrue($replay->accepted);
        self::assertTrue($replay->replayed);
        self::assertSame($approved, $replay->state);
        self::assertNull($replay->auditOutboxIntent);
    }

    public function test_whitespace_context_is_denied_and_maximum_version_does_not_overflow(): void
    {
        $guard = new ProposeTransition(new TransitionGuard(new TransitionPolicy(['review' => ['approved' => 'fixture.approve']])));
        $when = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $contextState = new WorkflowState('entity-1', 'record-1', 'review', 3);
        $whitespaceContexts = [
            $guard->propose($contextState, 'entity-1', 'approved', 3, ['fixture.approve'], true, ' ', 'corr', 'r', $when),
            $guard->propose($contextState, 'entity-1', 'approved', 3, ['fixture.approve'], true, 'actor', ' ', 'r', $when),
            $guard->propose($contextState, 'entity-1', 'approved', 3, ['fixture.approve'], true, 'actor', 'corr', ' ', $when),
            $guard->propose($contextState, 'entity-1', ' ', 3, ['fixture.approve'], true, 'actor', 'corr', 'r', $when),
        ];
        foreach ($whitespaceContexts as $denied) {
            self::assertFalse($denied->accepted);
            self::assertSame($contextState, $denied->state);
            self::assertNull($denied->auditOutboxIntent);
        }

        $maxState = new WorkflowState('entity-1', 'record-1', 'review', PHP_INT_MAX);
        $exhausted = $guard->propose($maxState, 'entity-1', 'approved', PHP_INT_MAX, ['fixture.approve'], true, 'actor', 'corr', 'new', $when);
        self::assertFalse($exhausted->accepted);
        self::assertSame('version_exhausted', $exhausted->reason);
        self::assertSame($maxState, $exhausted->state);
        self::assertNull($exhausted->auditOutboxIntent);

        $oldEntry = new TransitionHistoryEntry('review', 'approved', 'actor', 'corr', 'old', 'entity-1', 'record-1', PHP_INT_MAX - 1, $when);
        $maxWithHistory = new WorkflowState('entity-1', 'record-1', 'approved', PHP_INT_MAX, [$oldEntry]);
        $replay = $guard->propose($maxWithHistory, 'entity-1', 'approved', PHP_INT_MAX - 1, ['fixture.approve'], true, 'actor', 'corr', 'old', $when);
        self::assertTrue($replay->accepted);
        self::assertNull($replay->auditOutboxIntent);
    }
}

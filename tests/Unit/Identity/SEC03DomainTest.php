<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Application\SEC03\ProposeApprovalTransition;
use App\Modules\Identity\Domain\SEC03\ApprovalRequestState;
use App\Modules\Identity\Domain\SEC03\ApprovalStatus;
use App\Modules\Identity\Domain\SEC03\ApprovalTransitionGuard;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SEC03DomainTest extends TestCase
{
    private function service(): ProposeApprovalTransition
    {
        return new ProposeApprovalTransition(new ApprovalTransitionGuard);
    }

    private function pending(): ApprovalRequestState
    {
        return ApprovalRequestState::pending('entity-1', 'subject-1', 'requester-1', 'request-1');
    }

    public function test_each_terminal_and_intermediate_state_appends_immutable_history_and_outbox_intent(): void
    {
        $service = $this->service();
        $state = $this->pending();
        $when = new DateTimeImmutable('2026-01-01T04:00:00+04:00');

        foreach ([
            ApprovalStatus::Approved,
            ApprovalStatus::Rejected,
            ApprovalStatus::Cancelled,
            ApprovalStatus::Returned,
            ApprovalStatus::Delegated,
            ApprovalStatus::Expired,
        ] as $index => $status) {
            $result = $service->propose($state, 'actor-'.$index, $status, 'r-'.$index, $when, 'fixture reason', $status === ApprovalStatus::Delegated ? 'delegate-1' : null);

            self::assertTrue($result->accepted);
            self::assertSame($status, $result->state->status);
            self::assertCount(1, $result->state->history);
            self::assertSame('2026-01-01T00:00:00+00:00', $result->state->history[0]->occurredAtUtc->format('c'));
            self::assertSame($result->state->history[0], $result->auditOutboxIntent);
        }
    }

    public function test_invalid_authorization_and_self_approval_preserve_pending_state(): void
    {
        $state = $this->pending();
        $when = new DateTimeImmutable('2026-01-01T00:00:00Z');

        $self = $this->service()->propose($state, 'requester-1', ApprovalStatus::Approved, 'r-self', $when);
        $unauthorized = $this->service()->propose($state, 'actor-1', ApprovalStatus::Rejected, 'r-denied', $when, '', null, false);
        $mismatch = $this->service()->propose($state, 'actor-1', ApprovalStatus::Approved, 'r-mismatch', $when, '', null, true, 'other-entity');

        foreach ([$self, $unauthorized, $mismatch] as $result) {
            self::assertFalse($result->accepted);
            self::assertSame($state, $result->state);
            self::assertNull($result->auditOutboxIntent);
        }
        self::assertSame(ApprovalStatus::Pending, $state->status);
        self::assertSame([], $state->history);
    }

    public function test_replay_is_idempotent_and_rejected_transition_does_not_mutate(): void
    {
        $service = $this->service();
        $when = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $first = $service->propose($this->pending(), 'approver-1', ApprovalStatus::Approved, 'r-1', $when);
        $replay = $service->propose($first->state, 'approver-1', ApprovalStatus::Approved, 'r-1', $when);
        $invalid = $service->propose($first->state, 'approver-1', ApprovalStatus::Rejected, 'r-2', $when);

        self::assertTrue($first->accepted);
        self::assertSame('accepted', $first->reason);
        self::assertTrue($replay->accepted);
        self::assertSame('replayed', $replay->reason);
        self::assertNull($replay->auditOutboxIntent);
        self::assertFalse($invalid->accepted);
        self::assertSame($first->state, $invalid->state);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\SEC03;

use DateTimeImmutable;

final class ApprovalTransitionGuard
{
    /** @var array<string, list<ApprovalStatus>> */
    private const ALLOWED = [
        'pending' => [ApprovalStatus::Approved, ApprovalStatus::Rejected, ApprovalStatus::Cancelled, ApprovalStatus::Returned, ApprovalStatus::Delegated, ApprovalStatus::Expired],
        'returned' => [ApprovalStatus::Pending],
        'delegated' => [ApprovalStatus::Pending],
    ];

    public function propose(
        ApprovalRequestState $source,
        string $actorId,
        ApprovalStatus $to,
        string $requestId,
        DateTimeImmutable $occurredAt,
        string $reason = '',
        ?string $delegateId = null,
        bool $authorized = true,
        ?string $entityId = null,
    ): ApprovalTransition {
        $entityId ??= $source->entityId;
        if (! $authorized) {
            return new ApprovalTransition(false, 'authorization_denied', $source);
        }
        if (trim($actorId) === '' || trim($requestId) === '' || trim($reason) === '' && $to !== ApprovalStatus::Approved || $entityId !== $source->entityId) {
            return new ApprovalTransition(false, 'malformed_context', $source);
        }
        foreach ($source->history as $entry) {
            if ($entry->requestId !== $requestId) {
                continue;
            }
            if ($entry->actorId !== $actorId || $entry->to !== $to || $entry->entityId !== $entityId) {
                return new ApprovalTransition(false, 'request_id_conflict', $source);
            }

            return new ApprovalTransition(true, 'replayed', $source, null);
        }
        if ($to === ApprovalStatus::Approved && $actorId === $source->requesterId) {
            return new ApprovalTransition(false, 'self_approval_denied', $source);
        }
        $allowed = self::ALLOWED[$source->status->value] ?? [];
        if (! in_array($to, $allowed, true)) {
            return new ApprovalTransition(false, 'transition_not_allowed', $source);
        }
        if ($to === ApprovalStatus::Delegated && (trim((string) $delegateId) === '' || $delegateId === $actorId)) {
            return new ApprovalTransition(false, 'delegation_invalid', $source);
        }
        if ($source->version === PHP_INT_MAX) {
            return new ApprovalTransition(false, 'version_exhausted', $source);
        }
        $entry = new ApprovalHistoryEntry($source->status, $to, $actorId, $requestId, $entityId, $source->subjectId, trim($reason) === '' ? 'approved' : $reason, $occurredAt, $delegateId, $source->version);
        $state = new ApprovalRequestState($source->entityId, $source->subjectId, $source->requesterId, $source->requestId, $to, $source->version + 1, [...$source->history, $entry]);

        return new ApprovalTransition(true, 'accepted', $state, $entry);
    }
}

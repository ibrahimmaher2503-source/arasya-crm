<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE03;

use DateTimeImmutable;

final readonly class TransitionGuard
{
    public function __construct(private TransitionPolicy $policy) {}

    /**
     * Returns a pure proposal. Permissions and validator outcome are supplied inputs, not inferred authority.
     *
     * @param  list<mixed>  $permissions
     */
    public function propose(
        WorkflowState $source,
        string $entityId,
        string $to,
        int $expectedVersion,
        array $permissions,
        bool $validatorsPassed,
        string $actorId,
        string $correlationId,
        string $requestId,
        DateTimeImmutable $occurredAt,
    ): TransitionProposal {
        if (trim($entityId) === '' || $entityId !== $source->entityId) {
            return new TransitionProposal(false, 'entity_mismatch', $source);
        }
        if (trim($actorId) === '' || trim($correlationId) === '' || trim($requestId) === '' || trim($to) === '') {
            return new TransitionProposal(false, 'malformed_context', $source);
        }

        foreach ($source->history as $entry) {
            if ($entry->requestId !== $requestId) {
                continue;
            }
            if ($entry->entityId !== $entityId || $entry->subjectId !== $source->subjectId
                || $entry->to !== $to || $entry->actorId !== $actorId
                || $entry->correlationId !== $correlationId || $entry->sourceVersion !== $expectedVersion
            ) {
                return new TransitionProposal(false, 'request_id_conflict', $source);
            }

            $permission = $this->policy->permissionFor($entry->from, $entry->to);
            if ($permission === null) {
                return new TransitionProposal(false, 'transition_not_allowed', $source);
            }
            if (! in_array($permission, $permissions, true)) {
                return new TransitionProposal(false, 'permission_missing', $source);
            }
            if (! $validatorsPassed) {
                return new TransitionProposal(false, 'validation_failed', $source);
            }

            return new TransitionProposal(true, 'replayed', $source, null, true);
        }

        if ($expectedVersion !== $source->version) {
            return new TransitionProposal(false, 'stale_version', $source);
        }

        $permission = $this->policy->permissionFor($source->status, $to);
        if ($permission === null) {
            return new TransitionProposal(false, 'transition_not_allowed', $source);
        }
        if (! in_array($permission, $permissions, true)) {
            return new TransitionProposal(false, 'permission_missing', $source);
        }
        if (! $validatorsPassed) {
            return new TransitionProposal(false, 'validation_failed', $source);
        }
        if ($source->version === PHP_INT_MAX) {
            return new TransitionProposal(false, 'version_exhausted', $source);
        }

        $entry = new TransitionHistoryEntry(
            $source->status,
            $to,
            $actorId,
            $correlationId,
            $requestId,
            $entityId,
            $source->subjectId,
            $expectedVersion,
            $occurredAt,
        );
        $state = new WorkflowState(
            $source->entityId,
            $source->subjectId,
            $to,
            $source->version + 1,
            [...$source->history, $entry],
        );

        return new TransitionProposal(true, 'accepted', $state, $entry);
    }
}

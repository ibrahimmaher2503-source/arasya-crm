<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE01;

final class OrganizationReferenceGuard
{
    private const array REFERENCE_KINDS = [
        'entity',
        'department',
        'branch',
        'warehouse',
        'site',
        'currency',
        'unit',
        'status',
        'expense_category',
        'document_numbering',
        'approval_policy',
    ];

    public function allowsLink(OrganizationReference $source, OrganizationReference $target): bool
    {
        return $this->isValid($source)
            && $this->isValid($target)
            && $source->entityId === $target->entityId;
    }

    public function matches(
        string $kind,
        string $referenceId,
        string $entityId,
        OrganizationReference $reference,
    ): bool {
        return $this->isValid($reference)
            && $this->isValidIdentity($kind, $referenceId, $entityId)
            && $reference->kind === $kind
            && $reference->referenceId === $referenceId
            && $reference->entityId === $entityId;
    }

    private function isValid(OrganizationReference $reference): bool
    {
        return $this->isValidIdentity($reference->kind, $reference->referenceId, $reference->entityId)
            && ($reference->kind !== 'entity' || $reference->referenceId === $reference->entityId);
    }

    private function isValidIdentity(string $kind, string $referenceId, string $entityId): bool
    {
        return in_array($kind, self::REFERENCE_KINDS, true)
            && trim($referenceId) !== ''
            && trim($entityId) !== '';
    }
}

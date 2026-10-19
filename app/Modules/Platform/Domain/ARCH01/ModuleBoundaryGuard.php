<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\ARCH01;

final class ModuleBoundaryGuard
{
    /** @var list<string> */
    private const array KNOWN_MODULES = [
        'Organization',
        'Platform',
    ];

    /** @var array<string, string> Local guard-policy fixtures; not persisted table/schema registrations. */
    private const array RESOURCE_OWNERS = [
        'organization_references' => 'Organization',
    ];

    public function decide(ModuleBoundaryRequest $request): ModuleBoundaryDecision
    {
        if (! $this->hasValidData($request)
            || $request->sourceEntityId !== $request->targetEntityId
            || $request->sourceModule !== $request->targetModule
            || $request->boundaryContractId !== null) {
            return new ModuleBoundaryDecision(false, ModuleBoundaryDecision::DENIED);
        }

        $resourceOwner = self::RESOURCE_OWNERS[$request->targetTable] ?? null;
        $isModuleOwnedWrite = $resourceOwner !== null
            && $resourceOwner === $request->sourceModule
            && $resourceOwner === $request->targetModule;

        return new ModuleBoundaryDecision(
            $isModuleOwnedWrite,
            $isModuleOwnedWrite ? ModuleBoundaryDecision::OWNED : ModuleBoundaryDecision::DENIED,
        );
    }

    private function hasValidData(ModuleBoundaryRequest $request): bool
    {
        foreach ([
            $request->sourceModule,
            $request->sourceEntityId,
            $request->targetModule,
            $request->targetEntityId,
            $request->targetTable,
            $request->operation,
        ] as $value) {
            if (trim($value) === '') {
                return false;
            }
        }

        return in_array($request->sourceModule, self::KNOWN_MODULES, true)
            && in_array($request->targetModule, self::KNOWN_MODULES, true)
            && $request->operation === 'write'
            && ($request->boundaryContractId === null || trim($request->boundaryContractId) !== '');
    }
}

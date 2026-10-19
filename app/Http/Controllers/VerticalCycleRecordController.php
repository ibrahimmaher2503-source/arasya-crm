<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use App\Modules\Shared\Application\VerticalIntegration\PersistCycleRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class VerticalCycleRecordController
{
    public function store(Request $request, RuntimeIdentity $identity, PersistCycleRecord $persist): JsonResponse
    {
        $actor = $identity->fromRequest($request);
        if (! in_array('cycles.write', $actor->inheritedPermissions, true)) {
            throw new UnauthorizedHttpException('Cycle write permission is required.');
        }

        $idempotencyKey = $request->header('Idempotency-Key');
        validator(['idempotency_key' => $idempotencyKey], ['idempotency_key' => ['nullable', 'string', 'max:128']])->validate();

        $validated = $request->validate([
            'record_type' => ['required', 'string', 'max:100'],
            'payload' => ['required', 'array'],
        ]);
        $recordId = $persist->execute(
            $actor->entityId,
            $actor->branchId ?? $actor->departmentId,
            $validated['record_type'],
            $validated['payload'],
            is_string($idempotencyKey) ? $idempotencyKey : null,
        );

        return response()->json(['data' => [
            'record_id' => $recordId,
            'entity_id' => $actor->entityId,
            'branch_id' => $actor->branchId ?? $actor->departmentId,
        ]], 201);
    }
}

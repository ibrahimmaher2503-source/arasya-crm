<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\VerticalIntegration;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PersistCycleRecord
{
    /** @param array<string, mixed> $payload */
    public function execute(string $entityId, string $branchId, string $recordType, array $payload, ?string $idempotencyKey = null): string
    {
        $idempotencyKey = hash('sha256', json_encode([$entityId, $branchId, $idempotencyKey ?? (string) Str::ulid()], JSON_THROW_ON_ERROR));
        $query = DB::table('vertical_cycle_records')->where('entity_id', $entityId)->where('branch_id', $branchId)->where('idempotency_key', $idempotencyKey);
        $existing = (clone $query)->first();
        if ($existing !== null) {
            return $this->replay($existing, $recordType, $payload);
        }

        $recordId = (string) Str::ulid();

        try {
            DB::transaction(static function () use ($recordId, $entityId, $branchId, $recordType, $payload, $idempotencyKey): void {
                DB::table('vertical_cycle_records')->insert([
                    'record_id' => $recordId,
                    'entity_id' => $entityId,
                    'branch_id' => $branchId,
                    'record_type' => $recordType,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'idempotency_key' => $idempotencyKey,
                    'audit_reference' => 'audit-'.$recordId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = (clone $query)->useWritePdo()->first();
            if ($existing === null) {
                throw $exception;
            }

            return $this->replay($existing, $recordType, $payload);
        }

        return $recordId;
    }

    /** @param array<string, mixed> $payload */
    private function replay(stdClass $existing, string $recordType, array $payload): string
    {
        if ($existing->record_type !== $recordType || $this->orderedPayload(json_decode($existing->payload, true, 512, JSON_THROW_ON_ERROR)) !== $this->orderedPayload($payload)) {
            throw new ConflictHttpException('Idempotency key has already been used for different request data.');
        }

        return $existing->record_id;
    }

    /** @param array<mixed> $payload
     * @return array<mixed>
     */
    private function orderedPayload(array $payload): array
    {
        if (! array_is_list($payload)) {
            ksort($payload);
        }
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->orderedPayload($value);
            }
        }

        return $payload;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Runtime;

use App\Modules\Identity\Application\SEC04\AccessControl;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

abstract readonly class LeadRuntime
{
    public function __construct(protected AccessControl $access) {}

    /** @param array<string, mixed> $filters
     * @return array{int, int}
     */
    protected function pageInput(array $filters): array
    {
        $values = [];
        foreach (['page' => [1, 1000000], 'per_page' => [25, 100]] as $field => [$default, $maximum]) {
            $value = $filters[$field] ?? $default;
            if ((! is_int($value) && ! is_string($value)) || filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || (int) $value > $maximum) {
                $this->invalid($field, 'Choose a positive bounded page value.');
            }
            $values[] = (int) $value;
        }

        return [$values[0], $values[1]];
    }

    /** @return array{page: int, per_page: int, total: int, last_page: int} */
    protected function pageMeta(int $page, int $perPage, int $total): array
    {
        return ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))];
    }

    /** @param list<string> $permissions
     * @param  callable(array<string, mixed>): mixed  $operation
     */
    protected function withReadActor(Request $request, array $permissions, callable $operation): mixed
    {
        return $this->access->withReadLock($request, $permissions, $operation);
    }

    /** @param list<string> $permissions
     * @param  callable(array<string, mixed>): mixed  $operation
     */
    protected function withActor(Request $request, array $permissions, callable $operation): mixed
    {
        $initial = $this->access->actor($request);
        $companyId = $this->actorString($initial, 'company_id');

        return DB::transaction(function () use ($request, $initial, $companyId, $permissions, $operation): mixed {
            if (DB::table('access_companies')->where('id', $companyId)->lockForUpdate()->first() === null) {
                throw new AuthenticationException('The access actor company is no longer active.');
            }

            $actor = $this->access->actor($request);
            if ($this->actorString($actor, 'id') !== $this->actorString($initial, 'id')
                || $this->actorString($actor, 'company_id') !== $companyId) {
                throw new AuthenticationException('The access actor changed during the operation.');
            }
            foreach ($permissions as $permission) {
                $this->access->requirePermission($actor, $permission);
            }

            return $operation($actor);
        }, 3);
    }

    /** @param array<string, mixed> $actor */
    protected function visibleLeads(array $actor): Builder
    {
        $query = DB::table('crm_leads')->where('crm_leads.company_id', $this->actorString($actor, 'company_id'));
        if ($this->hasPermission($actor, 'crm.manage')) {
            return $query
                ->where('crm_leads.branch_id', $this->actorString($actor, 'branch_id'))
                ->where('crm_leads.department_id', $this->actorString($actor, 'department_id'));
        }

        return $query
            ->where('crm_leads.branch_id', $this->actorString($actor, 'branch_id'))
            ->where('crm_leads.department_id', $this->actorString($actor, 'department_id'))
            ->where('crm_leads.assignee_id', $this->actorString($actor, 'id'));
    }

    /** @param array<string, mixed> $actor
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $changes
     */
    protected function advanceLead(array $actor, stdClass $lead, string $action, array $metadata, array $changes = []): void
    {
        $version = (int) $lead->version + 1;
        $updated = DB::table('crm_leads')->where('id', $lead->id)->where('company_id', $lead->company_id)->where('version', $lead->version)->update($changes + ['version' => $version, 'updated_at' => now()]);
        if ($updated !== 1) {
            throw new ConflictHttpException('Lead version is stale.');
        }
        $this->recordAudit($actor, (string) $lead->id, $action, $metadata + ['version' => $version]);
    }

    /** @param array<string, mixed>|null $actor
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>|null  $effectiveActor
     */
    protected function recordAudit(?array $actor, string $subjectId, string $action, array $metadata, ?array $effectiveActor = null, ?string $companyId = null, ?string $correlationId = null): void
    {
        $actor ??= $effectiveActor;
        if ($actor === null) {
            throw new RuntimeException('CRM audit actor is missing.');
        }
        $companyId ??= $this->actorString($actor, 'company_id');
        $actorId = $this->actorString($actor, 'id');
        $metadata['correlation_id'] = $correlationId ?? (string) Str::ulid();
        $metadata = $this->safeAuditMetadata($metadata);
        $leadId = str_starts_with($action, 'lead.') ? $subjectId : null;

        DB::table('crm_audit')->insert([
            'id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'action' => $action,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now()->toDateTimeString(),
        ]);

        $this->access->record($actor, $action, $subjectId, $metadata);
    }

    /** @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    protected function safeAuditMetadata(array $metadata): array
    {
        $safe = [];
        foreach (['fields', 'version', 'reason_provided', 'encrypted_reason', 'contact_fields', 'kind', 'replaces_id', 'correlation_id', 'outbox_intent', 'activity_id', 'task_id', 'transfer_id', 'sender_id', 'recipient_id', 'customer_id'] as $key) {
            if (! array_key_exists($key, $metadata)) {
                continue;
            }
            if (in_array($key, ['activity_id', 'task_id', 'transfer_id', 'sender_id', 'recipient_id', 'customer_id'], true)) {
                if ($metadata[$key] === null || (is_string($metadata[$key]) && Str::isUlid($metadata[$key]))) {
                    $safe[$key] = $metadata[$key];
                }

                continue;
            }
            if (in_array($key, ['fields', 'contact_fields'], true) && is_array($metadata[$key])) {
                $safe[$key] = array_values(array_filter($metadata[$key], static fn (mixed $value): bool => is_string($value) && in_array($value, ['name', 'organization', 'source', 'contacts', 'consent', 'preferences', 'notes', 'stage_id', 'assignee_id', 'archived', 'email', 'phone', 'whatsapp'], true)));
            } elseif ($key === 'version' && is_int($metadata[$key]) && $metadata[$key] >= 0) {
                $safe[$key] = $metadata[$key];
            } elseif ($key === 'reason_provided' && is_bool($metadata[$key])) {
                $safe[$key] = $metadata[$key];
            } elseif ($key === 'encrypted_reason' && is_string($metadata[$key]) && strlen($metadata[$key]) <= 4096) {
                $safe[$key] = $metadata[$key];
            } elseif ($key === 'kind' && is_string($metadata[$key]) && in_array($metadata[$key], ['stage', 'outcome'], true)) {
                $safe[$key] = $metadata[$key];
            } elseif ($key === 'replaces_id' && (is_string($metadata[$key]) || $metadata[$key] === null)) {
                $safe[$key] = $metadata[$key];
            } elseif ($key === 'correlation_id' && is_string($metadata[$key]) && mb_strlen($metadata[$key]) <= 26) {
                $safe[$key] = $metadata[$key];
            } elseif ($key === 'outbox_intent' && is_array($metadata[$key])) {
                $intent = $metadata[$key];
                if (($intent['event_type'] ?? null) === 'lead.archived'
                    && ($intent['aggregate_type'] ?? null) === 'Lead'
                    && isset($intent['event_id'], $intent['aggregate_id'], $intent['aggregate_version'], $intent['payload'])
                    && is_string($intent['event_id'])
                    && is_string($intent['aggregate_id'])
                    && is_int($intent['aggregate_version'])
                    && is_array($intent['payload'])) {
                    $payload = [];
                    foreach (['entity_id', 'lead_id', 'action', 'actor_id'] as $payloadKey) {
                        if (isset($intent['payload'][$payloadKey]) && is_string($intent['payload'][$payloadKey])) {
                            $payload[$payloadKey] = $intent['payload'][$payloadKey];
                        }
                    }
                    $safe[$key] = [
                        'event_id' => $intent['event_id'],
                        'event_type' => 'lead.archived',
                        'event_version' => 1,
                        'aggregate_type' => 'Lead',
                        'aggregate_id' => $intent['aggregate_id'],
                        'aggregate_version' => $intent['aggregate_version'],
                        'payload' => $payload,
                    ];
                }
            }
        }

        return $safe;
    }

    /** @return array<string, mixed> */
    protected function presentLead(stdClass $lead, bool $withContacts = false, bool $reveal = false): array
    {
        $result = [
            'id' => (string) $lead->id,
            'company_id' => (string) $lead->company_id,
            'branch_id' => (string) $lead->branch_id,
            'department_id' => (string) $lead->department_id,
            'name' => (string) $lead->name,
            'organization' => (string) $lead->organization,
            'source' => (string) $lead->source,
            'priority' => (string) ($lead->priority ?? 'normal'),
            'stage_id' => $lead->stage_id === null ? null : (string) $lead->stage_id,
            'assignee_id' => $lead->assignee_id === null ? null : (string) $lead->assignee_id,
            'customer_id' => $lead->customer_id === null ? null : (string) $lead->customer_id,
            'consent' => (bool) $lead->consent,
            'preferences' => property_exists($lead, 'preferences') ? $this->decodePreferences($lead->preferences) : [],
            'notes' => property_exists($lead, 'notes') && $lead->notes !== null ? (string) $lead->notes : null,
            'archived' => (bool) $lead->archived,
            'version' => (int) $lead->version,
            'created_at' => (string) $lead->created_at,
            'updated_at' => (string) $lead->updated_at,
        ];

        if ($withContacts) {
            $contacts = $this->decryptContacts((string) $lead->contacts);
            $result['contacts'] = $reveal
                ? $contacts
                : array_map(static fn (?string $value): ?string => $value === null ? null : '[hidden]', $contacts);
        }

        return $result;
    }

    /** @param array<array-key, mixed> $data
     * @param  list<string>  $allowed
     */
    protected function onlyKeys(array $data, array $allowed): void
    {
        foreach (array_keys($data) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                $this->invalid('data', 'The submitted field is not supported.');
            }
        }
    }

    protected function booleanInput(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (in_array($value, [0, '0'], true)) {
            return false;
        }
        if (in_array($value, [1, '1'], true)) {
            return true;
        }

        $this->invalid($field, 'A valid boolean value is required.');
    }

    protected function assertVersion(int $expected, int $actual): void
    {
        if ($expected !== $actual) {
            throw new ConflictHttpException('Lead version is stale.');
        }
    }

    /** @return array{email: bool, phone: bool, whatsapp: bool} */
    protected function preferencesInput(mixed $value): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            $this->invalid('preferences', 'Preferences must be an object.');
        }
        $this->onlyKeys($value, ['email', 'phone', 'whatsapp']);

        return [
            'email' => array_key_exists('email', $value) ? $this->booleanInput($value['email'], 'preferences.email') : false,
            'phone' => array_key_exists('phone', $value) ? $this->booleanInput($value['phone'], 'preferences.phone') : false,
            'whatsapp' => array_key_exists('whatsapp', $value) ? $this->booleanInput($value['whatsapp'], 'preferences.whatsapp') : false,
        ];
    }

    /** @return array{email: bool, phone: bool, whatsapp: bool} */
    protected function decodePreferences(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (! is_array($value)) {
            throw new RuntimeException('Lead communication preferences could not be read.');
        }

        return $this->preferencesInput($value);
    }

    /** @param array<string, mixed> $actor */
    protected function hasPermission(array $actor, string $permission): bool
    {
        return is_array($actor['permissions'] ?? null) && in_array($permission, $actor['permissions'], true);
    }

    /** @param array<string, mixed> $actor */
    protected function actorString(array $actor, string $key): string
    {
        $value = $actor[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new AuthenticationException('The access actor is invalid.');
        }

        return $value;
    }

    protected function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /** @return array{email: ?string, phone: ?string, whatsapp: ?string} */
    protected function decryptContacts(string $encrypted): array
    {
        try {
            $decoded = json_decode(Crypt::decryptString($encrypted), true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new RuntimeException('Lead contact data could not be read.');
        }
        if (! is_array($decoded)) {
            throw new RuntimeException('Lead contact data could not be read.');
        }

        $contacts = ['email' => null, 'phone' => null, 'whatsapp' => null];
        foreach ($contacts as $field => $_) {
            if (isset($decoded[$field]) && is_string($decoded[$field])) {
                $contacts[$field] = $decoded[$field];
            } elseif (array_key_exists($field, $decoded) && $decoded[$field] !== null) {
                throw new RuntimeException('Lead contact data could not be read.');
            }
        }

        return $contacts;
    }
}

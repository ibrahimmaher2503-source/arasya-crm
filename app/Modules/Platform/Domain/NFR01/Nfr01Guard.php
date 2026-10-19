<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\NFR01;

final class Nfr01Guard
{
    /** @param list<string> $requestedFields */
    public function authorize(Nfr01AccessContext $context, Nfr01Resource $resource, array $requestedFields): Nfr01Decision
    {
        if ($context->revoked) {
            return $this->deny('session_revoked');
        }
        if (! $context->secureTransport) {
            return $this->deny('transport_not_secure');
        }
        if (! $context->secretStoreConfigured) {
            return $this->deny('secret_protection_unavailable');
        }
        if ($context->rateLimitRemaining < 1) {
            return $this->deny('rate_limit_exhausted');
        }
        if (! $context->can('record.read')) {
            return $this->deny('permission_denied');
        }
        if ($context->entityId !== $resource->entityId) {
            return $this->deny('entity_scope_denied');
        }
        if ($resource->ownerId !== $context->actorId && ! $context->can('record.read.any')) {
            return $this->deny('object_scope_denied');
        }

        $visible = [];
        foreach ($requestedFields as $field) {
            if (! array_key_exists($field, $resource->fields)) {
                return $this->deny('field_not_allowed');
            }
            if (in_array($field, $resource->sensitiveFields, true)) {
                if (! $context->can('record.read.sensitive')) {
                    $visible[$field] = null;

                    continue;
                }
            }
            $visible[$field] = $resource->fields[$field];
        }

        $reason = count(array_filter($requestedFields, fn (string $field): bool => in_array($field, $resource->sensitiveFields, true))) > 0
            && ! $context->can('record.read.sensitive')
            ? 'sensitive_field_masked'
            : 'authorized';

        return new Nfr01Decision(true, $reason, $visible);
    }

    private function deny(string $reason): Nfr01Decision
    {
        return new Nfr01Decision(false, $reason, []);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure\CORE03;

use App\Modules\Organization\Application\CORE03\ProposeTransition;
use App\Modules\Organization\Domain\CORE03\TransitionGuard;
use App\Modules\Organization\Domain\CORE03\TransitionHistoryEntry;
use App\Modules\Organization\Domain\CORE03\TransitionPolicy;
use App\Modules\Organization\Domain\CORE03\WorkflowState;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Evaluates only explicitly supplied synthetic fixtures; never authorizes callers. */
final class TransitionProposalDiagnosticAdapter
{
    private const MAX_ITEMS = 256;

    private const MAX_STRING = 256;

    /** @return array{decision: 'allow'|'deny', code: string, replayed: bool, intent_present: bool}|null */
    public function evaluate(mixed $input): ?array
    {
        if (! is_array($input) || ! $this->exactKeys($input, ['state', 'edges', 'permissions', 'validatorsPassed', 'entityId', 'to', 'expectedVersion', 'actorId', 'correlationId', 'requestId', 'occurredAt'])) {
            return null;
        }

        try {
            $stateInput = $input['state'];
            if (! is_array($stateInput) || array_is_list($stateInput) || ! $this->exactKeys($stateInput, ['entityId', 'subjectId', 'status', 'version', 'history'])
                || ! is_int($stateInput['version']) || $stateInput['version'] < 0 || ! is_array($stateInput['history'])
                || ! array_is_list($stateInput['history']) || count($stateInput['history']) > self::MAX_ITEMS) {
                return null;
            }

            $entityId = $this->string($stateInput['entityId']);
            $subjectId = $this->string($stateInput['subjectId']);
            $status = $this->string($stateInput['status']);
            if ($entityId === null || $subjectId === null || $status === null) {
                return null;
            }

            $history = [];
            foreach ($stateInput['history'] as $entry) {
                if (! is_array($entry) || array_is_list($entry) || ! $this->exactKeys($entry, ['from', 'to', 'actorId', 'correlationId', 'requestId', 'entityId', 'subjectId', 'sourceVersion', 'occurredAt']) || ! is_int($entry['sourceVersion']) || $entry['sourceVersion'] < 0) {
                    return null;
                }
                $values = [];
                foreach (['from', 'to', 'actorId', 'correlationId', 'requestId', 'entityId', 'subjectId'] as $key) {
                    $values[$key] = $this->string($entry[$key]);
                    if ($values[$key] === null) {
                        return null;
                    }
                }
                $occurredAt = $this->timestamp($entry['occurredAt']);
                if ($occurredAt === null) {
                    return null;
                }
                $history[] = new TransitionHistoryEntry($values['from'], $values['to'], $values['actorId'], $values['correlationId'], $values['requestId'], $values['entityId'], $values['subjectId'], $entry['sourceVersion'], $occurredAt);
            }

            if (! is_array($input['edges']) || array_is_list($input['edges']) || count($input['edges']) > self::MAX_ITEMS
                || ! is_array($input['permissions']) || ! array_is_list($input['permissions']) || count($input['permissions']) > self::MAX_ITEMS
                || ! is_bool($input['validatorsPassed']) || ! is_int($input['expectedVersion']) || $input['expectedVersion'] < 0) {
                return null;
            }
            $edges = [];
            foreach ($input['edges'] as $from => $destinations) {
                if (! is_string($from) || $this->string($from) === null || ! is_array($destinations) || array_is_list($destinations) || count($destinations) > self::MAX_ITEMS) {
                    return null;
                }
                foreach ($destinations as $to => $permission) {
                    if (! is_string($to) || $this->string($to) === null || ! is_string($permission) || $this->string($permission) === null) {
                        return null;
                    }
                }
                $edges[$from] = $destinations;
            }
            $permissions = [];
            foreach ($input['permissions'] as $permission) {
                if (! is_string($permission) || $this->string($permission) === null) {
                    return null;
                }
                $permissions[] = $permission;
            }
            $context = [];
            foreach (['entityId', 'to', 'actorId', 'correlationId', 'requestId'] as $key) {
                $context[$key] = $this->string($input[$key]);
                if ($context[$key] === null) {
                    return null;
                }
            }
            $when = $this->timestamp($input['occurredAt']);
            if ($when === null) {
                return null;
            }

            $source = new WorkflowState($entityId, $subjectId, $status, $stateInput['version'], $history);
            $proposal = (new ProposeTransition(new TransitionGuard(new TransitionPolicy($edges))))->propose(
                $source, $context['entityId'], $context['to'], $input['expectedVersion'], $permissions, $input['validatorsPassed'],
                $context['actorId'], $context['correlationId'], $context['requestId'], $when,
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        $code = match ($proposal->reason) {
            'accepted' => 'transition_proposed',
            'replayed' => 'transition_replayed',
            'entity_mismatch' => 'entity_mismatch',
            'stale_version' => 'stale_version',
            'permission_missing' => 'permission_missing',
            'validation_failed' => 'validation_failed',
            'request_id_conflict' => 'request_id_conflict',
            'version_exhausted' => 'version_exhausted',
            'transition_not_allowed' => 'transition_not_allowed',
            default => 'transition_denied',
        };

        return ['decision' => $proposal->accepted ? 'allow' : 'deny', 'code' => $code, 'replayed' => $proposal->replayed, 'intent_present' => $proposal->auditOutboxIntent !== null];
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && strlen($value) <= self::MAX_STRING ? $value : null;
    }

    private function timestamp(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || strlen($value) > 40 || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value) !== 1) {
            return null;
        }
        if (! str_ends_with($value, 'Z')) {
            $offset = substr($value, -6);
            $offsetHours = (int) substr($offset, 1, 2);
            $offsetMinutes = (int) substr($offset, 4, 2);
            if ($offsetHours > 23 || $offsetMinutes > 59) {
                return null;
            }
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $this->normalizeTimestamp($value));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    private function normalizeTimestamp(string $value): string
    {
        $value = str_ends_with($value, 'Z') ? substr($value, 0, -1).'+00:00' : $value;
        $parts = explode('.', $value, 2);
        if (count($parts) === 1) {
            return substr($parts[0], 0, 19).'.000000'.substr($parts[0], 19);
        }
        [$base, $fractionAndZone] = $parts;
        if (preg_match('/^(\d{1,6})(.*)$/D', $fractionAndZone, $matches) !== 1) {
            return $value;
        }

        return substr($base, 0, 19).'.'.str_pad($matches[1], 6, '0').$matches[2];
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  list<string>  $keys
     */
    private function exactKeys(array $value, array $keys): bool
    {
        if (count($value) !== count($keys)) {
            return false;
        }
        foreach ($keys as $key) {
            if (! array_key_exists($key, $value)) {
                return false;
            }
        }

        return true;
    }
}

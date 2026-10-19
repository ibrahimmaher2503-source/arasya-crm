<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure\CORE02;

use App\Modules\Organization\Application\CORE02\ProjectRecordLinkage;
use App\Modules\Organization\Domain\CORE02\RecordIdentity;
use App\Modules\Organization\Domain\CORE02\RecordKind;
use App\Modules\Organization\Domain\CORE02\RecordLink;
use App\Modules\Organization\Domain\CORE02\RecordLinkageGuard;
use InvalidArgumentException;

/** Projects only explicitly supplied, synthetic CORE02 identity snapshots. */
final class RecordLinkageDiagnosticAdapter
{
    private const MAX_RECORDS = 256;

    /**
     * @return array{decision: 'allow'|'deny', code: 'core02_linkage_projected'|'core02_linkage_denied'|'invalid_diagnostic_input'}
     */
    public function evaluate(mixed $input): array
    {
        if (! is_array($input)
            || ! $this->hasExactKeys($input, ['source', 'links', 'catalog'])
            || ! is_array($input['links'])
            || ! array_is_list($input['links'])
            || count($input['links']) > self::MAX_RECORDS
            || ! is_array($input['catalog'])
            || ! array_is_list($input['catalog'])
            || count($input['catalog']) > self::MAX_RECORDS) {
            return $this->invalidInput();
        }

        try {
            $source = $this->identity($input['source']);
            if ($source === null) {
                return $this->invalidInput();
            }

            $links = [];
            foreach ($input['links'] as $targetInput) {
                $target = $this->identity($targetInput);
                if ($target === null) {
                    return $this->invalidInput();
                }
                $links[] = new RecordLink($target);
            }

            $catalog = [];
            foreach ($input['catalog'] as $candidateInput) {
                $candidate = $this->identity($candidateInput);
                if ($candidate === null) {
                    return $this->invalidInput();
                }
                $catalog[] = $candidate;
            }
        } catch (InvalidArgumentException) {
            return $this->invalidInput();
        }

        $projector = new ProjectRecordLinkage(new RecordLinkageGuard);
        $model = $projector->project($source, $links, $catalog);

        return $model === null
            ? ['decision' => 'deny', 'code' => 'core02_linkage_denied']
            : ['decision' => 'allow', 'code' => 'core02_linkage_projected'];
    }

    private function identity(mixed $value): ?RecordIdentity
    {
        if (! is_array($value)
            || array_is_list($value)
            || ! $this->hasExactKeys($value, ['kind', 'id', 'entityId'])
            || ! is_string($value['kind'])
            || ! is_string($value['id'])
            || ! is_string($value['entityId'])
            || strlen($value['id']) > 128
            || strlen($value['entityId']) > 128) {
            return null;
        }

        $kind = RecordKind::tryFrom($value['kind']);
        if ($kind === null) {
            return null;
        }

        return new RecordIdentity($kind, $value['id'], $value['entityId']);
    }

    /** @param array<mixed> $value
     * @param  list<string>  $keys
     */
    private function hasExactKeys(array $value, array $keys): bool
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

    /** @return array{decision: 'deny', code: 'invalid_diagnostic_input'} */
    private function invalidInput(): array
    {
        return ['decision' => 'deny', 'code' => 'invalid_diagnostic_input'];
    }
}

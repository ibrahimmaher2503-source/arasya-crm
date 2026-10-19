<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure\CORE01;

use App\Modules\Organization\Application\CORE01\LinkOrganizationReferences;
use App\Modules\Organization\Domain\CORE01\OrganizationReference;
use App\Modules\Organization\Domain\CORE01\OrganizationReferenceGuard;

/** Evaluates explicit synthetic CORE01 reference fixtures without external access. */
final class ReferenceDiagnosticAdapter
{
    private const MAX_IDENTIFIER_LENGTH = 128;

    /**
     * @return array{decision: 'allow'|'deny', code: 'core01_link_allowed'|'core01_link_denied'|'core01_reference_matched'|'core01_reference_not_matched'|'invalid_diagnostic_input'}
     */
    public function evaluate(mixed $input): array
    {
        if (! is_array($input) || ! isset($input['operation']) || ! is_string($input['operation'])) {
            return $this->invalidInput();
        }

        $linker = new LinkOrganizationReferences(new OrganizationReferenceGuard);
        if ($input['operation'] === 'link' && $this->isValidLinkInput($input)) {
            $allowed = $linker->allowsLink($this->reference($input['source']), $this->reference($input['target']));

            return $allowed
                ? ['decision' => 'allow', 'code' => 'core01_link_allowed']
                : ['decision' => 'deny', 'code' => 'core01_link_denied'];
        }

        if ($input['operation'] === 'match' && $this->isValidMatchInput($input)) {
            $matched = $linker->matches(
                $input['kind'],
                $input['referenceId'],
                $input['entityId'],
                $this->reference($input['reference']),
            );

            return $matched
                ? ['decision' => 'allow', 'code' => 'core01_reference_matched']
                : ['decision' => 'deny', 'code' => 'core01_reference_not_matched'];
        }

        return $this->invalidInput();
    }

    /** @param array<mixed> $input */
    private function isValidLinkInput(array $input): bool
    {
        return $this->hasExactKeys($input, ['operation', 'source', 'target'])
            && is_array($input['source'])
            && is_array($input['target'])
            && $this->isValidReferenceShape($input['source'])
            && $this->isValidReferenceShape($input['target']);
    }

    /** @param array<mixed> $input */
    private function isValidMatchInput(array $input): bool
    {
        return $this->hasExactKeys($input, ['operation', 'kind', 'referenceId', 'entityId', 'reference'])
            && $this->isIdentifier($input['kind'])
            && $this->isIdentifier($input['referenceId'])
            && $this->isIdentifier($input['entityId'])
            && is_array($input['reference'])
            && $this->isValidReferenceShape($input['reference']);
    }

    /** @param array<mixed> $reference */
    private function isValidReferenceShape(array $reference): bool
    {
        return $this->hasExactKeys($reference, ['kind', 'referenceId', 'entityId'])
            && $this->isIdentifier($reference['kind'])
            && $this->isIdentifier($reference['referenceId'])
            && $this->isIdentifier($reference['entityId']);
    }

    /** @param array{kind:string,referenceId:string,entityId:string} $reference */
    private function reference(array $reference): OrganizationReference
    {
        return new OrganizationReference($reference['kind'], $reference['referenceId'], $reference['entityId']);
    }

    /**
     * @param  array<mixed>  $value
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

    private function isIdentifier(mixed $value): bool
    {
        return is_string($value)
            && strlen($value) <= self::MAX_IDENTIFIER_LENGTH;
    }

    /** @return array{decision: 'deny', code: 'invalid_diagnostic_input'} */
    private function invalidInput(): array
    {
        return ['decision' => 'deny', 'code' => 'invalid_diagnostic_input'];
    }
}

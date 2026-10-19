<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\SEC02;

use App\Modules\Identity\Application\SEC02\ProjectContactReadModel;
use App\Modules\Identity\Domain\SEC02\ContactMaskingProjector;

/** Local, read-only projection for explicit synthetic fixtures; no caller can authorize reveal. */
final class ContactDiagnosticAdapter
{
    private const MAX_CONTACT_VALUE_LENGTH = 256;

    /** @return array{contact: null, contact_visibility: 'hidden'}|null */
    public function project(mixed $input): ?array
    {
        if (! is_array($input)
            || ! $this->hasExactKeys($input, ['channel', 'contact'])
            || ! is_string($input['channel'])
            || ! in_array($input['channel'], ContactMaskingProjector::CHANNELS, true)
            || ! is_array($input['contact'])
            || ! $this->hasExactKeys($input['contact'], ['phone', 'whatsapp', 'email'])) {
            return null;
        }

        foreach ($input['contact'] as $value) {
            if ($value !== null && (! is_string($value) || strlen($value) > self::MAX_CONTACT_VALUE_LENGTH)) {
                return null;
            }
        }

        return (new ProjectContactReadModel(new ContactMaskingProjector))
            ->project($input['channel'], $input['contact'])
            ->toArray();
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
}

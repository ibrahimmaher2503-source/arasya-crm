<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\SEC02;

use JsonSerializable;

final readonly class ContactReadModel implements JsonSerializable
{
    /** @return array{contact: null, contact_visibility: 'hidden'} */
    public function toArray(): array
    {
        return [
            'contact' => null,
            'contact_visibility' => 'hidden',
        ];
    }

    /** @return array{contact: null, contact_visibility: 'hidden'} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return array{contact: null, contact_visibility: 'hidden'} */
    public function __debugInfo(): array
    {
        return $this->toArray();
    }
}

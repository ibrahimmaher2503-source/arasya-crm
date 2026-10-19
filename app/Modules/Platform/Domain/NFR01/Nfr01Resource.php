<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\NFR01;

use InvalidArgumentException;

final readonly class Nfr01Resource
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $sensitiveFields
     */
    public function __construct(
        public string $id,
        public string $entityId,
        public string $ownerId,
        public array $fields,
        public array $sensitiveFields = [],
    ) {
        if (trim($id) === '' || trim($entityId) === '' || trim($ownerId) === '') {
            throw new InvalidArgumentException('Resource identity is required.');
        }
        foreach ($sensitiveFields as $field) {
            if (! array_key_exists($field, $fields)) {
                throw new InvalidArgumentException('Sensitive fields must exist in the resource.');
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE02;

use InvalidArgumentException;

final readonly class RecordIdentity
{
    public function __construct(
        public RecordKind $kind,
        public string $id,
        public string $entityId,
    ) {
        if (! self::isValidId($id) || ! self::isValidId($entityId)) {
            throw new InvalidArgumentException('Malformed record identity.');
        }
    }

    private static function isValidId(string $value): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/D', $value) === 1;
    }
}

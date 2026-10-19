<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE02;

final readonly class RecordLink
{
    public function __construct(public RecordIdentity $target) {}
}

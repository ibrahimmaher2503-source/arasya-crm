<?php

declare(strict_types=1);

namespace App\Modules\Employees\Domain\EMP01;

use App\Shared\Outbox\OutboxEvent;

final readonly class EmployeeTransitionResult
{
    public function __construct(
        public bool $accepted,
        public EmployeeProfile $profile,
        public ?OutboxEvent $intent,
    ) {}
}

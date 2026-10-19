<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\DASH04;

use App\Modules\Reporting\Domain\DASH04\OperationalAlert;

final class TransitionAlert
{
    public function acknowledge(OperationalAlert $alert, string $event): OperationalAlert
    {
        return $alert->transition('acknowledged', $event);
    }

    public function resolve(OperationalAlert $alert, string $event): OperationalAlert
    {
        return $alert->transition('resolved', $event);
    }
}

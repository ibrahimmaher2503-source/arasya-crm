<?php

declare(strict_types=1);

namespace App\Modules\Performance\Domain\KPI01;

enum TargetOwnerType: string
{
    case Department = 'department';
    case Person = 'person';
}

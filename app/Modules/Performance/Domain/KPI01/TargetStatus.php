<?php

declare(strict_types=1);

namespace App\Modules\Performance\Domain\KPI01;

enum TargetStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}

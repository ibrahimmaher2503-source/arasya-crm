<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\DASH01;

enum MetricSource: string
{
    case Sales = 'sales';
    case Revenue = 'revenue';
    case Cash = 'cash';
    case Expenses = 'expenses';
    case Margin = 'margin';
    case Stock = 'stock';
    case Risk = 'risk';
}

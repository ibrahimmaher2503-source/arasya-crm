<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain\CORE02;

enum RecordKind: string
{
    case Customer = 'customer';
    case Lead = 'lead';
    case Employee = 'employee';
    case Project = 'project';
    case Contract = 'contract';
    case Quotation = 'quotation';
    case StockItem = 'stock_item';
    case Invoice = 'invoice';
    case Expense = 'expense';
    case Document = 'document';
}

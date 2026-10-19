<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\SEC03;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Delegated = 'delegated';
    case Expired = 'expired';
}

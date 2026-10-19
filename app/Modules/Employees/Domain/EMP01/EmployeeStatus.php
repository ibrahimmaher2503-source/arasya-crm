<?php

declare(strict_types=1);

namespace App\Modules\Employees\Domain\EMP01;

enum EmployeeStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
}

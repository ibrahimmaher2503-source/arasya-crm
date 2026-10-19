<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Employees\Application\EMP01\EmployeeWorkDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final readonly class EmployeeWorkController
{
    public function __construct(private EmployeeWorkDirectory $directory) {}

    public function index(Request $request): View
    {
        return view('employees.work', $this->directory->read($request));
    }
}

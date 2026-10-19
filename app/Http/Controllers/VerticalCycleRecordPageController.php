<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class VerticalCycleRecordPageController
{
    public function __invoke(Request $request, RuntimeIdentity $identity): View
    {
        $identity->fromRequest($request);

        return view('vertical.cycle-records', ['title' => 'دورة تشغيلية', 'active' => 'cycles']);
    }
}

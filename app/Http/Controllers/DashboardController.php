<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Application\SEC04\AccessControl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class DashboardController
{
    public function __construct(private AccessControl $access) {}

    public function home(Request $request): RedirectResponse
    {
        $this->authorizedActor($request);

        return redirect('/dashboard');
    }

    public function __invoke(Request $request): View
    {
        $actor = $this->authorizedActor($request);

        return view('dashboard.home', [
            'actor' => $actor,
            'permissions' => $actor['permissions'],
            'locale' => $request->query('locale') === 'en' ? 'en' : 'ar',
        ]);
    }

    /** @return array<string, mixed> */
    private function authorizedActor(Request $request): array
    {
        abort_unless(app()->environment(['local', 'testing']) === true, 404);
        app()->setLocale('ar');
        $actor = $this->access->actor($request);
        abort_if($actor['permissions'] === [], 403);

        return $actor;
    }
}

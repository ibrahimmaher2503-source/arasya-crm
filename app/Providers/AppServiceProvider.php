<?php

namespace App\Providers;

use App\Modules\Identity\Application\SEC04\AccessControl;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Laravel merges base auth arrays into empty app arrays during configuration loading.
        /** @var ConfigRepository $config */
        $config = $this->app->make('config');
        $config->set([
            'auth.defaults.guard' => null,
            'auth.defaults.passwords' => null,
            'auth.guards' => [],
            'auth.providers' => [],
            'auth.passwords' => [],
        ]);

        $this->app->register(HelpServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('components.erp.application-sidebar', function (View $view): void {
            $request = request();
            if ($request->hasSession() && $request->session()->has('access_user_id')) {
                $actor = app(AccessControl::class)->actor($request);
                $view->with(['actor' => $actor, 'permissions' => $actor['permissions']]);
            }
        });
    }
}

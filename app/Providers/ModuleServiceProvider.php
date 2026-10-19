<?php

namespace App\Providers;

use App\Modules\Shared\Application\ModuleCatalog;
use Illuminate\Support\ServiceProvider;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleCatalog::class);
    }
}

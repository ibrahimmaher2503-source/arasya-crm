<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Local-only Telescope wiring adapted to the My Arasya custom session auth.
 *
 * This project has no configured Laravel auth guard (see AppServiceProvider),
 * so the stock stub that type-hints App\Models\User cannot be used. Access is
 * restricted to loopback requests in local/testing environments, and sensitive
 * request data is always hidden (even in local).
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment(['local', 'testing']);

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal): bool {
            return $isLocal
                || $entry->isReportableException()
                || $entry->isFailedRequest()
                || $entry->isFailedJob()
                || $entry->isScheduledTask()
                || $entry->hasMonitoredTag();
        });
    }

    /**
     * Always hide credentials, tokens and cookies, in every environment.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters([
            '_token',
            '_method',
            'password',
            'password_confirmation',
            'current_password',
            'remember',
            'secret',
            'token',
            'api_key',
            'api_secret',
            'authorization',
            'cvv',
            'card',
        ]);

        Telescope::hideRequestHeaders([
            'authorization',
            'cookie',
            'set-cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'x-api-key',
            'php-auth-pw',
        ]);
    }

    /**
     * Authorize Telescope for loopback local developers only. There is no
     * Laravel guard, so $request->user() is expected to be null.
     */
    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(function ($request): bool {
            return ($this->app->environment(['local', 'testing'])
                    && in_array($request->ip(), ['127.0.0.1', '::1'], true))
                || Gate::check('viewTelescope', [$request->user()]);
        });
    }

    /**
     * Fallback gate for non-local environments (unused here, but kept safe).
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user = null): bool {
            return false;
        });
    }
}

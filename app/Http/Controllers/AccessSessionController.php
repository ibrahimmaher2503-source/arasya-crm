<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Application\SEC04\AccessControl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class AccessSessionController
{
    public function create(): View
    {
        abort_unless(app()->environment(['local', 'testing']) === true, 404);
        app()->setLocale('ar');

        return view('access.login', ['title' => 'تسجيل الدخول', 'locale' => 'ar']);
    }

    public function store(Request $request, AccessControl $access): JsonResponse|RedirectResponse
    {
        abort_unless(app()->environment(['local', 'testing']) === true, 404);
        $data = $request->validate(['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'string', 'max:1024']]);
        $key = 'access-login:'.hash('sha256', mb_strtolower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);
        $user = $access->authenticate($data['email'], $data['password']);
        RateLimiter::clear($key);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put(['access_user_id' => $user['id'], 'access_session_version' => $user['session_version']]);
        $actor = $access->actor($request);
        $access->record($actor, 'auth.login', (string) $user['id']);

        return $request->expectsJson()
            ? response()->json(['data' => ['user' => $user]])
            : redirect($request->query('locale') === 'en' ? '/dashboard?locale=en' : '/dashboard');
    }

    public function destroy(Request $request, AccessControl $access): JsonResponse|RedirectResponse
    {
        abort_unless(app()->environment(['local', 'testing']) === true, 404);
        $actor = $access->actor($request);
        $access->record($actor, 'auth.logout', (string) $actor['id']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson() ? response()->json(['data' => ['logged_out' => true]]) : redirect('/login');
    }
}

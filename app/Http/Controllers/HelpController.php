<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Help\Application\HelpCatalog;
use App\Modules\Help\Application\TutorialProgress;
use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use App\Modules\Identity\Domain\SEC01\ActorAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final readonly class HelpController
{
    public function __construct(private RuntimeIdentity $identity, private HelpCatalog $catalog, private TutorialProgress $progress) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $input = $request->validate([
            'locale' => ['sometimes', 'string', Rule::in(['ar', 'en'])],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);
        $locale = $input['locale'] ?? 'ar';
        app()->setLocale($locale);
        $tour = $this->catalog->tour($actor, 'help-center', $locale);
        $tourConfig = $this->catalog->tourConfig($actor, 'help-center', $locale);

        return response()->view('help.index', $this->catalog->index($actor, $locale, (string) ($input['q'] ?? '')) + [
            'actor' => $actor,
            'permissions' => $actor->inheritedPermissions,
            'locale' => $locale,
            'title' => __('help.ui.title', [], $locale),
            'pageSubtitle' => __('help.ui.subtitle', [], $locale),
            'pageIcon' => 'help',
            'breadcrumbs' => [['label' => __('help.ui.title', [], $locale)]],
            'tour' => $tour,
            'tourConfig' => $tourConfig,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function article(Request $request, string $article): Response
    {
        $actor = $this->actor($request);
        $locale = $request->query('locale') === 'en' ? 'en' : 'ar';
        app()->setLocale($locale);
        $page = $this->catalog->page($actor, $article, $locale);

        return response()->view('help.article', [
            'actor' => $actor,
            'permissions' => $actor->inheritedPermissions,
            'locale' => $locale,
            'title' => $page['title'],
            'pageSubtitle' => $page['summary'] ?? '',
            'pageIcon' => 'help',
            'breadcrumbs' => [
                ['label' => __('help.ui.title', [], $locale), 'url' => route('help.index', ['locale' => $locale])],
                ['label' => $page['title']],
            ],
            'page' => $page,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function progress(Request $request, string $tour): JsonResponse
    {
        try {
            $actor = $this->actor($request);

            return $this->privateJson(['data' => $this->progress->read($actor, $tour, $this->locale($request))]);
        } catch (AuthenticationException|UnauthorizedHttpException) {
            return $this->error(401, 'unauthenticated', $this->message('help.errors.unauthenticated'));
        } catch (AccessDeniedHttpException) {
            return $this->error(403, 'forbidden', $this->message('help.errors.forbidden'));
        } catch (NotFoundHttpException) {
            return $this->error(404, 'not_found', $this->message('help.errors.not_found'));
        } catch (ConflictHttpException) {
            return $this->error(409, 'stale_tour_version', $this->message('help.errors.stale_version'));
        } catch (QueryException) {
            return $this->error(503, 'progress_unavailable', $this->message('help.errors.progress_unavailable'));
        }
    }

    public function saveProgress(Request $request, string $tour): JsonResponse
    {
        try {
            $actor = $this->actor($request);
            if (strlen($request->getContent()) > 512) {
                return $this->error(413, 'payload_too_large', $this->message('help.errors.invalid_progress'));
            }

            $input = $request->json()->all();
            $keys = array_keys($input);
            sort($keys);
            if (! $request->isJson() || $keys !== ['status', 'step_key', 'version']) {
                throw ValidationException::withMessages(['data' => $this->message('help.errors.invalid_progress')]);
            }
            $data = Validator::make($input, [
                'version' => ['required', 'integer', 'min:1', 'max:1000'],
                'step_key' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9-]{0,79}$/D'],
                'status' => ['required', 'string', Rule::in(['paused', 'in_progress', 'completed', 'skipped'])],
            ])->validate();

            return $this->privateJson(['data' => $this->progress->save($actor, $tour, [
                'version' => (int) $data['version'],
                'step_key' => $data['step_key'],
                'status' => $data['status'],
            ], $this->locale($request))]);
        } catch (AuthenticationException|UnauthorizedHttpException) {
            return $this->error(401, 'unauthenticated', $this->message('help.errors.unauthenticated'));
        } catch (AccessDeniedHttpException) {
            return $this->error(403, 'forbidden', $this->message('help.errors.forbidden'));
        } catch (NotFoundHttpException) {
            return $this->error(404, 'not_found', $this->message('help.errors.not_found'));
        } catch (ConflictHttpException) {
            return $this->error(409, 'stale_tour_version', $this->message('help.errors.stale_version'));
        } catch (ValidationException) {
            return $this->error(422, 'invalid_progress', $this->message('help.errors.invalid_progress'));
        } catch (QueryException) {
            return $this->error(503, 'progress_unavailable', $this->message('help.errors.progress_unavailable'));
        }
    }

    private function actor(Request $request): ActorAccess
    {
        if (! $request->hasSession()
            || ! is_string($request->session()->get('access_user_id'))
            || $request->session()->get('access_user_id') === '') {
            throw new AuthenticationException('A persisted access identity is required.');
        }

        return $this->identity->fromRequest($request);
    }

    private function locale(Request $request): string
    {
        return $request->query('locale') === 'en' ? 'en' : 'ar';
    }

    /** @param array<string, mixed> $data */
    private function privateJson(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    private function message(string $key): string
    {
        $message = __($key);

        return is_string($message) ? $message : $key;
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return $this->privateJson(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}

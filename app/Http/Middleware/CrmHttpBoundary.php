<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\OriginMismatchException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

final class CrmHttpBoundary
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('crm', 'crm/*')) {
            return $next($request);
        }

        // Only explicit browser HTML requests opt into pages; legacy */* callers remain JSON.
        $html = $request->acceptsHtml() && ! $request->expectsJson()
            && ($request->headers->get('Sec-Fetch-Mode') === 'navigate' || $request->headers->get('Accept') === 'text/html')
            && ! $request->is('crm/taxonomy', 'crm/recipients', 'crm/transfers/inbox');
        $request->attributes->set('crm_html', $html);
        // Keep framework errors sanitized and prevent validation redirects flashing private input.
        $request->headers->set('Accept', 'application/json');

        if (! app()->environment(['local', 'testing'])) {
            return $this->error('not_found', 'Resource not found.', 404);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $response = $this->renderException($exception);
        }

        if ($response->getStatusCode() >= 400) {
            $response = $this->sanitizeResponse($response);
            if ($html) {
                $body = json_decode((string) $response->getContent(), true);
                $response = response()->view('crm.error', ['status' => $response->getStatusCode(), 'message' => $body['message'], 'fields' => array_keys($body['errors'] ?? []), 'errors' => new ViewErrorBag], $response->getStatusCode());
            }
        }

        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function renderException(Throwable $exception): JsonResponse
    {
        if ($exception instanceof AuthenticationException || $exception instanceof UnauthorizedHttpException) {
            return $this->error('unauthenticated', 'Authentication is required.', 401);
        }

        if ($exception instanceof AuthorizationException || $exception instanceof AccessDeniedHttpException || $exception instanceof OriginMismatchException) {
            return $this->error('forbidden', 'The action is not permitted.', 403);
        }

        if ($exception instanceof NotFoundHttpException || $exception instanceof ModelNotFoundException) {
            return $this->error('not_found', 'Resource not found.', 404);
        }

        if ($exception instanceof ConflictHttpException) {
            return $this->error('conflict', 'The request conflicts with current state.', 409);
        }

        if ($exception instanceof ValidationException) {
            return $this->validationError(array_keys($exception->errors()));
        }

        if ($exception instanceof TokenMismatchException) {
            return $this->error('csrf_failed', 'The request could not be verified.', 419);
        }

        return $this->error('server_error', 'The request could not be completed.', 500);
    }

    private function sanitizeResponse(Response $response): JsonResponse
    {
        $status = $response->getStatusCode();

        if ($status === 422) {
            $body = json_decode((string) $response->getContent(), true);
            $fields = is_array($body) && is_array($body['errors'] ?? null) ? array_keys($body['errors']) : [];

            return $this->validationError($fields);
        }

        return match ($status) {
            401 => $this->error('unauthenticated', 'Authentication is required.', 401),
            403 => $this->error('forbidden', 'The action is not permitted.', 403),
            404 => $this->error('not_found', 'Resource not found.', 404),
            409 => $this->error('conflict', 'The request conflicts with current state.', 409),
            419 => $this->error('csrf_failed', 'The request could not be verified.', 419),
            default => $this->error(
                $status >= 500 ? 'server_error' : 'request_failed',
                'The request could not be completed.',
                $status,
            ),
        };
    }

    /** @param list<string|int> $fields */
    private function validationError(array $fields): JsonResponse
    {
        return response()->json([
            'code' => 'validation_failed',
            'message' => 'The submitted data is invalid.',
            'errors' => array_fill_keys($fields, ['The submitted value is invalid.']),
        ], 422);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $status)
            ->header('Cache-Control', 'private, no-store');
    }
}

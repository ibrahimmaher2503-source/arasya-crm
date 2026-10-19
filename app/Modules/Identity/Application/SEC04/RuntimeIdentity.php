<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\SEC04;

use App\Modules\Identity\Domain\SEC01\ActorAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class RuntimeIdentity
{
    public function __construct(private AccessControl $access = new AccessControl) {}

    public function fromRequest(Request $request): ActorAccess
    {
        if ($request->hasSession() && $request->session()->has('access_user_id')) {
            $user = $this->access->actor($request);

            return $this->fromActor($user);
        }
        // Legacy session snapshots exist only for isolated synthetic acceptance fixtures.
        if (! app()->environment('testing')) {
            throw new UnauthorizedHttpException('Persisted session identity is required.');
        }

        return $this->fromSession($request->hasSession() ? $request->session()->all() : []);
    }

    /** Map an actor already verified by AccessControl, without repeating its database reads.
     * @param  array<string, mixed>  $actor
     */
    public function fromActor(array $actor): ActorAccess
    {
        return new ActorAccess((string) $actor['id'], (string) $actor['company_id'], (string) $actor['department_id'], $actor['permissions'], [], (string) $actor['branch_id']);
    }

    /** @param array<string, mixed> $session */
    public function fromSession(array $session): ActorAccess
    {
        $actorId = $session['actor_id'] ?? null;
        $entityId = $session['entity_id'] ?? null;
        $departmentId = $session['department_id'] ?? null;
        $permissions = $session['permissions'] ?? [];

        if (! is_string($actorId) || ! is_string($entityId) || ! is_string($departmentId) || ! is_array($permissions)) {
            throw new UnauthorizedHttpException('Session identity is required.');
        }

        return new ActorAccess($actorId, $entityId, $departmentId, array_values(array_filter($permissions, 'is_string')));
    }
}

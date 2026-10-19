<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Identity\Application\SEC04\AccessControl;
use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

final class RequestAuthorizationPerformanceTest extends TestCase
{
    use DatabaseMigrations;

    public function test_verified_read_identity_is_reused_across_service_instances_in_one_request(): void
    {
        $user = $this->seedActor();
        $request = $this->requestFor($user);
        DB::enableQueryLog();
        $actor = (new AccessControl)->actor($request);
        $initial = count(DB::getQueryLog());
        self::assertGreaterThan(0, $initial);
        self::assertSame($actor, (new AccessControl)->actor($request));
        (new AccessControl)->requirePermission($actor, 'crm.read');
        self::assertSame($actor['permissions'], (new AccessControl)->permissions($user));
        self::assertSame($user, (new RuntimeIdentity)->fromRequest($request)->actorId);
        self::assertCount($initial, DB::getQueryLog(), 'Repeated read authorization must add no SQL.');
    }

    public function test_a_new_request_rechecks_revocation_with_the_same_service_and_session(): void
    {
        $user = $this->seedActor();
        $access = new AccessControl;
        $access->actor($this->requestFor($user));
        DB::table('access_user_permissions')->where('user_id', $user)->where('permission', 'crm.read')->update(['allowed' => false]);
        $actor = $access->actor($this->requestFor($user));
        self::assertNotContains('crm.read', $actor['permissions']);
        $this->expectException(AccessDeniedHttpException::class);
        $access->requirePermission($actor, 'crm.read');
    }

    public function test_post_requests_never_reuse_a_stale_permission_snapshot(): void
    {
        $user = $this->seedActor();
        $request = $this->requestFor($user, 'POST');
        $access = new AccessControl;
        $access->actor($request);
        DB::table('access_user_permissions')->where('user_id', $user)->update(['allowed' => false]);
        self::assertNotContains('crm.read', $access->actor($request)['permissions']);
    }

    public function test_mutation_reauthenticates_after_a_read_snapshot_and_revocation(): void
    {
        $user = $this->seedActor();
        DB::table('access_user_permissions')->insert(['user_id' => $user, 'permission' => 'roles.write', 'allowed' => true]);
        $access = new AccessControl;
        $actor = $access->actor($this->requestFor($user));
        DB::table('access_user_permissions')->where('user_id', $user)->where('permission', 'roles.write')->update(['allowed' => false]);
        $this->expectException(AccessDeniedHttpException::class);
        $access->saveRole($actor, ['name' => 'Must not be written', 'permissions' => ['crm.read']]);
    }

    public function test_changed_session_identity_does_not_reuse_another_users_permissions(): void
    {
        $first = $this->seedActor();
        $second = $this->seedActor(false);
        $request = $this->requestFor($first);
        $access = new AccessControl;
        $access->actor($request);
        $request->session()->put('access_user_id', $second);
        $actor = $access->actor($request);
        self::assertSame($second, $actor['id']);
        self::assertNotContains('crm.read', $actor['permissions']);
    }

    public function test_session_version_invalidation_is_checked_on_the_next_request(): void
    {
        $user = $this->seedActor();
        $access = new AccessControl;
        $access->actor($this->requestFor($user));
        DB::table('access_users')->where('id', $user)->update(['session_version' => 1]);
        $this->expectException(AuthenticationException::class);
        $access->actor($this->requestFor($user));
    }

    public function test_transactional_authorization_bypasses_a_read_snapshot(): void
    {
        $user = $this->seedActor();
        $request = $this->requestFor($user);
        $access = new AccessControl;
        $access->actor($request);
        DB::transaction(function () use ($user, $request, $access): void {
            DB::table('access_user_permissions')->where('user_id', $user)->update(['allowed' => false]);
            self::assertNotContains('crm.read', $access->actor($request)['permissions']);
        });
        self::assertNotContains('crm.read', $access->actor($request)['permissions']);
    }

    public function test_cloned_request_cannot_inherit_a_previous_requests_verified_snapshot(): void
    {
        $user = $this->seedActor();
        $request = $this->requestFor($user);
        $access = new AccessControl;
        $access->actor($request);
        DB::table('access_user_permissions')->where('user_id', $user)->update(['allowed' => false]);
        $next = $request->duplicate();
        $this->app->instance('request', $next);
        self::assertNotContains('crm.read', $access->actor($next)['permissions']);
    }

    public function test_department_inheritance_explicit_deny_and_other_user_lookup_are_preserved(): void
    {
        $user = $this->seedActor(false);
        $other = $this->seedActor(false);
        $row = DB::table('access_users')->where('id', $user)->first();
        self::assertNotNull($row);
        $role = (string) Str::ulid();
        DB::table('access_roles')->insert(['id' => $role, 'company_id' => $row->company_id, 'name' => 'Inherited read role', 'version' => 0]);
        DB::table('access_role_permissions')->insert([['role_id' => $role, 'permission' => 'crm.read'], ['role_id' => $role, 'permission' => 'tasks.read']]);
        DB::table('access_department_roles')->insert(['company_id' => $row->company_id, 'branch_id' => $row->branch_id, 'department_id' => $row->department_id, 'role_id' => $role]);
        DB::table('access_user_permissions')->insert(['user_id' => $user, 'permission' => 'tasks.read', 'allowed' => false]);
        $request = $this->requestFor($user);
        $access = new AccessControl;
        $actor = $access->actor($request);
        self::assertContains('crm.read', $actor['permissions']);
        self::assertNotContains('tasks.read', $access->permissions($user));
        self::assertSame([], $access->permissions($other));
        $this->expectException(AuthenticationException::class);
        $access->requirePermission(array_replace($actor, ['department_id' => 'forged-department']), 'crm.read');
    }

    public function test_inactive_organization_is_rechecked_between_requests(): void
    {
        $user = $this->seedActor();
        $access = new AccessControl;
        $actor = $access->actor($this->requestFor($user));
        DB::table('access_departments')->where('id', $actor['department_id'])->update(['active' => false]);
        $this->expectException(AuthenticationException::class);
        $access->actor($this->requestFor($user));
    }

    public function test_locked_read_rechecks_revocation_instead_of_trusting_the_request_snapshot(): void
    {
        $user = $this->seedActor();
        $request = $this->requestFor($user);
        $access = new AccessControl;
        $access->actor($request);
        DB::table('access_user_permissions')->where('user_id', $user)->update(['allowed' => false]);
        $this->expectException(AccessDeniedHttpException::class);
        $access->withReadLock($request, ['crm.read'], fn (): never => self::fail('Revoked read callback must not run.'));
    }

    public function test_company_lock_is_held_through_the_protected_read_callback(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('Two-connection serialization requires native MariaDB.');
        }
        $user = $this->seedActor();
        $request = $this->requestFor($user);
        config(['database.connections.authorization_contender' => config('database.connections.'.config('database.default'))]);
        $contender = DB::connection('authorization_contender');
        $contender->statement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            (new AccessControl)->withReadLock($request, ['crm.read'], function (array $actor) use ($contender): void {
                $blocked = false;
                try {
                    $contender->transaction(fn () => $contender->table('access_companies')->where('id', $actor['company_id'])->lockForUpdate()->first());
                } catch (QueryException $exception) {
                    if (($exception->errorInfo[1] ?? null) !== 1205) {
                        throw $exception;
                    }
                    $blocked = true;
                }
                self::assertTrue($blocked, 'Concurrent authorization writers must wait until the read callback finishes.');
            });
        } finally {
            DB::purge('authorization_contender');
        }
    }

    private function requestFor(string $user, string $method = 'GET'): Request
    {
        $request = Request::create('/crm', $method);
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->flush();
        $request->session()->put(['access_user_id' => $user, 'access_session_version' => 0]);
        $this->app->instance('request', $request);

        return $request;
    }

    private function seedActor(bool $grant = true): string
    {
        $company = (string) Str::ulid();
        $branch = (string) Str::ulid();
        $department = (string) Str::ulid();
        $user = (string) Str::ulid();
        DB::table('access_companies')->insert(['id' => $company, 'name' => 'Synthetic performance company', 'version' => 0]);
        DB::table('access_branches')->insert(['id' => $branch, 'company_id' => $company, 'name' => 'Branch', 'code' => $branch, 'active' => true, 'version' => 0]);
        DB::table('access_departments')->insert(['id' => $department, 'company_id' => $company, 'branch_id' => $branch, 'name' => 'Department', 'code' => $department, 'active' => true, 'version' => 0]);
        DB::table('access_users')->insert(['id' => $user, 'company_id' => $company, 'branch_id' => $branch, 'department_id' => $department, 'name' => 'Synthetic performance actor', 'email' => $user.'@example.test', 'password' => 'unused', 'active' => true, 'is_admin' => false, 'session_version' => 0, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
        if ($grant) {
            DB::table('access_user_permissions')->insert(['user_id' => $user, 'permission' => 'crm.read', 'allowed' => true]);
        }

        return $user;
    }
}

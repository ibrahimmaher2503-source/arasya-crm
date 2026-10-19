<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Identity\Application\SEC04\AccessControl;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RequestAuthorizationIsolationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_new_get_requests_use_their_own_company_and_department_permissions(): void
    {
        $company = $this->seedOrganization('Primary');
        $departmentA = $this->seedDepartment($company, 'Department A');
        $departmentB = $this->seedDepartment($company, 'Department B');
        $userA = $this->seedUser($company, $departmentA, 'Department A user');
        $userB = $this->seedUser($company, $departmentB, 'Department B user');
        $roleA = $this->seedRole($company['company_id'], 'Department A reader', ['crm.read']);
        $roleB = $this->seedRole($company['company_id'], 'Department B reader', ['crm.read', 'tasks.read']);
        $this->assignDepartmentRole($company, $departmentA, $roleA);
        $this->assignDepartmentRole($company, $departmentB, $roleB);
        DB::table('access_user_permissions')->insert([
            'user_id' => $userB,
            'permission' => 'crm.read',
            'allowed' => false,
        ]);

        $foreignCompany = $this->seedOrganization('Foreign');
        $foreignDepartment = $this->seedDepartment($foreignCompany, 'Foreign department');
        $foreignUser = $this->seedUser($foreignCompany, $foreignDepartment, 'Foreign company user');
        $foreignRole = $this->seedRole($foreignCompany['company_id'], 'Foreign reader', ['users.read']);
        $this->assignDepartmentRole($foreignCompany, $foreignDepartment, $foreignRole);

        $access = new AccessControl;
        $actorA = $access->actor($this->requestFor($userA));
        $actorB = $access->actor($this->requestFor($userB));
        $foreignActor = $access->actor($this->requestFor($foreignUser));

        self::assertSame($company['company_id'], $actorA['company_id']);
        self::assertSame($departmentA, $actorA['department_id']);
        self::assertSame(['crm.read'], $actorA['permissions']);

        self::assertSame($company['company_id'], $actorB['company_id']);
        self::assertSame($departmentB, $actorB['department_id']);
        self::assertNotContains('crm.read', $actorB['permissions'], 'An explicit user deny must override a department role grant.');
        self::assertContains('tasks.read', $actorB['permissions']);

        self::assertSame($foreignCompany['company_id'], $foreignActor['company_id']);
        self::assertSame($foreignDepartment, $foreignActor['department_id']);
        self::assertSame(['users.read'], $foreignActor['permissions']);
    }

    public function test_department_scope_changes_between_get_requests_invalidate_old_session_and_refresh_permissions(): void
    {
        $company = $this->seedOrganization('Scope change');
        $departmentA = $this->seedDepartment($company, 'Original department');
        $departmentB = $this->seedDepartment($company, 'New department');
        $user = $this->seedUser($company, $departmentA, 'Moving user');
        $roleA = $this->seedRole($company['company_id'], 'Original department role', ['crm.read']);
        $roleB = $this->seedRole($company['company_id'], 'New department role', ['tasks.read']);
        $this->assignDepartmentRole($company, $departmentA, $roleA);
        $this->assignDepartmentRole($company, $departmentB, $roleB);

        $access = new AccessControl;
        $originalActor = $access->actor($this->requestFor($user, 0));
        self::assertSame($departmentA, $originalActor['department_id']);
        self::assertContains('crm.read', $originalActor['permissions']);

        DB::table('access_users')->where('id', $user)->update([
            'department_id' => $departmentB,
            'session_version' => 1,
        ]);

        $oldSessionRejected = false;
        try {
            $access->actor($this->requestFor($user, 0));
        } catch (AuthenticationException) {
            $oldSessionRejected = true;
        }
        self::assertTrue($oldSessionRejected, 'A request with the previous session version must be rejected after a scope change.');

        $currentActor = $access->actor($this->requestFor($user, 1));
        self::assertSame($departmentB, $currentActor['department_id']);
        self::assertNotContains('crm.read', $currentActor['permissions']);
        self::assertContains('tasks.read', $currentActor['permissions']);
    }

    public function test_mutation_rejects_a_cached_get_actor_after_its_session_is_invalidated(): void
    {
        $company = $this->seedOrganization('Mutation');
        $department = $this->seedDepartment($company, 'Mutation department');
        $user = $this->seedUser($company, $department, 'Mutation user');
        $role = $this->seedRole($company['company_id'], 'Role editor', ['roles.write']);
        $this->assignDepartmentRole($company, $department, $role);

        $access = new AccessControl;
        $request = $this->requestFor($user);
        $actor = $access->actor($request);
        self::assertContains('roles.write', $actor['permissions']);

        DB::table('access_users')->where('id', $user)->update(['session_version' => 1]);

        $rejected = false;
        try {
            $access->saveRole($actor, ['name' => 'Stale actor role', 'permissions' => ['crm.read']]);
        } catch (AuthenticationException) {
            $rejected = true;
        }

        self::assertTrue($rejected, 'A mutation must reauthenticate the actor after a cached GET snapshot.');
        $this->assertDatabaseMissing('access_roles', [
            'company_id' => $company['company_id'],
            'name' => 'Stale actor role',
        ]);
    }

    /** @return array{company_id: string, branch_id: string} */
    private function seedOrganization(string $name): array
    {
        $companyId = (string) Str::ulid();
        $branchId = (string) Str::ulid();

        DB::table('access_companies')->insert([
            'id' => $companyId,
            'name' => $name.' company',
            'version' => 0,
        ]);
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => $name.' branch',
            'code' => $branchId,
            'active' => true,
            'version' => 0,
        ]);

        return ['company_id' => $companyId, 'branch_id' => $branchId];
    }

    /** @param array{company_id: string, branch_id: string} $company */
    private function seedDepartment(array $company, string $name): string
    {
        $departmentId = (string) Str::ulid();

        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $company['company_id'],
            'branch_id' => $company['branch_id'],
            'name' => $name,
            'code' => $departmentId,
            'active' => true,
            'version' => 0,
        ]);

        return $departmentId;
    }

    /** @param array{company_id: string, branch_id: string} $company */
    private function seedUser(array $company, string $departmentId, string $name): string
    {
        $userId = (string) Str::ulid();

        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $company['company_id'],
            'branch_id' => $company['branch_id'],
            'department_id' => $departmentId,
            'name' => $name,
            'email' => $userId.'@example.test',
            'password' => 'unused',
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $userId;
    }

    /** @param list<string> $permissions */
    private function seedRole(string $companyId, string $name, array $permissions): string
    {
        $roleId = (string) Str::ulid();

        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $companyId,
            'name' => $name,
            'version' => 0,
        ]);
        DB::table('access_role_permissions')->insert(array_map(
            static fn (string $permission): array => ['role_id' => $roleId, 'permission' => $permission],
            $permissions,
        ));

        return $roleId;
    }

    /** @param array{company_id: string, branch_id: string} $company */
    private function assignDepartmentRole(array $company, string $departmentId, string $roleId): void
    {
        DB::table('access_department_roles')->insert([
            'company_id' => $company['company_id'],
            'branch_id' => $company['branch_id'],
            'department_id' => $departmentId,
            'role_id' => $roleId,
        ]);
    }

    private function requestFor(string $userId, int $sessionVersion = 0): Request
    {
        self::assertSame(0, DB::transactionLevel(), 'GET authorization snapshots must be exercised outside a transaction.');

        $request = Request::create('/access', 'GET');
        $session = $this->app['session.store'];
        $session->setId(Str::random(40));
        $session->replace([
            'access_user_id' => $userId,
            'access_session_version' => $sessionVersion,
        ]);
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        return $request;
    }
}

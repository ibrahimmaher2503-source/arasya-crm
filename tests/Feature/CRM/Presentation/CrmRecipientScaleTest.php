<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use App\Modules\CRM\Application\Runtime\LeadDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CrmRecipientScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipient_page_resolves_effective_permissions_in_a_bounded_number_of_queries(): void
    {
        $world = $this->seedWorld();
        $departmentRole = $this->insertRole($world['company_id'], 'department readers and writers', ['crm.read', 'crm.write']);
        DB::table('access_department_roles')->insert([
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'role_id' => $departmentRole,
        ]);
        $expectedIds = [$world['manager_id']];
        $departmentRecipientId = $this->insertUser($world, 'department-recipient');
        $expectedIds[] = $departmentRecipientId;
        $readerRole = $this->insertRole($world['company_id'], 'direct readers', ['crm.read']);

        for ($i = 0; $i < 40; $i++) {
            $userId = $this->insertUser($world, 'candidate-'.$i);
            $this->assignRole($userId, $readerRole);
            $expectedIds[] = $userId;
        }

        $explicitlyAllowedId = $this->insertUser($world, 'explicitly-allowed');
        $this->override($explicitlyAllowedId, 'crm.read', true);
        $expectedIds[] = $explicitlyAllowedId;

        $adminId = $this->insertUser($world, 'admin', true);
        $expectedIds[] = $adminId;

        $deniedReaderId = $this->insertUser($world, 'denied-reader');
        $this->assignRole($deniedReaderId, $readerRole);
        $this->override($deniedReaderId, 'crm.read', false);

        $deniedAdminId = $this->insertUser($world, 'denied-admin', true);
        $this->override($deniedAdminId, 'crm.write', false);

        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            $result = app(LeadDirectory::class)->recipientPage($this->requestFor($world['manager_id']), ['per_page' => 100]);
            $queries = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
        }

        self::assertSame(44, $result['meta']['total']);
        self::assertCount(44, $result['data']);
        self::assertEqualsCanonicalizing($expectedIds, array_column($result['data'], 'id'));
        self::assertLessThan(30, count($queries), 'Recipient permission checks must not issue a query per user.');
    }

    /** @return array<string, string> */
    private function seedWorld(): array
    {
        $companyId = $this->id();
        $branchId = $this->id();
        $departmentId = $this->id();
        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Synthetic CRM Company', 'version' => 0]);
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Synthetic CRM Branch',
            'code' => 'crm-'.strtolower(substr($branchId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Synthetic CRM Department',
            'code' => 'crm-'.strtolower(substr($departmentId, -6)),
            'active' => true,
            'version' => 0,
        ]);

        $world = ['company_id' => $companyId, 'branch_id' => $branchId, 'department_id' => $departmentId];
        $managerRole = $this->insertRole($companyId, 'CRM manager', ['crm.read', 'crm.manage', 'crm.assign']);
        $managerId = $this->insertUser($world, 'manager');
        $this->assignRole($managerId, $managerRole);

        return $world + ['manager_id' => $managerId];
    }

    /** @param array<string, string> $world */
    private function insertUser(array $world, string $suffix, bool $admin = false): string
    {
        $userId = $this->id();
        $now = now()->toDateTimeString();
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => 'Synthetic CRM '.$suffix,
            'email' => 'crm.'.str_replace('-', '.', $suffix).'.'.strtolower(substr($userId, -6)).'@example.test',
            'password' => Hash::make('unused-synthetic-password'),
            'active' => true,
            'is_admin' => $admin,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $userId;
    }

    /** @param list<string> $permissions */
    private function insertRole(string $companyId, string $name, array $permissions): string
    {
        $id = $this->id();
        DB::table('access_roles')->insert(['id' => $id, 'company_id' => $companyId, 'name' => $name, 'version' => 0]);
        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $id, 'permission' => $permission]);
        }

        return $id;
    }

    private function assignRole(string $userId, string $roleId): void
    {
        DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);
    }

    private function override(string $userId, string $permission, bool $allowed): void
    {
        DB::table('access_user_permissions')->insert(['user_id' => $userId, 'permission' => $permission, 'allowed' => $allowed]);
    }

    private function requestFor(string $userId): Request
    {
        $session = new Store('crm-test-'.$userId.'-'.Str::ulid(), new ArraySessionHandler(120));
        $session->start();
        $session->replace([
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ]);
        $request = Request::create('/crm/recipients', 'GET');
        $request->setLaravelSession($session);

        return $request;
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }
}

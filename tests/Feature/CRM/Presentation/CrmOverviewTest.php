<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use App\Modules\CRM\Application\Runtime\LeadDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

final class CrmOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_returns_scoped_counts_and_bounded_activity_and_follow_up_lists(): void
    {
        $world = $this->seedWorld();
        $stageId = $this->insertTaxonomy($world['company_id'], 'stage', 'New');
        $outcomeId = $this->insertTaxonomy($world['company_id'], 'outcome', 'Interested');
        $customerId = $this->insertCustomer($world);

        $assignedLead = $this->insertLead($world, 'Active assigned lead', 'website', $stageId, $world['owner_id']);
        $convertedLead = $this->insertLead($world, 'Active converted lead', 'referral', null, null, $customerId);
        $archivedLead = $this->insertLead($world, 'Archived lead', 'event', $stageId, $world['manager_id'], archived: true);
        $otherDepartmentId = $this->insertOtherDepartment($world);
        $otherDepartmentLead = $this->insertLead($world, 'Other department lead', 'partner', $stageId, $world['manager_id'], departmentId: $otherDepartmentId);

        $activityIds = [];
        $base = now()->subMinutes(5);
        for ($i = 0; $i < 12; $i++) {
            $activityIds[] = $this->insertActivity($world, $assignedLead, $outcomeId, $base->copy()->addSeconds($i), $base->copy()->addDay()->addSeconds($i));
        }
        $this->insertActivity($world, $convertedLead, $outcomeId, $base->copy()->subMinute(), now()->subDay());
        $this->insertActivity($world, $archivedLead, $outcomeId, now(), now()->addDay());
        $this->insertActivity($world, $otherDepartmentLead, $outcomeId, now(), now()->addDay());
        $this->insertPendingTransfer($world, $assignedLead);

        $directory = app(LeadDirectory::class);
        self::assertTrue(method_exists($directory, 'overview'), 'LeadDirectory should expose the CRM overview read contract.');
        $overview = $directory->overview($this->requestFor($world['manager_id']));

        self::assertSame(2, $overview['active_total']);
        self::assertSame(1, $overview['assigned_count']);
        self::assertSame(1, $overview['converted_count']);
        self::assertSame(1, $overview['pending_transfers_count']);
        self::assertEqualsCanonicalizing([
            ['source' => 'referral', 'count' => 1],
            ['source' => 'website', 'count' => 1],
        ], $overview['source_counts']);
        self::assertEqualsCanonicalizing([
            ['stage_id' => $stageId, 'stage' => 'New', 'count' => 1],
            ['stage_id' => null, 'stage' => null, 'count' => 1],
        ], $overview['stage_counts']);
        self::assertCount(10, $overview['recent_activities']);
        self::assertCount(10, $overview['upcoming_followups']);
        self::assertSame($activityIds[11], $overview['recent_activities'][0]['id']);
        self::assertSame($activityIds[0], $overview['upcoming_followups'][0]['id']);
        self::assertSame([$assignedLead], array_values(array_unique(array_column($overview['recent_activities'], 'lead_id'))));
        self::assertSame([$assignedLead], array_values(array_unique(array_column($overview['upcoming_followups'], 'lead_id'))));
        self::assertSame(1, $directory->overview($this->requestFor($world['recipient_id']))['pending_transfers_count']);
        self::assertArrayNotHasKey('contacts', $overview['recent_activities'][0]);
        self::assertArrayNotHasKey('note', $overview['recent_activities'][0]);
        self::assertStringNotContainsString('private dashboard', json_encode($overview, JSON_THROW_ON_ERROR));
    }

    public function test_overview_requires_the_persisted_crm_read_permission(): void
    {
        $world = $this->seedWorld();
        $directory = app(LeadDirectory::class);
        self::assertTrue(method_exists($directory, 'overview'), 'LeadDirectory should expose the CRM overview read contract.');

        $this->expectException(AccessDeniedHttpException::class);
        $directory->overview($this->requestFor($world['readless_id']));
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

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'manager_id' => $this->insertUser($companyId, $branchId, $departmentId, 'manager', ['crm.read', 'crm.manage', 'crm.write']),
            'owner_id' => $this->insertUser($companyId, $branchId, $departmentId, 'owner', ['crm.read', 'crm.write', 'crm.transfer']),
            'recipient_id' => $this->insertUser($companyId, $branchId, $departmentId, 'recipient', ['crm.read', 'crm.write']),
            'readless_id' => $this->insertUser($companyId, $branchId, $departmentId, 'readless', ['crm.write']),
        ];
    }

    /** @param list<string> $permissions */
    private function insertUser(string $companyId, string $branchId, string $departmentId, string $suffix, array $permissions): string
    {
        $userId = $this->id();
        $roleId = $this->id();
        $now = now()->toDateTimeString();
        DB::table('access_roles')->insert(['id' => $roleId, 'company_id' => $companyId, 'name' => 'Synthetic CRM '.$suffix.' role', 'version' => 0]);
        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'name' => 'Synthetic CRM '.$suffix,
            'email' => 'crm.'.$suffix.'.'.strtolower(substr($userId, -6)).'@example.test',
            'password' => Hash::make('unused-synthetic-password'),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);

        return $userId;
    }

    private function insertTaxonomy(string $companyId, string $kind, string $label): string
    {
        $id = $this->id();
        DB::table('crm_taxonomy')->insert(['id' => $id, 'company_id' => $companyId, 'kind' => $kind, 'label' => $label, 'replaces_id' => null, 'created_at' => now()]);

        return $id;
    }

    /** @param array<string, string> $world */
    private function insertCustomer(array $world): string
    {
        $id = $this->id();
        DB::table('crm_customers')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => 'Synthetic CRM Customer',
            'organization' => 'Synthetic CRM Organization',
            'duplicate_key' => null,
            'created_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string, string> $world */
    private function insertLead(array $world, string $name, string $source, ?string $stageId, ?string $assigneeId, ?string $customerId = null, bool $archived = false, ?string $departmentId = null): string
    {
        $id = $this->id();
        DB::table('crm_leads')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $departmentId ?? $world['department_id'],
            'name' => $name,
            'organization' => 'Synthetic CRM Organization',
            'source' => $source,
            'contacts' => Crypt::encryptString('private-contact'),
            'duplicate_key' => null,
            'consent' => true,
            'preferences' => json_encode(['email' => false], JSON_THROW_ON_ERROR),
            'notes' => 'private dashboard note sentinel',
            'stage_id' => $stageId,
            'assignee_id' => $assigneeId,
            'customer_id' => $customerId,
            'archived' => $archived,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string, string> $world */
    private function insertOtherDepartment(array $world): string
    {
        $id = $this->id();
        DB::table('access_departments')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'name' => 'Other synthetic department',
            'code' => 'crm-'.strtolower(substr($id, -6)),
            'active' => true,
            'version' => 0,
        ]);

        return $id;
    }

    /** @param array<string, string> $world */
    private function insertActivity(array $world, string $leadId, string $outcomeId, Carbon $occurredAt, Carbon $dueAt): string
    {
        $id = $this->id();
        DB::table('crm_activities')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'lead_id' => $leadId,
            'actor_id' => $world['manager_id'],
            'kind' => 'call',
            'outcome_id' => $outcomeId,
            'outcome_label' => 'Interested',
            'note' => 'private dashboard activity note sentinel',
            'next_step' => 'Call about installation',
            'occurred_at' => $occurredAt->toDateTimeString(),
            'next_follow_up' => $dueAt->toDateTimeString(),
            'task_id' => null,
            'created_at' => $occurredAt->toDateTimeString(),
        ]);

        return $id;
    }

    /** @param array<string, string> $world */
    private function insertPendingTransfer(array $world, string $leadId): void
    {
        DB::table('crm_transfers')->insert([
            'id' => $this->id(),
            'company_id' => $world['company_id'],
            'lead_id' => $leadId,
            'sender_id' => $world['owner_id'],
            'recipient_id' => $world['recipient_id'],
            'context' => 'Synthetic coverage handoff',
            'state' => 'pending',
            'requested_version' => 0,
            'created_at' => now(),
            'responded_at' => null,
        ]);
    }

    private function requestFor(string $userId): Request
    {
        $session = new Store('crm-test-'.$userId.'-'.Str::ulid(), new ArraySessionHandler(120));
        $session->start();
        $session->replace([
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ]);
        $request = Request::create('/crm', 'GET');
        $request->setLaravelSession($session);

        return $request;
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }
}

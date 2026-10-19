<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CrmLeadOverviewLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_overview_reference_layout_preserves_identity_private_contacts_and_complete_timeline(): void
    {
        $companyId = (string) Str::ulid();
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        $managerId = (string) Str::ulid();
        $roleId = (string) Str::ulid();
        $leadId = (string) Str::ulid();
        $stageId = (string) Str::ulid();
        $outcomeId = (string) Str::ulid();
        $now = now()->toDateTimeString();

        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Synthetic overview company', 'version' => 0]);
        DB::table('access_branches')->insert([
            'id' => $branchId, 'company_id' => $companyId, 'name' => 'Synthetic overview branch',
            'code' => 'overview-branch', 'active' => true, 'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId, 'company_id' => $companyId, 'branch_id' => $branchId,
            'name' => 'Synthetic overview department', 'code' => 'overview-department', 'version' => 0,
        ]);
        DB::table('access_roles')->insert(['id' => $roleId, 'company_id' => $companyId, 'name' => 'Synthetic overview manager', 'version' => 0]);
        DB::table('access_role_permissions')->insert(array_map(
            static fn (string $permission): array => ['role_id' => $roleId, 'permission' => $permission],
            ['crm.read', 'crm.manage', 'crm.write', 'contact.reveal'],
        ));
        DB::table('access_users')->insert([
            'id' => $managerId, 'company_id' => $companyId, 'branch_id' => $branchId,
            'department_id' => $departmentId, 'name' => 'Synthetic overview manager',
            'email' => 'overview-manager@example.test', 'password' => Hash::make('unused-overview-password'),
            'active' => true, 'is_admin' => false, 'session_version' => 0, 'version' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('access_user_roles')->insert(['user_id' => $managerId, 'role_id' => $roleId]);
        DB::table('crm_taxonomy')->insert([
            ['id' => $stageId, 'company_id' => $companyId, 'kind' => 'stage', 'label' => 'Synthetic reference stage', 'replaces_id' => null, 'created_at' => $now],
            ['id' => $outcomeId, 'company_id' => $companyId, 'kind' => 'outcome', 'label' => 'Synthetic reference outcome', 'replaces_id' => null, 'created_at' => $now],
        ]);
        DB::table('crm_leads')->insert([
            'id' => $leadId, 'company_id' => $companyId, 'branch_id' => $branchId, 'department_id' => $departmentId,
            'name' => 'Synthetic overview contact', 'organization' => 'Synthetic overview organization', 'source' => 'website',
            'contacts' => Crypt::encryptString(json_encode([
                'email' => 'private-overview@example.test', 'phone' => 'PRIVATE-OVERVIEW-PHONE', 'whatsapp' => 'PRIVATE-OVERVIEW-WHATSAPP',
            ], JSON_THROW_ON_ERROR)),
            'duplicate_key' => null, 'consent' => true, 'preferences' => '{"email":true,"phone":false,"whatsapp":true}',
            'notes' => 'Synthetic overview note remains visible.', 'stage_id' => $stageId, 'assignee_id' => $managerId,
            'customer_id' => null, 'archived' => false, 'priority' => 'high', 'version' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach (range(1, 11) as $number) {
            $occurredAt = now()->subMinutes($number)->toDateTimeString();
            DB::table('crm_activities')->insert([
                'id' => (string) Str::ulid(), 'company_id' => $companyId, 'lead_id' => $leadId,
                'actor_id' => $managerId, 'kind' => 'call', 'outcome_id' => $outcomeId,
                'outcome_label' => 'Synthetic reference outcome',
                'note' => $number === 1 ? 'OVERVIEW_NOTE_REMAINS_VISIBLE' : 'Synthetic overview activity '.$number,
                'next_step' => $number === 1 ? 'OVERVIEW_NEXT_STEP_REMAINS_VISIBLE' : 'Synthetic overview next step '.$number,
                'occurred_at' => $occurredAt,
                'next_follow_up' => $number === 1 ? '2026-10-22 14:30:00' : null,
                'task_id' => null, 'created_at' => $occurredAt,
            ]);
        }

        $session = ['access_user_id' => $managerId, 'access_session_version' => 0];
        $overview = $this->withSession($session)
            ->withHeaders(['Accept' => 'text/html'])
            ->get('/crm/leads/'.$leadId.'?locale=en&per_page=10')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Synthetic overview organization')
            ->assertSee('Synthetic reference stage')
            ->assertSeeText('High')
            ->assertSeeText($leadId)
            ->assertSee('class="crm-lead-overview-main"', false)
            ->assertSee('crm-lead-activities', false)
            ->assertSeeText('OVERVIEW_NEXT_STEP_REMAINS_VISIBLE')
            ->assertSeeText('OVERVIEW_NOTE_REMAINS_VISIBLE')
            ->assertSeeText('2026-10-22 14:30:00')
            ->assertSee('aria-label="Activity pages"', false)
            ->assertSee('name="reason"', false)
            ->assertSee('required', false)
            ->assertDontSeeText('Lead actions')
            ->assertDontSeeText('Open lead overview');

        foreach (['private-overview@example.test', 'PRIVATE-OVERVIEW-PHONE', 'PRIVATE-OVERVIEW-WHATSAPP'] as $privateContact) {
            $overview->assertDontSee($privateContact, false);
        }
        self::assertSame(1, preg_match_all('/<h1\b/i', (string) $overview->getContent()));
        self::assertSame(0, DB::table('crm_audit')->where('lead_id', $leadId)->where('action', 'lead.contacts.revealed')->count());

        $this->withSession($session)
            ->withHeaders(['Accept' => 'text/html'])
            ->get('/crm/leads/'.$leadId.'/workspace/activities?locale=en')
            ->assertOk()
            ->assertSeeText('Open lead overview')
            ->assertSee('href="/crm/leads/'.$leadId.'?locale=en"', false);
    }
}

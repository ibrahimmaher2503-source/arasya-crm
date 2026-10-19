<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Http;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class LeadSecurityHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_routes_require_a_current_persisted_session_identity(): void
    {
        config(['app.debug' => true]);
        $org = $this->organization();
        $reader = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Reader', ['crm.read']);

        $this->assertSafeNoStoreError($this->getJson('/crm/leads?access_user_id='.$reader.'&access_session_version=0')->assertUnauthorized());

        $this->assertSafeNoStoreError($this->withSession(['access_user_id' => $this->id(), 'access_session_version' => 0])->getJson('/crm/leads')->assertUnauthorized());

        $staleVersion = (int) DB::table('access_users')->where('id', $reader)->value('session_version');
        DB::table('access_users')->where('id', $reader)->update(['session_version' => 1]);
        $this->assertSafeNoStoreError($this->withSession(['access_user_id' => $reader, 'access_session_version' => $staleVersion])->getJson('/crm/leads')->assertUnauthorized());

        $inactive = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Inactive Reader', ['crm.read']);
        DB::table('access_users')->where('id', $inactive)->update(['active' => false]);
        $this->assertSafeNoStoreError($this->sessionAs($inactive)->getJson('/crm/leads')->assertUnauthorized());
    }

    public function test_requests_without_accept_header_keep_the_json_session_boundary(): void
    {
        $org = $this->organization();
        $manager = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Header Manager', ['crm.read']);

        $managerResponse = $this->sessionAs($manager)->get('/crm/leads')->assertOk();
        self::assertStringContainsString('application/json', strtolower((string) $managerResponse->headers->get('Content-Type')));
        $managerResponse->assertJsonStructure(['data', 'meta']);
        $this->assertNoStore($managerResponse);

        $this->flushSession();
        $guestResponse = $this->get('/crm/leads')->assertUnauthorized();
        self::assertStringContainsString('application/json', strtolower((string) $guestResponse->headers->get('Content-Type')));
        $guestResponse->assertJsonStructure(['code', 'message']);
        $this->assertNoStore($guestResponse);
    }

    public function test_routing_time_404_and_405_errors_are_safe_json_without_accept_header(): void
    {
        config(['app.debug' => true]);

        $missingJson = $this->getJson('/crm/no-such-route')->assertNotFound();
        $this->assertSafeNoStoreError($missingJson);

        $unsupportedMethod = $this->deleteJson('/crm/leads')->assertMethodNotAllowed();
        $this->assertSafeNoStoreError($unsupportedMethod);

        $missingPlain = $this->get('/crm/no-such-route')->assertNotFound();
        $this->assertSafeNoStoreError($missingPlain);
    }

    public function test_missing_grants_are_forbidden_and_admins_cannot_bypass_contact_reveal(): void
    {
        config(['app.debug' => true]);
        $org = $this->organization();
        $manager = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Manager', ['crm.read', 'crm.manage', 'crm.write', 'crm.assign']);
        $unprivileged = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Unprivileged');
        $admin = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Admin', isAdmin: true);
        $lead = $this->createLead($manager, 'Grant Check');
        $unknown = $this->id();

        $requests = [
            ['GET', '/crm/leads', []],
            ['POST', '/crm/leads', $this->leadPayload('Forbidden Create')],
            ['GET', '/crm/leads/'.$lead['id'], []],
            ['PATCH', '/crm/leads/'.$lead['id'], ['name' => 'Forbidden update', 'version' => 0]],
            ['POST', '/crm/leads/'.$lead['id'].'/archive', ['version' => 0]],
            ['POST', '/crm/leads/'.$lead['id'].'/assignment', ['assignee_id' => $unprivileged, 'version' => 0]],
            ['POST', '/crm/leads/'.$lead['id'].'/reveal', ['reason' => 'Support request']],
            ['POST', '/crm/leads/'.$lead['id'].'/activities', ['kind' => 'note', 'outcome_id' => $unknown, 'next_step' => 'Follow up', 'occurred_at' => '2026-10-17T10:00:00Z', 'version' => 0]],
            ['POST', '/crm/leads/'.$lead['id'].'/transfers', ['recipient_id' => $unprivileged, 'context' => 'Support handoff', 'version' => 0]],
            ['POST', '/crm/leads/'.$lead['id'].'/convert', ['version' => 0]],
            ['GET', '/crm/taxonomy', []],
            ['POST', '/crm/taxonomy', ['kind' => 'stage', 'label' => 'Qualified']],
            ['GET', '/crm/transfers/inbox', []],
            ['POST', '/crm/transfers/'.$unknown.'/response', ['decision' => 'reject', 'version' => 0]],
            ['GET', '/crm/recipients', []],
        ];

        foreach ($requests as [$method, $uri, $payload]) {
            $response = $this->sessionAs($unprivileged)->json($method, $uri, $payload);
            $response->assertForbidden();
            $this->assertSafeNoStoreError($response);
        }

        $adminReveal = $this->sessionAs($admin)->postJson('/crm/leads/'.$lead['id'].'/reveal', ['reason' => 'Support request'])->assertForbidden();
        $this->assertSafeNoStoreError($adminReveal);
        $this->assertContactsAbsent($adminReveal, $lead['contacts']);

        DB::table('access_user_permissions')->insert([
            'user_id' => $admin,
            'permission' => 'crm.read',
            'allowed' => false,
        ]);
        $this->assertSafeNoStoreError($this->sessionAs($admin)->getJson('/crm/leads')->assertForbidden());
    }

    public function test_scoped_http_reads_and_mutations_hide_contacts_and_return_not_found_for_hidden_leads(): void
    {
        config(['app.debug' => true]);
        $org = $this->organization();
        $manager = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Scope Manager', ['crm.read', 'crm.manage', 'crm.write', 'crm.assign']);
        $otherDepartmentManager = $this->user($org, 'company_a', 'branch_a', 'department_a_other', 'HTTP Other Department Manager', ['crm.read', 'crm.manage', 'crm.write']);
        $otherBranchManager = $this->user($org, 'company_a', 'branch_b', 'department_b', 'HTTP Other Branch Manager', ['crm.read', 'crm.manage', 'crm.write']);
        $otherCompanyManager = $this->user($org, 'company_b', 'branch_foreign', 'department_foreign', 'HTTP Other Company Manager', ['crm.read', 'crm.manage', 'crm.write']);
        $sales = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Assigned Sales', ['crm.read', 'crm.write']);
        $peer = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Peer Sales', ['crm.read', 'crm.write']);

        $assigned = $this->createLead($manager, 'Assigned Prospect');
        $this->assertNoStore($this->sessionAs($manager)->postJson('/crm/leads/'.$assigned['id'].'/assignment', ['assignee_id' => $sales, 'version' => 0])->assertSuccessful());
        $unassigned = $this->createLead($manager, 'Unassigned Prospect');
        $peerLead = $this->createLead($manager, 'Peer Prospect');
        $this->assertNoStore($this->sessionAs($manager)->postJson('/crm/leads/'.$peerLead['id'].'/assignment', ['assignee_id' => $peer, 'version' => 0])->assertSuccessful());
        $otherDepartment = $this->createLead($otherDepartmentManager, 'Other Department Prospect');
        $otherBranch = $this->createLead($otherBranchManager, 'Other Branch Prospect');
        $otherCompany = $this->createLead($otherCompanyManager, 'Other Company Prospect');

        $listing = $this->sessionAs($sales)->getJson('/crm/leads')->assertOk()->assertJsonStructure(['data', 'meta']);
        $this->assertNoStore($listing);
        $listingJson = $this->encoded($listing->json('data'));
        self::assertStringContainsString($assigned['id'], $listingJson);
        foreach ([$unassigned, $peerLead, $otherDepartment, $otherBranch, $otherCompany] as $hidden) {
            self::assertStringNotContainsString($hidden['id'], $listingJson);
        }
        $this->assertContactsAbsent($listing, $assigned['contacts']);

        $detail = $this->sessionAs($sales)->getJson('/crm/leads/'.$assigned['id'])->assertOk();
        $this->assertNoStore($detail);
        foreach ($assigned['contacts'] as $field => $_value) {
            self::assertSame('[hidden]', $detail->json('data.contacts.'.$field));
        }
        $this->assertContactsAbsent($detail, $assigned['contacts']);

        $updated = $this->sessionAs($manager)->patchJson('/crm/leads/'.$assigned['id'], ['organization' => 'Updated Organization', 'version' => 1])->assertOk();
        $this->assertNoStore($updated);
        $this->assertContactsAbsent($updated, $assigned['contacts']);

        $hiddenUnassigned = $this->sessionAs($sales)->getJson('/crm/leads/'.$unassigned['id'])->assertNotFound();
        $this->assertSafeNoStoreError($hiddenUnassigned);
        $this->assertContactsAbsent($hiddenUnassigned, $unassigned['contacts']);

        foreach ([$otherDepartment, $otherBranch, $otherCompany] as $hidden) {
            $hiddenDetail = $this->sessionAs($manager)->getJson('/crm/leads/'.$hidden['id'])->assertNotFound();
            $this->assertSafeNoStoreError($hiddenDetail);
            $this->assertContactsAbsent($hiddenDetail, $hidden['contacts']);
            $hiddenUpdate = $this->sessionAs($manager)->patchJson('/crm/leads/'.$hidden['id'], ['organization' => 'Cross-scope edit', 'version' => 0])->assertNotFound();
            $this->assertSafeNoStoreError($hiddenUpdate);
            $this->assertContactsAbsent($hiddenUpdate, $hidden['contacts']);
        }
    }

    public function test_contact_reveal_requires_a_grant_and_reason_and_audits_only_an_encrypted_reason(): void
    {
        $org = $this->organization();
        $manager = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Reveal Manager', ['crm.read', 'crm.manage', 'crm.write', 'crm.assign']);
        $sales = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Reveal Sales', ['crm.read', 'crm.write']);
        $lead = $this->createLead($manager, 'Reveal Prospect');
        $this->assertNoStore($this->sessionAs($manager)->postJson('/crm/leads/'.$lead['id'].'/assignment', ['assignee_id' => $sales, 'version' => 0])->assertSuccessful());

        $before = DB::table('crm_audit')->where('lead_id', $lead['id'])->where('action', 'lead.contacts.revealed')->count();
        $denied = $this->sessionAs($sales)->postJson('/crm/leads/'.$lead['id'].'/reveal', ['reason' => 'Customer requested help'])->assertForbidden();
        $this->assertSafeNoStoreError($denied);
        $this->assertContactsAbsent($denied, $lead['contacts']);
        self::assertSame($before, DB::table('crm_audit')->where('lead_id', $lead['id'])->where('action', 'lead.contacts.revealed')->count());

        DB::table('access_user_permissions')->insert([
            'user_id' => $sales,
            'permission' => 'contact.reveal',
            'allowed' => true,
        ]);
        $this->assertSafeNoStoreError($this->sessionAs($sales)->postJson('/crm/leads/'.$lead['id'].'/reveal', ['reason' => ''])->assertUnprocessable());
        self::assertSame($before, DB::table('crm_audit')->where('lead_id', $lead['id'])->where('action', 'lead.contacts.revealed')->count());

        $reason = 'Customer requested a callback about a quote';
        $revealed = $this->sessionAs($sales)->postJson('/crm/leads/'.$lead['id'].'/reveal', ['reason' => $reason])
            ->assertOk()
            ->assertJsonStructure(['data' => ['contacts' => ['phone', 'email', 'whatsapp']]]);
        $this->assertNoStore($revealed);
        foreach ($lead['contacts'] as $field => $value) {
            self::assertSame($value, $revealed->json('data.contacts.'.$field));
        }

        $audit = DB::table('crm_audit')->where('lead_id', $lead['id'])->where('action', 'lead.contacts.revealed')->first();
        self::assertNotNull($audit);
        $metadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsString($metadata['encrypted_reason'] ?? null);
        self::assertSame($reason, Crypt::decryptString($metadata['encrypted_reason']));
        self::assertStringNotContainsString($reason, (string) $audit->metadata);
    }

    public function test_unknown_identity_body_and_query_fields_are_rejected_without_echoing_submitted_values(): void
    {
        config(['app.debug' => true]);
        $org = $this->organization();
        $manager = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Strict Input Manager', ['crm.read', 'crm.manage']);
        $secretContacts = [
            'phone' => 'HTTP-SECRET-REJECTED-PHONE',
            'email' => 'http-secret-rejected@example.test',
            'whatsapp' => 'HTTP-SECRET-REJECTED-WHATSAPP',
        ];
        $body = array_replace($this->leadPayload('Rejected Sensitive Input'), [
            'company_id' => $org['company_b'],
            'access_user_id' => $manager,
            'is_admin' => true,
            'contacts' => $secretContacts,
        ]);

        $bodyError = $this->sessionAs($manager)->postJson('/crm/leads', $body)->assertUnprocessable();
        $this->assertSafeNoStoreError($bodyError);
        $this->assertContactsAbsent($bodyError, $secretContacts);
        self::assertStringNotContainsString($org['company_b'], $bodyError->getContent());
        self::assertStringNotContainsString($manager, $bodyError->getContent());

        $querySecret = 'HTTP-SECRET-QUERY-CONTACT';
        $queryError = $this->sessionAs($manager)->getJson('/crm/leads?company_id='.$org['company_b'].'&access_user_id='.$manager.'&contacts='.$querySecret)->assertUnprocessable();
        $this->assertSafeNoStoreError($queryError);
        self::assertStringNotContainsString($org['company_b'], $queryError->getContent());
        self::assertStringNotContainsString($manager, $queryError->getContent());
        self::assertStringNotContainsString($querySecret, $queryError->getContent());
    }

    public function test_unexpected_contact_decryption_errors_are_sanitized_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);
        $org = $this->organization();
        $manager = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP Error Manager', ['crm.read', 'crm.manage']);
        $lead = $this->createLead($manager, 'Corrupt Contact');
        $corruptValue = 'HTTP-INVALID-ENCRYPTED-CONTACT-PAYLOAD';
        DB::table('crm_leads')->where('id', $lead['id'])->update(['contacts' => $corruptValue]);

        $response = $this->sessionAs($manager)->getJson('/crm/leads/'.$lead['id'])->assertStatus(500);
        $this->assertSafeNoStoreError($response);
        $this->assertContactsAbsent($response, $lead['contacts']);
        self::assertStringNotContainsString($corruptValue, $response->getContent());
        self::assertDoesNotMatchRegularExpression('/\b(?:SQLSTATE|select\s|insert\s|update\s|delete\s)/i', $response->getContent());
    }

    public function test_web_post_routes_enforce_csrf_and_return_no_store_json_errors(): void
    {
        $org = $this->organization();
        $manager = $this->user($org, 'company_a', 'branch_a', 'department_a', 'HTTP CSRF Manager', ['crm.read', 'crm.manage', 'crm.write']);
        $this->app->bind(PreventRequestForgery::class, static fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });

        $csrfToken = Str::random(40);
        $session = ['_token' => $csrfToken, 'access_user_id' => $manager, 'access_session_version' => 0];
        $payload = $this->leadPayload('CSRF Probe');
        $leadCount = DB::table('crm_leads')->count();
        $auditCount = DB::table('crm_audit')->count();

        $rejected = $this->withSession($session)->postJson('/crm/leads', $payload)->assertStatus(419);
        $this->assertSafeNoStoreError($rejected);
        self::assertSame($leadCount, DB::table('crm_leads')->count());
        self::assertSame($auditCount, DB::table('crm_audit')->count());

        $created = $this->withSession($session)->postJson('/crm/leads', $payload, ['X-CSRF-TOKEN' => $csrfToken])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id']]);
        $this->assertNoStore($created);
    }

    /** @return array<string, string> */
    private function organization(): array
    {
        $companyA = $this->id();
        $companyB = $this->id();
        DB::table('access_companies')->insert([
            ['id' => $companyA, 'name' => 'HTTP CRM Company A', 'version' => 0],
            ['id' => $companyB, 'name' => 'HTTP CRM Company B', 'version' => 0],
        ]);

        $ids = [
            'branch_a' => $this->id(),
            'branch_b' => $this->id(),
            'branch_foreign' => $this->id(),
            'department_a' => $this->id(),
            'department_a_other' => $this->id(),
            'department_b' => $this->id(),
            'department_foreign' => $this->id(),
        ];
        foreach ([
            [$ids['branch_a'], $companyA],
            [$ids['branch_b'], $companyA],
            [$ids['branch_foreign'], $companyB],
        ] as [$branchId, $companyId]) {
            DB::table('access_branches')->insert([
                'id' => $branchId,
                'company_id' => $companyId,
                'name' => 'HTTP CRM Branch '.$branchId,
                'code' => 'http-'.strtolower(substr($branchId, -8)),
                'active' => true,
                'version' => 0,
            ]);
        }
        foreach ([
            [$ids['department_a'], $companyA, $ids['branch_a']],
            [$ids['department_a_other'], $companyA, $ids['branch_a']],
            [$ids['department_b'], $companyA, $ids['branch_b']],
            [$ids['department_foreign'], $companyB, $ids['branch_foreign']],
        ] as [$departmentId, $companyId, $branchId]) {
            DB::table('access_departments')->insert([
                'id' => $departmentId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => 'HTTP CRM Department '.$departmentId,
                'code' => 'http-'.strtolower(substr($departmentId, -8)),
                'active' => true,
                'version' => 0,
            ]);
        }

        return ['company_a' => $companyA, 'company_b' => $companyB] + $ids;
    }

    /** @param list<string> $permissions */
    private function user(array $org, string $company, string $branch, string $department, string $name, array $permissions = [], bool $isAdmin = false): string
    {
        $id = $this->id();
        DB::table('access_users')->insert([
            'id' => $id,
            'company_id' => $org[$company],
            'branch_id' => $org[$branch],
            'department_id' => $org[$department],
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.$id.'@example.test',
            'password' => Hash::make('unused-http-test-password'),
            'active' => true,
            'is_admin' => $isAdmin,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($permissions !== []) {
            $role = $this->id();
            DB::table('access_roles')->insert([
                'id' => $role,
                'company_id' => $org[$company],
                'name' => $name.' role '.$role,
                'version' => 0,
            ]);
            foreach ($permissions as $permission) {
                DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => $permission]);
            }
            DB::table('access_user_roles')->insert(['user_id' => $id, 'role_id' => $role]);
        }

        return $id;
    }

    /** @return array{id: string, contacts: array{phone: string, email: string, whatsapp: string}} */
    private function createLead(string $userId, string $name): array
    {
        $contacts = [
            'phone' => 'HTTP-SECRET-PHONE-'.$name,
            'email' => 'http-secret-'.strtolower(str_replace(' ', '-', $name)).'@example.test',
            'whatsapp' => 'HTTP-SECRET-WHATSAPP-'.$name,
        ];
        $response = $this->sessionAs($userId)->postJson('/crm/leads', [
            'name' => $name,
            'organization' => 'HTTP CRM Organization '.$name,
            'source' => 'Referral',
            'contacts' => $contacts,
            'consent' => true,
            'preferences' => ['email' => true],
            'notes' => 'Synthetic HTTP CRM fixture',
        ])->assertSuccessful()->assertJsonStructure(['data' => ['id']]);
        $this->assertNoStore($response);

        return ['id' => (string) $response->json('data.id'), 'contacts' => $contacts];
    }

    /** @return array<string, mixed> */
    private function leadPayload(string $name): array
    {
        return [
            'name' => $name,
            'organization' => 'HTTP CRM Organization '.$name,
            'source' => 'Referral',
            'contacts' => ['phone' => 'HTTP-SECRET-PHONE-'.$name, 'email' => null, 'whatsapp' => null],
            'consent' => true,
            'preferences' => [],
            'notes' => 'Synthetic HTTP CRM fixture',
        ];
    }

    private function sessionAs(string $userId): self
    {
        return $this->withSession([
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ]);
    }

    /** @param array<string, string> $contacts */
    private function assertContactsAbsent(TestResponse $response, array $contacts): void
    {
        $body = $response->getContent();
        foreach ($contacts as $contact) {
            self::assertStringNotContainsString($contact, $body);
        }
    }

    private function assertNoStore(TestResponse $response): void
    {
        self::assertStringContainsString('no-store', strtolower((string) $response->headers->get('Cache-Control')));
    }

    private function assertSafeNoStoreError(TestResponse $response): void
    {
        $this->assertNoStore($response);
        $response->assertHeader('Content-Type', 'application/json');
        $body = $response->json();
        self::assertIsArray($body);
        foreach (['exception', 'trace', 'file', 'line'] as $internalField) {
            self::assertArrayNotHasKey($internalField, $body);
        }
    }

    private function encoded(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }
}

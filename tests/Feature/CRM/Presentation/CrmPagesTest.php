<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use App\Modules\CRM\Application\Runtime\LeadDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class CrmPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_pages_render_in_both_directions_and_plain_get_keeps_json(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->createLead($world, 'Presentation Lead');
        $pages = [
            '/crm',
            '/crm/leads',
            '/crm/leads/create',
            '/crm/leads/'.$leadId.'/edit',
            '/crm/leads/'.$leadId,
        ];

        foreach ($pages as $page) {
            $this->html($world['manager_id'], $page.'?locale=ar')
                ->assertOk()
                ->assertSee('<html lang="ar" dir="rtl">', false)
                ->assertSee('<main id="main-content"', false)
                ->assertSee('<h1', false);
            $this->html($world['manager_id'], $page.'?locale=en')
                ->assertOk()
                ->assertSee('<html lang="en" dir="ltr">', false)
                ->assertSee('<main id="main-content"', false)
                ->assertSee('<h1', false);
        }

        $this->withSession($this->sessionFor($world['manager_id']))
            ->withHeaders(['Accept' => '*/*'])
            ->get('/crm/leads')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_overview_count_uses_only_the_actors_authorized_lead_scope(): void
    {
        $world = $this->seedWorld();
        $this->seedLeadRow($world, $this->fixtureId(1), 'Scoped Prospect', $world['branch_id'], $world['department_id']);
        $foreignScope = $this->seedScope($world['company_id'], 'Foreign CRM Branch');
        $this->seedLeadRow($world, $this->fixtureId(2), 'Foreign Prospect', $foreignScope['branch_id'], $foreignScope['department_id']);

        $this->html($world['manager_id'], '/crm?locale=en')
            ->assertOk()
            ->assertSee('ui-stat-card', false)
            ->assertSeeText('Active leads')
            ->assertSee('class="ui-stat-card__value">1</dd>', false)
            ->assertDontSeeText('Foreign Prospect');
    }

    public function test_html_directory_search_paginates_scoped_rows_and_ignores_locale_as_a_filter(): void
    {
        $world = $this->seedWorld();
        $names = [];
        $createdAt = '2026-10-17 10:00:00';
        foreach (range(1, 27) as $number) {
            $name = sprintf('Needle Prospect %02d', $number);
            $names[] = $name;
            $this->seedLeadRow(
                $world,
                $this->fixtureId($number),
                $name,
                $world['branch_id'],
                $world['department_id'],
                $createdAt,
            );
        }
        $foreignScope = $this->seedScope($world['company_id'], 'Foreign CRM Branch');
        $this->seedLeadRow($world, $this->fixtureId(28), 'Needle Foreign Prospect', $foreignScope['branch_id'], $foreignScope['department_id'], $createdAt);

        $firstPage = $this->html($world['manager_id'], '/crm/leads?q=Needle&locale=en')
            ->assertOk()
            ->assertDontSeeText('Needle Foreign Prospect');
        $firstPageNames = $this->visibleNames((string) $firstPage->getContent(), $names);
        self::assertCount(25, $firstPageNames);

        $secondPage = $this->html($world['manager_id'], '/crm/leads?q=Needle&locale=en&page=2')
            ->assertOk()
            ->assertDontSeeText('Needle Foreign Prospect');
        $secondPageNames = $this->visibleNames((string) $secondPage->getContent(), $names);

        self::assertCount(2, $secondPageNames);
        self::assertSame([], array_values(array_intersect($firstPageNames, $secondPageNames)));
        self::assertEqualsCanonicalizing($names, array_merge($firstPageNames, $secondPageNames));
    }

    public function test_detail_escapes_lead_text_masks_contacts_and_hides_out_of_scope_records(): void
    {
        $world = $this->seedWorld();
        $name = '<script>crm-presentation-xss</script>';
        $leadId = $this->createLead($world, $name, [
            'contacts' => [
                'phone' => 'PRIVATE-PHONE-CRM-DETAIL',
                'email' => 'private-crm-detail@example.test',
                'whatsapp' => 'PRIVATE-WHATSAPP-CRM-DETAIL',
            ],
        ]);
        $html = $this->html($world['manager_id'], '/crm/leads/'.$leadId.'?locale=en')->assertOk()->getContent();

        self::assertStringContainsString('&lt;script&gt;crm-presentation-xss&lt;/script&gt;', (string) $html);
        self::assertStringNotContainsString('<script>crm-presentation-xss</script>', (string) $html);
        self::assertStringNotContainsString('PRIVATE-PHONE-CRM-DETAIL', (string) $html);
        self::assertStringNotContainsString('private-crm-detail@example.test', (string) $html);
        self::assertStringNotContainsString('PRIVATE-WHATSAPP-CRM-DETAIL', (string) $html);

        $foreignScope = $this->seedScope($world['company_id'], 'Hidden CRM Branch');
        $foreignId = $this->fixtureId(90);
        $this->seedLeadRow($world, $foreignId, 'Hidden Prospect', $foreignScope['branch_id'], $foreignScope['department_id']);

        $this->html($world['manager_id'], '/crm/leads/'.$foreignId.'?locale=en')->assertNotFound();
    }

    public function test_html_create_and_edit_redirect_and_advance_the_existing_lead_version(): void
    {
        $world = $this->seedWorld();
        $this->html($world['manager_id'], '/crm/leads/create?locale=en')
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('name="name"', false);

        $this->withSession($this->sessionFor($world['manager_id']))
            ->withHeaders(['Accept' => 'text/html'])
            ->post('/crm/leads?locale=en', $this->leadPayload('Created from CRM form') + ['_token' => csrf_token()])
            ->assertRedirect();
        $leadId = (string) DB::table('crm_leads')->where('name', 'Created from CRM form')->value('id');
        self::assertNotSame('', $leadId);
        $this->assertDatabaseHas('crm_leads', ['id' => $leadId, 'version' => 0]);

        $this->html($world['manager_id'], '/crm/leads/'.$leadId.'/edit?locale=en')
            ->assertOk()
            ->assertSee('name="version"', false)
            ->assertSee('value="0"', false);

        $this->withSession($this->sessionFor($world['manager_id']))
            ->withHeaders(['Accept' => 'text/html'])
            ->patch('/crm/leads/'.$leadId.'?locale=en', [
                'name' => 'Edited from CRM form',
                'version' => '0',
                '_token' => csrf_token(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('crm_leads', [
            'id' => $leadId,
            'name' => 'Edited from CRM form',
            'version' => 1,
        ]);
    }

    public function test_html_create_and_manage_actions_are_forbidden_without_manage_permission(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->createLead($world, 'Managed Prospect');
        $readerId = $this->seedUser($world, 'Read Only CRM User', ['crm.read']);

        $this->html($readerId, '/crm/leads/create?locale=en')
            ->assertForbidden()
            ->assertDontSee('name="name"', false);
        $this->html($readerId, '/crm/leads/'.$leadId.'/edit?locale=en')->assertForbidden();

        $this->withSession($this->sessionFor($readerId))
            ->withHeaders(['Accept' => 'text/html'])
            ->post('/crm/leads?locale=en', $this->leadPayload('Forbidden HTML Create') + ['_token' => csrf_token()])
            ->assertForbidden();
        $this->withSession($this->sessionFor($readerId))
            ->withHeaders(['Accept' => 'text/html'])
            ->patch('/crm/leads/'.$leadId.'?locale=en', [
                'name' => 'Forbidden HTML Update',
                'version' => '0',
                '_token' => csrf_token(),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('crm_leads', ['name' => 'Forbidden HTML Create']);
        $this->assertDatabaseHas('crm_leads', ['id' => $leadId, 'name' => 'Managed Prospect', 'version' => 0]);
    }

    public function test_html_validation_errors_do_not_echo_or_flash_submitted_contacts(): void
    {
        $world = $this->seedWorld();
        $contacts = [
            'phone' => 'PRIVATE-422-PHONE',
            'email' => 'PRIVATE-422-INVALID-EMAIL',
            'whatsapp' => 'PRIVATE-422-WHATSAPP',
        ];
        $payload = $this->leadPayload('Rejected HTML Prospect', ['contacts' => $contacts]);

        $response = $this->withSession($this->sessionFor($world['manager_id']))
            ->withHeaders(['Accept' => 'text/html'])
            ->post('/crm/leads?locale=en', $payload + ['_token' => csrf_token()])
            ->assertStatus(422)
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSessionMissing('_old_input');

        foreach ($contacts as $contact) {
            self::assertStringNotContainsString($contact, (string) $response->getContent());
        }
        $this->assertDatabaseMissing('crm_leads', ['name' => 'Rejected HTML Prospect']);
    }

    public function test_stale_html_update_returns_409_without_mutating_the_lead(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->createLead($world, 'Versioned HTML Prospect');

        $response = $this->withSession($this->sessionFor($world['manager_id']))
            ->withHeaders(['Accept' => 'text/html'])
            ->patch('/crm/leads/'.$leadId.'?locale=en', [
                'name' => 'Stale HTML Change',
                'version' => '9',
                '_token' => csrf_token(),
            ])
            ->assertStatus(409)
            ->assertSee('<html lang="en" dir="ltr">', false);

        self::assertStringNotContainsString('Stale HTML Change', (string) $response->getContent());
        $this->assertDatabaseHas('crm_leads', [
            'id' => $leadId,
            'name' => 'Versioned HTML Prospect',
            'version' => 0,
        ]);
    }

    public function test_html_contact_reveal_is_grant_and_reason_gated_audited_and_ephemeral(): void
    {
        $world = $this->seedWorld();
        $contacts = [
            'phone' => 'PRIVATE-REVEAL-PHONE',
            'email' => 'private-reveal@example.test',
            'whatsapp' => 'PRIVATE-REVEAL-WHATSAPP',
        ];
        $leadId = $this->createLead($world, 'HTML Reveal Prospect', ['contacts' => $contacts]);
        $reason = 'Customer requested a callback about a quote';

        $this->withSession($this->sessionFor($world['manager_id']))
            ->withHeaders(['Accept' => 'text/html'])
            ->post('/crm/leads/'.$leadId.'/reveal?locale=en', ['reason' => $reason, '_token' => csrf_token()])
            ->assertForbidden();
        self::assertSame(0, DB::table('crm_audit')->where('lead_id', $leadId)->where('action', 'lead.contacts.revealed')->count());

        DB::table('access_user_permissions')->insert([
            'user_id' => $world['manager_id'],
            'permission' => 'contact.reveal',
            'allowed' => true,
        ]);
        $withoutReason = $this->withSession($this->sessionFor($world['manager_id']))
            ->withHeaders(['Accept' => 'text/html'])
            ->post('/crm/leads/'.$leadId.'/reveal?locale=en', ['reason' => '', '_token' => csrf_token()])
            ->assertStatus(422);
        foreach ($contacts as $contact) {
            self::assertStringNotContainsString($contact, (string) $withoutReason->getContent());
        }
        self::assertSame(0, DB::table('crm_audit')->where('lead_id', $leadId)->where('action', 'lead.contacts.revealed')->count());

        $revealed = $this->withSession($this->sessionFor($world['manager_id']))
            ->withHeaders(['Accept' => 'text/html'])
            ->post('/crm/leads/'.$leadId.'/reveal?locale=en', ['reason' => $reason, '_token' => csrf_token()])
            ->assertOk();
        $this->assertPrivateNoStore($revealed);
        foreach ($contacts as $contact) {
            $revealed->assertSee($contact, false);
        }

        $audit = DB::table('crm_audit')->where('lead_id', $leadId)->where('action', 'lead.contacts.revealed')->first();
        self::assertNotNull($audit);
        $metadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($metadata);
        $encryptedReason = $metadata['encrypted_reason'] ?? null;
        self::assertIsString($encryptedReason);
        self::assertSame($reason, Crypt::decryptString($encryptedReason));
        self::assertStringNotContainsString($reason, (string) $audit->metadata);

        $subsequentPage = $this->html($world['manager_id'], '/crm/leads/'.$leadId.'?locale=en')
            ->assertOk();
        $this->assertPrivateNoStore($subsequentPage);
        foreach ($contacts as $contact) {
            $subsequentPage->assertDontSee($contact, false);
        }
    }

    public function test_browser_navigation_headers_select_html_for_crm_pages(): void
    {
        $world = $this->seedWorld();

        $this->withSession($this->sessionFor($world['manager_id']))
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Sec-Fetch-Mode' => 'navigate',
            ])
            ->get('/crm/leads/create')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('name="_token"', false);
    }

    public function test_page_only_routes_return_safe_json_404_for_non_html_accepts_while_api_stays_json(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->createLead($world, 'JSON Boundary Prospect');
        $pageOnlyRoutes = ['/crm', '/crm/leads/create', '/crm/leads/'.$leadId.'/edit'];

        foreach (['application/json', '*/*'] as $accept) {
            foreach ($pageOnlyRoutes as $route) {
                $response = $this->withSession($this->sessionFor($world['manager_id']))
                    ->withHeaders(['Accept' => $accept])
                    ->get($route)
                    ->assertNotFound()
                    ->assertExactJson(['code' => 'not_found', 'message' => 'Resource not found.']);
                $this->assertPrivateNoStore($response);
            }
        }

        foreach (['application/json', '*/*'] as $accept) {
            $this->withSession($this->sessionFor($world['manager_id']))
                ->withHeaders(['Accept' => $accept])
                ->get('/crm/leads')
                ->assertOk()
                ->assertHeader('Content-Type', 'application/json')
                ->assertJsonStructure(['data', 'meta']);
        }
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id: string} $world */
    private function createLead(array $world, string $name, array $overrides = []): string
    {
        $request = Request::create('/crm/leads', 'POST');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->replace($this->sessionFor($world['manager_id']));
        $lead = app(LeadDirectory::class)->save($request, array_replace($this->leadPayload($name), $overrides));

        return (string) $lead['id'];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id: string} $world */
    private function seedLeadRow(
        array $world,
        string $id,
        string $name,
        string $branchId,
        string $departmentId,
        ?string $createdAt = null,
    ): void {
        $timestamp = $createdAt ?? now()->toDateTimeString();
        DB::table('crm_leads')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'name' => $name,
            'organization' => 'Synthetic Organization '.$name,
            'source' => 'website',
            'contacts' => 'fixture-contacts',
            'duplicate_key' => null,
            'consent' => false,
            'preferences' => '{}',
            'notes' => null,
            'stage_id' => null,
            'assignee_id' => null,
            'customer_id' => null,
            'archived' => false,
            'version' => 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /** @return array{company_id: string, branch_id: string, department_id: string, manager_id: string} */
    private function seedWorld(): array
    {
        $companyId = (string) Str::ulid();
        DB::table('access_companies')->insert(['id' => $companyId, 'name' => 'Synthetic CRM Presentation Company', 'version' => 0]);
        $scope = $this->seedScope($companyId, 'Presentation CRM Branch');
        $userId = (string) Str::ulid();
        $roleId = (string) Str::ulid();
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $companyId,
            'name' => 'Synthetic CRM Presentation Manager',
            'version' => 0,
        ]);
        foreach (['crm.read', 'crm.manage', 'crm.write'] as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $companyId,
            'branch_id' => $scope['branch_id'],
            'department_id' => $scope['department_id'],
            'name' => 'Synthetic CRM Presentation Manager',
            'email' => 'crm-presentation-'.$userId.'@example.test',
            'password' => Hash::make('unused-presentation-password'),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);

        return [
            'company_id' => $companyId,
            'branch_id' => $scope['branch_id'],
            'department_id' => $scope['department_id'],
            'manager_id' => $userId,
        ];
    }

    /** @return array{branch_id: string, department_id: string} */
    private function seedScope(string $companyId, string $name): array
    {
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => $name,
            'code' => 'crm-'.strtolower(substr($branchId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => $name.' Department',
            'code' => 'crm-'.strtolower(substr($departmentId, -6)),
            'active' => true,
            'version' => 0,
        ]);

        return ['branch_id' => $branchId, 'department_id' => $departmentId];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id: string} $world
     * @param  list<string>  $permissions
     */
    private function seedUser(array $world, string $name, array $permissions): string
    {
        $userId = (string) Str::ulid();
        $roleId = (string) Str::ulid();
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $world['company_id'],
            'name' => 'Synthetic CRM '.$name.' Role',
            'version' => 0,
        ]);
        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => $name,
            'email' => 'crm-'.strtolower(str_replace(' ', '-', $userId)).'@example.test',
            'password' => Hash::make('unused-presentation-password'),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);

        return $userId;
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function leadPayload(string $name, array $overrides = []): array
    {
        $slug = strtolower(str_replace(' ', '-', strip_tags($name)));

        return array_replace([
            'name' => $name,
            'organization' => 'Synthetic CRM Organization '.$name,
            'source' => 'website',
            'contacts' => [
                'phone' => 'CRM-PRESENTATION-PHONE-'.$slug,
                'email' => 'crm-presentation-'.$slug.'@example.test',
                'whatsapp' => 'CRM-PRESENTATION-WHATSAPP-'.$slug,
            ],
            'consent' => true,
            'preferences' => ['email' => true, 'phone' => false, 'whatsapp' => false],
            'notes' => 'Synthetic CRM presentation note',
            'stage_id' => null,
        ], $overrides);
    }

    /** @return array{access_user_id: string, access_session_version: int} */
    private function sessionFor(string $userId): array
    {
        return [
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ];
    }

    private function fixtureId(int $number): string
    {
        return sprintf('01ARASYA%018d', $number);
    }

    /** @param list<string> $names
     * @return list<string>
     */
    private function visibleNames(string $html, array $names): array
    {
        return array_values(array_filter($names, static fn (string $name): bool => str_contains($html, $name)));
    }

    private function html(string $userId, string $uri): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))
            ->withHeaders(['Accept' => 'text/html'])
            ->get($uri);
    }

    private function assertPrivateNoStore(TestResponse $response): void
    {
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }
}

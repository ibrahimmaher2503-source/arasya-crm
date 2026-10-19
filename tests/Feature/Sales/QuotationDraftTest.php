<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Modules\Sales\Application\QuotationDrafts;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class QuotationDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculation_returns_exact_decimal_totals_without_rounding(): void
    {
        $totals = app(QuotationDrafts::class)->calculate([$this->line()]);

        foreach (['subtotal' => '20.50', 'discount' => '0.50', 'tax' => '3', 'total' => '23'] as $key => $expected) {
            self::assertIsString($totals[$key]);
            self::assertTrue(BigDecimal::of($totals[$key])->isEqualTo($expected), $key);
        }
    }

    public function test_creation_normalizes_sparse_line_keys_and_returns_correct_totals(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(6);
        $this->seedLead($world, $leadId);
        $payload = $this->draftPayload();
        $payload['lines'] = [
            0 => $this->line(),
            2 => array_replace($this->line(), [
                'description' => 'Second synthetic item',
                'quantity' => '1',
                'unit_price' => '4.00',
                'discount' => '0',
                'tax_rate' => '0',
            ]),
        ];

        $response = $this->postJsonAs($world['manager_id'], '/crm/leads/'.$leadId.'/quotations', $payload)
            ->assertCreated();
        $data = $response->json('data');

        self::assertSame([0, 1], array_keys($data['lines']));
        foreach (['subtotal' => '24.50', 'discount' => '0.50', 'tax' => '3', 'total' => '27'] as $key => $expected) {
            self::assertTrue(BigDecimal::of($data['totals'][$key])->isEqualTo($expected), $key);
        }
    }

    public function test_calculation_rejects_invalid_decimal_inputs_and_more_than_fifty_lines(): void
    {
        $drafts = app(QuotationDrafts::class);
        $valid = $this->line();
        $invalid = [
            [array_replace($valid, ['quantity' => 2.0])],
            [array_replace($valid, ['quantity' => '-1'])],
            [array_replace($valid, ['quantity' => '0.0000001'])],
            [array_replace($valid, ['unit_price' => '-0.01'])],
            [array_replace($valid, ['unit_price' => '10.2500001'])],
            [array_replace($valid, ['discount' => '-0.01'])],
            [array_replace($valid, ['discount' => '20.51'])],
            [array_replace($valid, ['tax_rate' => '-0.1'])],
            [array_diff_key($valid, ['tax_rate' => true])],
            [array_replace($valid, ['tax_rate' => '15%'])],
            [array_replace($valid, ['tax_rate' => '100.000001'])],
            [array_replace($valid, ['description' => str_repeat('x', 201)])],
            array_fill(0, 51, $valid),
        ];

        foreach ($invalid as $lines) {
            try {
                $drafts->calculate($lines);
                self::fail('Invalid quotation lines must be rejected.');
            } catch (ValidationException $exception) {
                self::assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_scoped_lead_can_create_list_and_preview_only_a_draft(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(1);
        $this->seedLead($world, $leadId);
        $url = '/crm/leads/'.$leadId.'/quotations';
        $payload = $this->draftPayload();

        $created = $this->postJsonAs($world['manager_id'], $url, $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 0);
        $quotationId = $created->json('data.id');
        self::assertIsString($quotationId);
        $this->assertDatabaseHas('quotation_drafts', [
            'id' => $quotationId,
            'company_id' => $world['company_id'],
            'lead_id' => $leadId,
            'created_by' => $world['manager_id'],
            'currency' => 'USD',
            'status' => 'draft',
            'version' => 0,
        ]);

        $this->htmlAs($world['manager_id'], $url.'?locale=en')
            ->assertOk()
            ->assertSeeText('Synthetic quotation draft');
        $preview = $this->htmlAs($world['manager_id'], $url.'/'.$quotationId.'/preview?locale=en')->assertOk();
        self::assertStringContainsString('text/html', (string) $preview->headers->get('Content-Type'));
        $preview->assertSeeText('Synthetic quotation draft')->assertSeeText('USD');

        $this->postJsonAs($world['manager_id'], $url, array_replace($payload, ['status' => 'approved']))
            ->assertUnprocessable();
        $this->postJsonAs($world['manager_id'], $url, array_replace($payload, ['currency' => 'usd']))
            ->assertUnprocessable();
        self::assertSame(1, DB::table('quotation_drafts')->where('lead_id', $leadId)->count());
    }

    public function test_synthetic_drafts_require_testing_environment_and_explicit_config(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(2);
        $this->seedLead($world, $leadId);
        $url = '/crm/leads/'.$leadId.'/quotations';

        config(['sales.synthetic_drafts' => false]);
        $this->postJsonAs($world['manager_id'], $url, $this->draftPayload())->assertNotFound();

        config(['sales.synthetic_drafts' => true]);
        $environment = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            $response = $this->postJsonAs($world['manager_id'], $url, $this->draftPayload());
        } finally {
            $this->app['env'] = $environment;
        }

        $response->assertNotFound();
        self::assertSame(0, DB::table('quotation_drafts')->where('lead_id', $leadId)->count());
    }

    public function test_quote_reads_and_writes_require_crm_grants_and_lead_scope(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(3);
        $this->seedLead($world, $leadId);
        $url = '/crm/leads/'.$leadId.'/quotations';

        $readerId = $this->seedUser($world, 'Synthetic quotation reader', ['crm.read']);
        DB::table('crm_leads')->where('id', $leadId)->update(['assignee_id' => $readerId]);
        $this->htmlAs($readerId, $url.'?locale=en')->assertOk();
        $this->postJsonAs($readerId, $url, $this->draftPayload())->assertForbidden();

        $writeOnlyId = $this->seedUser($world, 'Synthetic quotation writer', ['crm.write']);
        $this->postJsonAs($writeOnlyId, $url, $this->draftPayload())->assertForbidden();

        $unassignedId = $this->seedUser($world, 'Synthetic unassigned quotation writer', ['crm.read', 'crm.write']);
        $this->postJsonAs($unassignedId, $url, $this->draftPayload())->assertNotFound();

        $foreignWorld = $this->seedWorld();
        $foreignLeadId = $this->fixtureId(4);
        $this->seedLead($foreignWorld, $foreignLeadId);
        $this->htmlAs($world['manager_id'], '/crm/leads/'.$foreignLeadId.'/quotations?locale=en')->assertNotFound();
    }

    public function test_updates_reject_stale_versions_and_approval_or_sending(): void
    {
        config(['sales.synthetic_drafts' => true]);
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(5);
        $this->seedLead($world, $leadId);
        $url = '/crm/leads/'.$leadId.'/quotations';
        $payload = $this->draftPayload();
        $created = $this->postJsonAs($world['manager_id'], $url, $payload)->assertCreated();
        $quotationId = (string) $created->json('data.id');
        $itemUrl = $url.'/'.$quotationId;

        $update = array_replace($payload, ['title' => 'Updated synthetic draft', 'version' => 0]);
        $this->patchJsonAs($world['manager_id'], $itemUrl, $update)
            ->assertOk()
            ->assertJsonPath('data.version', 1);
        $this->patchJsonAs($world['manager_id'], $itemUrl, array_replace($update, ['title' => 'Stale edit']))
            ->assertStatus(409);

        foreach (['approved', 'sent'] as $status) {
            $this->patchJsonAs($world['manager_id'], $itemUrl, array_replace($payload, [
                'version' => 1,
                'status' => $status,
            ]))->assertUnprocessable();
        }
        $this->postJsonAs($world['manager_id'], $itemUrl.'/approve', [])->assertForbidden();
        $this->postJsonAs($world['manager_id'], $itemUrl.'/send', [])->assertForbidden();

        $this->assertDatabaseHas('quotation_drafts', [
            'id' => $quotationId,
            'status' => 'draft',
            'version' => 1,
            'title' => 'Updated synthetic draft',
        ]);
    }

    /** @return array{description: string, quantity: string, unit_price: string, discount: string, tax_rate: string} */
    private function line(): array
    {
        return [
            'description' => 'Synthetic item',
            'quantity' => '2',
            'unit_price' => '10.25',
            'discount' => '0.50',
            'tax_rate' => '15',
        ];
    }

    /** @return array{title: string, currency: string, lines: list<array<string, string>>} */
    private function draftPayload(): array
    {
        return [
            'title' => 'Synthetic quotation draft',
            'currency' => 'USD',
            'lines' => [$this->line()],
        ];
    }

    /** @return array{company_id: string, branch_id: string, department_id: string, manager_id: string} */
    private function seedWorld(): array
    {
        $companyId = (string) Str::ulid();
        DB::table('access_companies')->insert([
            'id' => $companyId,
            'name' => 'Synthetic Quotation Company',
            'version' => 0,
        ]);
        $branchId = (string) Str::ulid();
        $departmentId = (string) Str::ulid();
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => 'Synthetic Quotation Branch',
            'code' => 'quote-'.strtolower(substr($branchId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Synthetic Quotation Department',
            'code' => 'quote-'.strtolower(substr($departmentId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        $world = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
        ];

        return $world + ['manager_id' => $this->seedUser(
            $world,
            'Synthetic Quotation Manager',
            ['crm.read', 'crm.manage', 'crm.write'],
        )];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string} $world */
    private function seedLead(array $world, string $id): void
    {
        DB::table('crm_leads')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => 'Synthetic Quotation Lead',
            'organization' => 'Synthetic Quotation Organization',
            'source' => 'website',
            'contacts' => Crypt::encryptString(json_encode([
                'phone' => null,
                'email' => null,
                'whatsapp' => null,
            ], JSON_THROW_ON_ERROR)),
            'duplicate_key' => null,
            'consent' => false,
            'preferences' => '{}',
            'notes' => null,
            'stage_id' => null,
            'assignee_id' => null,
            'customer_id' => null,
            'archived' => false,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param array{company_id: string, branch_id: string, department_id: string} $world
     * @param list<string> $permissions
     */
    private function seedUser(array $world, string $name, array $permissions): string
    {
        $userId = (string) Str::ulid();
        $roleId = (string) Str::ulid();
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $world['company_id'],
            'name' => $name.' role',
            'version' => 0,
        ]);
        foreach ($permissions as $permission) {
            DB::table('access_role_permissions')->insert([
                'role_id' => $roleId,
                'permission' => $permission,
            ]);
        }
        DB::table('access_users')->insert([
            'id' => $userId,
            'company_id' => $world['company_id'],
            'branch_id' => $world['branch_id'],
            'department_id' => $world['department_id'],
            'name' => $name,
            'email' => 'quotation-'.strtolower(substr($userId, -8)).'@example.test',
            'password' => Hash::make('unused-synthetic-quotation-password'),
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('access_user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);

        return $userId;
    }

    /** @return array{access_user_id: string, access_session_version: int} */
    private function sessionFor(string $userId): array
    {
        return [
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')
                ->where('id', $userId)
                ->value('session_version'),
        ];
    }

    private function fixtureId(int $number): string
    {
        return sprintf('01ARASYA%018d', $number);
    }

    private function htmlAs(string $userId, string $uri): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))
            ->withHeaders(['Accept' => 'text/html'])
            ->get($uri);
    }

    /** @param array<string, mixed> $payload */
    private function postJsonAs(string $userId, string $uri, array $payload): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))->postJson($uri, $payload);
    }

    /** @param array<string, mixed> $payload */
    private function patchJsonAs(string $userId, string $uri, array $payload): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))->patchJson($uri, $payload);
    }
}


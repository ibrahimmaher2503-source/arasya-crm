<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LeadDirectoryHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_routes_create_read_update_and_archive_a_lead(): void
    {
        $world = $this->seedWorld();

        $created = $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/leads', $this->leadPayload('Route Prospect'))
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'version']])
            ->assertJsonPath('data.name', 'Route Prospect')
            ->assertJsonPath('data.version', 0);
        $leadId = (string) $created->json('data.id');

        $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/leads/'.$leadId)
            ->assertOk()
            ->assertJsonPath('data.id', $leadId)
            ->assertJsonPath('data.name', 'Route Prospect');

        $this->withSession($this->sessionFor($world['manager_id']))
            ->patchJson('/crm/leads/'.$leadId, ['name' => 'Updated Prospect', 'version' => 0])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Prospect')
            ->assertJsonPath('data.version', 1);

        $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/leads/'.$leadId.'/archive', ['version' => 1])
            ->assertOk()
            ->assertJsonPath('data.archived', true)
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('crm_leads', [
            'id' => $leadId,
            'name' => 'Updated Prospect',
            'archived' => true,
            'version' => 2,
        ]);
    }

    public function test_lead_listing_is_scoped_filtered_paginated_and_stable(): void
    {
        $world = $this->seedWorld();
        $stageId = $this->seedStage($world['company_id'], 'Qualified');
        $otherStageId = $this->seedStage($world['company_id'], 'Contacted');
        $sameTime = '2026-10-17 10:00:00';

        foreach (range(1, 27) as $number) {
            $this->seedLeadRow(
                $world,
                $this->fixtureId($number),
                sprintf('Needle Prospect %02d', $number),
                $stageId,
                false,
                $sameTime,
            );
        }

        $archivedId = $this->fixtureId(28);
        $this->seedLeadRow($world, $archivedId, 'Needle Archived', $stageId, true, $sameTime);
        $this->seedLeadRow($world, $this->fixtureId(29), 'Needle Different Stage', $otherStageId, false, $sameTime);
        $this->seedLeadRow($world, $this->fixtureId(30), 'Unrelated Prospect', $stageId, false, $sameTime);

        $foreignScope = $this->seedScope($world['company_id'], 'Foreign Branch');
        $this->seedLeadRow(
            $world,
            $this->fixtureId(31),
            'Needle Foreign Branch',
            $stageId,
            false,
            $sameTime,
            $foreignScope['branch_id'],
            $foreignScope['department_id'],
        );
        $latestId = $this->fixtureId(0);
        $this->seedLeadRow($world, $latestId, 'Needle Latest Prospect', $stageId, false, '2026-10-17 10:01:00');

        $filters = http_build_query(['q' => 'Needle', 'stage' => $stageId, 'archived' => 'false']);
        $firstPage = $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/leads?'.$filters)
            ->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 28)
            ->assertJsonPath('meta.last_page', 2);

        $this->assertSame(
            array_merge(
                [$latestId],
                array_map(fn (int $number): string => $this->fixtureId($number), range(27, 4)),
            ),
            array_column($firstPage->json('data'), 'id'),
        );

        $secondPage = $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/leads?'.$filters.'&page=2')
            ->assertOk()
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.total', 28);
        $this->assertSame(
            [$this->fixtureId(3), $this->fixtureId(2), $this->fixtureId(1)],
            array_column($secondPage->json('data'), 'id'),
        );

        $allMatching = $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/leads?'.$filters.'&per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('meta.last_page', 1);
        $this->assertCount(28, $allMatching->json('data'));

        $archived = $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/leads?'.http_build_query([
                'q' => 'Needle',
                'stage' => $stageId,
                'archived' => 'true',
            ]))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $this->assertSame([$archivedId], array_column($archived->json('data'), 'id'));
    }

    public function test_invalid_list_filters_return_unprocessable(): void
    {
        $world = $this->seedWorld();
        $invalidQueriesByRoute = [
            '/crm/leads' => [
                ['page' => 0],
                ['per_page' => 0],
                ['per_page' => 101],
                ['q' => str_repeat('x', 121)],
                ['stage' => 'missing-stage'],
                ['archived' => 'sometimes'],
            ],
            '/crm/taxonomy' => [
                ['page' => 0],
                ['per_page' => 101],
            ],
            '/crm/recipients' => [
                ['page' => 0],
                ['per_page' => 101],
            ],
        ];

        foreach ($invalidQueriesByRoute as $route => $queries) {
            foreach ($queries as $query) {
                $this->withSession($this->sessionFor($world['manager_id']))
                    ->getJson($route.'?'.http_build_query($query))
                    ->assertUnprocessable();
            }
        }
    }

    public function test_duplicate_contact_creation_returns_conflict_without_a_second_lead(): void
    {
        $world = $this->seedWorld();
        $payload = $this->leadPayload('First Duplicate Prospect');

        $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/leads', $payload)
            ->assertCreated();

        $auditCount = DB::table('crm_audit')->count();
        $duplicate = array_replace($payload, ['name' => 'Second Duplicate Prospect']);

        $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/leads', $duplicate)
            ->assertConflict();

        $this->assertSame(1, DB::table('crm_leads')->where('company_id', $world['company_id'])->count());
        $this->assertSame($auditCount, DB::table('crm_audit')->count());
    }

    public function test_stale_lead_version_returns_conflict_without_overwriting(): void
    {
        $world = $this->seedWorld();
        $created = $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/leads', $this->leadPayload('Versioned Prospect'))
            ->assertCreated();
        $leadId = (string) $created->json('data.id');
        $auditCount = DB::table('crm_audit')->count();

        $this->withSession($this->sessionFor($world['manager_id']))
            ->patchJson('/crm/leads/'.$leadId, ['name' => 'Stale Change', 'version' => 9])
            ->assertConflict();

        $this->assertDatabaseHas('crm_leads', [
            'id' => $leadId,
            'name' => 'Versioned Prospect',
            'version' => 0,
        ]);
        $this->assertSame($auditCount, DB::table('crm_audit')->count());
    }

    public function test_recipient_list_and_assignment_route_use_the_session_actor(): void
    {
        $world = $this->seedWorld();
        $recipients = $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/recipients')
            ->assertOk()
            ->assertJsonStructure(['data']);
        $this->assertContains($world['owner_id'], array_column($recipients->json('data'), 'id'));

        $created = $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/leads', $this->leadPayload('Assigned Prospect'))
            ->assertCreated();
        $leadId = (string) $created->json('data.id');

        $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/leads/'.$leadId.'/assignment', [
                'assignee_id' => $world['owner_id'],
                'version' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.assignee_id', $world['owner_id'])
            ->assertJsonPath('data.version', 1);
    }

    public function test_recipient_listing_filters_permissions_before_bounded_pagination(): void
    {
        $world = $this->seedWorld();

        foreach (range(0, 9) as $number) {
            $this->seedUser($world['company_id'], $world['branch_id'], $world['department_id'], sprintf('Aaa Ineligible %03d', $number), []);
        }

        $eligibleIds = [];
        foreach (range(0, 104) as $number) {
            $eligibleIds[] = $this->seedUser(
                $world['company_id'],
                $world['branch_id'],
                $world['department_id'],
                sprintf('Eligible %03d', $number),
                ['crm.read', 'crm.write'],
            );
        }

        $defaultPage = $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/recipients')
            ->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 106)
            ->assertJsonPath('meta.last_page', 5);
        $this->assertSame(array_slice($eligibleIds, 0, 25), array_column($defaultPage->json('data'), 'id'));

        $firstHundred = $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/recipients?per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('meta.total', 106)
            ->assertJsonPath('meta.last_page', 2);
        $this->assertSame(array_slice($eligibleIds, 0, 100), array_column($firstHundred->json('data'), 'id'));

        $lastPage = $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/recipients?per_page=100&page=2')
            ->assertOk()
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.total', 106);
        $this->assertSame(
            [...array_slice($eligibleIds, 100), $world['owner_id']],
            array_column($lastPage->json('data'), 'id'),
        );
    }

    public function test_taxonomy_listing_has_bounded_pages_sorted_by_kind_label_and_id(): void
    {
        $world = $this->seedWorld();
        $callId = $this->fixtureId(1000);
        $emailId = $this->fixtureId(1001);
        $this->seedTaxonomyRow($world['company_id'], $emailId, 'outcome', 'Email');
        $this->seedTaxonomyRow($world['company_id'], $callId, 'outcome', 'Call');

        foreach (range(0, 101) as $number) {
            $this->seedTaxonomyRow(
                $world['company_id'],
                $this->fixtureId($number + 1),
                'stage',
                sprintf('Stage %03d', $number),
            );
        }
        $secondStageFiftyId = $this->fixtureId(105);
        $this->seedTaxonomyRow($world['company_id'], $secondStageFiftyId, 'stage', 'Stage 050');

        $defaultPage = $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/taxonomy')
            ->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 105)
            ->assertJsonPath('meta.last_page', 5);
        $this->assertCount(25, $defaultPage->json('data'));

        $firstHundred = $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/taxonomy?per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('meta.total', 105)
            ->assertJsonPath('meta.last_page', 2);
        $this->assertCount(100, $firstHundred->json('data'));
        foreach ($firstHundred->json('data') as $value) {
            $this->assertArrayHasKey('id', $value);
            $this->assertArrayHasKey('kind', $value);
            $this->assertArrayHasKey('label', $value);
        }
        $firstHundredIds = array_column($firstHundred->json('data'), 'id');
        $this->assertSame(
            [$callId, $emailId, $this->fixtureId(1), $this->fixtureId(2)],
            array_slice($firstHundredIds, 0, 4),
        );
        $this->assertLessThan(
            array_search($secondStageFiftyId, $firstHundredIds, true),
            array_search($this->fixtureId(51), $firstHundredIds, true),
        );

        $lastPage = $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/taxonomy?per_page=100&page=2')
            ->assertOk()
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.total', 105);
        $this->assertSame(
            array_map(fn (int $number): string => $this->fixtureId($number), range(98, 102)),
            array_column($lastPage->json('data'), 'id'),
        );
    }

    public function test_taxonomy_replacement_keeps_the_old_value_and_existing_lead_reference(): void
    {
        $world = $this->seedWorld();
        $original = $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/taxonomy', ['kind' => 'stage', 'label' => 'New'])
            ->assertCreated()
            ->json('data');
        $lead = $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/leads', $this->leadPayload('Historical Stage Prospect', [
                'stage_id' => $original['id'],
            ]))
            ->assertCreated();
        $leadId = (string) $lead->json('data.id');

        $replacement = $this->withSession($this->sessionFor($world['manager_id']))
            ->postJson('/crm/taxonomy', [
                'kind' => 'stage',
                'label' => 'Qualified',
                'replaces_id' => $original['id'],
            ])
            ->assertCreated()
            ->json('data');

        $current = $this->withSession($this->sessionFor($world['manager_id']))
            ->getJson('/crm/taxonomy')
            ->assertOk()
            ->json('data');
        $currentIds = array_column($current, 'id');

        $this->assertContains($replacement['id'], $currentIds);
        $this->assertNotContains($original['id'], $currentIds);
        $this->assertDatabaseHas('crm_taxonomy', [
            'id' => $original['id'],
            'label' => 'New',
            'replaces_id' => null,
        ]);
        $this->assertDatabaseHas('crm_taxonomy', [
            'id' => $replacement['id'],
            'label' => 'Qualified',
            'replaces_id' => $original['id'],
        ]);
        $this->assertDatabaseHas('crm_leads', ['id' => $leadId, 'stage_id' => $original['id']]);
    }

    /** @return array<string, string> */
    private function seedWorld(): array
    {
        $companyId = $this->id();
        DB::table('access_companies')->insert([
            'id' => $companyId,
            'name' => 'Synthetic CRM Company',
            'version' => 0,
        ]);
        $scope = $this->seedScope($companyId, 'CRM Branch');

        return [
            'company_id' => $companyId,
            'branch_id' => $scope['branch_id'],
            'department_id' => $scope['department_id'],
            'manager_id' => $this->seedUser($companyId, $scope['branch_id'], $scope['department_id'], 'Manager', [
                'crm.read', 'crm.manage', 'crm.assign', 'crm.taxonomy',
            ]),
            'owner_id' => $this->seedUser($companyId, $scope['branch_id'], $scope['department_id'], 'Owner', [
                'crm.read', 'crm.write',
            ]),
        ];
    }

    /** @return array{branch_id: string, department_id: string} */
    private function seedScope(string $companyId, string $name): array
    {
        $branchId = $this->id();
        $departmentId = $this->id();
        DB::table('access_branches')->insert([
            'id' => $branchId,
            'company_id' => $companyId,
            'name' => $name,
            'code' => 'branch-'.strtolower(substr($branchId, -6)),
            'active' => true,
            'version' => 0,
        ]);
        DB::table('access_departments')->insert([
            'id' => $departmentId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => $name.' Department',
            'code' => 'department-'.strtolower(substr($departmentId, -6)),
            'active' => true,
            'version' => 0,
        ]);

        return ['branch_id' => $branchId, 'department_id' => $departmentId];
    }

    /** @param list<string> $permissions */
    private function seedUser(string $companyId, string $branchId, string $departmentId, string $name, array $permissions): string
    {
        $userId = $this->id();
        $roleId = $this->id();
        $now = now()->toDateTimeString();
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $companyId,
            'name' => 'Synthetic CRM '.$name.' Role '.$roleId,
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
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'name' => 'Synthetic CRM '.$name,
            'email' => 'crm.'.strtolower($name).'.'.strtolower(substr($userId, -6)).'@example.test',
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

    private function seedStage(string $companyId, string $label): string
    {
        $id = $this->id();
        $this->seedTaxonomyRow($companyId, $id, 'stage', $label);

        return $id;
    }

    private function seedTaxonomyRow(string $companyId, string $id, string $kind, string $label, ?string $replacesId = null): void
    {
        DB::table('crm_taxonomy')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'kind' => $kind,
            'label' => $label,
            'replaces_id' => $replacesId,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    private function seedLeadRow(
        array $world,
        string $id,
        string $name,
        string $stageId,
        bool $archived,
        string $createdAt,
        ?string $branchId = null,
        ?string $departmentId = null,
    ): void {
        DB::table('crm_leads')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'branch_id' => $branchId ?? $world['branch_id'],
            'department_id' => $departmentId ?? $world['department_id'],
            'name' => $name,
            'organization' => 'Synthetic Organization '.$name,
            'source' => 'website',
            'contacts' => 'fixture-contacts',
            'duplicate_key' => null,
            'consent' => false,
            'preferences' => '{}',
            'notes' => null,
            'stage_id' => $stageId,
            'assignee_id' => null,
            'customer_id' => null,
            'archived' => $archived,
            'version' => 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /** @return array<string, mixed> */
    private function leadPayload(string $name, array $overrides = []): array
    {
        $slug = strtolower(str_replace(' ', '-', $name));

        return array_replace([
            'name' => $name,
            'organization' => 'Synthetic Organization '.$name,
            'source' => 'website',
            'contacts' => [
                'phone' => 'phone-'.$slug,
                'email' => $slug.'@example.test',
                'whatsapp' => 'whatsapp-'.$slug,
            ],
            'consent' => true,
            'preferences' => ['email' => true, 'whatsapp' => false],
            'notes' => 'Synthetic lead note',
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

    private function id(): string
    {
        return (string) Str::ulid();
    }
}

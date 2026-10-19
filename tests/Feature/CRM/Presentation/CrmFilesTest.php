<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Presentation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class CrmFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_reads_preserve_the_public_contract_without_lead_mutation_or_contact_disclosure(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(999);
        $this->seedLead($world, $leadId, 'Characterized files lead');
        $response = $this->html($world['manager_id'], '/crm/leads/'.$leadId.'/files?locale=en')->assertOk();
        $response->assertViewHas('bytes', 0)->assertViewHas('categories', []);
        self::assertArrayNotHasKey('contacts', $response->viewData('lead'));
        self::assertSame(0, $response->viewData('lead')['version']);
        self::assertSame(0, DB::table('crm_audit')->where('lead_id', $leadId)->count());
        $this->assertDatabaseHas('crm_leads', ['id' => $leadId, 'version' => 0]);
    }

    public function test_file_list_is_html_searchable_category_filtered_and_bounded(): void
    {
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(1);
        $this->seedLead($world, $leadId, 'Synthetic Files Lead');
        $baseUrl = '/crm/leads/'.$leadId.'/files';

        $empty = $this->html($world['manager_id'], $baseUrl.'?locale=en')->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('<table', false);

        $names = [];
        foreach (range(1, 105) as $number) {
            $name = sprintf('Needle-contract-%03d.pdf', $number);
            $names[] = $name;
            $this->seedFile($world, $leadId, $number + 100, $name, 'Contracts');
        }
        $this->seedFile($world, $leadId, 300, 'Needle-other-category.pdf', 'Other');
        $this->seedFile($world, $leadId, 301, 'Unrelated-contract.pdf', 'Contracts');
        $privatePathFile = $this->seedFile($world, $leadId, 302, 'Needle-private-path-check.pdf', 'Contracts');
        $names[] = 'Needle-private-path-check.pdf';

        $filters = ['locale' => 'en', 'q' => 'Needle', 'category' => 'Contracts'];
        $pageOne = $this->html($world['manager_id'], $baseUrl.'?'.http_build_query($filters))->assertOk();
        $pageOneNames = $this->visibleNames((string) $pageOne->getContent(), $names);
        self::assertCount(25, $pageOneNames);
        $pageOne->assertDontSeeText('Needle-other-category.pdf')
            ->assertDontSeeText('Unrelated-contract.pdf');

        $pageTwo = $this->html(
            $world['manager_id'],
            $baseUrl.'?'.http_build_query($filters + ['page' => 2]),
        )->assertOk();
        self::assertCount(25, $this->visibleNames((string) $pageTwo->getContent(), $names));

        $capped = $this->html(
            $world['manager_id'],
            $baseUrl.'?'.http_build_query($filters + ['per_page' => 100]),
        )->assertOk();
        self::assertCount(100, $this->visibleNames((string) $capped->getContent(), $names));
        $this->html(
            $world['manager_id'],
            $baseUrl.'?'.http_build_query($filters + ['per_page' => 999]),
        )->assertStatus(422);

        $pathPrivacy = $this->html(
            $world['manager_id'],
            $baseUrl.'?'.http_build_query(['locale' => 'en', 'q' => 'private-path-check', 'category' => 'Contracts']),
        )->assertOk()->assertSeeText('Needle-private-path-check.pdf');
        self::assertStringNotContainsString($privatePathFile['path'], (string) $pathPrivacy->getContent());
    }

    public function test_upload_accepts_allowlisted_types_up_to_50mb_and_keeps_storage_path_private(): void
    {
        Storage::fake('local');
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(2);
        $this->seedLead($world, $leadId, 'Synthetic Upload Lead');
        $allowedFiles = [
            ['contract.pdf', 'application/pdf', 1],
            ['photo.png', 'image/png', 1],
            ['photo.jpg', 'image/jpeg', 1],
            ['photo.jpeg', 'image/jpeg', 1],
            ['readme.txt', 'text/plain', 1],
            ['report.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 1],
            ['report.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 1],
            ['max-size.pdf', 'application/pdf', 51200],
        ];

        foreach ($allowedFiles as $index => [$name, $mime, $kilobytes]) {
            $response = $this->postFile($world['manager_id'], $leadId, [
                'file' => UploadedFile::fake()->create($name, $kilobytes, $mime),
                'version' => (string) $index,
            ])->assertCreated()
                ->assertJsonStructure(['data' => ['id', 'name', 'mime', 'size', 'description', 'category']]);

            $data = $response->json('data');
            self::assertIsArray($data);
            self::assertArrayNotHasKey('path', $data);
            $fileId = (string) $data['id'];
            $stored = DB::table('crm_files')->where('id', $fileId)->first();
            self::assertNotNull($stored);
            self::assertSame($name, $stored->name);
            self::assertSame($world['manager_id'], $stored->uploader_id);
            self::assertMatchesRegularExpression(
                '#^crm/'.preg_quote($world['company_id'], '#').'/'.preg_quote($leadId, '#').'/[0-9A-HJKMNP-TV-Z]{26}$#',
                $stored->path,
            );
            self::assertStringNotContainsString($stored->path, json_encode($data, JSON_THROW_ON_ERROR));
            Storage::disk('local')->assertExists($stored->path);

            if ($index === 0) {
                self::assertNull($stored->description);
                self::assertSame('Other', $stored->category);
            }
        }

        $this->assertDatabaseHas('crm_leads', ['id' => $leadId, 'version' => count($allowedFiles)]);
    }

    public function test_upload_rejects_executable_markup_svg_oversize_and_invalid_metadata_before_storage(): void
    {
        Storage::fake('local');
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(3);
        $this->seedLead($world, $leadId, 'Synthetic Rejected Upload Lead');
        $invalidFiles = [
            ['payload.exe', 'application/x-msdownload', 1],
            ['page.html', 'text/html', 1],
            ['drawing.svg', 'image/svg+xml', 1],
            ['too-large.pdf', 'application/pdf', 51201],
        ];

        foreach ($invalidFiles as [$name, $mime, $kilobytes]) {
            $this->postFile($world['manager_id'], $leadId, [
                'file' => UploadedFile::fake()->create($name, $kilobytes, $mime),
                'version' => '0',
            ])->assertUnprocessable()->assertJsonValidationErrors('file');
        }

        $validPdf = static fn (): UploadedFile => UploadedFile::fake()->create('metadata.pdf', 1, 'application/pdf');
        $this->postFile($world['manager_id'], $leadId, [
            'file' => $validPdf(), 'version' => '0', 'category' => str_repeat('C', 81),
        ])->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->postFile($world['manager_id'], $leadId, [
            'file' => $validPdf(), 'version' => '0', 'description' => str_repeat('D', 501),
        ])->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->postFile($world['manager_id'], $leadId, ['file' => $validPdf()])
            ->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->postFile($world['manager_id'], $leadId, [
            'file' => $validPdf(), 'version' => 'not-an-integer',
        ])->assertUnprocessable()->assertJsonValidationErrors('version');

        self::assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseHas('crm_leads', ['id' => $leadId, 'version' => 0]);
        self::assertSame(0, DB::table('crm_files')->where('lead_id', $leadId)->count());
    }

    public function test_read_only_assignee_can_list_and_download_own_lead_files_but_cannot_upload_or_cross_scope(): void
    {
        Storage::fake('local');
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(4);
        $this->seedLead($world, $leadId, 'Synthetic Private File Lead');
        $readerId = $this->seedUser($world, 'Synthetic Read Only File User', ['crm.read']);
        DB::table('crm_leads')->where('id', $leadId)->update(['assignee_id' => $readerId]);

        $ownFile = $this->seedFile($world, $leadId, 401, 'private-report.pdf', 'Contracts');
        Storage::disk('local')->put($ownFile['path'], '%PDF-1.4 synthetic private file');
        $this->html($readerId, '/crm/leads/'.$leadId.'/files?locale=en')->assertOk();

        $this->postFile($readerId, $leadId, [
            'file' => UploadedFile::fake()->create('forbidden.pdf', 1, 'application/pdf'),
            'version' => '0',
        ])->assertForbidden();

        $unassignedWriterId = $this->seedUser($world, 'Synthetic Unassigned File Writer', ['crm.read', 'crm.write']);
        $this->postFile($unassignedWriterId, $leadId, [
            'file' => UploadedFile::fake()->create('unassigned.pdf', 1, 'application/pdf'),
            'version' => '0',
        ])->assertNotFound();

        $download = $this->html(
            $readerId,
            '/crm/leads/'.$leadId.'/files/'.$ownFile['id'].'/download',
        )->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $disposition = (string) $download->headers->get('Content-Disposition');
        self::assertStringContainsString('attachment', $disposition);
        self::assertStringContainsString('private-report.pdf', $disposition);

        $branchScope = $this->seedScope($world['company_id'], 'File Branch Scope');
        $branchLeadId = $this->fixtureId(5);
        $this->seedLead($world, $branchLeadId, 'Hidden Branch File Lead', $branchScope['branch_id'], $branchScope['department_id']);
        $branchFile = $this->seedFile($world, $branchLeadId, 501, 'branch-file.pdf', 'Contracts');

        $department = $this->seedDepartment($world['company_id'], $world['branch_id'], 'File Department Scope');
        $departmentLeadId = $this->fixtureId(6);
        $this->seedLead($world, $departmentLeadId, 'Hidden Department File Lead', $world['branch_id'], $department);
        $departmentFile = $this->seedFile($world, $departmentLeadId, 601, 'department-file.pdf', 'Contracts');

        $foreignWorld = $this->seedWorld();
        $foreignLeadId = $this->fixtureId(7);
        $this->seedLead($foreignWorld, $foreignLeadId, 'Foreign Company File Lead');
        $foreignFile = $this->seedFile($foreignWorld, $foreignLeadId, 701, 'foreign-company-file.pdf', 'Contracts');

        foreach ([
            [$branchLeadId, $branchFile['id']],
            [$departmentLeadId, $departmentFile['id']],
            [$foreignLeadId, $foreignFile['id']],
        ] as [$hiddenLeadId, $hiddenFileId]) {
            $this->html($readerId, '/crm/leads/'.$hiddenLeadId.'/files/'.$hiddenFileId.'/download')->assertNotFound();
        }
        $this->html($readerId, '/crm/leads/'.$leadId.'/files/'.$foreignFile['id'].'/download')->assertNotFound();
    }

    public function test_upload_rejects_stale_and_archived_leads_without_storing_a_file(): void
    {
        Storage::fake('local');
        $world = $this->seedWorld();
        $leadId = $this->fixtureId(8);
        $this->seedLead($world, $leadId, 'Synthetic Versioned File Lead');
        $pdf = static fn (): UploadedFile => UploadedFile::fake()->create('versioned.pdf', 1, 'application/pdf');

        $this->postFile($world['manager_id'], $leadId, ['file' => $pdf(), 'version' => '9'])
            ->assertStatus(409);
        DB::table('crm_leads')->where('id', $leadId)->update(['archived' => true]);
        $this->postFile($world['manager_id'], $leadId, ['file' => $pdf(), 'version' => '0'])
            ->assertStatus(409);

        self::assertSame([], Storage::disk('local')->allFiles());
        self::assertSame(0, DB::table('crm_files')->where('lead_id', $leadId)->count());
        $this->assertDatabaseHas('crm_leads', ['id' => $leadId, 'version' => 0, 'archived' => true]);
    }

    /** @return array{company_id: string, branch_id: string, department_id: string, manager_id: string} */
    private function seedWorld(): array
    {
        $companyId = (string) Str::ulid();
        DB::table('access_companies')->insert([
            'id' => $companyId,
            'name' => 'Synthetic CRM Files Company',
            'version' => 0,
        ]);
        $scope = $this->seedScope($companyId, 'Synthetic CRM Files Branch');
        $world = [
            'company_id' => $companyId,
            'branch_id' => $scope['branch_id'],
            'department_id' => $scope['department_id'],
        ];

        return $world + ['manager_id' => $this->seedUser($world, 'Synthetic CRM Files Manager', ['crm.read', 'crm.manage', 'crm.write'])];
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id?: string} $world */
    private function seedLead(array $world, string $id, string $name, ?string $branchId = null, ?string $departmentId = null): void
    {
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
            'stage_id' => null,
            'assignee_id' => null,
            'customer_id' => null,
            'archived' => false,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array{company_id: string, branch_id: string, department_id: string, manager_id?: string} $world
     * @param  list<string>  $permissions
     */
    private function seedUser(array $world, string $name, array $permissions): string
    {
        $userId = (string) Str::ulid();
        $roleId = (string) Str::ulid();
        DB::table('access_roles')->insert([
            'id' => $roleId,
            'company_id' => $world['company_id'],
            'name' => 'Synthetic CRM Files '.$name.' Role',
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
            'email' => 'crm-files-'.strtolower(substr($userId, -8)).'@example.test',
            'password' => Hash::make('unused-crm-files-password'),
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

    private function seedDepartment(string $companyId, string $branchId, string $name): string
    {
        $id = (string) Str::ulid();
        DB::table('access_departments')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => $name,
            'code' => 'crm-'.strtolower(substr($id, -6)),
            'active' => true,
            'version' => 0,
        ]);

        return $id;
    }

    /** @return array{id: string, path: string} */
    private function seedFile(array $world, string $leadId, int $number, string $name, string $category): array
    {
        $id = $this->fixtureId($number);
        $path = 'crm/'.$world['company_id'].'/'.$leadId.'/'.$id;
        DB::table('crm_files')->insert([
            'id' => $id,
            'company_id' => $world['company_id'],
            'lead_id' => $leadId,
            'uploader_id' => $world['manager_id'],
            'name' => $name,
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => 123,
            'description' => 'Synthetic file metadata',
            'category' => $category,
            'created_at' => '2026-10-18 12:00:00',
        ]);

        return ['id' => $id, 'path' => $path];
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

    private function postFile(string $userId, string $leadId, array $data): TestResponse
    {
        return $this->withSession($this->sessionFor($userId))
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/crm/leads/'.$leadId.'/files', $data + ['_token' => csrf_token()]);
    }
}

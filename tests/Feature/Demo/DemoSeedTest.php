<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Demo\DemoSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    private string $originalStoragePath;

    private string $isolatedStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStoragePath = $this->app->storagePath();
        $this->isolatedStoragePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'demo-seed-'.Str::uuid();
        $this->app->useStoragePath($this->isolatedStoragePath);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        try {
            $this->app->useStoragePath($this->originalStoragePath);
            if (is_dir($this->isolatedStoragePath)) {
                (new Filesystem)->deleteDirectory($this->isolatedStoragePath);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function newSeeder(): DemoSeeder
    {
        return new DemoSeeder((int) config('demo.seed'));
    }

    public function test_seed_creates_a_coherent_relational_dataset(): void
    {
        $result = $this->newSeeder()->seed('small');
        $counts = $result['counts'];

        self::assertSame(1, $counts['access_companies']);
        self::assertSame(2, $counts['access_branches']);
        self::assertSame(4, $counts['access_departments']);
        self::assertSame(12, $counts['access_users']);
        self::assertSame(45, $counts['crm_leads']);
        self::assertSame(16, $counts['employee_profiles']);
        $followUpTaskCount = DB::table('crm_activities')->where('company_id', config('demo.company_id'))->whereNotNull('task_id')->count();
        self::assertSame(34, $counts['tasks_cycle'] - $followUpTaskCount);
        self::assertGreaterThan(0, $counts['crm_activities']);
        self::assertGreaterThan(0, $counts['crm_customers']);
        self::assertGreaterThan(0, $counts['tasks_cycle_evidence']);
        self::assertGreaterThan(0, $counts['employee_audit']);

        $this->assertEveryLeadIsConsistent();
        $this->assertEveryTaskIsConsistent();
        $this->assertEveryEmployeeIsConsistent();
    }

    public function test_seed_is_repeatable_without_duplication(): void
    {
        $first = $this->newSeeder()->seed('small');
        $second = $this->newSeeder()->seed('small');

        self::assertSame($first['counts'], $second['counts']);
        self::assertSame(45, DB::table('crm_leads')->where('company_id', config('demo.company_id'))->count());
        self::assertSame(1, DB::table('access_companies')->where('id', config('demo.company_id'))->count());
    }

    public function test_medium_and_large_presets_rebuild_the_expected_consistent_dataset(): void
    {
        $presets = [
            'medium' => ['branches' => 3, 'departments' => 6, 'users' => 30, 'leads' => 180, 'employees' => 45, 'tasks' => 110],
            'large' => ['branches' => 4, 'departments' => 12, 'users' => 72, 'leads' => 650, 'employees' => 110, 'tasks' => 380],
        ];

        foreach ($presets as $size => $expected) {
            $result = $this->newSeeder()->seed($size);
            $counts = $result['counts'];

            self::assertSame($size, $result['size']);
            self::assertSame($expected['branches'], $counts['access_branches']);
            self::assertSame($expected['departments'], $counts['access_departments']);
            self::assertSame($expected['users'], $counts['access_users']);
            self::assertSame($expected['leads'], $counts['crm_leads']);
            self::assertSame($expected['employees'], $counts['employee_profiles']);

            $followUps = DB::table('crm_activities')
                ->where('company_id', config('demo.company_id'))
                ->whereNotNull('next_follow_up')
                ->count();
            $linkedFollowUps = DB::table('crm_activities')
                ->where('company_id', config('demo.company_id'))
                ->whereNotNull('task_id')
                ->count();
            self::assertGreaterThan(0, $followUps);
            self::assertSame($followUps, $linkedFollowUps);
            self::assertSame($expected['tasks'], $counts['tasks_cycle'] - $linkedFollowUps);

            $evidenceCount = DB::table('tasks_cycle_evidence as evidence')
                ->join('tasks_cycle as task', 'task.id', '=', 'evidence.task_id')
                ->where('task.entity_id', config('demo.company_id'))
                ->count();
            self::assertGreaterThan(0, $evidenceCount);
            self::assertSame($counts['tasks_cycle_evidence'], $evidenceCount);

            $this->assertEveryLeadIsConsistent();
            $this->assertEveryTaskIsConsistent();
            $this->assertEveryEmployeeIsConsistent();
        }
    }

    public function test_demo_seed_and_clean_commands_round_trip(): void
    {
        $this->artisan('demo:seed', ['--size' => 'small'])->assertExitCode(0);
        $credentialsPath = storage_path('app/private/demo-credentials.json');
        self::assertFileExists($credentialsPath);
        self::assertSame(45, DB::table('crm_leads')->where('company_id', config('demo.company_id'))->count());

        $unrelatedPath = storage_path('app/private/unrelated-sentinel.txt');
        file_put_contents($unrelatedPath, 'unrelated sentinel');

        $this->artisan('demo:clean')->assertExitCode(0);
        self::assertSame(0, DB::table('crm_leads')->where('company_id', config('demo.company_id'))->count());
        self::assertSame(0, DB::table('access_companies')->where('id', config('demo.company_id'))->count());
        self::assertFileDoesNotExist($credentialsPath);
        self::assertSame('unrelated sentinel', file_get_contents($unrelatedPath));
    }

    public function test_seed_command_fails_with_a_credentials_diagnostic_when_credentials_path_is_a_directory(): void
    {
        $credentialsPath = storage_path('app/private/demo-credentials.json');
        self::assertTrue(mkdir($credentialsPath, 0777, true));

        $this->artisan('demo:seed', ['--size' => 'small'])
            ->expectsOutputToContain('credentials')
            ->assertExitCode(1);

        self::assertSame(0, DB::table('access_companies')->where('id', config('demo.company_id'))->count());
        self::assertSame(0, DB::table('crm_leads')->where('company_id', config('demo.company_id'))->count());
    }

    public function test_demo_credentials_log_in_and_reach_the_core_pages(): void
    {
        $result = $this->newSeeder()->seed('small');
        $admin = $result['credentials']['admin'];

        $this->postJson('/login', ['email' => $admin['email'], 'password' => $admin['password']])->assertSuccessful();

        foreach (['/dashboard', '/access/users', '/access/organization', '/employees', '/tasks', '/crm/leads'] as $url) {
            $response = $this->get($url);
            self::assertSame(200, $response->status(), "GET {$url} returned {$response->status()}");
        }

        // CRM HTML pages only render for explicit browser navigation requests.
        foreach (['/crm', '/crm/leads/create'] as $url) {
            $response = $this->get($url, ['Accept' => 'text/html']);
            self::assertSame(200, $response->status(), "GET {$url} returned {$response->status()}");
        }
    }

    public function test_seeded_task_evidence_is_downloadable_text_and_purge_preserves_unrelated_files(): void
    {
        $disk = Storage::disk('local');
        $sentinelPath = 'task-evidence/unrelated/sentinel.txt';
        $disk->put($sentinelPath, 'unrelated sentinel');

        $this->newSeeder()->seed('small');
        $evidenceRows = DB::table('tasks_cycle_evidence as evidence')
            ->join('tasks_cycle as task', 'task.id', '=', 'evidence.task_id')
            ->where('task.entity_id', config('demo.company_id'))
            ->get(['evidence.*', 'task.entity_id', 'task.department_id', 'task.owner_id']);
        self::assertNotEmpty($evidenceRows);

        $ownedPaths = [];
        foreach ($evidenceRows as $index => $evidence) {
            $ownedPaths[] = (string) $evidence->path;
            self::assertSame('text/plain', $evidence->mime);
            self::assertSame('txt', strtolower(pathinfo($evidence->path, PATHINFO_EXTENSION)));
            $disk->assertExists($evidence->path);
            $contents = $disk->get($evidence->path);
            self::assertSame(strlen($contents), (int) $evidence->size);

            if ($index === 0) {
                $download = $this->withSession([
                    'actor_id' => (string) $evidence->owner_id,
                    'entity_id' => (string) $evidence->entity_id,
                    'department_id' => (string) $evidence->department_id,
                    'permissions' => ['tasks.read'],
                ])->get('/tasks/'.$evidence->task_id.'/evidence/'.$evidence->id);

                $download->assertOk()->assertHeader('Content-Disposition');
                self::assertSame($contents, $download->streamedContent());
            }
        }

        $this->newSeeder()->purge();

        foreach ($ownedPaths as $path) {
            $disk->assertMissing($path);
        }
        $disk->assertExists($sentinelPath);
        self::assertSame('unrelated sentinel', $disk->get($sentinelPath));
    }

    public function test_purge_removes_only_demo_rows(): void
    {
        $foreignCompany = (string) Str::ulid();
        $foreignBranch = (string) Str::ulid();
        $foreignDepartment = (string) Str::ulid();
        DB::table('access_companies')->insert(['id' => $foreignCompany, 'name' => 'Foreign Company', 'version' => 0]);
        DB::table('access_branches')->insert(['id' => $foreignBranch, 'company_id' => $foreignCompany, 'name' => 'Foreign Branch', 'code' => 'fb', 'active' => true, 'version' => 0]);
        DB::table('access_departments')->insert(['id' => $foreignDepartment, 'company_id' => $foreignCompany, 'branch_id' => $foreignBranch, 'name' => 'Foreign Department', 'code' => 'fd', 'active' => true, 'version' => 0]);
        DB::table('access_users')->insert([
            'id' => (string) Str::ulid(),
            'company_id' => $foreignCompany,
            'branch_id' => $foreignBranch,
            'department_id' => $foreignDepartment,
            'name' => 'Foreign User',
            'email' => 'foreign@example.test',
            'password' => 'x',
            'active' => true,
            'is_admin' => false,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $this->newSeeder()->seed('small');
        $removed = $this->newSeeder()->purge();

        self::assertGreaterThan(0, $removed['removed']);
        self::assertSame(0, DB::table('crm_leads')->where('company_id', config('demo.company_id'))->count());
        self::assertSame(0, DB::table('access_users')->where('company_id', config('demo.company_id'))->count());
        self::assertSame(0, DB::table('access_companies')->where('id', config('demo.company_id'))->count());

        self::assertSame(1, DB::table('access_companies')->where('id', $foreignCompany)->count());
        self::assertSame(1, DB::table('access_users')->where('company_id', $foreignCompany)->count());
        self::assertSame(1, DB::table('access_departments')->where('company_id', $foreignCompany)->count());
    }

    public function test_purge_refuses_when_the_demo_company_id_belongs_to_another_company(): void
    {
        DB::table('access_companies')->insert(['id' => config('demo.company_id'), 'name' => 'Not The Demo Company', 'version' => 0]);

        $this->expectException(RuntimeException::class);

        $this->newSeeder()->purge();
    }

    public function test_seeding_is_rejected_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('production');

        $this->newSeeder()->assertEnvironmentAllowed(true);
    }

    public function test_seeding_outside_local_testing_requires_force(): void
    {
        $this->app['env'] = 'staging';

        $this->newSeeder()->assertEnvironmentAllowed(true);

        $this->expectException(RuntimeException::class);
        $this->newSeeder()->assertEnvironmentAllowed(false);
    }

    private function assertEveryLeadIsConsistent(): void
    {
        $companyId = config('demo.company_id');
        $branches = DB::table('access_branches')->where('company_id', $companyId)->pluck('id')->all();
        $departments = DB::table('access_departments')->where('company_id', $companyId)->pluck('id')->all();
        $users = DB::table('access_users')->where('company_id', $companyId)->pluck('id')->all();
        $stages = DB::table('crm_taxonomy')->where('company_id', $companyId)->where('kind', 'stage')->pluck('id')->all();
        $customers = DB::table('crm_customers')->where('company_id', $companyId)->pluck('id')->all();

        foreach (DB::table('crm_leads')->where('company_id', $companyId)->get() as $lead) {
            self::assertContains($lead->branch_id, $branches);
            self::assertContains($lead->department_id, $departments);
            self::assertContains($lead->stage_id, $stages);
            self::assertContains($lead->priority, ['low', 'normal', 'high']);
            if ($lead->assignee_id !== null) {
                self::assertContains($lead->assignee_id, $users);
            }
            if ($lead->customer_id !== null) {
                self::assertContains($lead->customer_id, $customers);

                $customer = DB::table('crm_customers')->where('id', $lead->customer_id)->first();
                self::assertNotNull($customer);
                foreach (['company_id', 'branch_id', 'department_id', 'name', 'organization', 'duplicate_key'] as $field) {
                    self::assertSame($lead->{$field}, $customer->{$field}, "Converted lead {$lead->id} differs from its customer on {$field}.");
                }
            }
            $contacts = json_decode(Crypt::decryptString($lead->contacts), true);
            self::assertIsArray($contacts);
            self::assertStringEndsWith('@example.test', $contacts['email']);

            $lifecycleAudits = DB::table('crm_audit')->where('lead_id', $lead->id)
                ->whereIn('action', ['lead.created', 'lead.assigned', 'lead.converted', 'lead.archived', 'lead.activity.recorded', 'lead.transfer.requested', 'lead.transfer.accepted', 'lead.transfer.rejected'])
                ->get()
                ->map(static function (object $audit): object {
                    $audit->metadata = json_decode($audit->metadata, true, flags: JSON_THROW_ON_ERROR);

                    return $audit;
                })
                ->sortBy(static fn (object $audit): int => (int) $audit->metadata['version'])
                ->values();
            $expectedVersion = 0;
            $latestLifecycleAt = null;
            foreach ($lifecycleAudits as $audit) {
                self::assertSame($expectedVersion, (int) $audit->metadata['version'], "Lead {$lead->id} has a lifecycle audit version gap.");
                $expectedVersion++;
                $latestLifecycleAt = (string) $audit->created_at;
            }
            self::assertSame($expectedVersion - 1, (int) $lead->version, "Lead {$lead->id} version differs from its lifecycle audit.");
            self::assertSame($latestLifecycleAt, (string) $lead->updated_at, "Lead {$lead->id} timestamp differs from its latest lifecycle audit.");
        }

        foreach (DB::table('crm_transfers')->where('company_id', $companyId)->get() as $transfer) {
            $requestAudit = DB::table('crm_audit')->where('lead_id', $transfer->lead_id)->where('action', 'lead.transfer.requested')->first();
            self::assertNotNull($requestAudit);
            $requestMetadata = json_decode($requestAudit->metadata, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame((int) $transfer->requested_version, (int) $requestMetadata['version']);

            $lead = DB::table('crm_leads')->where('id', $transfer->lead_id)->first();
            self::assertNotNull($lead);
            if ($transfer->state === 'pending') {
                self::assertSame((int) $transfer->requested_version, (int) $lead->version);

                continue;
            }

            $responseAudit = DB::table('crm_audit')->where('lead_id', $transfer->lead_id)->where('action', 'lead.transfer.'.$transfer->state)->first();
            self::assertNotNull($responseAudit);
            $responseMetadata = json_decode($responseAudit->metadata, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame((int) $transfer->requested_version + 1, (int) $responseMetadata['version']);
            self::assertSame((int) $responseMetadata['version'], (int) $lead->version);
            self::assertSame($transfer->state === 'accepted' ? $transfer->recipient_id : $transfer->sender_id, $lead->assignee_id);
        }
    }

    private function assertEveryTaskIsConsistent(): void
    {
        $companyId = config('demo.company_id');
        $users = DB::table('access_users')->where('company_id', $companyId)->pluck('id')->all();

        foreach (DB::table('tasks_cycle')->where('entity_id', $companyId)->get() as $task) {
            self::assertContains($task->status, ['pending', 'in_progress', 'completed', 'cancelled']);
            self::assertContains($task->owner_id, $users);
            if ($task->assignee_id !== null) {
                self::assertContains($task->assignee_id, $users);
            }
            if ($task->status === 'completed') {
                self::assertSame(1, DB::table('tasks_cycle_evidence')->where('task_id', $task->id)->count());
                self::assertSame(1, DB::table('tasks_cycle_audit')->where('task_id', $task->id)->where('action', 'complete')->count());
            }

            $audits = DB::table('tasks_cycle_audit')->where('task_id', $task->id)->get()->keyBy('action');
            $create = json_decode($audits['create']->payload, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(0, (int) $create['version']);
            $expectedVersion = 0;
            if ($task->status !== 'pending') {
                self::assertArrayHasKey('start', $audits);
                $start = json_decode($audits['start']->payload, true, flags: JSON_THROW_ON_ERROR);
                self::assertSame(1, (int) $start['version']);
                $expectedVersion = 1;
            }
            if (isset($audits['evidence'])) {
                $evidenceAudit = json_decode($audits['evidence']->payload, true, flags: JSON_THROW_ON_ERROR);
                self::assertSame(2, (int) $evidenceAudit['version']);
                $expectedVersion = 2;
            }
            if ($task->status === 'completed') {
                $complete = json_decode($audits['complete']->payload, true, flags: JSON_THROW_ON_ERROR);
                self::assertSame(3, (int) $complete['version']);
                $expectedVersion = 3;
            }
            self::assertSame($expectedVersion, (int) $task->version, "Task {$task->id} version differs from its lifecycle.");
            self::assertSame(
                (string) DB::table('tasks_cycle_audit')->where('task_id', $task->id)->max('created_at'),
                (string) $task->updated_at,
                "Task {$task->id} timestamp differs from its latest audit.",
            );
        }

        foreach (DB::table('crm_activities')->where('company_id', $companyId)->whereNotNull('next_follow_up')->get() as $activity) {
            self::assertNotNull($activity->task_id, "Activity {$activity->id} has a follow-up but no task.");
            $task = DB::table('tasks_cycle')->where('id', $activity->task_id)->first();
            self::assertNotNull($task, "Activity {$activity->id} references a missing task.");
            self::assertSame($activity->actor_id, $task->owner_id);
            self::assertSame($activity->actor_id, $task->assignee_id);
        }
    }

    private function assertEveryEmployeeIsConsistent(): void
    {
        $companyId = config('demo.company_id');

        foreach (DB::table('employee_profiles')->where('company_id', $companyId)->get() as $employee) {
            self::assertContains($employee->status, ['active', 'on_leave', 'suspended', 'terminated']);
            if ($employee->manager_id !== null) {
                $manager = DB::table('employee_profiles')->where('id', $employee->manager_id)->first();
                self::assertNotNull($manager);
                self::assertSame($employee->branch_id, $manager->branch_id);
                self::assertSame($employee->department_id, $manager->department_id);
            }

            $createdAudit = DB::table('employee_audit')->where('employee_id', $employee->id)->where('action', 'employee.created')->first();
            self::assertNotNull($createdAudit);
            $createdMetadata = json_decode($createdAudit->metadata, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('active', $createdMetadata['status']);
            self::assertSame(0, (int) $createdMetadata['version']);
            self::assertSame((string) $employee->created_at, (string) $createdAudit->created_at);

            if ($employee->status === 'active') {
                self::assertSame(0, (int) $employee->version);
                self::assertSame((string) $createdAudit->created_at, (string) $employee->updated_at);

                continue;
            }

            $updatedAudit = DB::table('employee_audit')->where('employee_id', $employee->id)->where('action', 'employee.updated')->first();
            self::assertNotNull($updatedAudit);
            $updatedMetadata = json_decode($updatedAudit->metadata, true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('active', $updatedMetadata['before_status']);
            self::assertSame($employee->status, $updatedMetadata['after_status']);
            self::assertSame(0, (int) $updatedMetadata['before_version']);
            self::assertSame(1, (int) $updatedMetadata['after_version']);
            self::assertSame(1, (int) $employee->version);
            self::assertSame((string) $updatedAudit->created_at, (string) $employee->updated_at);
        }
    }
}

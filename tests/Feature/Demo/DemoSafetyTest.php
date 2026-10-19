<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Demo\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class DemoSafetyTest extends TestCase
{
    use RefreshDatabase;

    private string $originalStoragePath;

    private string $isolatedStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStoragePath = $this->app->storagePath();
        $this->isolatedStoragePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'demo-safety-'.Str::uuid();
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

    public function test_staging_requires_explicit_force_to_seed(): void
    {
        $this->app['env'] = 'staging';

        try {
            (new DemoSeeder((int) config('demo.seed')))->seed('small');
            self::fail('Seeding in staging without force should be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('--force', $exception->getMessage());
        }

        self::assertSame(0, DB::table('access_companies')->where('id', config('demo.company_id'))->count());

        $result = (new DemoSeeder((int) config('demo.seed')))->seed('small', true);

        self::assertSame('small', $result['size']);
        self::assertSame(45, $result['counts']['crm_leads']);
    }

    public function test_production_rejects_forced_seeding_through_the_public_api(): void
    {
        $this->app['env'] = 'production';

        try {
            (new DemoSeeder((int) config('demo.seed')))->seed('small', true);
            self::fail('Production seeding must remain forbidden even with force.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('production', strtolower($exception->getMessage()));
        }

        self::assertSame(0, DB::table('access_companies')->where('id', config('demo.company_id'))->count());
    }

    public function test_purge_is_rejected_in_production_even_after_forced_staging_seed(): void
    {
        $this->app['env'] = 'staging';
        $seeder = new DemoSeeder((int) config('demo.seed'));
        $result = $seeder->seed('small', true);
        $filesBefore = Storage::disk('local')->allFiles();

        $this->app['env'] = 'production';

        try {
            $seeder->purge(true);
            self::fail('Production purge must be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('production', strtolower($exception->getMessage()));
        }

        foreach ($result['counts'] as $table => $expected) {
            self::assertSame($expected, DB::table($table)->count(), "{$table} changed after rejected production purge.");
        }
        self::assertSame($filesBefore, Storage::disk('local')->allFiles());
    }

    public function test_late_purge_failure_rolls_back_every_demo_table(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            self::markTestSkipped('The late-delete trigger is specific to SQLite.');
        }

        $disk = Storage::disk('local');
        $disk->put('task-evidence/unrelated/sentinel.txt', 'unrelated sentinel');
        $result = (new DemoSeeder((int) config('demo.seed')))->seed('small');
        $filesBefore = $disk->allFiles();
        sort($filesBefore, SORT_STRING);
        $contentsBefore = [];
        foreach ($filesBefore as $path) {
            $contentsBefore[$path] = $disk->get($path);
        }

        $companyId = str_replace("'", "''", (string) config('demo.company_id'));
        DB::unprepared("CREATE TRIGGER fail_demo_company_purge BEFORE DELETE ON access_companies WHEN OLD.id = '{$companyId}' BEGIN SELECT RAISE(ABORT, 'fixture late purge failure'); END");

        try {
            (new DemoSeeder((int) config('demo.seed')))->purge();
            self::fail('The late-delete trigger should abort purge.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('fixture late purge failure', $exception->getMessage());
        }

        foreach ($result['counts'] as $table => $expected) {
            self::assertSame($expected, DB::table($table)->count(), "{$table} was not restored after purge rollback.");
        }

        $filesAfter = $disk->allFiles();
        sort($filesAfter, SORT_STRING);
        self::assertSame($filesBefore, $filesAfter, 'Evidence file paths were not restored after purge rollback.');
        foreach ($contentsBefore as $path => $contents) {
            self::assertSame($contents, $disk->get($path), "Evidence bytes were not restored for {$path}.");
        }
    }
}

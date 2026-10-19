<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Demo\DemoSeeder;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DemoTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private string $originalStoragePath;

    private string $isolatedStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStoragePath = $this->app->storagePath();
        $this->isolatedStoragePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'demo-timezone-'.Str::uuid();
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

    public function test_demo_timestamps_follow_the_database_session_timezone(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('MariaDB session timezone behavior is required.');
        }

        $originalTimeZone = (string) DB::selectOne('SELECT @@session.time_zone AS session_time_zone')->session_time_zone;

        try {
            DB::statement("SET SESSION time_zone = '+02:00'");
            $databaseNow = (string) DB::selectOne('SELECT CURRENT_TIMESTAMP AS database_clock')->database_clock;
            $expectedWindowStart = (new DateTimeImmutable($databaseNow, new DateTimeZone('+02:00')))->modify('-15 months');

            (new DemoSeeder((int) config('demo.seed')))->seed('small');

            $createdAt = DB::table('access_audit')
                ->where('company_id', config('demo.company_id'))
                ->min('created_at');
            self::assertNotNull($createdAt);

            $actualWindowStart = new DateTimeImmutable((string) $createdAt, new DateTimeZone('+02:00'));
            self::assertLessThanOrEqual(
                60,
                abs($actualWindowStart->getTimestamp() - $expectedWindowStart->getTimestamp()),
                'Demo audit timestamps should use the database session timezone.',
            );
        } finally {
            DB::statement('SET SESSION time_zone = ?', [$originalTimeZone]);
        }
    }
}

<?php

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require $argv[2].'/vendor/autoload.php';
$app = require $argv[2].'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class AdrValidationCrashLeaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $started, public string $release) {}

    public function handle(): void
    {
        if ($this->attempts() === 1) {
            file_put_contents($this->started, 'owned-job-started');
            $deadline = microtime(true) + 40;
            while (! is_file($this->release) && microtime(true) < $deadline) {
                usleep(20000);
            } if (! is_file($this->release)) {
                throw new RuntimeException('bounded_job_wait_expired');
            }
        }
        DB::table('adrv_consumer_effects')->insertOrIgnore(['event_id' => 'worker-crash-effect', 'effect' => 'synthetic']);
    }
}
final class AdrValidationFailedReplayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $eventId) {}

    public function handle(): void
    {
        $allowed = DB::table('adrv_retry_controls')->where('event_id', $this->eventId)->value('authorized');
        if (! $allowed) {
            throw new RuntimeException('synthetic bounded failure');
        }
        DB::table('adrv_consumer_effects')->insertOrIgnore(['event_id' => $this->eventId, 'effect' => 'synthetic']);
    }
}

$mode = $argv[1] ?? '';
$root = $argv[2] ?? '';
$runPath = $argv[3] ?? '';
config(['queue.connections.database.retry_after' => 1]);
if ($mode === 'crash-worker') {
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0, '--tries' => 2]);
    exit(0);
}

try {
    if (! Schema::hasTable('failed_jobs')) {
        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }
    if (! Schema::hasTable('adrv_retry_controls')) {
        Schema::create('adrv_retry_controls', function (Blueprint $table): void {
            $table->string('event_id', 40)->primary();
            $table->boolean('authorized')->default(false);
        });
    }
    $started = $runPath.DIRECTORY_SEPARATOR.'job-started.marker';
    $release = $runPath.DIRECTORY_SEPARATOR.'job-release.marker';
    @unlink($started);
    @unlink($release);
    AdrValidationCrashLeaseJob::dispatch($started, $release)->onQueue('default');
    $pipes = [];
    $child = proc_open([PHP_BINARY, __FILE__, 'crash-worker', $root, $runPath], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (! is_resource($child)) {
        throw new RuntimeException('QUEUE_WORKER_START_FAILED');
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $deadline = microtime(true) + 15;
    while (! is_file($started) && microtime(true) < $deadline) {
        $status = proc_get_status($child);
        if (! $status['running']) {
            break;
        } usleep(20000);
    }
    if (! is_file($started)) {
        proc_terminate($child);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($child);
        throw new RuntimeException('QUEUE_JOB_START_TIMEOUT');
    }
    proc_terminate($child);
    $stopAt = microtime(true) + 3;
    while (($status = proc_get_status($child))['running'] && microtime(true) < $stopAt) {
        usleep(20000);
    }
    if ($status['running']) {
        proc_terminate($child, 9);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($child);
    sleep(2);
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0, '--tries' => 2]);
    if (DB::table('adrv_consumer_effects')->where('event_id', 'worker-crash-effect')->count() !== 1 || DB::table('jobs')->where('payload', 'like', '%AdrValidationCrashLeaseJob%')->exists()) {
        throw new RuntimeException('QUEUE_LEASE_REDELIVERY_ASSERTION');
    }
    fwrite(STDOUT, "PASS: owned Laravel worker killed during leased job; expired lease redelivered once and effect committed\n");

    DB::table('adrv_retry_controls')->insert(['event_id' => 'authorized-replay-event', 'authorized' => false]);
    AdrValidationFailedReplayJob::dispatch('authorized-replay-event')->onQueue('default');
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0, '--tries' => 1]);
    $failed = DB::table('failed_jobs')->where('payload', 'like', '%authorized-replay-event%')->first();
    if ($failed === null) {
        throw new RuntimeException('QUEUE_FAILED_JOBS_ASSERTION');
    }
    fwrite(STDOUT, "PASS: bounded failing job moved to database failed_jobs\n");
    DB::table('adrv_retry_controls')->where('event_id', 'authorized-replay-event')->update(['authorized' => true]);
    if (DB::table('adrv_inbox')->where('event_id', 'authorized-replay-event')->exists()) {
        throw new RuntimeException('QUEUE_REPLAY_ALREADY_CONSUMED');
    }
    Artisan::call('queue:retry', ['id' => [$failed->uuid]]);
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0, '--tries' => 1]);
    if (DB::table('adrv_consumer_effects')->where('event_id', 'authorized-replay-event')->count() !== 1 || DB::table('failed_jobs')->where('id', $failed->id)->exists()) {
        throw new RuntimeException('QUEUE_AUTHORIZED_REPLAY_ASSERTION');
    }
    Artisan::call('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0]);
    if (DB::table('jobs')->where('payload', 'like', '%AdrValidation%')->exists()) {
        throw new RuntimeException('QUEUE_DRAIN_ASSERTION');
    }
    fwrite(STDOUT, "PASS: authorized same-event-id failed job replayed and real Laravel worker drained queue\nRESULT: PASS assertions=3\n");
} catch (Throwable $failure) {
    @unlink($release ?? '');
    if ($failure instanceof PDOException && isset($failure->errorInfo[0], $failure->errorInfo[1])) {
        fwrite(STDOUT, 'SQL_ERROR: SQLSTATE='.$failure->errorInfo[0].' code='.(int) $failure->errorInfo[1]."\n");
    }
    $assertion = preg_match('/^QUEUE_[A-Z_]+$/D', $failure->getMessage()) ? $failure->getMessage() : 'QUEUE_UNEXPECTED_EXCEPTION';
    fwrite(STDOUT, 'FAIL: '.$assertion."\nRESULT: FAIL assertions=0\n");
    exit(1);
}

<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$root = dirname(__DIR__, 2);
require $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
$runId = getenv('ARASYA_TASKS_WORKSPACE_RUN_ID') ?: '';
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$assert(preg_match('/^[a-f0-9-]{36}$/iD', $runId) === 1, 'Tasks workspace browser fixture requires a unique run ID.');
$runtimeBase = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'tasks-workspace';
if (! is_dir($runtimeBase) && ! mkdir($runtimeBase, 0700, true) && ! is_dir($runtimeBase)) {
    throw new RuntimeException('Could not create the ignored task-workspace runtime directory.');
}
$runtimeBaseReal = realpath($runtimeBase);
$runDirectory = $runtimeBase.DIRECTORY_SEPARATOR.$runId;
if (! is_dir($runDirectory) && ! mkdir($runDirectory, 0700, true) && ! is_dir($runDirectory)) {
    throw new RuntimeException('Could not create the unique task-workspace run directory.');
}
$runDirectoryReal = realpath($runDirectory);
$assert($runtimeBaseReal !== false && $runDirectoryReal !== false
    && dirname($runDirectoryReal) === $runtimeBaseReal && basename($runDirectoryReal) === $runId,
    'The task-workspace fixture run directory is outside its owned ignored parent.');
$assert(! file_exists($runDirectoryReal.DIRECTORY_SEPARATOR.'.env')
    && ! file_exists($runDirectoryReal.DIRECTORY_SEPARATOR.'.env.tasks-workspace-disabled'),
    'The private task-workspace environment directory must not contain an environment file.');

$expectedAppKey = 'base64:QUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUE=';
$expectedDatabase = $runDirectoryReal.DIRECTORY_SEPARATOR.'tasks.sqlite';
$normalizePath = static fn (string $path): string => strtolower(rtrim(str_replace('\\', '/', $path), '\\/'));
$expectedBaseUrl = 'http://127.0.0.1:8776';
$environment = [
    'APP_ENV' => 'testing',
    'APP_KEY' => $expectedAppKey,
    'APP_DEBUG' => 'false',
    'APP_URL' => $expectedBaseUrl,
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $expectedDatabase,
    'SESSION_DRIVER' => 'file',
    'ARASYA_BROWSER_BASE_URL' => $expectedBaseUrl,
    'ARASYA_TASKS_WORKSPACE_FIXTURE' => $runDirectoryReal.DIRECTORY_SEPARATOR.'fixture.json',
    'ARASYA_TASKS_WORKSPACE_STATE' => $runDirectoryReal.DIRECTORY_SEPARATOR.'authorized-state.json',
    'ARASYA_TASKS_WORKSPACE_READONLY_STATE' => $runDirectoryReal.DIRECTORY_SEPARATOR.'read-only-state.json',
];
foreach ($environment as $name => $expected) {
    $actual = getenv($name);
    $matches = in_array($name, [
        'DB_DATABASE', 'ARASYA_TASKS_WORKSPACE_FIXTURE',
        'ARASYA_TASKS_WORKSPACE_STATE', 'ARASYA_TASKS_WORKSPACE_READONLY_STATE',
    ], true)
        ? is_string($actual) && $normalizePath($actual) === $normalizePath($expected)
        : $actual === $expected;
    $assert($matches, "Task-workspace browser environment value {$name} is not the explicit isolated value.");
}
$assert(parse_url($expectedBaseUrl, PHP_URL_SCHEME) === 'http'
    && parse_url($expectedBaseUrl, PHP_URL_HOST) === '127.0.0.1'
    && parse_url($expectedBaseUrl, PHP_URL_PORT) === 8776,
    'Task-workspace browser requests may target the private loopback port only.');
if (! is_file($expectedDatabase)) {
    $databaseHandle = fopen($expectedDatabase, 'x+b');
    if ($databaseHandle === false) {
        throw new RuntimeException('Could not create the unique browser SQLite file before migrations.');
    }
    fclose($databaseHandle);
}
$databaseFileReal = realpath($expectedDatabase);
$assert($databaseFileReal !== false && $normalizePath($databaseFileReal) === $normalizePath($expectedDatabase),
    'The browser SQLite file escaped the unique task-workspace run directory.');

$makeDirectory = static function (string $path): void {
    if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
        throw new RuntimeException('Could not create a private task-workspace runtime directory.');
    }
};
$makeDirectory($runDirectoryReal.DIRECTORY_SEPARATOR.'sessions');
$makeDirectory($runDirectoryReal.DIRECTORY_SEPARATOR.'views');
$makeDirectory($runDirectoryReal.DIRECTORY_SEPARATOR.'laravel-storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private');
$makeDirectory($runDirectoryReal.DIRECTORY_SEPARATOR.'laravel-storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'cache');

$bootstrap = static function () use ($root, $runDirectoryReal, $expectedAppKey, $expectedDatabase, $normalizePath, $assert, $makeDirectory) {
    $app = require $root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
    $app->useEnvironmentPath($runDirectoryReal);
    $app->loadEnvironmentFrom('.env.tasks-workspace-disabled');
    $app->useStoragePath($runDirectoryReal.DIRECTORY_SEPARATOR.'laravel-storage');
    $kernel = $app->make(ConsoleKernel::class);
    $kernel->bootstrap();

    $assert($app->environment('testing')
        && config('app.key') === $expectedAppKey
        && config('database.default') === 'sqlite'
        && $normalizePath((string) config('database.connections.sqlite.database')) === $normalizePath($expectedDatabase)
        && config('session.driver') === 'file',
        'Tasks browser fixture bootstrap escaped the unique testing environment.');

    $makeDirectory($runDirectoryReal.DIRECTORY_SEPARATOR.'views');
    $makeDirectory($runDirectoryReal.DIRECTORY_SEPARATOR.'sessions');
    config([
        'session.files' => $runDirectoryReal.DIRECTORY_SEPARATOR.'sessions',
        'view.compiled' => $runDirectoryReal.DIRECTORY_SEPARATOR.'views',
        'filesystems.disks.local.root' => $runDirectoryReal.DIRECTORY_SEPARATOR.'laravel-storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private',
    ]);

    return [$app, $kernel];
};

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($requestPath === '/__tasks-workspace-health') {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ok';
        exit(0);
    }

    $publicRoot = realpath($root.DIRECTORY_SEPARATOR.'public');
    $staticPath = $publicRoot === false ? false : realpath($publicRoot.DIRECTORY_SEPARATOR.ltrim(str_replace('/', DIRECTORY_SEPARATOR, $requestPath), DIRECTORY_SEPARATOR));
    if ($publicRoot !== false && $staticPath !== false && is_file($staticPath)
        && str_starts_with($staticPath, $publicRoot.DIRECTORY_SEPARATOR)) {
        return false;
    }

    [$app] = $bootstrap();
    $kernel = $app->make(HttpKernel::class);
    $request = Request::capture();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
    exit(0);
}

$mode = $argv[1] ?? throw new RuntimeException('Pass seed, verify, or cleanup.');
if (! in_array($mode, ['seed', 'verify', 'cleanup'], true)) {
    throw new RuntimeException('Unknown task-workspace fixture mode.');
}

[$app] = $bootstrap();
$fixturePath = $environment['ARASYA_TASKS_WORKSPACE_FIXTURE'];
$authorizedStatePath = $environment['ARASYA_TASKS_WORKSPACE_STATE'];
$readOnlyStatePath = $environment['ARASYA_TASKS_WORKSPACE_READONLY_STATE'];

$assert(is_file($root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php'), 'The Laravel application bootstrap is missing.');
$assert(config('database.default') === 'sqlite' && config('session.driver') === 'file', 'Only isolated SQLite and file sessions are supported.');

$writeSessionState = static function (array $permissions, string $destination) use ($app, $runDirectoryReal, $expectedBaseUrl, $assert): string {
    $assert(realpath(dirname($destination)) === $runDirectoryReal && ! file_exists($destination), 'Browser session state must be new and remain inside the unique run directory.');
    $session = $app->make('session')->driver();
    $sessionId = (string) Str::random(40);
    $session->flush();
    $session->setId($sessionId);
    $session->start();
    $session->put([
        'actor_id' => 'tasks-workspace-actor',
        'entity_id' => 'tasks-workspace-entity',
        'department_id' => 'tasks-workspace-department',
        'permissions' => $permissions,
    ]);
    $session->save();

    $cookieName = (string) config('session.cookie');
    $encrypter = $app->make('encrypter');
    $cookie = $encrypter->encrypt(
        CookieValuePrefix::create($cookieName, $encrypter->getKey()).$sessionId,
        EncryptCookies::serialized($cookieName),
    );
    $state = ['cookies' => [[
        'name' => $cookieName,
        'value' => $cookie,
        'domain' => parse_url($expectedBaseUrl, PHP_URL_HOST),
        'path' => '/',
        'expires' => -1,
        'httpOnly' => true,
        'secure' => false,
        'sameSite' => 'Lax',
    ]], 'origins' => []];
    if (file_put_contents($destination, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('Could not write private synthetic browser state.');
    }

    return $sessionId;
};

$loadFixture = static function () use ($fixturePath, $runId, $assert): array {
    $assert(realpath(dirname($fixturePath)) !== false && is_file($fixturePath), 'The owned task-workspace fixture file is missing.');
    $fixture = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
    $assert(is_array($fixture) && ($fixture['run_id'] ?? null) === $runId
        && isset($fixture['task_ids'], $fixture['evidence_ids'], $fixture['audit_ids'], $fixture['titles'], $fixture['session_ids']),
        'The task-workspace fixture ownership record is incomplete.');

    return $fixture;
};

if ($mode === 'seed') {
    $assert(! file_exists($fixturePath) && ! file_exists($authorizedStatePath) && ! file_exists($readOnlyStatePath), 'An owned task-workspace fixture already exists.');
    $migrationStatus = Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
    $assert($migrationStatus === 0, 'Could not migrate the unique browser SQLite database.');
    foreach (['tasks_cycle', 'tasks_cycle_audit', 'tasks_cycle_evidence'] as $table) {
        $assert(Schema::hasTable($table), "The isolated browser database is missing {$table}.");
    }

    $now = now('UTC');
    $tag = substr(str_replace('-', '', $runId), 0, 8);
    $titles = [
        'overdue' => "Synthetic workspace overdue {$tag}",
        'today' => "Synthetic workspace due today {$tag}",
        'upcoming' => "Synthetic workspace upcoming {$tag}",
        'completed' => "Synthetic workspace completed {$tag}",
        'foreign' => "Synthetic workspace outside scope {$tag}",
    ];
    $taskIds = [];
    $expectedTasks = [];
    $seedTask = static function (string $title, string $status, string $dueAt, array $scope = []) use (&$taskIds, &$expectedTasks, $now): string {
        $id = (string) Str::ulid();
        $entityId = $scope['entity_id'] ?? 'tasks-workspace-entity';
        $departmentId = $scope['department_id'] ?? 'tasks-workspace-department';
        $ownerId = $scope['owner_id'] ?? 'tasks-workspace-actor';
        $assigneeId = $scope['assignee_id'] ?? 'tasks-workspace-actor';
        DB::table('tasks_cycle')->insert([
            'id' => $id,
            'entity_id' => $entityId,
            'department_id' => $departmentId,
            'owner_id' => $ownerId,
            'assignee_id' => $assigneeId,
            'title' => $title,
            'priority' => 'normal',
            'due_at' => $dueAt,
            'status' => $status,
            'version' => 0,
            'create_key_hash' => hash('sha256', $id),
            'create_payload_hash' => hash('sha256', $title),
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);
        $taskIds[] = $id;
        $expectedTasks[] = ['id' => $id, 'title' => $title, 'status' => $status, 'version' => 0];

        return $id;
    };

    $seedTask($titles['overdue'], 'pending', $now->copy()->subDay()->startOfDay()->addHours(10)->toDateTimeString());
    $todayTaskId = $seedTask($titles['today'], 'pending', $now->copy()->startOfDay()->addHours(15)->toDateTimeString());
    $seedTask($titles['upcoming'], 'in_progress', $now->copy()->addDays(2)->startOfDay()->addHours(10)->toDateTimeString());
    $completedTaskId = $seedTask($titles['completed'], 'completed', $now->copy()->startOfDay()->addHours(11)->toDateTimeString());
    for ($index = 1; $index <= 12; $index++) {
        $pageTitle = sprintf('Synthetic workspace page %02d %s', $index, $tag);
        $titles['page_'.$index] = $pageTitle;
        $seedTask($pageTitle, 'pending', $now->copy()->addDays(2)->startOfDay()->addHours(11)->toDateTimeString());
    }
    $foreignTaskId = $seedTask($titles['foreign'], 'pending', $now->copy()->startOfDay()->addHours(13)->toDateTimeString(), [
        'owner_id' => 'tasks-workspace-other-actor',
        'assignee_id' => 'tasks-workspace-other-actor',
    ]);
    $otherEntityTitle = "Synthetic workspace other entity {$tag}";
    $otherEntityTaskId = $seedTask($otherEntityTitle, 'pending', $now->copy()->startOfDay()->addHours(13)->toDateTimeString(), [
        'entity_id' => 'tasks-workspace-other-entity',
    ]);
    $otherDepartmentTitle = "Synthetic workspace other department {$tag}";
    $otherDepartmentTaskId = $seedTask($otherDepartmentTitle, 'pending', $now->copy()->startOfDay()->addHours(13)->toDateTimeString(), [
        'department_id' => 'tasks-workspace-other-department',
    ]);
    $titles['other_entity'] = $otherEntityTitle;
    $titles['other_department'] = $otherDepartmentTitle;

    $evidenceId = (string) Str::ulid();
    $evidencePath = 'task-evidence/'.$todayTaskId.'/'.$evidenceId.'.txt';
    DB::table('tasks_cycle_evidence')->insert([
        'id' => $evidenceId,
        'task_id' => $todayTaskId,
        'path' => $evidencePath,
        'name' => "Synthetic workspace evidence {$tag}.txt",
        'mime' => 'text/plain',
        'size' => 24,
        'created_at' => $now->toDateTimeString(),
    ]);
    $assert(Storage::disk('local')->put($evidencePath, "Synthetic evidence {$tag}."), 'Could not create private synthetic task evidence.');
    $foreignEvidenceId = (string) Str::ulid();
    $foreignEvidencePath = 'task-evidence/'.$foreignTaskId.'/'.$foreignEvidenceId.'.txt';
    DB::table('tasks_cycle_evidence')->insert([
        'id' => $foreignEvidenceId,
        'task_id' => $foreignTaskId,
        'path' => $foreignEvidencePath,
        'name' => "Synthetic foreign evidence {$tag}.txt",
        'mime' => 'text/plain',
        'size' => 24,
        'created_at' => $now->toDateTimeString(),
    ]);
    $assert(Storage::disk('local')->put($foreignEvidencePath, "Synthetic foreign evidence {$tag}."), 'Could not create private synthetic out-of-scope evidence.');

    $auditId = (string) Str::ulid();
    DB::table('tasks_cycle_audit')->insert([
        'id' => $auditId,
        'task_id' => $completedTaskId,
        'actor_id' => 'tasks-workspace-actor',
        'action' => 'complete',
        'request_key' => 'workspace-complete-'.$tag,
        'payload_hash' => hash('sha256', 'workspace-complete-'.$tag),
        'receipt_id' => (string) Str::ulid(),
        'payload' => '{}',
        'created_at' => $now->toDateTimeString(),
    ]);

    $authorizedSessionId = $writeSessionState(['tasks.read', 'tasks.write'], $authorizedStatePath);
    $readOnlySessionId = $writeSessionState(['tasks.read'], $readOnlyStatePath);
    $fixture = [
        'run_id' => $runId,
        'tag' => $tag,
        'task_ids' => $taskIds,
        'expected_tasks' => $expectedTasks,
        'evidence_ids' => [$evidenceId, $foreignEvidenceId],
        'evidence_paths' => [$evidencePath, $foreignEvidencePath],
        'audit_ids' => [$auditId],
        'session_ids' => [$authorizedSessionId, $readOnlySessionId],
        'authorized_task_count' => 16,
        'titles' => $titles,
        'evidence_name' => "Synthetic workspace evidence {$tag}.txt",
        'foreign_evidence_name' => "Synthetic foreign evidence {$tag}.txt",
        'foreign_task_ids' => [$foreignTaskId, $otherEntityTaskId, $otherDepartmentTaskId],
    ];
    if (file_put_contents($fixturePath, json_encode($fixture, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX) === false) {
        throw new RuntimeException('Could not write the task-workspace fixture ownership record.');
    }

    echo "Synthetic Tasks workspace fixture seeded for {$runId}.\n";
    exit(0);
}

$fixture = $loadFixture();
$taskIds = $fixture['task_ids'];
$evidenceIds = $fixture['evidence_ids'];
$auditIds = $fixture['audit_ids'];
$assert(count($taskIds) === count(array_unique($taskIds)) && count($taskIds) === 19, 'The task-workspace fixture task ownership list is not exact.');
$assert(DB::table('tasks_cycle')->whereIn('id', $taskIds)->count() === count($taskIds), 'The fixture task rows changed or escaped their owned database.');
$assert(DB::table('tasks_cycle_evidence')->whereIn('id', $evidenceIds)->count() === 2, 'The fixture evidence rows changed.');
$assert(DB::table('tasks_cycle_audit')->whereIn('id', $auditIds)->count() === 1, 'The fixture audit row changed.');
foreach ($fixture['expected_tasks'] as $expected) {
    $task = DB::table('tasks_cycle')->where('id', $expected['id'])->first();
    $assert($task !== null && $task->title === $expected['title'] && $task->status === $expected['status']
        && (int) $task->version === $expected['version'], 'A browser fixture task changed or was not preserved.');
}
foreach ($fixture['evidence_paths'] as $path) {
    $assert(Storage::disk('local')->exists($path), 'A private task-workspace evidence file is missing.');
}

if ($mode === 'verify') {
    echo json_encode([
        'run_id' => $runId,
        'tasks' => count($taskIds),
        'authorized_tasks' => $fixture['authorized_task_count'],
        'evidence_rows' => count($evidenceIds),
        'audit_rows' => count($auditIds),
        'browser_reads_persisted_no_mutations' => true,
    ], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}

$removePrivateDirectory = static function (string $name) use ($runDirectoryReal, $assert): void {
    $target = $runDirectoryReal.DIRECTORY_SEPARATOR.$name;
    $resolved = realpath($target);
    if ($resolved === false) {
        return;
    }
    $assert(dirname($resolved) === $runDirectoryReal && basename($resolved) === $name
        && in_array($name, ['sessions', 'views', 'laravel-storage'], true), 'Refusing to remove a directory outside the task-workspace run folder.');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        $entryPath = $entry->getPathname();
        $assert(str_starts_with($entryPath, $resolved.DIRECTORY_SEPARATOR), 'Refusing to remove a nested path outside the private run directory.');
        if ($entry->isDir() && ! $entry->isLink()) {
            rmdir($entryPath);
        } else {
            unlink($entryPath);
        }
    }
    rmdir($resolved);
};
$removePrivateFile = static function (string $name) use ($runDirectoryReal, $assert): void {
    $path = $runDirectoryReal.DIRECTORY_SEPARATOR.$name;
    if (! is_file($path)) {
        return;
    }
    $assert(realpath(dirname($path)) === $runDirectoryReal && realpath($path) === $path
        && in_array($name, [
            'tasks.sqlite', 'fixture.json', 'authorized-state.json', 'read-only-state.json',
            'config.php', 'routes.php', 'events.php', 'services.php', 'packages.php',
        ], true), 'Refusing to remove an unowned task-workspace runtime file.');
    unlink($path);
};

DB::transaction(static function () use ($taskIds, $evidenceIds, $auditIds): void {
    DB::table('tasks_cycle_audit')->whereIn('id', $auditIds)->delete();
    DB::table('tasks_cycle_evidence')->whereIn('id', $evidenceIds)->delete();
    DB::table('tasks_cycle')->whereIn('id', $taskIds)->delete();
});
foreach ($fixture['evidence_paths'] as $path) {
    Storage::disk('local')->delete($path);
}
$assert(DB::table('tasks_cycle')->whereIn('id', $taskIds)->doesntExist()
    && DB::table('tasks_cycle_evidence')->whereIn('id', $evidenceIds)->doesntExist()
    && DB::table('tasks_cycle_audit')->whereIn('id', $auditIds)->doesntExist(),
    'The explicit synthetic task-workspace fixture rows remain after cleanup.');
DB::disconnect('sqlite');

$removePrivateDirectory('sessions');
$removePrivateDirectory('views');
$removePrivateDirectory('laravel-storage');
foreach (['tasks.sqlite', 'fixture.json', 'authorized-state.json', 'read-only-state.json', 'config.php', 'routes.php', 'events.php', 'services.php', 'packages.php'] as $file) {
    $removePrivateFile($file);
}
echo "Removed only the verified synthetic task-workspace fixture {$runId}; screenshot and report artifacts are preserved.\n";

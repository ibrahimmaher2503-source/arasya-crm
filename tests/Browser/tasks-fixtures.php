<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$assert($app->environment('testing') && config('session.driver') === 'file', 'Tasks browser fixtures require APP_ENV=testing and SESSION_DRIVER=file.');
$assert(config('database.default') === 'mysql'
    && config('database.connections.mysql.database') === 'arasya_windows_browser'
    && config('database.connections.mysql.host') === '127.0.0.1'
    && (string) config('database.connections.mysql.port') === '13316',
    'Tasks browser fixtures require the isolated loopback MariaDB database.');
$assert(rtrim((string) config('filesystems.disks.local.root'), '\\/') === rtrim(storage_path('app/private'), '\\/'), 'Tasks browser fixtures require the private local disk root.');

$mode = $argv[1] ?? throw new RuntimeException('Pass authorized, denied, other-entity, verify, or cleanup.');

if (in_array($mode, ['authorized', 'denied', 'other-entity'], true)) {
    $destination = $argv[2] ?? throw new RuntimeException('Pass a private browser storage-state path.');
    $allowedDirectory = realpath(dirname(__DIR__, 2).'/.local/windows-qa');
    $destinationDirectory = realpath(dirname($destination));
    $assert($allowedDirectory !== false && $destinationDirectory === $allowedDirectory, 'Synthetic sessions may be written only under .local/windows-qa.');
    $baseUrl = getenv('ARASYA_BROWSER_BASE_URL') ?: 'http://127.0.0.1:8765';
    $assert(in_array(parse_url($baseUrl, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true), 'Synthetic sessions may target loopback only.');

    $session = $app->make('session')->driver();
    $session->start();
    $session->put([
        'actor_id' => 'synthetic-tasks-actor',
        'entity_id' => $mode === 'other-entity' ? 'synthetic-tasks-other-entity' : 'synthetic-tasks-entity',
        'department_id' => 'synthetic-tasks-branch',
        'permissions' => $mode === 'denied' ? ['tasks.read'] : ['tasks.read', 'tasks.write', 'tasks.assist'],
    ]);
    $session->save();

    $name = config('session.cookie');
    $encrypter = $app->make('encrypter');
    $cookie = $encrypter->encrypt(
        CookieValuePrefix::create($name, $encrypter->getKey()).$session->getId(),
        EncryptCookies::serialized($name),
    );
    $state = ['cookies' => [[
        'name' => $name,
        'value' => $cookie,
        'domain' => parse_url($baseUrl, PHP_URL_HOST),
        'path' => '/',
        'expires' => -1,
        'httpOnly' => true,
        'secure' => false,
        'sameSite' => 'Lax',
    ]], 'origins' => []];
    $assert(file_put_contents($destination, json_encode($state, JSON_THROW_ON_ERROR)) !== false, 'Could not write synthetic browser storage state.');
    echo "Synthetic {$mode} Tasks browser state created.\n";
    exit(0);
}

if (! in_array($mode, ['verify', 'cleanup'], true)) {
    throw new RuntimeException('Unknown Tasks browser fixture mode.');
}

$arguments = array_slice($argv, 2);
$taskIds = [];
$absentTitles = [];
$assistantUnchangedIds = [];
for ($index = 0; $index < count($arguments); $index++) {
    $argument = $arguments[$index];
    if (str_starts_with($argument, '--absent-title=')) {
        $absentTitles[] = substr($argument, strlen('--absent-title='));
    } elseif ($argument === '--assistant-unchanged') {
        $assistantUnchangedIds[] = $arguments[++$index] ?? throw new RuntimeException('Pass a task ID after --assistant-unchanged.');
    } else {
        $taskIds[] = $argument;
    }
}
$assert($taskIds !== [], 'Pass the explicit synthetic task IDs to verify or clean up.');
$assert(count($taskIds) === count(array_unique($taskIds)) && ! in_array('', $taskIds, true), 'Pass each explicit synthetic task ID once.');

$tasks = DB::table('tasks_cycle')->whereIn('id', $taskIds)->get(['id', 'entity_id', 'department_id', 'owner_id', 'status', 'version']);
$assert($tasks->count() === count($taskIds), 'Every supplied ID must identify an existing synthetic task.');
foreach ($tasks as $task) {
    $assert($task->owner_id === 'synthetic-tasks-actor'
        && in_array($task->entity_id, ['synthetic-tasks-entity', 'synthetic-tasks-other-entity'], true)
        && $task->department_id === 'synthetic-tasks-branch',
        "Task {$task->id} is outside the owned synthetic scope.");
}

$evidence = DB::table('tasks_cycle_evidence')->whereIn('task_id', $taskIds)->get(['task_id', 'path']);
$safePath = static function (string $path, string $taskId): string {
    $normalized = str_replace('\\', '/', $path);
    if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:/', $normalized)
        || str_contains($normalized, "\0") || preg_match('~(?:^|/)\.\.(?:/|$)~', $normalized)
        || ! str_starts_with($normalized, 'task-evidence/'.$taskId.'/')) {
        throw new RuntimeException("Task {$taskId} has an unsafe private evidence path.");
    }

    return $normalized;
};

if ($mode === 'verify') {
    $audits = DB::table('tasks_cycle_audit')->whereIn('task_id', $taskIds)->get(['task_id', 'request_key']);
    foreach ($taskIds as $taskId) {
        $keys = $audits->where('task_id', $taskId)->pluck('request_key');
        $assert($keys->isNotEmpty() && $keys->every(static fn ($key): bool => is_string($key) && trim($key) !== '')
            && $keys->count() === $keys->unique()->count(), "Task {$taskId} has persisted, idempotent audit receipts.");
    }
    foreach ($evidence as $item) {
        $path = $safePath((string) $item->path, (string) $item->task_id);
        $assert(Storage::disk('local')->exists($path), "Task {$item->task_id} private evidence is present on the local disk.");
    }
    foreach ($absentTitles as $title) {
        $assert($title !== '' && ! DB::table('tasks_cycle')->where('title', $title)->exists(), 'A denied synthetic task was not persisted.');
    }
    foreach ($assistantUnchangedIds as $taskId) {
        $task = $tasks->firstWhere('id', $taskId);
        $assert($task !== null && $task->status === 'pending' && (int) $task->version === 0, "Assistant task {$taskId} remains unchanged and pending.");
        $assert($audits->where('task_id', $taskId)->count() === 1, "Assistant preview and blocked confirmation added no task audit mutation for {$taskId}.");
    }
    echo 'PASS: '.count($taskIds).' explicit synthetic task IDs, audit receipts, and owned evidence verified. '.count($absentTitles).' denied titles absent. '.count($assistantUnchangedIds).' assistant tasks unchanged.'.PHP_EOL;
    exit(0);
}

$disk = Storage::disk('local');
foreach ($evidence as $item) {
    $path = $safePath((string) $item->path, (string) $item->task_id);
    if ($disk->exists($path)) {
        $assert($disk->delete($path), "Could not remove owned private evidence for task {$item->task_id}.");
    }
}
DB::transaction(static function () use ($taskIds): void {
    DB::table('tasks_cycle_audit')->whereIn('task_id', $taskIds)->delete();
    DB::table('tasks_cycle_evidence')->whereIn('task_id', $taskIds)->delete();
    DB::table('tasks_cycle')->whereIn('id', $taskIds)->delete();
});
$assert(! DB::table('tasks_cycle')->whereIn('id', $taskIds)->exists(), 'Explicit synthetic tasks remain after cleanup.');
$assert(! DB::table('tasks_cycle_audit')->whereIn('task_id', $taskIds)->exists()
    && ! DB::table('tasks_cycle_evidence')->whereIn('task_id', $taskIds)->exists(), 'Audit or evidence rows remain after cleanup.');
echo 'PASS: only '.count($taskIds).' explicit synthetic task IDs and their owned evidence were removed.'.PHP_EOL;

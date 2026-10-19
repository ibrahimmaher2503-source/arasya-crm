<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$storage = getenv('LARAVEL_STORAGE_PATH');
if (! is_string($storage) || $storage === '' || ! is_dir($storage)) {
    throw new RuntimeException('An owned Laravel storage directory is required.');
}
$app->useStoragePath($storage);
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.mariadb.database');
$host = (string) config('database.connections.mariadb.host');
if (! app()->environment('testing') || config('database.default') !== 'mariadb'
    || ! str_starts_with($database, 'help_') || ! in_array($host, ['127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('Role-permission verification requires an owned help_-prefixed testing database on loopback.');
}

$evidencePath = getenv('ARASYA_CLOSURE_EVIDENCE');
$resultPath = is_string($evidencePath) ? $evidencePath.DIRECTORY_SEPARATOR.'workflow-results.json' : '';
if ($evidencePath === false || ! is_dir($evidencePath) || ! is_file($resultPath)) {
    throw new RuntimeException('Owned role-permission browser workflow evidence is missing.');
}
$results = json_decode((string) file_get_contents($resultPath), true, 512, JSON_THROW_ON_ERROR);
if (! is_array($results) || count($results) !== 3) {
    throw new RuntimeException('Expected exactly three role-permission viewport results.');
}

$checks = 0;
foreach ($results as $result) {
    $task = DB::table('tasks_cycle')->where('id', $result['taskId'])->first();
    if ($task === null) {
        throw new RuntimeException('Role assignment task was not persisted.');
    }
    foreach ([
        $task->owner_id === $result['owner'],
        $task->assignee_id === $result['assignee'],
        $task->status === 'in_progress',
        (int) $task->version === 3,
        DB::table('tasks_cycle_audit')->where('task_id', $task->id)->where('action', 'create')->count() === 1,
        DB::table('tasks_cycle_audit')->where('task_id', $task->id)->where('action', 'assign')->count() === 2,
        DB::table('tasks_cycle_audit')->where('task_id', $task->id)->where('action', 'start')->where('actor_id', $result['assignee'])->count() === 1,
        DB::table('access_user_roles')->where('user_id', $result['owner'])->where('role_id', $result['roleId'])->count() === 0,
    ] as $ok) {
        if (! $ok) {
            throw new RuntimeException('Role assignment persistence/audit mismatch.');
        }
        $checks++;
    }
}

$auditCount = DB::table('crm_audit')->where('action', 'lead.contacts.revealed')->count();
if ($auditCount !== count($results)) {
    throw new RuntimeException('Contact disclosure audit mismatch.');
}
$checks++;
file_put_contents($evidencePath.DIRECTORY_SEPARATOR.'database-audit.json', json_encode(['checks' => $checks, 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'PASS '.$checks." isolated SQL persistence/audit checks\n";

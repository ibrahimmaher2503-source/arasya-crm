<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

if (PHP_SAPI === 'cli-server' && (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') === '/__employee-work-health') {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ok';
    exit(0);
}

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$runId = getenv('ARASYA_EMPLOYEE_WORK_RUN_ID') ?: '';
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$assert(preg_match('/\\A[a-f0-9-]{36}\\z/iD', $runId) === 1, 'Employee work browser fixtures require a unique run ID.');

$runtimeBase = $root.'/storage/framework/testing/employee-work';
if (! is_dir($runtimeBase) && ! mkdir($runtimeBase, 0700, true) && ! is_dir($runtimeBase)) {
    throw new RuntimeException('Could not create the private employee-work runtime directory.');
}
$runtimeBaseReal = realpath($runtimeBase);
$runtimeDirectory = $runtimeBase.DIRECTORY_SEPARATOR.$runId;
if (! is_dir($runtimeDirectory) && ! mkdir($runtimeDirectory, 0700, true) && ! is_dir($runtimeDirectory)) {
    throw new RuntimeException('Could not create the unique employee-work run directory.');
}
$runtimeDirectory = realpath($runtimeDirectory);
$assert($runtimeBaseReal !== false && $runtimeDirectory !== false
    && dirname($runtimeDirectory) === $runtimeBaseReal && basename($runtimeDirectory) === $runId,
    'Employee-work fixture path escaped its owned runtime directory.');
$assert(! file_exists($runtimeDirectory.DIRECTORY_SEPARATOR.'.env')
    && ! file_exists($runtimeDirectory.DIRECTORY_SEPARATOR.'.env.employee-work-disabled'),
    'The private employee-work runtime must not contain an environment file.');

$ownerPath = $runtimeDirectory.DIRECTORY_SEPARATOR.'.owner';
$ownerMarker = 'A1-EMP-007 employee-work run '.$runId;
if (! file_exists($ownerPath)) {
    $owner = fopen($ownerPath, 'xb');
    $assert($owner !== false, 'Could not create the employee-work run ownership marker.');
    try {
        $assert(fwrite($owner, $ownerMarker) === strlen($ownerMarker), 'Could not write the employee-work run ownership marker.');
    } finally {
        fclose($owner);
    }
}
$assert(realpath(dirname($ownerPath)) === $runtimeDirectory && file_get_contents($ownerPath) === $ownerMarker, 'Employee-work run ownership marker does not match.');

$expectedAppKey = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';
$expectedBaseUrl = 'http://127.0.0.1:8787';
$cookieName = 'employee_work_'.substr(str_replace('-', '', $runId), 0, 16);
$expectedDatabase = $runtimeDirectory.DIRECTORY_SEPARATOR.'employee-work.sqlite';
$fixturePath = $runtimeDirectory.DIRECTORY_SEPARATOR.'employee-work-fixture.json';
$authPath = $runtimeDirectory.DIRECTORY_SEPARATOR.'employee-work-auth.json';
$relativeCacheDirectory = 'storage/framework/testing/employee-work/'.$runId;
$normalizePath = static fn (string $path): string => strtolower(rtrim(str_replace('\\', '/', $path), '/'));
$environment = [
    'APP_ENV' => 'testing',
    'APP_KEY' => $expectedAppKey,
    'APP_DEBUG' => 'false',
    'APP_URL' => $expectedBaseUrl,
    'APP_CONFIG_CACHE' => $relativeCacheDirectory.'/config.php',
    'APP_ROUTES_CACHE' => $relativeCacheDirectory.'/routes.php',
    'APP_EVENTS_CACHE' => $relativeCacheDirectory.'/events.php',
    'APP_SERVICES_CACHE' => $relativeCacheDirectory.'/services.php',
    'APP_PACKAGES_CACHE' => $relativeCacheDirectory.'/packages.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $expectedDatabase,
    'DB_FOREIGN_KEYS' => 'true',
    'SESSION_DRIVER' => 'file',
    'SESSION_COOKIE' => $cookieName,
    'SESSION_FILES' => $runtimeDirectory.DIRECTORY_SEPARATOR.'sessions',
    'VIEW_COMPILED_PATH' => $runtimeDirectory.DIRECTORY_SEPARATOR.'views',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'LOG_CHANNEL' => 'stderr',
    'ARASYA_BROWSER_BASE_URL' => $expectedBaseUrl,
    'ARASYA_EMPLOYEE_WORK_RUN_ID' => $runId,
    'ARASYA_EMPLOYEE_WORK_RUN_DIRECTORY' => $runtimeDirectory,
    'ARASYA_EMPLOYEE_WORK_FIXTURE' => $fixturePath,
    'ARASYA_EMPLOYEE_WORK_AUTH' => $authPath,
];
$pathEnvironment = ['APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'DB_DATABASE', 'SESSION_FILES', 'VIEW_COMPILED_PATH', 'ARASYA_EMPLOYEE_WORK_RUN_DIRECTORY', 'ARASYA_EMPLOYEE_WORK_FIXTURE', 'ARASYA_EMPLOYEE_WORK_AUTH'];
foreach ($environment as $name => $expected) {
    $actual = getenv($name);
    $matches = in_array($name, $pathEnvironment, true)
        ? is_string($actual) && $normalizePath($actual) === $normalizePath($expected)
        : $actual === $expected;
    $assert($matches, "Employee-work browser environment value {$name} is not the explicit isolated value.");
}

$makeDirectory = static function (string $path): void {
    if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
        throw new RuntimeException('Could not create an employee-work runtime directory.');
    }
};
foreach (['sessions', 'views', 'laravel-storage/app/private', 'laravel-storage/framework/cache'] as $directory) {
    $makeDirectory($runtimeDirectory.DIRECTORY_SEPARATOR.$directory);
}
if (! is_file($expectedDatabase)) {
    $databaseHandle = fopen($expectedDatabase, 'x+b');
    $assert($databaseHandle !== false, 'Could not create the unique employee-work SQLite file.');
    fclose($databaseHandle);
}
$databaseReal = realpath($expectedDatabase);
$assert($databaseReal !== false && $normalizePath($databaseReal) === $normalizePath($expectedDatabase), 'The employee-work SQLite file escaped its unique run directory.');

$bootstrap = static function () use ($root, $runtimeDirectory, $expectedAppKey, $expectedDatabase, $normalizePath, $assert) {
    $app = require $root.'/bootstrap/app.php';
    $app->useEnvironmentPath($runtimeDirectory);
    $app->loadEnvironmentFrom('.env.employee-work-disabled');
    $app->useStoragePath($runtimeDirectory.DIRECTORY_SEPARATOR.'laravel-storage');
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $assert($app->environment('testing') && config('app.key') === $expectedAppKey
        && config('database.default') === 'sqlite'
        && $normalizePath((string) config('database.connections.sqlite.database')) === $normalizePath($expectedDatabase)
        && config('session.driver') === 'file',
        'Employee-work fixture bootstrap escaped its synthetic isolated environment.');
    config([
        'session.files' => $runtimeDirectory.DIRECTORY_SEPARATOR.'sessions',
        'view.compiled' => $runtimeDirectory.DIRECTORY_SEPARATOR.'views',
        'filesystems.disks.local.root' => $runtimeDirectory.DIRECTORY_SEPARATOR.'laravel-storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private',
    ]);

    return [$app, $kernel];
};

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $publicRoot = realpath($root.'/public');
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

[$app] = $bootstrap();
$mode = $argv[1] ?? throw new RuntimeException('Pass seed, verify, or cleanup.');

$saveFixture = static function (array $fixture) use ($fixturePath, $runtimeDirectory, $assert): void {
    $assert(realpath(dirname($fixturePath)) === $runtimeDirectory && basename($fixturePath) === 'employee-work-fixture.json', 'Fixture metadata must remain inside the unique run directory.');
    if (file_put_contents($fixturePath, json_encode($fixture, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('Could not save the private fixture ownership record.');
    }
};

$deleteSession = static function (?string $sessionId) use ($assert): void {
    if ($sessionId === null) {
        return;
    }

    $assert(preg_match('/\A[A-Za-z0-9,-]{20,128}\z/', $sessionId) === 1, 'Refusing to remove an invalid session identifier.');
    $sessionDirectory = realpath((string) config('session.files'));
    $assert($sessionDirectory !== false, 'The session directory cannot be verified.');
    $sessionPath = $sessionDirectory.DIRECTORY_SEPARATOR.$sessionId;
    if (is_file($sessionPath)) {
        $assert(realpath(dirname($sessionPath)) === $sessionDirectory && basename($sessionPath) === $sessionId, 'Refusing to remove a session outside the expected session directory.');
        unlink($sessionPath);
    }
};

$cleanupOwned = static function () use ($fixturePath, $authPath, $runtimeDirectory, $runId, $assert, $deleteSession): void {
    $assert(is_file($fixturePath), 'The employee work ownership record is missing; refusing cleanup.');
    $fixture = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
    $assert(is_array($fixture) && ($fixture['marker'] ?? null) === 'A1-EMP-007 employee-work synthetic fixture'
    && ($fixture['run_id'] ?? null) === $runId, 'Fixture ownership marker does not match this run.');

    $company = $fixture['company_id'] ?? null;
    $branch = $fixture['branch_id'] ?? null;
    $department = $fixture['department_id'] ?? null;
    $userIds = [$fixture['actor_id'] ?? null, $fixture['peer_id'] ?? null];
    $roleIds = $fixture['role_ids'] ?? [];
    $profileIds = [$fixture['manager_profile_id'] ?? null, $fixture['actor_profile_id'] ?? null, $fixture['peer_profile_id'] ?? null];
    $taskIds = $fixture['task_ids'] ?? [];
    $evidenceIds = $fixture['evidence_ids'] ?? [];
    $noteIds = $fixture['note_ids'] ?? [];
    $assert(is_string($company) && Str::isUlid($company)
        && is_string($branch) && Str::isUlid($branch)
        && is_string($department) && Str::isUlid($department)
        && count(array_filter($userIds, static fn ($id): bool => is_string($id) && Str::isUlid($id))) === 2
        && count(array_filter($roleIds, static fn ($id): bool => is_string($id) && Str::isUlid($id))) === 2
        && count(array_filter($profileIds, static fn ($id): bool => is_string($id) && Str::isUlid($id))) === 3
        && count(array_filter($taskIds, static fn ($id): bool => is_string($id) && Str::isUlid($id))) === 3
        && count(array_filter($evidenceIds, static fn ($id): bool => is_string($id) && Str::isUlid($id))) === 2
        && count(array_filter($noteIds, static fn ($id): bool => is_string($id) && Str::isUlid($id))) === 2,
        'Fixture ownership identifiers are incomplete or invalid.');

    $companyName = 'A1-EMP-007 Synthetic Work Company';
    $companyExists = DB::table('access_companies')->where('id', $company)->exists();
    if ($companyExists) {
        $assert(DB::table('access_companies')->where('id', $company)->value('name') === $companyName, 'Fixture company ownership cannot be established.');
        $assert(! DB::table('tasks_cycle_audit')->whereIn('task_id', $taskIds)->exists()
            && ! DB::table('employee_audit')->where('company_id', $company)->whereIn('employee_id', $profileIds)->exists(),
            'Unexpected audit rows exist for the synthetic records; preserving them and refusing broad cleanup.');

        DB::transaction(function () use ($company, $branch, $department, $userIds, $roleIds, $profileIds, $taskIds, $evidenceIds, $noteIds): void {
            DB::table('employee_evidence')->where('company_id', $company)->whereIn('id', $noteIds)->delete();
            DB::table('tasks_cycle_evidence')->whereIn('id', $evidenceIds)->whereIn('task_id', $taskIds)->delete();
            DB::table('employee_profiles')->where('company_id', $company)->whereIn('id', $profileIds)->update(['manager_id' => null]);
            DB::table('employee_profiles')->where('company_id', $company)->whereIn('id', $profileIds)->delete();
            DB::table('tasks_cycle')->where('entity_id', $company)->whereIn('id', $taskIds)->delete();
            DB::table('access_user_roles')->whereIn('user_id', $userIds)->whereIn('role_id', $roleIds)->delete();
            DB::table('access_role_permissions')->whereIn('role_id', $roleIds)->delete();
            DB::table('access_roles')->where('company_id', $company)->whereIn('id', $roleIds)->delete();
            DB::table('access_users')->where('company_id', $company)->whereIn('id', $userIds)->delete();
            DB::table('access_departments')->where('company_id', $company)->where('id', $department)->delete();
            DB::table('access_branches')->where('company_id', $company)->where('id', $branch)->delete();
            DB::table('access_companies')->where('id', $company)->where('name', 'A1-EMP-007 Synthetic Work Company')->delete();
        });
    } else {
        $assert(! DB::table('access_branches')->where('id', $branch)->exists()
            && ! DB::table('access_departments')->where('id', $department)->exists()
            && ! DB::table('access_users')->whereIn('id', $userIds)->exists()
            && ! DB::table('access_roles')->whereIn('id', $roleIds)->exists()
            && ! DB::table('employee_profiles')->whereIn('id', $profileIds)->exists()
            && ! DB::table('tasks_cycle')->whereIn('id', $taskIds)->exists()
            && ! DB::table('tasks_cycle_evidence')->whereIn('id', $evidenceIds)->exists()
            && ! DB::table('employee_evidence')->whereIn('id', $noteIds)->exists(),
            'Orphaned rows were found without the owned company; preserving the ownership record for manual recovery.');
    }

    $assert(! DB::table('access_companies')->where('id', $company)->exists()
        && ! DB::table('tasks_cycle')->whereIn('id', $taskIds)->exists()
        && ! DB::table('employee_profiles')->whereIn('id', $profileIds)->exists(),
        'Owned synthetic rows remain after cleanup.');

    $deleteSession(is_string($fixture['session_id'] ?? null) ? $fixture['session_id'] : null);
    if (is_file($authPath)) {
        $expectedHash = $fixture['auth_sha256'] ?? null;
        $assert(is_string($expectedHash) && hash_file('sha256', $authPath) === $expectedHash, 'Browser state no longer matches the owned fixture; preserving it.');
        $assert(realpath(dirname($authPath)) === $runtimeDirectory && basename($authPath) === 'employee-work-auth.json', 'Refusing to remove browser state outside the private runtime directory.');
        unlink($authPath);
    }

    $assert(realpath(dirname($fixturePath)) === $runtimeDirectory && basename($fixturePath) === 'employee-work-fixture.json', 'Refusing to remove fixture metadata outside the private runtime directory.');
    unlink($fixturePath);
};

if ($mode === 'seed') {
    $assert(! is_file($fixturePath) && ! is_file($authPath), 'Employee work fixture files already exist; inspect their ownership before proceeding.');
    $migrationStatus = Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
    $assert($migrationStatus === 0, 'Could not migrate the unique employee-work SQLite database. '.Artisan::output());
    foreach (['access_companies', 'employee_profiles', 'employee_evidence', 'tasks_cycle', 'tasks_cycle_evidence'] as $table) {
        $assert(Schema::hasTable($table), "The isolated employee-work database is missing {$table}.");
    }

    $company = (string) Str::ulid();
    $branch = (string) Str::ulid();
    $department = (string) Str::ulid();
    $actor = (string) Str::ulid();
    $peer = (string) Str::ulid();
    $actorRole = (string) Str::ulid();
    $peerRole = (string) Str::ulid();
    $managerProfile = (string) Str::ulid();
    $actorProfile = (string) Str::ulid();
    $peerProfile = (string) Str::ulid();
    $todayTask = (string) Str::ulid();
    $secondTask = (string) Str::ulid();
    $peerTask = (string) Str::ulid();
    $todayEvidence = (string) Str::ulid();
    $peerEvidence = (string) Str::ulid();
    $noteId = (string) Str::ulid();
    $peerNoteId = (string) Str::ulid();
    $now = now();
    $fixture = [
        'marker' => 'A1-EMP-007 employee-work synthetic fixture',
        'run_id' => $runId,
        'company_id' => $company,
        'branch_id' => $branch,
        'department_id' => $department,
        'actor_id' => $actor,
        'peer_id' => $peer,
        'role_ids' => [$actorRole, $peerRole],
        'manager_profile_id' => $managerProfile,
        'actor_profile_id' => $actorProfile,
        'peer_profile_id' => $peerProfile,
        'task_ids' => [$todayTask, $secondTask, $peerTask],
        'evidence_ids' => [$todayEvidence, $peerEvidence],
        'note_ids' => [$noteId, $peerNoteId],
        'session_id' => null,
    ];
    $saveFixture($fixture);

    $sessionId = null;
    $authCreated = false;
    try {
        DB::transaction(function () use (
            $company, $branch, $department, $actor, $peer, $actorRole, $peerRole,
            $managerProfile, $actorProfile, $peerProfile, $todayTask, $secondTask, $peerTask,
            $todayEvidence, $peerEvidence, $noteId, $peerNoteId, $now,
        ): void {
            DB::table('access_companies')->insert(['id' => $company, 'name' => 'A1-EMP-007 Synthetic Work Company', 'version' => 0]);
            DB::table('access_branches')->insert(['id' => $branch, 'company_id' => $company, 'name' => 'Synthetic Work Branch', 'code' => 'work-'.$branch, 'active' => true, 'version' => 0]);
            DB::table('access_departments')->insert(['id' => $department, 'company_id' => $company, 'branch_id' => $branch, 'name' => 'Synthetic Work Department', 'code' => 'work-'.$department, 'active' => true, 'version' => 0]);

            foreach ([[$actor, 'Employee work actor'], [$peer, 'Employee work peer']] as [$userId, $name]) {
                DB::table('access_users')->insert([
                    'id' => $userId, 'company_id' => $company, 'branch_id' => $branch, 'department_id' => $department,
                    'name' => $name, 'email' => 'employee-work-'.$userId.'@example.test',
                    'password' => Hash::make('synthetic-employee-work-browser-password'), 'active' => true, 'is_admin' => false,
                    'session_version' => 0, 'version' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            foreach ([[$actorRole, $actor], [$peerRole, $peer]] as [$roleId, $userId]) {
                DB::table('access_roles')->insert(['id' => $roleId, 'company_id' => $company, 'name' => 'Employee work synthetic role '.$roleId, 'version' => 0]);
                DB::table('access_role_permissions')->insert([
                    ['role_id' => $roleId, 'permission' => 'employees.read'],
                    ['role_id' => $roleId, 'permission' => 'tasks.read'],
                ]);
                DB::table('access_user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);
            }

            DB::table('employee_profiles')->insert([
                ['id' => $managerProfile, 'company_id' => $company, 'branch_id' => $branch, 'department_id' => $department, 'manager_id' => null, 'user_id' => null, 'code' => 'WORK-MANAGER', 'name' => 'Synthetic work supervisor', 'role' => 'Supervisor', 'hire_date' => '2026-01-01', 'status' => 'active', 'version' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['id' => $actorProfile, 'company_id' => $company, 'branch_id' => $branch, 'department_id' => $department, 'manager_id' => $managerProfile, 'user_id' => $actor, 'code' => 'WORK-ACTOR', 'name' => 'Synthetic current employee', 'role' => 'Technician', 'hire_date' => '2026-01-01', 'status' => 'active', 'version' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['id' => $peerProfile, 'company_id' => $company, 'branch_id' => $branch, 'department_id' => $department, 'manager_id' => $managerProfile, 'user_id' => $peer, 'code' => 'WORK-PEER', 'name' => 'Synthetic peer employee', 'role' => 'Technician', 'hire_date' => '2026-01-01', 'status' => 'active', 'version' => 0, 'created_at' => $now, 'updated_at' => $now],
            ]);

            foreach ([
                [$todayTask, $actor, $actor, 'Synthetic task due today', 'pending', $now->copy()->startOfDay()->addHours(12)],
                [$secondTask, $actor, $actor, 'Synthetic task in progress', 'in_progress', $now->copy()->addDay()],
                [$peerTask, $peer, $peer, 'Peer task must stay hidden', 'pending', $now->copy()->startOfDay()->addHours(13)],
            ] as [$taskId, $ownerId, $assigneeId, $title, $status, $dueAt]) {
                DB::table('tasks_cycle')->insert([
                    'id' => $taskId, 'entity_id' => $company, 'department_id' => $department, 'owner_id' => $ownerId, 'assignee_id' => $assigneeId,
                    'title' => $title, 'priority' => 'normal', 'due_at' => $dueAt, 'status' => $status, 'version' => 0,
                    'create_key_hash' => hash('sha256', $taskId), 'create_payload_hash' => hash('sha256', $title), 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            DB::table('tasks_cycle_evidence')->insert([
                ['id' => $todayEvidence, 'task_id' => $todayTask, 'path' => 'employee-work-fixture/'.$todayEvidence.'.txt', 'name' => 'Synthetic work evidence.txt', 'mime' => 'text/plain', 'size' => 24, 'created_at' => $now],
                ['id' => $peerEvidence, 'task_id' => $peerTask, 'path' => 'employee-work-fixture/'.$peerEvidence.'.txt', 'name' => 'Peer evidence must stay hidden.txt', 'mime' => 'text/plain', 'size' => 31, 'created_at' => $now],
            ]);
            DB::table('employee_evidence')->insert([
                ['id' => $noteId, 'company_id' => $company, 'employee_id' => $actorProfile, 'job_id' => $todayTask, 'actor_id' => $actor, 'note' => 'Synthetic recorded work-evidence note', 'created_at' => $now],
                ['id' => $peerNoteId, 'company_id' => $company, 'employee_id' => $actorProfile, 'job_id' => $peerTask, 'actor_id' => $peer, 'note' => 'Peer note must stay hidden', 'created_at' => $now],
            ]);
        });

        config(['session.cookie' => $cookieName, 'session.driver' => 'file']);
        $session = $app->make('session')->driver();
        $session->start();
        $sessionId = $session->getId();
        $fixture['session_id'] = $sessionId;
        $saveFixture($fixture);
        $session->put(['access_user_id' => $actor, 'access_session_version' => 0]);
        $session->save();

        $baseUrl = $expectedBaseUrl;
        $assert(parse_url($baseUrl, PHP_URL_HOST) === '127.0.0.1' && parse_url($baseUrl, PHP_URL_PORT) === 8787, 'Employee work browser state may target the configured loopback port only.');
        $encrypter = $app->make('encrypter');
        $cookie = $encrypter->encrypt(
            CookieValuePrefix::create($cookieName, $encrypter->getKey()).$sessionId,
            EncryptCookies::serialized($cookieName),
        );
        $state = json_encode(['cookies' => [[
            'name' => $cookieName,
            'value' => $cookie,
            'domain' => parse_url($baseUrl, PHP_URL_HOST),
            'path' => '/',
            'expires' => -1,
            'httpOnly' => true,
            'secure' => false,
            'sameSite' => 'Lax',
        ]], 'origins' => []], JSON_THROW_ON_ERROR);
        $authStream = fopen($authPath, 'xb');
        if ($authStream === false) {
            throw new RuntimeException('Could not create the private employee work browser state.');
        }
        $authCreated = true;
        try {
            if (fwrite($authStream, $state) !== strlen($state)) {
                throw new RuntimeException('Could not save the private employee work browser state.');
            }
        } finally {
            fclose($authStream);
        }
        $fixture['auth_sha256'] = hash('sha256', $state);
        $saveFixture($fixture);
        echo "A1-EMP-007 synthetic browser fixture seeded; session state remains private.\n";
    } catch (Throwable $exception) {
        try {
            if ($authCreated && is_file($authPath)) {
                $assert(realpath(dirname($authPath)) === $runtimeDirectory && basename($authPath) === 'employee-work-auth.json', 'Refusing to remove browser state outside the private runtime directory.');
                unlink($authPath);
            }
            $deleteSession($sessionId);
            $cleanupOwned();
        } catch (Throwable $cleanupException) {
            throw new RuntimeException('Employee work seed failed; the ownership record was retained because safe cleanup could not be confirmed.', 0, $cleanupException);
        }
        throw $exception;
    }

    exit(0);
}

$assert(in_array($mode, ['verify', 'cleanup'], true), 'Pass seed, verify, or cleanup.');
$assert(is_file($fixturePath), 'The employee work ownership record is missing.');
$fixture = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
$assert(is_array($fixture) && ($fixture['marker'] ?? null) === 'A1-EMP-007 employee-work synthetic fixture'
    && ($fixture['run_id'] ?? null) === $runId, 'Fixture ownership marker does not match this run.');
$company = $fixture['company_id'] ?? null;
$assert(is_string($company) && Str::isUlid($company), 'Fixture company identifier is invalid.');

if ($mode === 'verify') {
    $assert(DB::table('access_companies')->where('id', $company)->value('name') === 'A1-EMP-007 Synthetic Work Company', 'Fixture company ownership cannot be established.');
    $assert(DB::table('tasks_cycle')->whereIn('id', $fixture['task_ids'])->count() === 3, 'Synthetic work tasks changed unexpectedly.');
    $assert(DB::table('tasks_cycle_evidence')->whereIn('id', $fixture['evidence_ids'])->count() === 2, 'Synthetic task evidence changed unexpectedly.');
    $assert(DB::table('employee_evidence')->whereIn('id', $fixture['note_ids'])->count() === 2, 'Synthetic work notes changed unexpectedly.');
    $assert(is_file($authPath) && hash_file('sha256', $authPath) === ($fixture['auth_sha256'] ?? null), 'Synthetic browser state changed unexpectedly.');
    echo "PASS: three owned synthetic tasks, two evidence rows, two notes, and private browser state remain intact.\n";
    exit(0);
}

$cleanupOwned();
echo "Removed only the owned A1-EMP-007 synthetic fixture and private session state.\n";

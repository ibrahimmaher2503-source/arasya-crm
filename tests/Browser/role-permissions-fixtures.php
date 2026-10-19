<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$storage = getenv('LARAVEL_STORAGE_PATH');
if (! is_string($storage) || $storage === '' || ! is_dir($storage)) {
    throw new RuntimeException('An owned Laravel storage directory is required.');
}
$app->useStoragePath($storage);
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$database = (string) config('database.connections.mariadb.database');
$host = (string) config('database.connections.mariadb.host');
$assert(app()->environment('testing')
    && config('database.default') === 'mariadb'
    && str_starts_with($database, 'help_')
    && in_array($host, ['127.0.0.1', 'localhost'], true),
    'Role-permission fixtures require an owned help_-prefixed testing database on loopback.');

$privatePath = getenv('ARASYA_CLOSURE_PRIVATE');
$tempRoot = realpath(sys_get_temp_dir().DIRECTORY_SEPARATOR.'arasya-ui-closure');
$runDirectory = is_string($privatePath) ? realpath(dirname($privatePath)) : false;
$assert(is_string($privatePath) && basename($privatePath) === 'fixture.json'
    && $tempRoot !== false && $runDirectory !== false
    && str_starts_with($runDirectory, $tempRoot.DIRECTORY_SEPARATOR),
    'Role-permission fixture path must be fixture.json inside its owned temporary run directory.');
$ownerPath = $runDirectory.DIRECTORY_SEPARATOR.'.owner';
$owner = is_file($ownerPath) ? trim((string) file_get_contents($ownerPath), "\xEF\xBB\xBF \t\n\r\0\x0B") : '';
$assert(preg_match('/\AA1-CORE-018:([a-f0-9]{32})\z/', $owner, $ownerMatch) === 1
    && hash_equals($ownerMatch[1], basename($runDirectory)),
    'Role-permission fixture directory has no matching owner marker.');
$fixturePath = $runDirectory.DIRECTORY_SEPARATOR.'fixture.json';
$assert(strcasecmp($fixturePath, $privatePath) === 0 && ! file_exists($fixturePath),
    'An owned role-permission fixture already exists or its path does not match.');

$migrationStatus = Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
$assert($migrationStatus === 0, 'Role-permission fixture migrations failed.');

$id = static fn (): string => (string) Str::ulid();
$suffix = strtolower((string) Str::ulid());
$password = static fn (): string => Str::random(48);
$fixture = [
    'company' => $id(),
    'branch' => $id(),
    'department' => $id(),
    'admin' => ['id' => $id(), 'email' => "synthetic-role-admin-{$suffix}@example.test", 'password' => $password()],
    'sales' => ['id' => $id(), 'email' => "synthetic-role-sales-{$suffix}@example.test", 'password' => $password()],
    'recipient' => ['id' => $id(), 'email' => "synthetic-role-recipient-{$suffix}@example.test", 'password' => $password()],
    'privateContact' => ['email' => "private-{$suffix}@example.test", 'phone' => '+20111'.random_int(1000000, 9999999), 'whatsapp' => '+20112'.random_int(1000000, 9999999)],
];
$fixture['assigned_lead_id'] = $id();
$now = now();

DB::transaction(static function () use ($fixture, $now, $suffix, $fixturePath): void {
    DB::table('access_companies')->insert(['id' => $fixture['company'], 'name' => "Synthetic role company {$suffix}", 'version' => 0]);
    DB::table('access_branches')->insert(['id' => $fixture['branch'], 'company_id' => $fixture['company'], 'name' => "Synthetic role branch {$suffix}", 'code' => $suffix, 'active' => true, 'version' => 0]);
    DB::table('access_departments')->insert(['id' => $fixture['department'], 'company_id' => $fixture['company'], 'branch_id' => $fixture['branch'], 'name' => "Synthetic role department {$suffix}", 'code' => $suffix, 'active' => true, 'version' => 0]);

    foreach (['admin', 'sales', 'recipient'] as $kind) {
        $user = $fixture[$kind];
        DB::table('access_users')->insert([
            'id' => $user['id'],
            'company_id' => $fixture['company'],
            'branch_id' => $fixture['branch'],
            'department_id' => $fixture['department'],
            'name' => "Synthetic role {$kind} {$suffix}",
            'email' => $user['email'],
            'password' => Hash::make($user['password']),
            'active' => true,
            'is_admin' => $kind === 'admin',
            'session_version' => 0,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    foreach (['crm' => ['crm.read', 'crm.write'], 'tasks' => ['tasks.read', 'tasks.write']] as $module => $permissions) {
        $roleId = (string) Str::ulid();
        DB::table('access_roles')->insert(['id' => $roleId, 'company_id' => $fixture['company'], 'name' => "Synthetic role {$module} {$suffix}", 'version' => 0]);
        DB::table('access_role_permissions')->insert(array_map(static fn (string $permission): array => ['role_id' => $roleId, 'permission' => $permission], $permissions));
        DB::table('access_user_roles')->insert([
            ['user_id' => $fixture['sales']['id'], 'role_id' => $roleId],
            ['user_id' => $fixture['recipient']['id'], 'role_id' => $roleId],
        ]);
    }

    DB::table('crm_leads')->insert([
        'id' => $fixture['assigned_lead_id'],
        'company_id' => $fixture['company'],
        'branch_id' => $fixture['branch'],
        'department_id' => $fixture['department'],
        'name' => "Synthetic role lead {$suffix}",
        'organization' => 'Synthetic client',
        'source' => 'Synthetic role test',
        'contacts' => Crypt::encryptString(json_encode($fixture['privateContact'], JSON_THROW_ON_ERROR)),
        'duplicate_key' => null,
        'consent' => true,
        'preferences' => '{}',
        'notes' => null,
        'stage_id' => null,
        'assignee_id' => $fixture['sales']['id'],
        'customer_id' => null,
        'archived' => false,
        'version' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $fixture['run'] = basename(dirname($fixturePath));
    if (file_put_contents($fixturePath, json_encode($fixture, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX) === false) {
        throw new RuntimeException('Could not save the owned role-permission fixture.');
    }
});

echo "PASS: synthetic role-permission fixtures seeded; credentials were not emitted.\n";

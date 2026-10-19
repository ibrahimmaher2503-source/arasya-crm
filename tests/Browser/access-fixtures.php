<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$assert($app->environment('testing') && config('session.driver') === 'file', 'Access browser fixtures require APP_ENV=testing and SESSION_DRIVER=file.');
$assert(config('database.default') === 'mysql'
    && config('database.connections.mysql.database') === 'arasya_windows_browser'
    && config('database.connections.mysql.host') === '127.0.0.1'
    && (string) config('database.connections.mysql.port') === '13316',
    'Access browser fixtures require the isolated loopback MariaDB database.');
$baseUrl = getenv('ARASYA_BROWSER_BASE_URL') ?: 'http://127.0.0.1:8765';
$assert(parse_url($baseUrl, PHP_URL_SCHEME) === 'http'
    && in_array(parse_url($baseUrl, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true),
    'Access browser fixtures may target loopback only.');

foreach (['access_companies', 'access_branches', 'access_departments', 'access_users', 'access_roles', 'access_role_permissions', 'access_user_roles', 'access_user_permissions', 'access_audit'] as $table) {
    $assert(Schema::hasTable($table), "Access browser fixture table {$table} is missing; migrate the isolated database first.");
}

$mode = $argv[1] ?? throw new RuntimeException('Pass seed, verify, or cleanup.');
$fixtureDirectory = storage_path('framework/testing');
if (! is_dir($fixtureDirectory) && ! mkdir($fixtureDirectory, 0700, true) && ! is_dir($fixtureDirectory)) {
    throw new RuntimeException('Could not create the ignored browser-fixture directory.');
}
$allowedDirectory = realpath($fixtureDirectory);
$assert($allowedDirectory !== false, 'The browser-fixture directory could not be resolved.');
$fixturePath = $allowedDirectory.DIRECTORY_SEPARATOR.'access-fixture.json';
$assert(realpath(dirname($fixturePath)) === $allowedDirectory, 'Access fixture files may be written only under storage/framework/testing.');

$loadFixture = static function () use ($assert, $fixturePath): array {
    $contents = file_get_contents($fixturePath);
    $assert($contents !== false, 'The access browser fixture file is missing.');
    $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    $assert(is_array($fixture)
        && isset($fixture['company']['id'], $fixture['company']['name'], $fixture['root_branch']['id'], $fixture['root_branch']['name'], $fixture['root_department']['id'], $fixture['root_department']['name'], $fixture['admin']['id'], $fixture['admin']['name'], $fixture['admin']['email'], $fixture['admin']['password']),
        'The access browser fixture file is incomplete.');

    return $fixture;
};

if ($mode === 'seed') {
    $assert(! file_exists($fixturePath), 'An access fixture already exists; verify or clean it before seeding another.');

    $suffix = strtolower((string) Str::ulid());
    $password = Str::random(48);
    $now = now();
    $fixture = [
        'company' => ['id' => (string) Str::ulid(), 'name' => "Synthetic access company {$suffix}"],
        'root_branch' => ['id' => (string) Str::ulid(), 'name' => "Synthetic access root branch {$suffix}"],
        'root_department' => ['id' => (string) Str::ulid(), 'name' => "Synthetic access root department {$suffix}"],
        'admin' => [
            'id' => (string) Str::ulid(),
            'name' => "Synthetic access administrator {$suffix}",
            'email' => "synthetic-access-admin-{$suffix}@example.test",
            'password' => $password,
        ],
    ];

    try {
        DB::transaction(static function () use ($fixture, $now, $password, $fixturePath): void {
            DB::table('access_companies')->insert([
                'id' => $fixture['company']['id'],
                'name' => $fixture['company']['name'],
                'version' => 0,
            ]);
            DB::table('access_branches')->insert([
                'id' => $fixture['root_branch']['id'],
                'company_id' => $fixture['company']['id'],
                'name' => $fixture['root_branch']['name'],
                'code' => 'root',
                'active' => true,
                'version' => 0,
            ]);
            DB::table('access_departments')->insert([
                'id' => $fixture['root_department']['id'],
                'company_id' => $fixture['company']['id'],
                'branch_id' => $fixture['root_branch']['id'],
                'name' => $fixture['root_department']['name'],
                'code' => 'root',
                'active' => true,
                'version' => 0,
            ]);
            DB::table('access_users')->insert([
                'id' => $fixture['admin']['id'],
                'company_id' => $fixture['company']['id'],
                'branch_id' => $fixture['root_branch']['id'],
                'department_id' => $fixture['root_department']['id'],
                'name' => $fixture['admin']['name'],
                'email' => $fixture['admin']['email'],
                'password' => Hash::make($password),
                'active' => true,
                'is_admin' => true,
                'session_version' => 0,
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (file_put_contents($fixturePath, json_encode($fixture, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX) === false) {
                throw new RuntimeException('Could not write the ignored access browser fixture file.');
            }
        });
    } catch (Throwable $exception) {
        if (file_exists($fixturePath)) {
            unlink($fixturePath);
        }

        throw $exception;
    }

    echo "Synthetic access fixture seeded; credentials are stored in the ignored browser-fixture file.\n";
    exit(0);
}

if (! in_array($mode, ['verify', 'cleanup'], true)) {
    throw new RuntimeException('Unknown access browser fixture mode.');
}

$fixture = $loadFixture();
$company = DB::table('access_companies')->where('id', $fixture['company']['id'])->first();
$assert($company !== null && $company->name === $fixture['company']['name'] && str_starts_with($company->name, 'Synthetic access company '), 'The fixture does not own this company.');
$rootBranch = DB::table('access_branches')->where('id', $fixture['root_branch']['id'])->where('company_id', $company->id)->first();
$rootDepartment = DB::table('access_departments')->where('id', $fixture['root_department']['id'])->where('company_id', $company->id)->first();
$admin = DB::table('access_users')->where('id', $fixture['admin']['id'])->where('company_id', $company->id)->first();
$assert($rootBranch !== null && $rootBranch->name === $fixture['root_branch']['name'], 'The fixture root branch is outside the owned company.');
$assert($rootDepartment !== null && $rootDepartment->branch_id === $rootBranch->id && $rootDepartment->name === $fixture['root_department']['name'], 'The fixture root department is outside the owned branch.');
$assert($admin !== null && $admin->email === $fixture['admin']['email'] && (bool) $admin->is_admin, 'The fixture administrator is outside the owned company.');

if ($mode === 'verify') {
    $assert(Hash::check($fixture['admin']['password'], $admin->password) && $admin->password !== $fixture['admin']['password'], 'The administrator password must authenticate and remain hashed in storage.');

    $branches = DB::table('access_branches')->where('company_id', $company->id)->get(['id', 'name']);
    $departments = DB::table('access_departments')->where('company_id', $company->id)->get(['id', 'branch_id', 'name']);
    $users = DB::table('access_users')->where('company_id', $company->id)->get(['id', 'branch_id', 'department_id', 'email', 'password']);
    $roles = DB::table('access_roles')->where('company_id', $company->id)->get(['id', 'name']);
    $createdBranches = $branches->reject(static fn ($row): bool => $row->id === $fixture['root_branch']['id']);
    $createdDepartments = $departments->reject(static fn ($row): bool => $row->id === $fixture['root_department']['id']);
    $createdUsers = $users->reject(static fn ($row): bool => $row->id === $fixture['admin']['id']);
    $createdRoles = $roles->filter(static fn ($row): bool => str_starts_with($row->name, 'Synthetic access role '));
    $assert($createdBranches->isNotEmpty() && $createdDepartments->isNotEmpty() && $createdUsers->isNotEmpty() && $createdRoles->isNotEmpty()
        && $createdRoles->count() === $roles->count(), 'The browser flow must create only synthetic branches, departments, users, and roles inside the fixture company.');

    foreach ($createdBranches as $row) {
        $assert(str_starts_with($row->name, 'Synthetic access branch '), 'A non-synthetic branch exists under the fixture company.');
    }
    foreach ($createdDepartments as $row) {
        $assert(str_starts_with($row->name, 'Synthetic access department ')
            && $createdBranches->contains('id', $row->branch_id), 'A department is outside the browser-created branch scope.');
    }
    foreach ($createdUsers as $row) {
        $assert(str_starts_with($row->email, 'synthetic-access-user-')
            && $createdBranches->contains('id', $row->branch_id)
            && $createdDepartments->contains(static fn ($department): bool => $department->id === $row->department_id && $department->branch_id === $row->branch_id)
            && Hash::check('SyntheticAccess!2026', $row->password)
            && $row->password !== 'SyntheticAccess!2026',
            'A browser-created user is not synthetic, branch-scoped, or stored with a password hash.');
    }

    $roleIds = $createdRoles->pluck('id')->all();
    $permission = DB::table('access_role_permissions')->whereIn('role_id', $roleIds)->where('permission', 'organization.read')->exists();
    $assert($permission, 'The browser-created restricted role must retain organization.read.');
    $assignedUsers = $createdUsers->pluck('id')->all();
    $assert(DB::table('access_user_roles')->whereIn('user_id', $assignedUsers)->whereIn('role_id', $roleIds)->doesntExist(), 'The browser flow must revoke the restricted role before verification.');
    $assert(DB::table('access_user_permissions')->whereIn('user_id', $assignedUsers)->doesntExist(), 'The browser flow must leave no direct permission override after revocation.');

    $auditSubjects = $createdBranches->pluck('id')
        ->merge($createdDepartments->pluck('id'))
        ->merge($createdUsers->pluck('id'))
        ->merge($createdRoles->pluck('id'))
        ->unique()
        ->all();
    $audits = DB::table('access_audit')->where('company_id', $company->id)->where('actor_id', $admin->id)->get(['subject_id']);
    foreach ($auditSubjects as $subjectId) {
        $assert($audits->contains('subject_id', $subjectId), 'The administrator audit trail is missing a browser-created access record.');
    }
    foreach ($assignedUsers as $userId) {
        $assert(DB::table('access_audit')->where('company_id', $company->id)->where('actor_id', $admin->id)->where('subject_id', $userId)->count() >= 2, 'The administrator audit trail must record granting and revoking the restricted role.');
    }

    echo "Access browser data is company-scoped, audited, revoked, and password-hashed.\n";
    exit(0);
}

$branches = DB::table('access_branches')->where('company_id', $company->id)->get(['id', 'name']);
$departments = DB::table('access_departments')->where('company_id', $company->id)->get(['id', 'name']);
$users = DB::table('access_users')->where('company_id', $company->id)->get(['id', 'email']);
$roles = DB::table('access_roles')->where('company_id', $company->id)->get(['id', 'name']);
$assert($branches->every(static fn ($row): bool => str_starts_with($row->name, 'Synthetic access ')), 'Cleanup refused a company containing a non-synthetic branch.');
$assert($departments->every(static fn ($row): bool => str_starts_with($row->name, 'Synthetic access ')), 'Cleanup refused a company containing a non-synthetic department.');
$assert($users->every(static fn ($row): bool => $row->id === $fixture['admin']['id'] || str_starts_with($row->email, 'synthetic-access-user-')), 'Cleanup refused a company containing a non-synthetic user.');
$assert($roles->every(static fn ($row): bool => str_starts_with($row->name, 'Synthetic access role ')), 'Cleanup refused a company containing a non-synthetic role.');

$userIds = $users->pluck('id')->all();
$roleIds = $roles->pluck('id')->all();
DB::transaction(static function () use ($company, $userIds, $roleIds): void {
    DB::table('access_audit')->where('company_id', $company->id)->delete();
    DB::table('access_department_roles')->where('company_id', $company->id)->delete();
    if ($userIds !== []) {
        DB::table('access_user_roles')->whereIn('user_id', $userIds)->delete();
        DB::table('access_user_permissions')->whereIn('user_id', $userIds)->delete();
    }
    if ($roleIds !== []) {
        DB::table('access_user_roles')->whereIn('role_id', $roleIds)->delete();
        DB::table('access_role_permissions')->whereIn('role_id', $roleIds)->delete();
    }
    DB::table('access_users')->whereIn('id', $userIds)->delete();
    DB::table('access_departments')->where('company_id', $company->id)->delete();
    DB::table('access_branches')->where('company_id', $company->id)->delete();
    DB::table('access_roles')->where('company_id', $company->id)->delete();
    DB::table('access_companies')->where('id', $company->id)->delete();
});

if (! unlink($fixturePath)) {
    throw new RuntimeException('Fixture data was removed, but its ignored credential file could not be deleted.');
}

echo "Synthetic access browser fixture cleaned up by company ownership.\n";

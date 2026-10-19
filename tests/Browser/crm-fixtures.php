<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
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

$assert($app->environment('testing') && config('session.driver') === 'file', 'CRM browser fixtures require APP_ENV=testing and SESSION_DRIVER=file.');
$assert(config('database.default') === 'mysql'
    && config('database.connections.mysql.database') === 'arasya_cycle03_acceptance'
    && config('database.connections.mysql.host') === '127.0.0.1'
    && (string) config('database.connections.mysql.port') === '13317',
    'CRM browser fixtures require the isolated loopback Cycle03 MariaDB database on port 13317.');
$assert(is_string(config('app.key')) && config('app.key') !== '', 'CRM browser fixtures require the same nonempty APP_KEY as the HTTP server.');
$database = DB::selectOne('SELECT VERSION() AS server_version, @@datadir AS data_directory, DATABASE() AS database_name');
$expectedDataDirectory = realpath(getenv('ARASYA_CRM_QA_DATADIR') ?: dirname(__DIR__, 2).'/.local/windows-qa/cycle03-db');
$actualDataDirectory = is_string($database?->data_directory ?? null) ? realpath((string) $database->data_directory) : false;
$assert($database !== null
    && preg_match('/^11\.4\.13-MariaDB(?:[-+].*)?$/D', (string) $database->server_version) === 1
    && $database->database_name === 'arasya_cycle03_acceptance'
    && $expectedDataDirectory !== false
    && $actualDataDirectory !== false
    && strcasecmp(rtrim($actualDataDirectory, '\\/'), rtrim($expectedDataDirectory, '\\/')) === 0,
    'CRM browser fixtures require MariaDB 11.4.13 at the isolated Cycle03 datadir and database.');

$baseUrl = getenv('ARASYA_BROWSER_BASE_URL') ?: 'http://127.0.0.1:8765';
$assert(parse_url($baseUrl, PHP_URL_SCHEME) === 'http'
    && in_array(parse_url($baseUrl, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)
    && parse_url($baseUrl, PHP_URL_USER) === null
    && parse_url($baseUrl, PHP_URL_PASS) === null,
    'CRM browser fixtures may target loopback HTTP only.');

foreach ([
    'access_companies', 'access_branches', 'access_departments', 'access_users', 'access_roles',
    'access_role_permissions', 'access_user_roles', 'access_user_permissions', 'access_audit',
    'crm_customers', 'crm_taxonomy', 'crm_leads', 'crm_activities', 'crm_transfers', 'crm_audit',
] as $table) {
    $assert(Schema::hasTable($table), "CRM browser fixture table {$table} is missing; migrate the isolated database first.");
}

$mode = $argv[1] ?? throw new RuntimeException('Pass seed, verify, or cleanup.');
$directory = storage_path('framework/testing');
if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
    throw new RuntimeException('Could not create the ignored CRM browser-fixture directory.');
}
$resolvedDirectory = realpath($directory);
$assert($resolvedDirectory !== false, 'The CRM browser-fixture directory could not be resolved.');
$projectRoot = realpath(dirname(__DIR__, 2));
$expectedFixtureDirectory = $projectRoot === false
    ? false
    : $projectRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing';
$assert($expectedFixtureDirectory !== false
    && strcasecmp(rtrim($resolvedDirectory, '\\/'), rtrim($expectedFixtureDirectory, '\\/')) === 0,
    'CRM browser credentials may be stored only in this project’s storage/framework/testing directory.');
$path = $resolvedDirectory.DIRECTORY_SEPARATOR.'crm-fixture.json';
$assert(realpath(dirname($path)) === $resolvedDirectory, 'CRM fixture files may be written only under storage/framework/testing.');

$loadFixture = static function () use ($assert, $path): array {
    $contents = file_get_contents($path);
    $assert($contents !== false, 'The CRM browser fixture file is missing.');
    $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    $assert(is_array($fixture)
        && isset($fixture['company']['id'], $fixture['company']['name'], $fixture['branch']['id'], $fixture['department']['id'],
            $fixture['stage_id'], $fixture['outcome_id'], $fixture['lead_count'], $fixture['assigned_lead_id'], $fixture['unrelated_lead_id'],
            $fixture['manager']['id'], $fixture['manager']['email'], $fixture['manager']['password'],
            $fixture['sales']['id'], $fixture['sales']['email'], $fixture['sales']['password'],
            $fixture['unrelated_sales']['id'], $fixture['unrelated_sales']['email'], $fixture['unrelated_sales']['password']),
        'The CRM browser fixture file is incomplete.');

    return $fixture;
};

if ($mode === 'seed') {
    $assert(! file_exists($path), 'A CRM fixture already exists; verify or clean it before seeding another.');

    $suffix = strtolower(substr((string) Str::ulid(), -10));
    $makeUser = static function (string $kind, array $permissions) use ($suffix): array {
        return [
            'id' => (string) Str::ulid(),
            'name' => "Synthetic CRM {$kind} {$suffix}",
            'email' => "synthetic-crm-{$kind}-{$suffix}@example.test",
            'password' => Str::random(48),
            'permissions' => $permissions,
        ];
    };
    $fixture = [
        'owner_suffix' => $suffix,
        'company' => ['id' => (string) Str::ulid(), 'name' => "Synthetic CRM browser company {$suffix}"],
        'branch' => ['id' => (string) Str::ulid(), 'name' => "Synthetic CRM browser branch {$suffix}"],
        'department' => ['id' => (string) Str::ulid(), 'name' => "Synthetic CRM browser department {$suffix}"],
        'stage_id' => (string) Str::ulid(),
        'outcome_id' => (string) Str::ulid(),
        'manager' => $makeUser('manager', ['crm.read', 'crm.manage', 'crm.write', 'crm.assign', 'crm.transfer', 'crm.convert', 'contact.reveal']),
        'sales' => $makeUser('sales', ['crm.read', 'crm.write']),
        'unrelated_sales' => $makeUser('unrelated-sales', ['crm.read', 'crm.write']),
        'lead_count' => 300,
    ];
    $fixture['assigned_lead_id'] = (string) Str::ulid();
    $fixture['unrelated_lead_id'] = (string) Str::ulid();
    $now = now()->toDateTimeString();

    try {
        DB::transaction(static function () use ($fixture, $now, $path): void {
            DB::table('access_companies')->insert([
                'id' => $fixture['company']['id'], 'name' => $fixture['company']['name'], 'version' => 0,
            ]);
            DB::table('access_branches')->insert([
                'id' => $fixture['branch']['id'], 'company_id' => $fixture['company']['id'],
                'name' => $fixture['branch']['name'], 'code' => 'crm-'.$fixture['owner_suffix'], 'active' => true, 'version' => 0,
            ]);
            DB::table('access_departments')->insert([
                'id' => $fixture['department']['id'], 'company_id' => $fixture['company']['id'],
                'branch_id' => $fixture['branch']['id'], 'name' => $fixture['department']['name'],
                'code' => 'crm-'.$fixture['owner_suffix'], 'active' => true, 'version' => 0,
            ]);

            foreach (['manager', 'sales', 'unrelated_sales'] as $kind) {
                $user = $fixture[$kind];
                $roleId = (string) Str::ulid();
                DB::table('access_roles')->insert([
                    'id' => $roleId, 'company_id' => $fixture['company']['id'],
                    'name' => "Synthetic CRM Browser {$fixture['owner_suffix']} {$kind}", 'version' => 0,
                ]);
                DB::table('access_role_permissions')->insert(array_map(
                    static fn (string $permission): array => ['role_id' => $roleId, 'permission' => $permission],
                    $user['permissions'],
                ));
                DB::table('access_users')->insert([
                    'id' => $user['id'], 'company_id' => $fixture['company']['id'], 'branch_id' => $fixture['branch']['id'],
                    'department_id' => $fixture['department']['id'], 'name' => $user['name'], 'email' => $user['email'],
                    'password' => Hash::make($user['password']), 'active' => true, 'is_admin' => false,
                    'session_version' => 0, 'version' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('access_user_roles')->insert(['user_id' => $user['id'], 'role_id' => $roleId]);
            }

            DB::table('crm_taxonomy')->insert([
                'id' => $fixture['stage_id'], 'company_id' => $fixture['company']['id'], 'kind' => 'stage',
                'label' => "Synthetic new {$fixture['owner_suffix']}", 'replaces_id' => null, 'created_at' => $now,
            ]);
            DB::table('crm_taxonomy')->insert([
                'id' => $fixture['outcome_id'], 'company_id' => $fixture['company']['id'], 'kind' => 'outcome',
                'label' => "Synthetic completed {$fixture['owner_suffix']}", 'replaces_id' => null, 'created_at' => $now,
            ]);

            $leads = [];
            foreach (range(1, $fixture['lead_count']) as $number) {
                $leadId = match ($number) {
                    1 => $fixture['assigned_lead_id'],
                    2 => $fixture['unrelated_lead_id'],
                    default => (string) Str::ulid(),
                };
                $name = sprintf('Synthetic CRM Prospect %03d %s', $number, $fixture['owner_suffix']);
                $email = sprintf('synthetic-crm-%s-lead-%03d@example.test', $fixture['owner_suffix'], $number);
                $contacts = ['email' => $email, 'phone' => sprintf('+2012000%05d', $number), 'whatsapp' => sprintf('+2013000%05d', $number)];
                $assigneeId = match ($number) {
                    1 => $fixture['sales']['id'],
                    2 => $fixture['unrelated_sales']['id'],
                    default => null,
                };
                $leads[] = [
                    'id' => $leadId, 'company_id' => $fixture['company']['id'], 'branch_id' => $fixture['branch']['id'],
                    'department_id' => $fixture['department']['id'], 'name' => $name,
                    'organization' => sprintf('Synthetic CRM Organization %03d %s', $number, $fixture['owner_suffix']),
                    'source' => 'website',
                    'contacts' => Crypt::encryptString(json_encode($contacts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
                    'duplicate_key' => hash_hmac('sha256', 'email:'.mb_strtolower(trim($email)), (string) config('app.key')),
                    'consent' => true, 'preferences' => '{"email":true,"phone":true,"whatsapp":true}',
                    'notes' => 'Synthetic browser fixture record.', 'stage_id' => $fixture['stage_id'],
                    'assignee_id' => $assigneeId, 'customer_id' => null, 'archived' => false, 'version' => 0,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            DB::table('crm_leads')->insert($leads);

            if (file_put_contents($path, json_encode($fixture, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX) === false) {
                throw new RuntimeException('Could not write the ignored CRM browser fixture file.');
            }
        });
    } catch (Throwable $exception) {
        if (file_exists($path)) {
            unlink($path);
        }

        throw $exception;
    }

    echo "CRM synthetic browser fixture seeded; login credentials are stored only in ignored storage.\n";
    exit(0);
}

if (! in_array($mode, ['verify', 'verify-polish', 'cleanup'], true)) {
    throw new RuntimeException('Unknown CRM fixture mode. Pass seed, verify, verify-polish, or cleanup.');
}

$fixture = $loadFixture();
$company = DB::table('access_companies')->where('id', $fixture['company']['id'])->first();
$assert($company !== null
    && $company->name === $fixture['company']['name']
    && $company->name === "Synthetic CRM browser company {$fixture['owner_suffix']}",
    'The CRM fixture does not own this company.');
$branch = DB::table('access_branches')->where('id', $fixture['branch']['id'])->where('company_id', $company->id)->first();
$department = DB::table('access_departments')->where('id', $fixture['department']['id'])->where('company_id', $company->id)->first();
$assert($branch !== null && $branch->name === "Synthetic CRM browser branch {$fixture['owner_suffix']}", 'The CRM fixture branch is outside its owned company.');
$assert($department !== null && $department->branch_id === $branch->id
    && $department->name === "Synthetic CRM browser department {$fixture['owner_suffix']}",
    'The CRM fixture department is outside its owned branch.');
$taxonomy = DB::table('crm_taxonomy')->where('company_id', $company->id)->get();
$assert($taxonomy->count() === 2
    && $taxonomy->contains(fn ($row): bool => $row->id === $fixture['stage_id'] && $row->kind === 'stage'
        && $row->label === "Synthetic new {$fixture['owner_suffix']}" && $row->replaces_id === null)
    && $taxonomy->contains(fn ($row): bool => $row->id === $fixture['outcome_id'] && $row->kind === 'outcome'
        && $row->label === "Synthetic completed {$fixture['owner_suffix']}" && $row->replaces_id === null),
    'The CRM taxonomy contains only the fixture-owned stage and activity outcome.');

$users = collect(['manager', 'sales', 'unrelated_sales'])->mapWithKeys(static function (string $kind) use ($fixture, $company, $assert): array {
    $user = $fixture[$kind];
    $row = DB::table('access_users')->where('id', $user['id'])->where('company_id', $company->id)->first();
    $assert($row !== null && $row->email === $user['email'] && $row->name === $user['name']
        && Hash::check($user['password'], $row->password), "The synthetic {$kind} login is not owned by this CRM fixture.");

    return [$kind => $row];
});

if ($mode === 'verify-polish') {
    $leads = DB::table('crm_leads')->where('company_id', $company->id)->get();
    $polishPrefix = "Synthetic CRM Polish {$fixture['owner_suffix']} ";
    $polishLeads = $leads->filter(static fn ($lead): bool => str_starts_with($lead->name, $polishPrefix));
    $flows = ['visual', 'cancel', 'edit', 'stale', 'activity', 'transfer-accept', 'transfer-reject', 'converted', 'archived', 'privacy'];
    $idsByFlow = [];
    foreach ($flows as $flow) {
        $flowLeads = $polishLeads->filter(static fn ($lead): bool => str_starts_with($lead->name, $polishPrefix.$flow.' '));
        $assert($flowLeads->isNotEmpty(), "The CRM polish browser flow {$flow} did not create its synthetic lead.");
        $idsByFlow[$flow] = $flowLeads->pluck('id')->all();
    }
    $assert($polishLeads->every(fn ($lead): bool => ! str_contains($lead->contacts, '@example.test')),
        'CRM polish contacts were stored in plaintext.');
    $assert($polishLeads->whereIn('id', $idsByFlow['cancel'])->every(fn ($lead): bool => (int) $lead->version === 0
        && $lead->organization === "Synthetic CRM Polish initial {$fixture['owner_suffix']}"),
        'Cancelled edits did not persist.');
    $assert($polishLeads->whereIn('id', $idsByFlow['edit'])->every(fn ($lead): bool => (int) $lead->version === 1
        && $lead->organization === "Synthetic CRM Polish edited {$fixture['owner_suffix']}"),
        'Saved edits did not persist at version one.');
    $assert($polishLeads->whereIn('id', $idsByFlow['stale'])->every(fn ($lead): bool => (int) $lead->version === 1
        && $lead->organization === "Synthetic CRM Polish winner {$fixture['owner_suffix']}"),
        'A stale edit overwrote the winning browser edit.');

    $activities = DB::table('crm_activities')->where('company_id', $company->id)->whereIn('lead_id', $idsByFlow['activity'])->get();
    $assert($activities->isNotEmpty() && $activities->every(fn ($activity): bool => $activity->kind === 'call'
        && $activity->outcome_id === $fixture['outcome_id']),
        'The browser activity was not saved with the fixture outcome.');
    $acceptedIds = $idsByFlow['transfer-accept'];
    $acceptedTransfers = DB::table('crm_transfers')->where('company_id', $company->id)->whereIn('lead_id', $acceptedIds)->get();
    $assert($acceptedTransfers->isNotEmpty() && $acceptedTransfers->every(fn ($transfer): bool => $transfer->state === 'accepted'
        && $transfer->recipient_id === $users['unrelated_sales']->id)
        && $polishLeads->whereIn('id', $acceptedIds)->every(fn ($lead): bool => $lead->assignee_id === $users['unrelated_sales']->id),
        'The accepted browser transfer did not change lead ownership.');
    $rejectedIds = $idsByFlow['transfer-reject'];
    $rejectedTransfers = DB::table('crm_transfers')->where('company_id', $company->id)->whereIn('lead_id', $rejectedIds)->get();
    $assert($rejectedTransfers->isNotEmpty() && $rejectedTransfers->every(fn ($transfer): bool => $transfer->state === 'rejected')
        && $polishLeads->whereIn('id', $rejectedIds)->every(fn ($lead): bool => $lead->assignee_id === $users['sales']->id),
        'The rejected browser transfer changed lead ownership.');
    $assert($polishLeads->whereIn('id', $idsByFlow['converted'])->every(fn ($lead): bool => $lead->customer_id !== null
        && DB::table('crm_customers')->where('id', $lead->customer_id)->where('company_id', $company->id)->exists()),
        'The browser conversion did not persist its customer.');
    $assert($polishLeads->whereIn('id', $idsByFlow['archived'])->every(fn ($lead): bool => (bool) $lead->archived),
        'The browser archive did not persist.');

    $polishIds = $polishLeads->pluck('id')->all();
    $actions = DB::table('crm_audit')->where('company_id', $company->id)->whereIn('lead_id', $polishIds)->pluck('action');
    foreach (['lead.created', 'lead.updated', 'lead.assigned', 'lead.activity.recorded', 'lead.transfer.requested',
        'lead.transfer.accepted', 'lead.transfer.rejected', 'lead.converted', 'lead.archived'] as $action) {
        $assert($actions->contains($action), "The CRM polish browser audit is missing {$action}.");
    }
    $assert(DB::table('crm_audit')->where('company_id', $company->id)->whereIn('lead_id', $polishIds)
        ->whereNotIn('actor_id', $users->pluck('id'))->doesntExist(),
        'A CRM polish audit row is attributed outside the synthetic fixture.');

    echo json_encode(['synthetic_leads' => $polishLeads->count(), 'verified_flows' => count($flows)], JSON_THROW_ON_ERROR)."\n";
    exit(0);
}

if ($mode === 'verify') {
    $leads = DB::table('crm_leads')->where('company_id', $company->id)->get();
    $ownsLeadName = fn (string $name): bool => preg_match('/^Synthetic CRM Prospect \d{3} '.preg_quote($fixture['owner_suffix'], '/').'$/D', $name) === 1
        || str_starts_with($name, "Synthetic CRM Polish {$fixture['owner_suffix']} ");
    $assert($leads->count() >= $fixture['lead_count'], 'The CRM browser directory has fewer than 300 synthetic rows.');
    $assert($leads->every(fn ($lead): bool => $ownsLeadName($lead->name)
        && ! str_contains($lead->contacts, '@example.test')),
        'The CRM fixture contains a non-synthetic lead or an unencrypted contact value.');
    $browserLeadIds = [];
    foreach ([301, 302] as $number) {
        $name = sprintf('Synthetic CRM Prospect %03d %s', $number, $fixture['owner_suffix']);
        $browserLead = $leads->first(static fn ($lead): bool => $lead->name === $name);
        $assert($browserLead !== null
            && (int) $browserLead->version === 1
            && $browserLead->organization === "Synthetic CRM Organization updated {$fixture['owner_suffix']}",
            'A desktop or mobile browser-created CRM lead was not saved and updated.');
        $contacts = json_decode(Crypt::decryptString($browserLead->contacts), true, 8, JSON_THROW_ON_ERROR);
        $assert(is_array($contacts)
            && filter_var($contacts['email'] ?? null, FILTER_VALIDATE_EMAIL) !== false
            && str_ends_with((string) $contacts['email'], '@example.test')
            && preg_match('/^\+201555[a-f0-9]{10}$/D', (string) ($contacts['phone'] ?? '')) === 1
            && preg_match('/^\+201556[a-f0-9]{10}$/D', (string) ($contacts['whatsapp'] ?? '')) === 1,
            'A browser-created CRM lead does not contain the expected synthetic encrypted contacts.');
        $browserLeadIds[] = $browserLead->id;
    }
    $assert(DB::table('crm_audit')->where('company_id', $company->id)->whereIn('lead_id', $browserLeadIds)
        ->where('action', 'lead.contacts.revealed')->count() >= 2,
        'The browser manager did not create both expected reasoned contact-reveal audits.');
    $assert(DB::table('crm_leads')->where('id', $fixture['assigned_lead_id'])->where('assignee_id', $users['sales']->id)->exists()
        && DB::table('crm_leads')->where('id', $fixture['unrelated_lead_id'])->where('assignee_id', $users['unrelated_sales']->id)->exists(),
        'The manager, assigned salesperson, and unrelated salesperson do not have distinct lead scopes.');
    $assert(DB::table('crm_audit')->where('company_id', $company->id)->whereNotIn('actor_id', $users->pluck('id'))->doesntExist(),
        'A CRM audit row is attributed to a user outside the synthetic fixture.');

    echo json_encode(['company' => 'synthetic', 'leads' => $leads->count(), 'users' => $users->count()], JSON_THROW_ON_ERROR)."\n";
    exit(0);
}

$branchRows = DB::table('access_branches')->where('company_id', $company->id)->get();
$departmentRows = DB::table('access_departments')->where('company_id', $company->id)->get();
$roleRows = DB::table('access_roles')->where('company_id', $company->id)->get();
$companyUserIds = DB::table('access_users')->where('company_id', $company->id)->pluck('id')->all();
$fixtureUserIds = $users->pluck('id')->all();
$assert($branchRows->count() === 1 && $branchRows->first()->id === $branch->id && $branch->name === "Synthetic CRM browser branch {$fixture['owner_suffix']}",
    'Cleanup refused a company containing a non-fixture branch.');
$assert($departmentRows->count() === 1 && $departmentRows->first()->id === $department->id
    && $department->name === "Synthetic CRM browser department {$fixture['owner_suffix']}",
    'Cleanup refused a company containing a non-fixture department.');
$assert($roleRows->count() === 3
    && $roleRows->every(fn ($row): bool => str_starts_with($row->name, "Synthetic CRM Browser {$fixture['owner_suffix']} ")),
    'Cleanup refused a company containing a non-fixture role.');
$assert(count($companyUserIds) === count($fixtureUserIds) && array_diff($companyUserIds, $fixtureUserIds) === [],
    'Cleanup refused a company containing a non-fixture user.');
$ownsLeadName = fn (string $name): bool => preg_match('/^Synthetic CRM Prospect \d{3} '.preg_quote($fixture['owner_suffix'], '/').'$/D', $name) === 1
    || str_starts_with($name, "Synthetic CRM Polish {$fixture['owner_suffix']} ");
$leadRows = DB::table('crm_leads')->where('company_id', $company->id)->get(['id', 'name']);
$leadIds = $leadRows->pluck('id')->all();
$userIds = $users->pluck('id')->all();
$assert($leadRows->isNotEmpty() && $leadRows->every(fn ($row): bool => $ownsLeadName($row->name)), 'Cleanup refused a company containing a non-fixture lead.');
$assert(DB::table('crm_customers')->where('company_id', $company->id)->get(['name'])->every(fn ($row): bool => $ownsLeadName($row->name)),
    'Cleanup refused a company containing a non-fixture customer.');
$assert(DB::table('crm_activities')->where('company_id', $company->id)->whereNotIn('lead_id', $leadIds)->doesntExist()
    && DB::table('crm_activities')->where('company_id', $company->id)->whereNotIn('actor_id', $userIds)->doesntExist()
    && DB::table('crm_transfers')->where('company_id', $company->id)->whereNotIn('lead_id', $leadIds)->doesntExist()
    && DB::table('crm_transfers')->where('company_id', $company->id)->whereNotIn('sender_id', $userIds)->doesntExist()
    && DB::table('crm_transfers')->where('company_id', $company->id)->whereNotIn('recipient_id', $userIds)->doesntExist()
    && DB::table('crm_audit')->where('company_id', $company->id)->whereNotIn('actor_id', $userIds)->doesntExist(),
    'Cleanup refused a CRM audit row attributed to a user outside the fixture.');

$roleIds = $roleRows->pluck('id')->all();
DB::transaction(static function () use ($company, $roleIds, $userIds): void {
    DB::table('crm_audit')->where('company_id', $company->id)->delete();
    DB::table('crm_transfers')->where('company_id', $company->id)->delete();
    DB::table('crm_activities')->where('company_id', $company->id)->delete();
    DB::table('crm_leads')->where('company_id', $company->id)->update(['customer_id' => null]);
    DB::table('crm_leads')->where('company_id', $company->id)->delete();
    DB::table('crm_taxonomy')->where('company_id', $company->id)->delete();
    DB::table('crm_customers')->where('company_id', $company->id)->delete();
    DB::table('access_audit')->where('company_id', $company->id)->delete();
    DB::table('access_user_permissions')->whereIn('user_id', $userIds)->delete();
    DB::table('access_user_roles')->whereIn('user_id', $userIds)->delete();
    DB::table('access_role_permissions')->whereIn('role_id', $roleIds)->delete();
    DB::table('access_roles')->whereIn('id', $roleIds)->delete();
    DB::table('access_users')->whereIn('id', $userIds)->delete();
    DB::table('access_departments')->where('company_id', $company->id)->delete();
    DB::table('access_branches')->where('company_id', $company->id)->delete();
    DB::table('access_companies')->where('id', $company->id)->delete();
});

if (! unlink($path)) {
    throw new RuntimeException('CRM fixture data was removed, but its ignored credential file could not be deleted.');
}

echo "CRM synthetic browser fixture cleaned up by verified company ownership.\n";

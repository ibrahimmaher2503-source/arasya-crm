<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'arasya_employees_browser'
    || config('database.connections.mysql.host') !== '127.0.0.1'
    || (string) config('database.connections.mysql.port') !== '13418'
    || config('session.driver') !== 'file') {
    throw new RuntimeException('Employee fixtures require the isolated loopback Windows browser database and file sessions.');
}
$path = storage_path('framework/testing/employee-fixture.json');
$mode = $argv[1] ?? '';
if ($mode === 'seed') {
    $existing = null;
    if (is_file($path)) {
        $existing = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($existing)) {
            throw new RuntimeException('Existing employee fixture is not a JSON object.');
        }
        $existingCompany = $existing['company_id'] ?? null;
        if (! is_string($existingCompany) || ! Str::isUlid($existingCompany)
            || DB::table('access_companies')->where('id', $existingCompany)->value('name') !== 'Cycle02 Synthetic Browser') {
            throw new RuntimeException('Existing employee fixture ownership cannot be established.');
        }
    }
    $fixture = DB::transaction(function () use ($existing): array {
        if ($existing === null) {
            $company = (string) Str::ulid();
            $branch = (string) Str::ulid();
            $department = (string) Str::ulid();
            $otherBranch = (string) Str::ulid();
            $otherDepartment = (string) Str::ulid();
            DB::table('access_companies')->insert(['id' => $company, 'name' => 'Cycle02 Synthetic Browser', 'version' => 0]);
            foreach ([[$branch, $department, 'Main'], [$otherBranch, $otherDepartment, 'Other']] as [$b, $d, $name]) {
                DB::table('access_branches')->insert(['id' => $b, 'company_id' => $company, 'name' => $name.' branch', 'code' => strtolower($name), 'active' => true, 'version' => 0]);
                DB::table('access_departments')->insert(['id' => $d, 'company_id' => $company, 'branch_id' => $b, 'name' => $name.' department', 'code' => strtolower($name), 'active' => true, 'version' => 0]);
            }
            $result = ['company_id' => $company, 'branch_id' => $branch, 'department_id' => $department];
            foreach (['admin', 'supervisor', 'other', 'link'] as $kind) {
                $id = (string) Str::ulid();
                $password = Str::random(32).'!9a';
                $email = 'employee-'.$kind.'-'.strtolower($id).'@example.test';
                DB::table('access_users')->insert(['id' => $id, 'company_id' => $company, 'branch_id' => $kind === 'other' ? $otherBranch : $branch, 'department_id' => $kind === 'other' ? $otherDepartment : $department, 'name' => 'Synthetic '.$kind, 'email' => $email, 'password' => Hash::make($password), 'active' => true, 'is_admin' => $kind === 'admin', 'session_version' => 0, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $result[$kind] = ['id' => $id, 'email' => $email, 'password' => $password];
                if (in_array($kind, ['supervisor', 'other'], true)) {
                    $role = (string) Str::ulid();
                    DB::table('access_roles')->insert(['id' => $role, 'company_id' => $company, 'name' => 'Employee browser '.$kind, 'version' => 0]);
                    foreach (['employees.read', 'employees.write', 'employees.evidence.write', 'tasks.read', 'tasks.write'] as $permission) {
                        DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => $permission]);
                    }
                    DB::table('access_user_roles')->insert(['user_id' => $id, 'role_id' => $role]);
                }
            }
            $manager = (string) Str::ulid();
            DB::table('employee_profiles')->insert(['id' => $manager, 'company_id' => $company, 'branch_id' => $branch, 'department_id' => $department, 'manager_id' => null, 'user_id' => $result['supervisor']['id'], 'code' => 'BROWSER-MANAGER', 'name' => 'Synthetic supervisor', 'role' => 'Supervisor', 'hire_date' => '2026-01-01', 'status' => 'active', 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $result['manager_id'] = $manager;
            $result['other_branch_id'] = $otherBranch;
            $result['other_department_id'] = $otherDepartment;
        } else {
            $result = $existing;
            $company = (string) $result['company_id'];
            $result['other_branch_id'] ??= DB::table('access_branches')->where('company_id', $company)->where('code', 'other')->value('id');
            $result['other_department_id'] ??= DB::table('access_departments')->where('company_id', $company)->where('branch_id', $result['other_branch_id'])->where('code', 'other')->value('id');
            if (! is_string($result['branch_id'] ?? null) || ! is_string($result['department_id'] ?? null)
                || ! is_string($result['manager_id'] ?? null) || ! is_string($result['other_branch_id']) || ! is_string($result['other_department_id'])
                || ! DB::table('employee_profiles')->where('id', $result['manager_id'])->where('company_id', $company)->exists()) {
                throw new RuntimeException('Existing employee fixture is missing its owned organization or manager records.');
            }
        }

        $company = (string) $result['company_id'];
        $now = now();
        $ensureEmployee = static function (array $values) use ($company, $now): string {
            $existingEmployee = DB::table('employee_profiles')->where('company_id', $company)->where('code', $values['code'])->first();
            if ($existingEmployee !== null) {
                return (string) $existingEmployee->id;
            }

            $id = (string) Str::ulid();
            DB::table('employee_profiles')->insert([
                'id' => $id,
                'company_id' => $company,
                ...$values,
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $id;
        };

        for ($index = 1; $index <= 65; $index++) {
            $number = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $employeeId = $ensureEmployee([
                'branch_id' => $result['branch_id'],
                'department_id' => $result['department_id'],
                'manager_id' => $result['manager_id'],
                'user_id' => null,
                'code' => 'BROWSER-EMPLOYEE-'.$number,
                'name' => 'Synthetic employee '.$number,
                'role' => 'Technician',
                'hire_date' => '2026-01-01',
                'status' => $index === 65 ? 'on_leave' : 'active',
                'private_notes' => $index === 1 ? 'SYNTHETIC-PRIVATE-NOTE-EMPLOYEE' : null,
            ]);
            if ($index === 1) {
                $result['private_employee_id'] = $employeeId;
                if (DB::table('employee_profiles')->where('id', $employeeId)->whereNull('private_notes')->exists()) {
                    DB::table('employee_profiles')->where('id', $employeeId)->update(['private_notes' => 'SYNTHETIC-PRIVATE-NOTE-EMPLOYEE', 'updated_at' => $now]);
                }
            } elseif ($index === 2) {
                $result['peer_employee_id'] = $employeeId;
            }
        }

        $result['other_employee_id'] = $ensureEmployee([
            'branch_id' => $result['other_branch_id'],
            'department_id' => $result['other_department_id'],
            'manager_id' => null,
            'user_id' => null,
            'code' => 'BROWSER-BRANCH-OTHER',
            'name' => 'Synthetic employee in other branch',
            'role' => 'Technician',
            'hire_date' => '2026-01-01',
            'status' => 'active',
            'private_notes' => null,
        ]);

        return $result;
    });
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    if (file_put_contents($path, json_encode($fixture, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('Could not save ignored synthetic fixture credentials; retain the company for manual scoped cleanup.');
    }
    echo "Employee synthetic fixture seeded or confirmed; credentials remain in ignored storage only.\n";
    exit;
}
if (! is_file($path)) {
    throw new RuntimeException('Owned employee fixture is missing.');
}
$fixture = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$company = $fixture['company_id'];
if (! is_string($company) || ! Str::isUlid($company)
    || DB::table('access_companies')->where('id', $company)->value('name') !== 'Cycle02 Synthetic Browser') {
    throw new RuntimeException('Fixture ownership cannot be established.');
}
if ($mode === 'verify') {
    $profiles = DB::table('employee_profiles')->where('company_id', $company)->where('code', 'like', 'BROWSER-EMPLOYEE-000-%')->get();
    $directoryProfiles = DB::table('employee_profiles')->where('company_id', $company)->count();
    if ($directoryProfiles < 67 || $profiles->count() < 3 || DB::table('employee_evidence')->where('company_id', $company)->where('actor_id', $fixture['supervisor']['id'])->count() < 3) {
        throw new RuntimeException('The directory baseline, desktop/mobile workers, or supervisor evidence are missing.');
    }
    foreach (['employee-desktop', 'employee-tablet', 'employee-mobile'] as $project) {
        if (! DB::table('employee_profiles')->where('company_id', $company)->where('code', 'like', 'BROWSER-EMPLOYEE-000-'.$project.'-%')->where('name', 'like', 'Updated browser worker '.$project.'-%')->whereNull('user_id')->exists()
            || ! DB::table('employee_evidence')->where('company_id', $company)->where('actor_id', $fixture['supervisor']['id'])->where('note', 'like', 'Recorded browser evidence '.$project.'-%')->exists()) {
            throw new RuntimeException('Missing persisted employee edit or attributed evidence for '.$project);
        }
    }
    if (DB::table('employee_audit')->where('company_id', $company)->whereNull('actor_id')->exists()) {
        throw new RuntimeException('Unattributed employee audit row.');
    }
    echo json_encode(['server' => DB::selectOne('SELECT VERSION() AS version')->version, 'directory_profiles' => $directoryProfiles, 'workers' => $profiles->count(), 'evidence' => DB::table('employee_evidence')->where('company_id', $company)->count(), 'audit' => DB::table('employee_audit')->where('company_id', $company)->count()], JSON_THROW_ON_ERROR)."\n";
    exit;
}
if ($mode !== 'cleanup') {
    throw new RuntimeException('Pass seed, verify or cleanup.');
}
DB::transaction(function () use ($company): void {
    $users = DB::table('access_users')->where('company_id', $company)->pluck('id');
    $roles = DB::table('access_roles')->where('company_id', $company)->pluck('id');
    DB::table('employee_evidence')->where('company_id', $company)->delete();
    DB::table('employee_audit')->where('company_id', $company)->delete();
    DB::table('employee_profiles')->where('company_id', $company)->update(['manager_id' => null]);
    DB::table('employee_profiles')->where('company_id', $company)->delete();
    DB::table('tasks_cycle')->where('entity_id', $company)->delete();
    DB::table('access_audit')->where('company_id', $company)->delete();
    DB::table('access_user_permissions')->whereIn('user_id', $users)->delete();
    DB::table('access_user_roles')->whereIn('user_id', $users)->delete();
    DB::table('access_department_roles')->whereIn('role_id', $roles)->delete();
    DB::table('access_role_permissions')->whereIn('role_id', $roles)->delete();
    DB::table('access_roles')->where('company_id', $company)->delete();
    DB::table('access_users')->where('company_id', $company)->delete();
    DB::table('access_departments')->where('company_id', $company)->delete();
    DB::table('access_branches')->where('company_id', $company)->delete();
    DB::table('access_companies')->where('id', $company)->delete();
});
unlink($path);
echo "Removed only the owned Cycle02 synthetic fixture.\n";

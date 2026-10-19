<?php

declare(strict_types=1);

namespace App\Demo;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Generates a realistic, internally consistent Egyptian demo dataset for the
 * implemented Access / CRM / Employees / Tasks modules.
 *
 * Design guarantees:
 *  - Every row lives under one fixed synthetic company (config/demo.php), so it
 *    can be identified and purged without touching any other data.
 *  - Generation is seeded and deterministic; re-running rebuilds the same
 *    dataset instead of accumulating duplicates.
 *  - All foreign keys, scope columns (company/branch/department), enum values,
 *    encrypted contacts, duplicate fingerprints, task evidence rules and audit
 *    trails follow the invariants the application services enforce.
 *  - Seeding refuses to run in production.
 */
final class DemoSeeder
{
    /**
     * Departments per branch, in order. The first entry is the CRM department
     * that owns leads.
     *
     * @var list<array{name: string, head: string, member: string}>
     */
    private const DEPARTMENTS = [
        ['name' => 'المبيعات والتسويق', 'head' => 'sales_manager', 'member' => 'sales_rep'],
        ['name' => 'المشروعات والتنفيذ', 'head' => 'operations_manager', 'member' => 'field_supervisor'],
        ['name' => 'المالية والحسابات', 'head' => 'finance', 'member' => 'finance'],
        ['name' => 'الموارد البشرية', 'head' => 'hr_manager', 'member' => 'hr_manager'],
        ['name' => 'الجودة والسلامة', 'head' => 'field_supervisor', 'member' => 'field_supervisor'],
        ['name' => 'المخازن والتوريدات', 'head' => 'field_supervisor', 'member' => 'field_supervisor'],
    ];

    /**
     * Role templates keyed by template name. Values are limited to
     * AccessControl::PERMISSIONS.
     *
     * @var array<string, list<string>>
     */
    private const ROLES = [
        'general_manager' => ['organization.read', 'users.read', 'employees.read', 'employees.write', 'employees.manage_all', 'employee.private', 'crm.read', 'crm.manage', 'crm.write', 'crm.assign', 'crm.transfer', 'crm.convert', 'crm.taxonomy', 'contact.reveal', 'tasks.read', 'tasks.write', 'tasks.assist', 'audit.read', 'cycles.read'],
        'sales_manager' => ['organization.read', 'users.read', 'employees.read', 'crm.read', 'crm.manage', 'crm.write', 'crm.assign', 'crm.transfer', 'crm.convert', 'crm.taxonomy', 'contact.reveal', 'tasks.read', 'tasks.write', 'tasks.assist'],
        'sales_rep' => ['organization.read', 'crm.read', 'crm.write', 'tasks.read', 'tasks.write'],
        'operations_manager' => ['organization.read', 'employees.read', 'employees.write', 'employees.manage_all', 'crm.read', 'tasks.read', 'tasks.write', 'tasks.assist'],
        'hr_manager' => ['organization.read', 'users.read', 'employees.read', 'employees.write', 'employees.manage_all', 'employees.evidence.write', 'employee.private', 'tasks.read', 'tasks.write'],
        'field_supervisor' => ['organization.read', 'employees.read', 'employees.write', 'employees.evidence.write', 'crm.read', 'tasks.read', 'tasks.write', 'tasks.assist'],
        'finance' => ['organization.read', 'crm.read', 'audit.read', 'employees.read'],
        'auditor' => ['organization.read', 'users.read', 'roles.read', 'audit.read', 'crm.read', 'employees.read', 'tasks.read'],
        'access_admin' => ['organization.read', 'organization.write', 'users.read', 'users.write', 'roles.read', 'roles.write', 'audit.read'],
    ];

    private const ROLE_LABELS = [
        'general_manager' => 'المدير العام',
        'sales_manager' => 'مدير مبيعات',
        'sales_rep' => 'مندوب مبيعات',
        'operations_manager' => 'مدير مشروعات',
        'hr_manager' => 'مدير موارد بشرية',
        'field_supervisor' => 'مشرف ميداني',
        'finance' => 'محاسب',
        'auditor' => 'مراجع داخلي',
        'access_admin' => 'مسؤول صلاحيات',
    ];

    /** @var list<string> */
    private const TASK_TITLES = [
        'متابعة عرض سعر لخامات تبطين', 'معاينة موقع أحواض الاستزراع', 'تركيب رول تبطين HDPE',
        'لحام غشاء بولي إيثيلين', 'تحصيل دفعة مستحقة', 'زيارة ميدانية لمزرعة سمكية',
        'إعداد تقرير جودة للتنفيذ', 'رد على استفسار عميل', 'تجهيز أمر تسليم للموقع',
        'مراجعة عقد مشروع تبطين', 'حصر كميات مشروع', 'جدولة حملة تسويق رقمي',
        'تسوية حساب مورد', 'متابعة شكوى عميل', 'تدريب فنيي اللحام',
    ];

    private const ACTIVITY_KINDS = ['call', 'message', 'meeting', 'visit', 'note', 'quotation'];

    private const SOURCE_NAMES = ['موقع إلكتروني', 'معرض', 'إحالة عميل', 'واتساب', 'مكالمة مباشرة', 'فيسبوك', 'معرض زراعي', 'توصية', 'بريد إلكتروني'];

    private const ADMIN_EMAIL = 'demo.admin@example.test';

    private const SALES_EMAIL = 'demo.sales@example.test';

    private const EVIDENCE_TEXT = "ملاحظة تنفيذ تجريبية للعرض المصري فقط؛ ليست إثبات عمل حقيقي.\n";

    private string $companyId = '';

    private string $companyName = '';

    /** @var array{branches: int, departments_per_branch: int, users_per_department: int, leads: int, employees: int, tasks: int, transfers: int, history_months: int} */
    private array $size;

    private EgyptianData $data;

    private DateTimeImmutable $windowStart;

    private DateTimeImmutable $now;

    /** @var array<string, string|null> */
    private array $evidenceBackups = [];

    public function __construct(int $seed)
    {
        $this->data = new EgyptianData($seed);
    }

    /**
     * @return array{size: string, company_id: string, counts: array<string, int>, credentials: array<string, array{email: string, password: string, role: string}>}
     */
    public function seed(string $size, bool $force = false): array
    {
        $this->assertEnvironmentAllowed($force);

        $preset = config('demo.sizes.'.$size);
        if (! is_array($preset)) {
            throw new RuntimeException("Unknown demo size [{$size}]; choose small, medium, or large.");
        }

        $this->companyId = (string) config('demo.company_id');
        $this->companyName = (string) config('demo.company_name');
        $this->size = [
            'branches' => $this->presetInt($preset, 'branches'),
            'departments_per_branch' => $this->presetInt($preset, 'departments_per_branch'),
            'users_per_department' => $this->presetInt($preset, 'users_per_department'),
            'leads' => $this->presetInt($preset, 'leads'),
            'employees' => $this->presetInt($preset, 'employees'),
            'tasks' => $this->presetInt($preset, 'tasks'),
            'transfers' => $this->presetInt($preset, 'transfers'),
            'history_months' => $this->presetInt($preset, 'history_months'),
        ];
        $timezone = (string) config('app.timezone', 'UTC');
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $zone = DB::selectOne("SELECT IF(@@session.time_zone = 'SYSTEM', @@system_time_zone, @@session.time_zone) AS zone");
            if (! is_string($zone->zone ?? null)) {
                throw new RuntimeException('Cannot determine the database session timezone for demo timestamps.');
            }
            $timezone = $zone->zone;
        }
        // TIMESTAMP columns interpret wall times in the connection zone, including Cairo's DST gaps.
        $this->now = new DateTimeImmutable('now', new DateTimeZone($timezone));
        $this->windowStart = $this->now->modify('-'.$this->size['history_months'].' months');

        $result = $this->withEvidenceTransaction(function (): array {
            $this->purgeRows();

            return $this->build();
        });

        return [
            'size' => $size,
            'company_id' => $this->companyId,
            'counts' => $result['counts'],
            'credentials' => $result['credentials'],
        ];
    }

    /**
     * Remove only rows that belong to the synthetic demo company, children
     * first so no `restrictOnDelete` foreign key is ever violated.
     *
     * @return array{removed: int}
     */
    public function purge(bool $force = false): array
    {
        $this->assertEnvironmentAllowed($force);

        return $this->withEvidenceTransaction(fn (): array => $this->purgeRows());
    }

    /**
     * @template T of array
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    private function withEvidenceTransaction(Closure $operation): array
    {
        $this->evidenceBackups = [];
        try {
            return DB::transaction($operation);
        } catch (Throwable $exception) {
            $this->restoreEvidence($exception);
            throw $exception;
        } finally {
            $this->evidenceBackups = [];
        }
    }

    private function restoreEvidence(Throwable $exception): void
    {
        foreach ($this->evidenceBackups as $path => $content) {
            $restored = $content === null
                ? Storage::disk('local')->delete($path)
                : Storage::disk('local')->put($path, $content);
            if (! $restored) {
                throw new RuntimeException('Demo database rolled back but evidence restoration failed.', previous: $exception);
            }
        }
    }

    private function rememberEvidence(string $path): void
    {
        if (! array_key_exists($path, $this->evidenceBackups)) {
            $disk = Storage::disk('local');
            $this->evidenceBackups[$path] = $disk->exists($path) ? $disk->get($path) : null;
        }
    }

    /** @return array{removed: int} */
    private function purgeRows(): array
    {
        $companyId = (string) config('demo.company_id');
        $company = DB::table('access_companies')->where('id', $companyId)->first();
        if ($company === null) {
            return ['removed' => 0];
        }
        if ((string) $company->name !== (string) config('demo.company_name')) {
            throw new RuntimeException('Refusing to purge: the demo company id is in use by a different company.');
        }

        $removed = 0;
        $taskIds = DB::table('tasks_cycle')->where('entity_id', $companyId)->pluck('id')->all();
        $userIds = DB::table('access_users')->where('company_id', $companyId)->pluck('id')->all();
        $roleIds = DB::table('access_roles')->where('company_id', $companyId)->pluck('id')->all();

        foreach (DB::table('tasks_cycle_evidence')->whereIn('task_id', $taskIds)->get() as $evidence) {
            $allowed = array_map(fn (string $extension): string => 'task-evidence/'.$evidence->task_id.'/'.$evidence->id.'.'.$extension, ['pdf', 'png', 'jpg', 'txt']);
            if (in_array($evidence->path, $allowed, true)) {
                $this->rememberEvidence($evidence->path);
                if (! Storage::disk('local')->delete($evidence->path)) {
                    throw new RuntimeException('Could not remove owned demo evidence.');
                }
            }
        }

        $removed += DB::table('crm_audit')->where('company_id', $companyId)->delete();
        $removed += DB::table('crm_transfers')->where('company_id', $companyId)->delete();
        $removed += DB::table('crm_activities')->where('company_id', $companyId)->delete();

        $removed += DB::table('employee_evidence')->where('company_id', $companyId)->delete();
        $removed += DB::table('employee_audit')->where('company_id', $companyId)->delete();
        DB::table('employee_profiles')->where('company_id', $companyId)->update(['manager_id' => null]);
        $removed += DB::table('employee_profiles')->where('company_id', $companyId)->delete();

        if ($taskIds !== []) {
            $removed += DB::table('tasks_cycle_evidence')->whereIn('task_id', $taskIds)->delete();
            $removed += DB::table('tasks_cycle_audit')->whereIn('task_id', $taskIds)->delete();
            $removed += DB::table('tasks_cycle')->whereIn('id', $taskIds)->delete();
        }

        $removed += DB::table('crm_leads')->where('company_id', $companyId)->delete();
        $removed += DB::table('crm_customers')->where('company_id', $companyId)->delete();
        $removed += DB::table('crm_taxonomy')->where('company_id', $companyId)->delete();

        if ($userIds !== []) {
            $removed += DB::table('access_user_roles')->whereIn('user_id', $userIds)->delete();
            $removed += DB::table('access_user_permissions')->whereIn('user_id', $userIds)->delete();
        }
        if ($roleIds !== []) {
            $removed += DB::table('access_user_roles')->whereIn('role_id', $roleIds)->delete();
        }
        $removed += DB::table('access_department_roles')->where('company_id', $companyId)->delete();
        $removed += DB::table('access_audit')->where('company_id', $companyId)->delete();
        $removed += DB::table('access_users')->where('company_id', $companyId)->delete();
        $removed += DB::table('access_departments')->where('company_id', $companyId)->delete();
        $removed += DB::table('access_branches')->where('company_id', $companyId)->delete();
        if ($roleIds !== []) {
            $removed += DB::table('access_role_permissions')->whereIn('role_id', $roleIds)->delete();
        }
        $removed += DB::table('access_roles')->where('company_id', $companyId)->delete();
        $removed += DB::table('access_companies')->where('id', $companyId)->delete();

        return ['removed' => $removed];
    }

    /** @param array<mixed, mixed> $preset */
    private function presetInt(array $preset, string $key): int
    {
        $value = $preset[$key] ?? null;
        if (! is_int($value) || $value < 1) {
            throw new RuntimeException("Demo preset [{$key}] must be a positive integer.");
        }

        return $value;
    }

    public function assertEnvironmentAllowed(bool $force): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo seeding is forbidden in production.');
        }
        if (! app()->environment(['local', 'testing']) && ! $force) {
            throw new RuntimeException('Demo seeding outside local/testing requires the explicit --force flag.');
        }
    }

    /**
     * @return array{counts: array<string, int>, credentials: array<string, array{email: string, password: string, role: string}>}
     */
    private function build(): array
    {
        DB::table('access_companies')->insert([
            'id' => $this->companyId,
            'name' => $this->companyName,
            'version' => 0,
        ]);

        $roles = $this->insertRoles();
        $directory = $this->insertDirectory($roles);
        $taxonomy = $this->insertTaxonomy();
        $this->insertCrm($directory['salesUsers'], $taxonomy);
        $tasks = $this->insertTasks($directory['allUsers']);
        $this->insertEmployees($directory['allUsers'], $tasks, $directory['departmentIds']);
        $this->insertAccessAudit($directory['allUsers'], $directory['branchIds'], $directory['departmentIds'], array_values($roles));

        return ['counts' => $this->counts(), 'credentials' => $directory['credentials']];
    }

    /**
     * @return array<string, string> template => role id
     */
    private function insertRoles(): array
    {
        $rows = [];
        $permissions = [];
        $ids = [];
        foreach (self::ROLES as $template => $grants) {
            $roleId = $this->data->ulid();
            $ids[$template] = $roleId;
            $rows[] = [
                'id' => $roleId,
                'company_id' => $this->companyId,
                'name' => self::ROLE_LABELS[$template],
                'version' => 0,
            ];
            foreach ($grants as $permission) {
                $permissions[] = ['role_id' => $roleId, 'permission' => $permission];
            }
        }
        DB::table('access_roles')->insert($rows);
        DB::table('access_role_permissions')->insert($permissions);

        return $ids;
    }

    /**
     * @param  array<string, string>  $roles
     * @return array{salesUsers: list<array{id: string, branch_id: string, department_id: string}>, allUsers: list<array{id: string, branch_id: string, department_id: string}>, branchIds: list<string>, departmentIds: list<string>, credentials: array<string, array{email: string, password: string, role: string}>}
     */
    private function insertDirectory(array $roles): array
    {
        $branchRows = [];
        $departmentRows = [];
        $userRows = [];
        $userRoleRows = [];
        $userPermissionRows = [];
        $departmentRoleRows = [];
        $branchIds = [];
        $departmentIds = [];
        $salesUsers = [];
        $allUsers = [];
        $credentials = [];

        $branchCount = $this->size['branches'];
        $departmentCount = $this->size['departments_per_branch'];
        $usersPerDepartment = $this->size['users_per_department'];
        $now = $this->windowStart->format('Y-m-d H:i:s');

        for ($branchIndex = 0; $branchIndex < $branchCount; $branchIndex++) {
            $location = $this->data->location();
            $branchId = $this->data->ulid();
            $branchIds[] = $branchId;
            $branchRows[] = [
                'id' => $branchId,
                'company_id' => $this->companyId,
                'name' => 'فرع '.$location['city'].' — '.$location['governorate'],
                'code' => 'BR-'.str_pad((string) ($branchIndex + 1), 2, '0', STR_PAD_LEFT),
                'active' => true,
                'version' => 0,
            ];

            for ($departmentIndex = 0; $departmentIndex < $departmentCount; $departmentIndex++) {
                $template = self::DEPARTMENTS[$departmentIndex];
                $departmentId = $this->data->ulid();
                $departmentIds[] = $departmentId;
                $departmentRows[] = [
                    'id' => $departmentId,
                    'company_id' => $this->companyId,
                    'branch_id' => $branchId,
                    'name' => $template['name'].' - '.$location['city'],
                    'code' => 'DEPT-'.str_pad((string) ($branchIndex + 1), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) ($departmentIndex + 1), 2, '0', STR_PAD_LEFT),
                    'active' => true,
                    'version' => 0,
                ];
                $departmentRoleRows[] = [
                    'company_id' => $this->companyId,
                    'branch_id' => $branchId,
                    'department_id' => $departmentId,
                    'role_id' => $roles[$template['head']],
                ];

                for ($userIndex = 0; $userIndex < $usersPerDepartment; $userIndex++) {
                    $isHead = $userIndex === 0;
                    $isAdmin = $branchIndex === 0 && $departmentIndex === 0 && $isHead;
                    $isSalesDemo = $branchIndex === 0 && $departmentIndex === 0 && $userIndex === 1;
                    $roleTemplate = $isHead ? $template['head'] : $template['member'];

                    $userId = $this->data->ulid();
                    $email = $isAdmin ? self::ADMIN_EMAIL : ($isSalesDemo ? self::SALES_EMAIL : 'user-'.mb_strtolower($this->data->ulid()).'@example.test');
                    $password = ($isAdmin || $isSalesDemo) ? 'Demo!'.Str::random(16) : Str::random(32);
                    $name = $isAdmin ? 'المدير العام — الأراسية' : $this->data->contactName();

                    $userRows[] = [
                        'id' => $userId,
                        'company_id' => $this->companyId,
                        'branch_id' => $branchId,
                        'department_id' => $departmentId,
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make($password),
                        'active' => true,
                        'is_admin' => $isAdmin,
                        'session_version' => 0,
                        'version' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $userRoleRows[] = ['user_id' => $userId, 'role_id' => $roles[$isAdmin ? 'general_manager' : $roleTemplate]];
                    if ($isSalesDemo) {
                        // Give the demo sales user the manager role so it carries contact.reveal,
                        // plus an explicit audit.read override to exercise the override table.
                        $userRoleRows[] = ['user_id' => $userId, 'role_id' => $roles['sales_manager']];
                        $userPermissionRows[] = ['user_id' => $userId, 'permission' => 'audit.read', 'allowed' => true];
                    }

                    $record = ['id' => $userId, 'branch_id' => $branchId, 'department_id' => $departmentId];
                    $allUsers[] = $record;
                    if ($departmentIndex === 0) {
                        $salesUsers[] = $record;
                    }

                    if ($isAdmin) {
                        $credentials['admin'] = ['email' => $email, 'password' => $password, 'role' => 'المدير العام'];
                    } elseif ($isSalesDemo) {
                        $credentials['sales'] = ['email' => $email, 'password' => $password, 'role' => 'مدير مبيعات'];
                    }
                }
            }
        }

        DB::table('access_branches')->insert($branchRows);
        DB::table('access_departments')->insert($departmentRows);
        DB::table('access_users')->insert($userRows);
        DB::table('access_user_roles')->insert($userRoleRows);
        DB::table('access_department_roles')->insert($departmentRoleRows);
        if ($userPermissionRows !== []) {
            DB::table('access_user_permissions')->insert($userPermissionRows);
        }

        return [
            'salesUsers' => $salesUsers,
            'allUsers' => $allUsers,
            'branchIds' => $branchIds,
            'departmentIds' => $departmentIds,
            'credentials' => $credentials,
        ];
    }

    /**
     * @return array{stages: array<string, string>, outcomes: array<string, string>}
     */
    private function insertTaxonomy(): array
    {
        $stages = [];
        $outcomes = [];
        $rows = [];
        foreach ($this->data->stages() as $index => $label) {
            $id = $this->data->ulid();
            $stages[$label] = $id;
            $rows[] = ['id' => $id, 'company_id' => $this->companyId, 'kind' => 'stage', 'label' => $label, 'replaces_id' => null, 'created_at' => $this->hoursAfter($index + 1)];
        }
        foreach ($this->data->outcomes() as $index => $label) {
            $id = $this->data->ulid();
            $outcomes[$label] = $id;
            $rows[] = ['id' => $id, 'company_id' => $this->companyId, 'kind' => 'outcome', 'label' => $label, 'replaces_id' => null, 'created_at' => $this->hoursAfter($index + 1)];
        }
        DB::table('crm_taxonomy')->insert($rows);

        return ['stages' => $stages, 'outcomes' => $outcomes];
    }

    /**
     * @param  list<array{id: string, branch_id: string, department_id: string}>  $salesUsers
     * @param  array{stages: array<string, string>, outcomes: array<string, string>}  $taxonomy
     */
    private function insertCrm(array $salesUsers, array $taxonomy): void
    {
        $leadCount = $this->size['leads'];
        $stageValues = array_values($taxonomy['stages']);
        $outcomeLabels = array_keys($taxonomy['outcomes']);
        $outcomeIds = array_values($taxonomy['outcomes']);

        $organizations = [];
        $organizationCount = max(8, (int) ceil($leadCount / 6));
        for ($index = 0; $index < $organizationCount; $index++) {
            $organizations[] = $this->data->organization();
        }
        $organizations = array_values(array_unique($organizations));
        $preferredOrganizationMax = max(0, intdiv(count($organizations), 2) - 1);

        $customers = [];
        $customerRows = [];
        $leadRows = [];
        $activityRows = [];
        $auditRows = [];
        $followUpTaskRows = [];
        $followUpTaskAuditRows = [];

        for ($index = 0; $index < $leadCount; $index++) {
            $organization = $this->data->int(1, 100) <= 60
                ? $organizations[$this->data->int(0, $preferredOrganizationMax)]
                : $organizations[$this->data->int(0, count($organizations) - 1)];
            $assignee = $salesUsers[$this->data->int(0, count($salesUsers) - 1)];
            $createdAt = $this->pastDate($this->windowStart, $this->now, true);
            $stageId = $stageValues[$this->data->int(0, count($stageValues) - 1)];
            $priority = $this->data->weighted(['normal' => 60, 'high' => 25, 'low' => 15]);
            $isAssigned = $this->data->int(1, 100) <= 82;
            $isConverted = $this->data->int(1, 100) <= 32;
            $isArchived = ! $isConverted && $this->data->int(1, 100) <= 10;

            $email = 'lead-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT).'@example.test';
            $contacts = [
                'email' => $email,
                'phone' => $this->data->mobile(),
                'whatsapp' => $this->data->int(1, 100) <= 70 ? $this->data->mobile() : null,
            ];

            $customerId = null;
            $leadName = $this->data->contactName();
            $duplicateKey = $this->fingerprint($email);
            if ($isConverted) {
                if (! isset($customers[$duplicateKey])) {
                    $customerId = $this->data->ulid();
                    $customers[$duplicateKey] = $customerId;
                    $customerRows[] = [
                        'id' => $customerId,
                        'company_id' => $this->companyId,
                        'branch_id' => $assignee['branch_id'],
                        'department_id' => $assignee['department_id'],
                        'name' => $leadName,
                        'organization' => $organization,
                        'duplicate_key' => $this->fingerprint($email),
                        'created_at' => $createdAt->format('Y-m-d H:i:s'),
                    ];
                } else {
                    $customerId = $customers[$duplicateKey];
                }
            }

            $leadId = $this->data->ulid();
            $leadIndex = count($leadRows);
            $leadRows[] = [
                'id' => $leadId,
                'company_id' => $this->companyId,
                'branch_id' => $assignee['branch_id'],
                'department_id' => $assignee['department_id'],
                'name' => $leadName,
                'organization' => $organization,
                'source' => self::SOURCE_NAMES[$this->data->int(0, count(self::SOURCE_NAMES) - 1)],
                'contacts' => Crypt::encryptString(json_encode($contacts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
                'duplicate_key' => $this->fingerprint($email),
                'consent' => $this->data->int(1, 100) <= 80,
                'preferences' => json_encode(['email' => $this->data->int(0, 1) === 1, 'phone' => true, 'whatsapp' => $this->data->int(0, 1) === 1], JSON_THROW_ON_ERROR),
                'notes' => $this->data->int(1, 100) <= 40 ? 'ملاحظة: عميل مهتم بخامات التبطين لمشروع زراعي.' : null,
                'stage_id' => $stageId,
                'priority' => $priority,
                'assignee_id' => $isAssigned ? $assignee['id'] : null,
                'customer_id' => $customerId,
                'archived' => $isArchived,
                'version' => $isConverted ? 2 : ($isAssigned ? 1 : 0),
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'updated_at' => $createdAt->format('Y-m-d H:i:s'),
            ];

            $auditRows[] = $this->crmAudit($leadId, $assignee['id'], 'lead.created', $createdAt, ['fields' => ['name', 'organization', 'source', 'contacts'], 'version' => 0]);
            $version = 0;
            $lastEventAt = $createdAt;
            if ($isAssigned) {
                $assignedAt = $this->after($createdAt, 5);
                $auditRows[] = $this->crmAudit($leadId, $assignee['id'], 'lead.assigned', $assignedAt, ['fields' => ['assignee_id'], 'version' => 1]);
                $version++;
                $lastEventAt = $assignedAt;
            }

            $activityCount = (int) $this->data->weighted([0 => 10, 1 => 35, 2 => 30, 3 => 18, 4 => 7]);
            for ($activityIndex = 0; $activityIndex < $activityCount; $activityIndex++) {
                $occurred = $this->pastDate($lastEventAt, $this->now, true);
                $outcomeIndex = $this->data->int(0, count($outcomeLabels) - 1);
                $followUpRoll = $this->data->int(1, 100);
                $followUp = $followUpRoll <= 40
                    ? $this->now->modify('+'.$this->data->int(1, 30).' days')
                    : ($followUpRoll <= 70 ? $this->pastDate($createdAt, $this->now, true) : null);
                $activityId = $this->data->ulid();
                $followUpTaskId = $followUp === null ? null : $this->insertFollowUpTask(
                    $assignee,
                    $activityId,
                    $occurred,
                    $followUp,
                    $followUpTaskRows,
                    $followUpTaskAuditRows,
                );
                $activityRows[] = [
                    'id' => $activityId,
                    'company_id' => $this->companyId,
                    'lead_id' => $leadId,
                    'actor_id' => $assignee['id'],
                    'kind' => self::ACTIVITY_KINDS[$this->data->int(0, count(self::ACTIVITY_KINDS) - 1)],
                    'outcome_id' => $outcomeIds[$outcomeIndex],
                    'outcome_label' => $outcomeLabels[$outcomeIndex],
                    'note' => $this->data->int(1, 100) <= 50 ? 'تم مناقشة المواصفات الفنية والكميات المطلوبة.' : null,
                    'next_step' => 'متابعة العميل وتأكيد الطلب',
                    'occurred_at' => $occurred->format('Y-m-d H:i:s'),
                    'next_follow_up' => $followUp?->format('Y-m-d H:i:s'),
                    'task_id' => $followUpTaskId,
                    'created_at' => $occurred->format('Y-m-d H:i:s'),
                ];
                $auditRows[] = $this->crmAudit($leadId, $assignee['id'], 'lead.activity.recorded', $occurred, ['activity_id' => $activityId, 'task_id' => $followUpTaskId, 'version' => ++$version]);
                $lastEventAt = $occurred;
            }
            if ($isConverted) {
                $lastEventAt = $this->after($lastEventAt, 1);
                $auditRows[] = $this->crmAudit($leadId, $assignee['id'], 'lead.converted', $lastEventAt, ['customer_id' => $customerId, 'version' => ++$version]);
            }
            if ($isArchived) {
                $lastEventAt = $this->after($lastEventAt, 1);
                $auditRows[] = $this->crmAudit($leadId, $assignee['id'], 'lead.archived', $lastEventAt, ['fields' => ['archived'], 'version' => ++$version]);
            }
            $leadRows[$leadIndex]['version'] = $version;
            $leadRows[$leadIndex]['updated_at'] = $lastEventAt->format('Y-m-d H:i:s');
        }

        $transferRows = $this->buildTransfers($leadRows, $salesUsers, $auditRows, $this->size['transfers']);

        if ($customerRows !== []) {
            $this->insertChunked('crm_customers', $customerRows, 50);
        }
        $this->insertChunked('crm_leads', $leadRows, 40);
        if ($followUpTaskRows !== []) {
            $this->insertChunked('tasks_cycle', $followUpTaskRows, 40);
            $this->insertChunked('tasks_cycle_audit', $followUpTaskAuditRows, 60);
        }
        if ($activityRows !== []) {
            $this->insertChunked('crm_activities', $activityRows, 50);
        }
        if ($transferRows !== []) {
            $this->insertChunked('crm_transfers', $transferRows, 50);
        }
        $this->insertChunked('crm_audit', $auditRows, 80);
    }

    /**
     * @param  array{id: string, department_id: string}  $assignee
     * @param  list<array<string, mixed>>  $taskRows
     * @param  list<array<string, mixed>>  $taskAuditRows
     */
    private function insertFollowUpTask(
        array $assignee,
        string $activityId,
        DateTimeImmutable $occurred,
        DateTimeImmutable $followUp,
        array &$taskRows,
        array &$taskAuditRows,
    ): string {
        $taskId = $this->data->ulid();
        $title = 'متابعة عميل من CRM';
        $dueAt = $followUp->format('Y-m-d H:i:s');
        $taskRows[] = [
            'id' => $taskId,
            'entity_id' => $this->companyId,
            'department_id' => $assignee['department_id'],
            'owner_id' => $assignee['id'],
            'assignee_id' => $assignee['id'],
            'title' => $title,
            'priority' => 'normal',
            'due_at' => $dueAt,
            'status' => 'pending',
            'version' => 0,
            'create_key_hash' => hash('sha256', $this->companyId.'|'.$assignee['id'].'|'.$assignee['department_id'].'|crm-follow-up|'.$activityId),
            'create_payload_hash' => hash('sha256', json_encode([
                'title' => $title,
                'priority' => 'normal',
                'due_at' => $dueAt,
                'assignee_id' => $assignee['id'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
            'created_at' => $occurred->format('Y-m-d H:i:s'),
            'updated_at' => $occurred->format('Y-m-d H:i:s'),
        ];
        $taskAuditRows[] = $this->taskAudit(
            $taskId,
            $assignee['id'],
            'create',
            'crm-follow-up-'.$activityId,
            $occurred,
            ['status' => 'pending', 'version' => 0],
        );

        return $taskId;
    }

    /**
     * @param  list<array<string, mixed>>  $leads
     * @param  list<array{id: string, branch_id: string, department_id: string}>  $salesUsers
     * @param  list<array<string, mixed>>  $auditRows  appended to in place
     * @return list<array<string, mixed>>
     */
    private function buildTransfers(array &$leads, array $salesUsers, array &$auditRows, int $transferCount): array
    {
        $eligible = [];
        foreach ($leads as $index => $lead) {
            if ($lead['assignee_id'] !== null && ! (bool) $lead['archived']) {
                $eligible[] = $index;
            }
        }
        if ($eligible === [] || $transferCount <= 0) {
            return [];
        }

        $rows = [];
        $used = [];
        $attempts = 0;
        $maxAttempts = max(20, $transferCount * 10);
        while (count($rows) < $transferCount && $attempts < $maxAttempts) {
            $attempts++;
            $leadIndex = $eligible[$this->data->int(0, count($eligible) - 1)];
            $lead = $leads[$leadIndex];
            if (isset($used[$lead['id']])) {
                continue;
            }
            $recipient = null;
            foreach ($salesUsers as $candidate) {
                if ($candidate['id'] !== $lead['assignee_id'] && $candidate['branch_id'] === $lead['branch_id'] && $candidate['department_id'] === $lead['department_id']) {
                    $recipient = $candidate;
                    break;
                }
            }
            if ($recipient === null) {
                continue;
            }
            $used[$lead['id']] = true;
            $senderId = (string) $lead['assignee_id'];
            $state = $this->data->weighted(['pending' => 40, 'accepted' => 35, 'rejected' => 25]);
            $requestedAt = $this->pastDate(new DateTimeImmutable((string) $lead['updated_at'], $this->now->getTimezone()), $this->now, true);
            $respondedAt = $state === 'pending' ? null : $this->after($requestedAt, 3);
            $transferId = $this->data->ulid();
            $requestedVersion = (int) $lead['version'] + 1;
            $lead['version'] = $requestedVersion;
            $lead['updated_at'] = $requestedAt->format('Y-m-d H:i:s');
            $rows[] = [
                'id' => $transferId,
                'company_id' => $this->companyId,
                'lead_id' => $lead['id'],
                'sender_id' => $senderId,
                'recipient_id' => $recipient['id'],
                'context' => 'تحويل متابعة العميل لاستكمال التفاوض في نفس الفرع.',
                'state' => $state,
                'requested_version' => $requestedVersion,
                'created_at' => $requestedAt->format('Y-m-d H:i:s'),
                'responded_at' => $respondedAt?->format('Y-m-d H:i:s'),
            ];
            $auditRows[] = $this->crmAudit($lead['id'], $senderId, 'lead.transfer.requested', $requestedAt, [
                'transfer_id' => $transferId,
                'sender_id' => $senderId,
                'recipient_id' => $recipient['id'],
                'version' => $requestedVersion,
            ]);
            if ($state !== 'pending' && $respondedAt !== null) {
                $responseVersion = $requestedVersion + 1;
                $lead['version'] = $responseVersion;
                $lead['updated_at'] = $respondedAt->format('Y-m-d H:i:s');
                if ($state === 'accepted') {
                    $lead['assignee_id'] = $recipient['id'];
                }
                $auditRows[] = $this->crmAudit($lead['id'], $recipient['id'], 'lead.transfer.'.$state, $respondedAt, [
                    'transfer_id' => $transferId,
                    'sender_id' => $senderId,
                    'recipient_id' => $recipient['id'],
                    'version' => $responseVersion,
                ]);
            }
            $leads[$leadIndex] = $lead;
        }

        return $rows;
    }

    /**
     * @param  list<array{id: string, branch_id: string, department_id: string}>  $allUsers
     * @return array{ids: list<string>}
     */
    private function insertTasks(array $allUsers): array
    {
        $taskCount = $this->size['tasks'];
        $rows = [];
        $auditRows = [];
        $evidenceRows = [];
        $taskIds = [];

        for ($index = 0; $index < $taskCount; $index++) {
            $assignee = $allUsers[$this->data->int(0, count($allUsers) - 1)];
            $createdAt = $this->pastDate($this->windowStart, $this->now, true);
            $status = $this->data->weighted(['pending' => 45, 'in_progress' => 30, 'completed' => 25]);
            $hasEvidence = $status === 'completed' || ($status === 'in_progress' && $this->data->int(1, 100) <= 40);
            $priority = $this->data->weighted(['normal' => 60, 'high' => 25, 'low' => 15]);
            $taskId = $this->data->ulid();
            $dueAt = $this->data->int(1, 100) <= 80 ? $createdAt->modify('+'.$this->data->int(3, 30).' days') : null;
            $version = match ($status) {
                'pending' => 0,
                'in_progress' => $hasEvidence ? 2 : 1,
                default => 3,
            };
            $rows[] = [
                'id' => $taskId,
                'entity_id' => $this->companyId,
                'department_id' => $assignee['department_id'],
                'owner_id' => $assignee['id'],
                'assignee_id' => $assignee['id'],
                'title' => self::TASK_TITLES[$this->data->int(0, count(self::TASK_TITLES) - 1)],
                'priority' => $priority,
                'due_at' => $dueAt?->format('Y-m-d H:i:s'),
                'status' => $status,
                'version' => $version,
                'create_key_hash' => hash('sha256', $this->companyId.'|'.$assignee['id'].'|'.$assignee['department_id'].'|demo-task-'.$index),
                'create_payload_hash' => hash('sha256', 'demo-payload-'.$index),
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'updated_at' => $this->after($createdAt, $version)->format('Y-m-d H:i:s'),
            ];
            $taskIds[] = $taskId;

            $auditRows[] = $this->taskAudit($taskId, $assignee['id'], 'create', 'create-'.$index, $createdAt, ['status' => 'pending', 'version' => 0]);
            if ($status !== 'pending') {
                $startedAt = $this->after($createdAt, 1);
                $auditRows[] = $this->taskAudit($taskId, $assignee['id'], 'start', 'start-'.$index, $startedAt, ['from' => 'pending', 'to' => 'in_progress', 'version' => 1]);
            }
            if ($status === 'completed') {
                $evidenceId = $this->data->ulid();
                $evidenceAt = $this->after($createdAt, 2);
                $evidenceRows[] = [
                    'id' => $evidenceId,
                    'task_id' => $taskId,
                    'path' => 'task-evidence/'.$taskId.'/'.$evidenceId.'.txt',
                    'name' => 'تقرير-تنفيذ-تجريبي-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT).'.txt',
                    'mime' => 'text/plain',
                    'size' => strlen(self::EVIDENCE_TEXT),
                    'created_at' => $evidenceAt->format('Y-m-d H:i:s'),
                ];
                $auditRows[] = $this->taskAudit($taskId, $assignee['id'], 'evidence', 'evidence-'.$index, $evidenceAt, ['evidence_id' => $evidenceId, 'mime' => 'text/plain', 'version' => 2]);
                $completedAt = $this->after($evidenceAt, 1);
                $auditRows[] = $this->taskAudit($taskId, $assignee['id'], 'complete', 'complete-'.$index, $completedAt, ['from' => 'in_progress', 'to' => 'completed', 'version' => $version]);
            } elseif ($hasEvidence) {
                $evidenceId = $this->data->ulid();
                $evidenceAt = $this->after($createdAt, 2);
                $evidenceRows[] = [
                    'id' => $evidenceId,
                    'task_id' => $taskId,
                    'path' => 'task-evidence/'.$taskId.'/'.$evidenceId.'.txt',
                    'name' => 'ملاحظة-ميدانية-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT).'.txt',
                    'mime' => 'text/plain',
                    'size' => strlen(self::EVIDENCE_TEXT),
                    'created_at' => $evidenceAt->format('Y-m-d H:i:s'),
                ];
                $auditRows[] = $this->taskAudit($taskId, $assignee['id'], 'evidence', 'evidence-'.$index, $evidenceAt, ['evidence_id' => $evidenceId, 'mime' => 'text/plain', 'version' => 2]);
            }
        }

        $this->insertChunked('tasks_cycle', $rows, 40);
        if ($auditRows !== []) {
            $this->insertChunked('tasks_cycle_audit', $auditRows, 60);
        }
        if ($evidenceRows !== []) {
            $this->insertChunked('tasks_cycle_evidence', $evidenceRows, 60);
            foreach ($evidenceRows as $evidence) {
                $this->rememberEvidence($evidence['path']);
                if (! Storage::disk('local')->put($evidence['path'], self::EVIDENCE_TEXT)) {
                    throw new RuntimeException('Could not write synthetic demo evidence.');
                }
            }
        }

        return ['ids' => $taskIds];
    }

    /**
     * @param  list<array{id: string, branch_id: string, department_id: string}>  $allUsers
     * @param  array{ids: list<string>}  $tasks
     * @param  list<string>  $departmentIds
     */
    private function insertEmployees(array $allUsers, array $tasks, array $departmentIds): void
    {
        $employeeCount = $this->size['employees'];
        $actorId = $allUsers[0]['id'];
        $profiles = [];
        $sequence = 0;

        // Link as many employees to login users as the size allows, then add
        // extra field workers that have no account.
        foreach ($allUsers as $user) {
            if (count($profiles) >= $employeeCount) {
                break;
            }
            $profiles[] = $this->employeeRow($user['id'], $user['branch_id'], $user['department_id'], $sequence++);
        }
        while (count($profiles) < $employeeCount) {
            $user = $allUsers[$this->data->int(0, count($allUsers) - 1)];
            $profiles[] = $this->employeeRow(null, $user['branch_id'], $user['department_id'], $sequence++);
        }

        // Reporting lines: within each (branch, department) the first profile is
        // the head; the rest report to it. No cycles are possible.
        $heads = [];
        foreach ($profiles as $profile) {
            $key = $profile['branch_id'].'|'.$profile['department_id'];
            $heads[$key] ??= $profile['id'];
        }
        foreach ($profiles as $index => $profile) {
            $head = $heads[$profile['branch_id'].'|'.$profile['department_id']];
            $profiles[$index]['manager_id'] = $profile['id'] === $head ? null : $head;
        }

        $auditRows = [];
        foreach ($profiles as $profileIndex => $profile) {
            $auditRows[] = $this->employeeAudit($profile['id'], $actorId, 'employee.created', $this->windowStart, [
                'fields' => ['code', 'name', 'role', 'branch_id', 'department_id', 'hire_date', 'status'],
                'employee_id' => $profile['id'],
                'status' => 'active',
                'version' => 0,
            ]);
            if ($profile['status'] !== 'active') {
                $changedAt = $this->pastDate($this->windowStart->modify('+1 second'), $this->now, true);
                $profiles[$profileIndex]['updated_at'] = $changedAt->format('Y-m-d H:i:s');
                $auditRows[] = $this->employeeAudit($profile['id'], $actorId, 'employee.updated', $changedAt, [
                    'fields' => ['status'],
                    'employee_id' => $profile['id'],
                    'before_status' => 'active',
                    'after_status' => $profile['status'],
                    'before_version' => 0,
                    'after_version' => 1,
                ]);
            }
        }

        // A few evidence records linking employees to real tasks.
        $evidenceRows = [];
        if ($tasks['ids'] !== []) {
            $evidenceCount = min(count($tasks['ids']), max(4, intdiv($employeeCount, 3)));
            for ($index = 0; $index < $evidenceCount; $index++) {
                $profile = $profiles[$this->data->int(0, count($profiles) - 1)];
                $occurred = $this->pastDate($this->windowStart, $this->now, true);
                $evidenceRows[] = [
                    'id' => $this->data->ulid(),
                    'company_id' => $this->companyId,
                    'employee_id' => $profile['id'],
                    'job_id' => $tasks['ids'][$this->data->int(0, count($tasks['ids']) - 1)],
                    'actor_id' => $profile['user_id'] ?? $actorId,
                    'note' => 'تم ربط مهمة ميدانية بالموظف مع توثيق التنفيذ.',
                    'created_at' => $occurred->format('Y-m-d H:i:s'),
                ];
            }
        }
        unset($departmentIds);

        // Insert managers first, then the rest, so the composite self-FK resolves.
        $headsOnly = array_values(array_filter($profiles, static fn (array $p): bool => $p['manager_id'] === null));
        $members = array_values(array_filter($profiles, static fn (array $p): bool => $p['manager_id'] !== null));
        if ($headsOnly !== []) {
            $this->insertChunked('employee_profiles', $headsOnly, 40);
        }
        if ($members !== []) {
            $this->insertChunked('employee_profiles', $members, 40);
        }
        if ($evidenceRows !== []) {
            $this->insertChunked('employee_evidence', $evidenceRows, 50);
        }
        if ($auditRows !== []) {
            $this->insertChunked('employee_audit', $auditRows, 60);
        }
    }

    /** @return array<string, mixed> */
    private function employeeRow(?string $userId, string $branchId, string $departmentId, int $sequence): array
    {
        $status = $this->data->weighted(['active' => 78, 'on_leave' => 12, 'suspended' => 6, 'terminated' => 4]);
        $hireDate = $this->pastDate($this->now->modify('-8 years'), $this->now, false);
        $stamp = $this->windowStart->format('Y-m-d H:i:s');

        return [
            'id' => $this->data->ulid(),
            'company_id' => $this->companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'manager_id' => null,
            'user_id' => $userId,
            'code' => 'EMP-'.str_pad((string) ($sequence + 1), 4, '0', STR_PAD_LEFT),
            'name' => $userId !== null ? 'موظف '.str_pad((string) ($sequence + 1), 4, '0', STR_PAD_LEFT) : $this->data->personName(),
            'role' => $this->data->jobTitle(),
            'hire_date' => $hireDate->format('Y-m-d'),
            'status' => $status,
            'phone' => $this->data->mobile(),
            'email' => $this->data->email('emp'.($sequence + 1)),
            'private_notes' => null,
            'version' => $status === 'active' ? 0 : 1,
            'created_at' => $stamp,
            'updated_at' => $stamp,
        ];
    }

    /**
     * @param  list<array{id: string, branch_id: string, department_id: string}>  $allUsers
     * @param  list<string>  $branchIds
     * @param  list<string>  $departmentIds
     * @param  list<string>  $roleIds
     */
    private function insertAccessAudit(array $allUsers, array $branchIds, array $departmentIds, array $roleIds): void
    {
        $actor = $allUsers[0]['id'];
        $rows = [];
        foreach ($branchIds as $branchId) {
            $rows[] = $this->accessAudit($actor, 'organization.branch.created', $branchId, 'branch');
        }
        foreach ($departmentIds as $departmentId) {
            $rows[] = $this->accessAudit($actor, 'organization.department.created', $departmentId, 'department');
        }
        foreach ($roleIds as $roleId) {
            $rows[] = $this->accessAudit($actor, 'role.created', $roleId, 'role');
        }
        foreach ($allUsers as $user) {
            $rows[] = $this->accessAudit($actor, 'user.created', $user['id'], 'user');
        }
        $this->insertChunked('access_audit', $rows, 80);
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $companyId = $this->companyId;
        $taskIds = DB::table('tasks_cycle')->where('entity_id', $companyId)->pluck('id')->all();
        $userIds = DB::table('access_users')->where('company_id', $companyId)->pluck('id')->all();
        $roleIds = DB::table('access_roles')->where('company_id', $companyId)->pluck('id')->all();

        return [
            'access_companies' => DB::table('access_companies')->where('id', $companyId)->count(),
            'access_branches' => DB::table('access_branches')->where('company_id', $companyId)->count(),
            'access_departments' => DB::table('access_departments')->where('company_id', $companyId)->count(),
            'access_users' => count($userIds),
            'access_roles' => count($roleIds),
            'access_role_permissions' => $roleIds === [] ? 0 : DB::table('access_role_permissions')->whereIn('role_id', $roleIds)->count(),
            'access_user_roles' => $userIds === [] ? 0 : DB::table('access_user_roles')->whereIn('user_id', $userIds)->count(),
            'access_user_permissions' => $userIds === [] ? 0 : DB::table('access_user_permissions')->whereIn('user_id', $userIds)->count(),
            'access_department_roles' => DB::table('access_department_roles')->where('company_id', $companyId)->count(),
            'access_audit' => DB::table('access_audit')->where('company_id', $companyId)->count(),
            'crm_taxonomy' => DB::table('crm_taxonomy')->where('company_id', $companyId)->count(),
            'crm_customers' => DB::table('crm_customers')->where('company_id', $companyId)->count(),
            'crm_leads' => DB::table('crm_leads')->where('company_id', $companyId)->count(),
            'crm_activities' => DB::table('crm_activities')->where('company_id', $companyId)->count(),
            'crm_transfers' => DB::table('crm_transfers')->where('company_id', $companyId)->count(),
            'crm_audit' => DB::table('crm_audit')->where('company_id', $companyId)->count(),
            'employee_profiles' => DB::table('employee_profiles')->where('company_id', $companyId)->count(),
            'employee_evidence' => DB::table('employee_evidence')->where('company_id', $companyId)->count(),
            'employee_audit' => DB::table('employee_audit')->where('company_id', $companyId)->count(),
            'tasks_cycle' => count($taskIds),
            'tasks_cycle_audit' => $taskIds === [] ? 0 : DB::table('tasks_cycle_audit')->whereIn('task_id', $taskIds)->count(),
            'tasks_cycle_evidence' => $taskIds === [] ? 0 : DB::table('tasks_cycle_evidence')->whereIn('task_id', $taskIds)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function crmAudit(string $leadId, string $actorId, string $action, DateTimeImmutable $at, array $metadata): array
    {
        $metadata['correlation_id'] = $this->data->ulid();

        return [
            'id' => $this->data->ulid(),
            'company_id' => $this->companyId,
            'lead_id' => $leadId,
            'actor_id' => $actorId,
            'action' => $action,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => $at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function taskAudit(string $taskId, string $actorId, string $action, string $keySalt, DateTimeImmutable $at, array $payload): array
    {
        return [
            'id' => $this->data->ulid(),
            'task_id' => $taskId,
            'actor_id' => $actorId,
            'action' => $action,
            'request_key' => 'demo-'.$action.':'.$keySalt,
            'payload_hash' => hash('sha256', $action.'|'.$keySalt),
            'receipt_id' => $this->data->ulid(),
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => $at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function employeeAudit(string $employeeId, string $actorId, string $action, DateTimeImmutable $at, array $metadata): array
    {
        $metadata += ['correlation_id' => $this->data->ulid(), 'origin' => 'demo', 'outcome' => 'accepted', 'approval_path' => []];

        return [
            'id' => $this->data->ulid(),
            'company_id' => $this->companyId,
            'employee_id' => $employeeId,
            'actor_id' => $actorId,
            'action' => $action,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => $at->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array<string, mixed> */
    private function accessAudit(string $actorId, string $action, string $subjectId, string $subjectType): array
    {
        return [
            'id' => $this->data->ulid(),
            'company_id' => $this->companyId,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_id' => $subjectId,
            'metadata' => json_encode(['subject_type' => $subjectType, 'origin' => 'demo'], JSON_THROW_ON_ERROR),
            'created_at' => $this->windowStart->format('Y-m-d H:i:s'),
        ];
    }

    private function fingerprint(string $email): string
    {
        return hash_hmac('sha256', 'email:'.mb_strtolower(trim($email)), (string) config('app.key', ''));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  positive-int  $chunkSize
     */
    private function insertChunked(string $table, array $rows, int $chunkSize): void
    {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function hoursAfter(int $hours): string
    {
        return $this->windowStart->modify('+'.$hours.' hours')->format('Y-m-d H:i:s');
    }

    private function after(DateTimeImmutable $from, int $days): DateTimeImmutable
    {
        $candidate = $from->modify('+'.$days.' days');

        return $candidate > $this->now ? $this->now : $candidate;
    }

    /**
     * A timestamp inside [$start, $end]. When $favorRecent is true the result is
     * biased toward recent dates so activity is not uniformly distributed.
     */
    private function pastDate(DateTimeImmutable $start, DateTimeImmutable $end, bool $favorRecent): DateTimeImmutable
    {
        if ($start >= $end) {
            return $start;
        }
        $span = $end->getTimestamp() - $start->getTimestamp();
        $roll = $this->data->int(0, 1000000) / 1000000;
        $fraction = $favorRecent ? 1.0 - sqrt($roll) : $roll;
        $offset = (int) floor($span * $fraction);

        return $start->setTimestamp($start->getTimestamp() + $offset);
    }
}

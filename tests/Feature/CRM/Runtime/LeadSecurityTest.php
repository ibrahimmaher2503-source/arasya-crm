<?php

declare(strict_types=1);

namespace Tests\Feature\CRM\Runtime;

use App\Modules\CRM\Application\Runtime\LeadDirectory;
use App\Modules\Identity\Application\SEC04\AccessControl;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;
use Throwable;

final class LeadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_session_cannot_read_leads(): void
    {
        self::assertTrue(class_exists(LeadDirectory::class), 'The persisted CRM lead directory must be available.');

        $this->assertRejected(
            fn () => $this->directory()->listing(Request::create('/crm/leads')),
            AuthenticationException::class,
        );
    }

    public function test_inactive_deleted_and_stale_session_identities_are_rejected(): void
    {
        $org = $this->organization();
        $reader = $this->user($org, 'Reader', 'branch_a', 'department_a', ['crm.read']);
        $lead = $this->lead($this->requestFor($this->user($org, 'Manager', 'branch_a', 'department_a', ['crm.read', 'crm.manage', 'crm.write'])), 'Identity Lead');

        $staleRequest = $this->requestFor($reader);
        DB::table('access_users')->where('id', $reader)->update(['session_version' => 1]);
        $this->assertRejected(fn () => $this->directory()->find($staleRequest, $lead), AuthenticationException::class);

        $inactive = $this->user($org, 'Inactive Reader', 'branch_a', 'department_a', ['crm.read']);
        $inactiveRequest = $this->requestFor($inactive);
        DB::table('access_users')->where('id', $inactive)->update(['active' => false]);
        $this->assertRejected(fn () => $this->directory()->find($inactiveRequest, $lead), AuthenticationException::class);

        $deleted = $this->user($org, 'Deleted Reader', 'branch_a', 'department_a', ['crm.read']);
        $deletedRequest = $this->requestFor($deleted);
        DB::table('access_user_roles')->where('user_id', $deleted)->delete();
        DB::table('access_user_permissions')->where('user_id', $deleted)->delete();
        DB::table('access_users')->where('id', $deleted)->delete();
        $this->assertRejected(fn () => $this->directory()->find($deletedRequest, $lead), AuthenticationException::class);
    }

    public function test_permissions_are_reloaded_for_the_same_session_and_explicit_denies_win(): void
    {
        $org = $this->organization();
        $reader = $this->user($org, 'Reader', 'branch_a', 'department_a', ['crm.read']);
        $request = $this->requestFor($reader);

        self::assertSame([], $this->directory()->listing($request));
        DB::table('access_role_permissions')->where('role_id', $this->roleId($reader))->where('permission', 'crm.read')->delete();
        $this->assertRejected(fn () => $this->directory()->listing($request), AccessDeniedHttpException::class);

        $granted = $this->user($org, 'Denied Reader', 'branch_a', 'department_a', ['crm.read']);
        DB::table('access_user_permissions')->insert([
            'user_id' => $granted,
            'permission' => 'crm.read',
            'allowed' => false,
        ]);
        $this->assertRejected(fn () => $this->directory()->listing($this->requestFor($granted)), AccessDeniedHttpException::class);
    }

    public function test_admin_contact_reveal_requires_an_explicit_role_or_user_grant(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'Permission Admin', 'branch_a', 'department_a', ['crm.read'], isAdmin: true);
        $access = app(AccessControl::class);

        self::assertNotContains('contact.reveal', $access->permissions($admin));

        DB::table('access_role_permissions')->insert([
            'role_id' => $this->roleId($admin),
            'permission' => 'contact.reveal',
        ]);
        self::assertContains('contact.reveal', $access->permissions($admin));

        DB::table('access_user_permissions')->insert([
            'user_id' => $admin,
            'permission' => 'contact.reveal',
            'allowed' => false,
        ]);
        self::assertNotContains('contact.reveal', $access->permissions($admin));

        DB::table('access_user_permissions')->where('user_id', $admin)->where('permission', 'contact.reveal')->update(['allowed' => true]);
        self::assertContains('contact.reveal', $access->permissions($admin));
    }

    public function test_sales_queries_and_writes_are_limited_to_the_current_assignment(): void
    {
        $org = $this->organization();
        $manager = $this->user($org, 'Manager', 'branch_a', 'department_a', ['crm.read', 'crm.manage', 'crm.write', 'crm.assign']);
        $sales = $this->user($org, 'Sales', 'branch_a', 'department_a', ['crm.read', 'crm.write', 'contact.reveal']);
        $otherSales = $this->user($org, 'Other Sales', 'branch_a', 'department_a', ['crm.read', 'crm.write']);
        $branchManager = $this->user($org, 'Branch Manager', 'branch_b', 'department_b', ['crm.read', 'crm.manage', 'crm.write']);
        $companyManager = $this->user($org, 'Company Manager', 'branch_c', 'department_c', ['crm.read', 'crm.manage', 'crm.write']);

        $own = $this->lead($this->requestFor($manager), 'Sales Own Lead');
        $this->directory()->assign($this->requestFor($manager), $own, ['assignee_id' => $sales, 'version' => 0]);
        $unassigned = $this->lead($this->requestFor($manager), 'Sales Unassigned Lead');
        $otherOwner = $this->lead($this->requestFor($manager), 'Sales Other Owner Lead');
        $this->directory()->assign($this->requestFor($manager), $otherOwner, ['assignee_id' => $otherSales, 'version' => 0]);
        $otherBranch = $this->lead($this->requestFor($branchManager), 'Sales Other Branch Lead');
        $otherCompany = $this->lead($this->requestFor($companyManager), 'Sales Other Company Lead');

        $request = $this->requestFor($sales);
        $listing = $this->encoded($this->directory()->listing($request));
        self::assertStringContainsString($own, $listing);
        foreach ([$unassigned, $otherOwner, $otherBranch, $otherCompany] as $hiddenLead) {
            self::assertStringNotContainsString($hiddenLead, $listing);
        }

        foreach ([$unassigned, $otherOwner, $otherBranch, $otherCompany] as $hiddenLead) {
            $this->assertRejected(fn () => $this->directory()->find($request, $hiddenLead), NotFoundHttpException::class);
            $this->assertRejected(
                fn () => $this->directory()->save($request, ['name' => 'Unauthorized Change', 'version' => 0], $hiddenLead),
                AccessDeniedHttpException::class,
                NotFoundHttpException::class,
            );
        }

        $maskedDetail = $this->encoded($this->directory()->find($request, $own));
        self::assertStringNotContainsString('SECRET-PHONE-Sales Own Lead', $maskedDetail);
        self::assertStringNotContainsString('SECRET-EMAIL-Sales Own Lead', $maskedDetail);
        self::assertStringNotContainsString('SECRET-WHATSAPP-Sales Own Lead', $maskedDetail);
        self::assertStringNotContainsString('SECRET-PHONE-Sales Own Lead', $listing);
        self::assertStringNotContainsString('SECRET-EMAIL-Sales Own Lead', $listing);
        self::assertStringNotContainsString('SECRET-WHATSAPP-Sales Own Lead', $listing);

        $this->assertRejected(
            fn () => $this->directory()->save($request, ['name' => 'Sales Updated Own Lead', 'version' => 1], $own),
            AccessDeniedHttpException::class,
        );
        self::assertSame('Sales Own Lead', (string) DB::table('crm_leads')->where('id', $own)->value('name'));
        self::assertSame(1, (int) DB::table('crm_leads')->where('id', $own)->value('version'));
    }

    public function test_managers_cannot_read_or_change_leads_outside_their_branch_department_or_company(): void
    {
        $org = $this->organization();
        $manager = $this->user($org, 'Manager', 'branch_a', 'department_a', ['crm.read', 'crm.manage', 'crm.write']);
        $otherDepartmentManager = $this->user($org, 'Other Department Manager', 'branch_a', 'department_a_other', ['crm.read', 'crm.manage', 'crm.write']);
        $otherBranchManager = $this->user($org, 'Other Branch Manager', 'branch_b', 'department_b', ['crm.read', 'crm.manage', 'crm.write']);
        $otherCompanyManager = $this->user($org, 'Other Company Manager', 'branch_c', 'department_c', ['crm.read', 'crm.manage', 'crm.write']);

        $own = $this->lead($this->requestFor($manager), 'Manager Own Branch Lead');
        $otherDepartment = $this->lead($this->requestFor($otherDepartmentManager), 'Manager Other Department Lead');
        $otherBranch = $this->lead($this->requestFor($otherBranchManager), 'Manager Other Branch Lead');
        $otherCompany = $this->lead($this->requestFor($otherCompanyManager), 'Manager Other Company Lead');
        $request = $this->requestFor($manager);

        $listing = $this->encoded($this->directory()->listing($request));
        self::assertStringContainsString($own, $listing);
        self::assertStringNotContainsString($otherDepartment, $listing);
        self::assertStringNotContainsString($otherBranch, $listing);
        self::assertStringNotContainsString($otherCompany, $listing);

        foreach ([$otherDepartment, $otherBranch, $otherCompany] as $hiddenLead) {
            $this->assertRejected(fn () => $this->directory()->find($request, $hiddenLead), NotFoundHttpException::class);
            $this->assertRejected(
                fn () => $this->directory()->save($request, ['name' => 'Cross Scope Change', 'version' => 0], $hiddenLead),
                AccessDeniedHttpException::class,
                NotFoundHttpException::class,
            );
        }

        self::assertSame('Manager Other Department Lead', (string) DB::table('crm_leads')->where('id', $otherDepartment)->value('name'));
        self::assertSame('Manager Other Branch Lead', (string) DB::table('crm_leads')->where('id', $otherBranch)->value('name'));
        self::assertSame('Manager Other Company Lead', (string) DB::table('crm_leads')->where('id', $otherCompany)->value('name'));
    }

    public function test_contact_reveal_needs_an_explicit_grant_and_reason_and_never_audits_contact_values(): void
    {
        $org = $this->organization();
        $admin = $this->user($org, 'CRM Admin', 'branch_a', 'department_a', ['crm.read', 'crm.manage', 'crm.write'], isAdmin: true);
        $lead = $this->lead($this->requestFor($admin), 'Reveal Lead');
        $adminRequest = $this->requestFor($admin);
        $reason = 'Customer requested a callback at +201000000000';
        $beforeAudit = DB::table('crm_audit')->count();

        $this->assertRejected(
            fn () => $this->directory()->reveal($adminRequest, $lead, ['reason' => $reason]),
            AccessDeniedHttpException::class,
        );
        self::assertSame($beforeAudit, DB::table('crm_audit')->count());

        $adminRole = $this->roleId($admin);
        DB::table('access_role_permissions')->insert([
            'role_id' => $adminRole,
            'permission' => 'contact.reveal',
        ]);
        DB::table('access_user_permissions')->insert([
            'user_id' => $admin,
            'permission' => 'contact.reveal',
            'allowed' => false,
        ]);
        $this->assertRejected(
            fn () => $this->directory()->reveal($adminRequest, $lead, ['reason' => $reason]),
            AccessDeniedHttpException::class,
        );
        self::assertSame($beforeAudit, DB::table('crm_audit')->count());

        DB::table('access_user_permissions')->where('user_id', $admin)->where('permission', 'contact.reveal')->delete();
        $this->assertRejected(
            fn () => $this->directory()->reveal($adminRequest, $lead, []),
            ValidationException::class,
        );
        self::assertSame($beforeAudit, DB::table('crm_audit')->count());

        $revealed = $this->encoded($this->directory()->reveal($adminRequest, $lead, ['reason' => $reason]));
        self::assertStringContainsString('SECRET-PHONE-Reveal Lead', $revealed);
        self::assertStringContainsString('SECRET-EMAIL-Reveal-Lead@example.test', $revealed);
        self::assertStringContainsString('SECRET-WHATSAPP-Reveal Lead', $revealed);

        $audit = DB::table('crm_audit')
            ->where('lead_id', $lead)
            ->where('action', 'lead.contacts.revealed')
            ->first();
        self::assertNotNull($audit);
        self::assertSame($admin, (string) $audit->actor_id);
        $metadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($metadata);
        $encryptedReason = $metadata['encrypted_reason'] ?? null;
        self::assertIsString($encryptedReason);
        self::assertSame($reason, Crypt::decryptString($encryptedReason));
        self::assertStringNotContainsString($reason, (string) $audit->metadata);
        self::assertStringNotContainsString('+201000000000', (string) $audit->metadata);
        self::assertStringNotContainsString('SECRET-PHONE-Reveal Lead', (string) $audit->metadata);
        self::assertStringNotContainsString('SECRET-EMAIL-Reveal-Lead@example.test', (string) $audit->metadata);
        self::assertStringNotContainsString('SECRET-WHATSAPP-Reveal Lead', (string) $audit->metadata);

        $history = $this->encoded($this->directory()->find($adminRequest, $lead));
        self::assertStringNotContainsString($reason, $history);
        self::assertStringNotContainsString('+201000000000', $history);

        $overrideReader = $this->user($org, 'Override Reader', 'branch_a', 'department_a', ['crm.read', 'crm.manage', 'crm.write']);
        DB::table('access_user_permissions')->insert([
            'user_id' => $overrideReader,
            'permission' => 'contact.reveal',
            'allowed' => true,
        ]);
        $overrideResult = $this->encoded($this->directory()->reveal(
            $this->requestFor($overrideReader),
            $lead,
            ['reason' => 'Explicit user permission'],
        ));
        self::assertStringContainsString('SECRET-EMAIL-Reveal-Lead@example.test', $overrideResult);
    }

    public function test_unknown_fields_and_stale_versions_leave_lead_and_audit_unchanged(): void
    {
        $org = $this->organization();
        $manager = $this->user($org, 'Manager', 'branch_a', 'department_a', ['crm.read', 'crm.manage', 'crm.write']);
        $request = $this->requestFor($manager);
        $lead = $this->lead($request, 'Validation Lead');
        $beforeAudit = DB::table('crm_audit')->where('lead_id', $lead)->count();

        foreach ([
            ['unexpected' => 'value', 'version' => 0],
            ['actor_id' => $this->user($org, 'Spoofed Actor', 'branch_a', 'department_a'), 'version' => 0],
        ] as $data) {
            $this->assertRejected(fn () => $this->directory()->save($request, $data, $lead), ValidationException::class);
        }
        $this->assertRejected(
            fn () => $this->directory()->save($request, ['name' => 'Stale Update', 'version' => 99], $lead),
            ConflictHttpException::class,
        );

        self::assertSame('Validation Lead', (string) DB::table('crm_leads')->where('id', $lead)->value('name'));
        self::assertSame(0, (int) DB::table('crm_leads')->where('id', $lead)->value('version'));
        self::assertSame($beforeAudit, DB::table('crm_audit')->where('lead_id', $lead)->count());
    }

    public function test_duplicate_contact_creation_is_rejected_without_a_second_lead_or_audit(): void
    {
        $org = $this->organization();
        $manager = $this->user($org, 'Manager', 'branch_a', 'department_a', ['crm.read', 'crm.manage', 'crm.write']);
        $request = $this->requestFor($manager);
        $email = 'shared-duplicate@example.test';
        $first = $this->lead($request, 'First Duplicate Lead', email: $email);
        $beforeLeads = DB::table('crm_leads')->where('company_id', $org['company_a'])->count();
        $beforeAudit = DB::table('crm_audit')->where('company_id', $org['company_a'])->count();

        $this->assertRejected(
            fn () => $this->lead($request, 'Second Duplicate Lead', email: $email),
            ValidationException::class,
            ConflictHttpException::class,
        );

        self::assertSame($beforeLeads, DB::table('crm_leads')->where('company_id', $org['company_a'])->count());
        self::assertSame($beforeAudit, DB::table('crm_audit')->where('company_id', $org['company_a'])->count());
        self::assertSame($first, (string) DB::table('crm_leads')->where('id', $first)->value('id'));
    }

    public function test_inactive_assignees_are_rejected_without_changes(): void
    {
        $org = $this->organization();
        $permissions = ['crm.read', 'crm.manage', 'crm.write', 'crm.assign', 'crm.transfer'];
        $manager = $this->user($org, 'Manager', 'branch_a', 'department_a', $permissions);
        $inactiveRecipient = $this->user($org, 'Inactive Recipient', 'branch_a', 'department_a', ['crm.read', 'crm.write', 'crm.transfer']);
        DB::table('access_users')->where('id', $inactiveRecipient)->update(['active' => false]);
        $lead = $this->lead($this->requestFor($manager), 'Recipient Lead');
        $beforeAudit = DB::table('crm_audit')->where('lead_id', $lead)->count();

        $this->assertRejected(
            fn () => $this->directory()->assign($this->requestFor($manager), $lead, [
                'assignee_id' => $inactiveRecipient,
                'version' => 0,
            ]),
            ValidationException::class,
            NotFoundHttpException::class,
        );
        self::assertNull(DB::table('crm_leads')->where('id', $lead)->value('assignee_id'));
        self::assertSame(0, (int) DB::table('crm_leads')->where('id', $lead)->value('version'));
        self::assertSame($beforeAudit, DB::table('crm_audit')->where('lead_id', $lead)->count());
    }

    public function test_database_rejects_a_cross_company_assignee_even_on_direct_update(): void
    {
        $org = $this->organization();
        $manager = $this->user($org, 'Manager', 'branch_a', 'department_a', ['crm.read', 'crm.manage', 'crm.write']);
        $otherCompanyUser = $this->user($org, 'Foreign Company User', 'branch_c', 'department_c', ['crm.read', 'crm.write']);
        $lead = $this->lead($this->requestFor($manager), 'Company Scoped Lead');

        self::assertNull(DB::table('crm_leads')->where('id', $lead)->value('assignee_id'));
        $this->assertRejected(
            fn () => DB::table('crm_leads')->where('id', $lead)->update(['assignee_id' => $otherCompanyUser]),
            QueryException::class,
        );
        self::assertNull(DB::table('crm_leads')->where('id', $lead)->value('assignee_id'));
    }

    /** @return array<string, string> */
    private function organization(): array
    {
        $companyA = $this->id();
        $companyB = $this->id();
        DB::table('access_companies')->insert([
            ['id' => $companyA, 'name' => 'CRM Test Company A', 'version' => 0],
            ['id' => $companyB, 'name' => 'CRM Test Company B', 'version' => 0],
        ]);

        $ids = [
            'branch_a' => $this->id(),
            'branch_b' => $this->id(),
            'branch_c' => $this->id(),
            'department_a' => $this->id(),
            'department_a_other' => $this->id(),
            'department_b' => $this->id(),
            'department_c' => $this->id(),
        ];
        foreach ([
            [$ids['branch_a'], $companyA, 'BR-A'],
            [$ids['branch_b'], $companyA, 'BR-B'],
            [$ids['branch_c'], $companyB, 'BR-C'],
        ] as [$branchId, $companyId, $code]) {
            DB::table('access_branches')->insert([
                'id' => $branchId,
                'company_id' => $companyId,
                'name' => $code,
                'code' => $code,
                'active' => true,
                'version' => 0,
            ]);
        }
        foreach ([
            [$ids['department_a'], $companyA, $ids['branch_a'], 'DEPT-A'],
            [$ids['department_a_other'], $companyA, $ids['branch_a'], 'DEPT-A-OTHER'],
            [$ids['department_b'], $companyA, $ids['branch_b'], 'DEPT-B'],
            [$ids['department_c'], $companyB, $ids['branch_c'], 'DEPT-C'],
        ] as [$departmentId, $companyId, $branchId, $code]) {
            DB::table('access_departments')->insert([
                'id' => $departmentId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => $code,
                'code' => $code,
                'active' => true,
                'version' => 0,
            ]);
        }

        return ['company_a' => $companyA, 'company_b' => $companyB] + $ids;
    }

    /** @param list<string> $permissions */
    private function user(array $org, string $name, string $branch, string $department, array $permissions = [], bool $isAdmin = false): string
    {
        $id = $this->id();
        $companyId = $branch === 'branch_c' ? $org['company_b'] : $org['company_a'];
        DB::table('access_users')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'branch_id' => $org[$branch],
            'department_id' => $org[$department],
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.$id.'@example.test',
            'password' => Hash::make('crm-test-password'),
            'active' => true,
            'is_admin' => $isAdmin,
            'session_version' => 0,
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($permissions !== []) {
            $roleId = $this->id();
            DB::table('access_roles')->insert([
                'id' => $roleId,
                'company_id' => $companyId,
                'name' => 'CRM Test Role '.$roleId,
                'version' => 0,
            ]);
            foreach ($permissions as $permission) {
                DB::table('access_role_permissions')->insert(['role_id' => $roleId, 'permission' => $permission]);
            }
            DB::table('access_user_roles')->insert(['user_id' => $id, 'role_id' => $roleId]);
        }

        return $id;
    }

    private function roleId(string $userId): string
    {
        return (string) DB::table('access_user_roles')->where('user_id', $userId)->value('role_id');
    }

    private function lead(Request $request, string $name, ?string $email = null): string
    {
        $this->directory()->save($request, [
            'name' => $name,
            'organization' => 'Synthetic Organization '.$name,
            'source' => 'Referral',
            'contacts' => [
                'phone' => 'SECRET-PHONE-'.$name,
                'email' => $email ?? 'SECRET-EMAIL-'.str_replace(' ', '-', $name).'@example.test',
                'whatsapp' => 'SECRET-WHATSAPP-'.$name,
            ],
            'consent' => true,
            'preferences' => [],
            'notes' => 'Synthetic note for '.$name,
        ]);
        $id = (string) DB::table('crm_leads')->where('name', $name)->value('id');
        self::assertNotSame('', $id, 'Creating a lead must persist the lead before returning it.');

        return $id;
    }

    private function requestFor(string $userId): Request
    {
        $request = Request::create('/crm/leads');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->replace([
            'access_user_id' => $userId,
            'access_session_version' => (int) DB::table('access_users')->where('id', $userId)->value('session_version'),
        ]);

        return $request;
    }

    private function directory(): LeadDirectory
    {
        return app(LeadDirectory::class);
    }

    private function assertRejected(callable $operation, string ...$exceptionTypes): Throwable
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            foreach ($exceptionTypes as $exceptionType) {
                if ($exception instanceof $exceptionType) {
                    self::assertInstanceOf($exceptionType, $exception);

                    return $exception;
                }
            }

            throw $exception;
        }

        throw new AssertionFailedError('Expected the operation to be rejected.');
    }

    private function encoded(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function id(): string
    {
        return (string) Str::ulid();
    }
}

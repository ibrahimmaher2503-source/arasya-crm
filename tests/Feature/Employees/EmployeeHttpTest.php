<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Http\Controllers\EmployeeController;
use App\Modules\Employees\Application\EMP01\EmployeeDirectory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

final class EmployeeHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_renders_real_scoped_profiles_in_both_languages(): void
    {
        self::assertTrue(class_exists(EmployeeController::class), 'The authenticated employee controller must exist.');
        $company = (string) Str::ulid();
        $branch = (string) Str::ulid();
        $department = (string) Str::ulid();
        $user = (string) Str::ulid();
        DB::table('access_companies')->insert(['id' => $company, 'name' => 'Synthetic UI Company', 'version' => 0]);
        DB::table('access_branches')->insert(['id' => $branch, 'company_id' => $company, 'name' => 'Branch', 'code' => 'branch', 'active' => true, 'version' => 0]);
        DB::table('access_departments')->insert(['id' => $department, 'company_id' => $company, 'branch_id' => $branch, 'name' => 'Synthetic crew unit', 'code' => 'dept', 'active' => true, 'version' => 0]);
        DB::table('access_users')->insert(['id' => $user, 'company_id' => $company, 'branch_id' => $branch, 'department_id' => $department, 'name' => 'Synthetic administrator', 'email' => 'ui@example.test', 'password' => Hash::make('synthetic-only-password'), 'active' => true, 'is_admin' => true, 'session_version' => 0, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $session = app('session')->driver();
        $session->put('access_user_id', $user);
        $session->put('access_session_version', 0);
        $request = Request::create('/employees?locale=en');
        $request->setLaravelSession($session);
        $profile = app(EmployeeDirectory::class)->save($request, ['code' => 'UI-001', 'name' => 'Persisted technician', 'role' => 'Technician', 'branch_id' => $branch, 'department_id' => $department, 'manager_id' => null, 'user_id' => null, 'hire_date' => '2026-01-01', 'status' => 'active']);
        $controller = app(EmployeeController::class);
        app('view')->share('errors', new ViewErrorBag);
        $english = $controller->index($request)->render();
        self::assertStringContainsString('dir="ltr"', $english);
        self::assertStringContainsString('Persisted technician', $english);
        self::assertStringContainsString('/employees/create', $english);
        $arabicRequest = Request::create('/employees?locale=ar');
        $arabicRequest->setLaravelSession($session);
        self::assertStringContainsString('dir="rtl"', $controller->index($arabicRequest)->render());
        $detail = $controller->show($request, $profile['id'])->render();
        self::assertStringContainsString('Persisted technician', $detail);
        self::assertStringContainsString('Pending integration', $detail);
        self::assertStringContainsString('Synthetic crew unit', $detail);
        self::assertStringContainsString('employee-code', $controller->create($request)->render());
        self::assertStringContainsString('UI-001', $controller->edit($request, $profile['id'])->render());
        $post = Request::create('/employees?locale=en', 'POST', ['code' => 'UI-002', 'name' => 'Saved through controller', 'role' => 'Technician', 'branch_id' => $branch, 'department_id' => $department, 'manager_id' => '', 'user_id' => '', 'hire_date' => '2026-01-01', 'status' => 'active', '_token' => 'synthetic-form-token']);
        $post->setLaravelSession($session);
        $post->headers->set('Accept', 'application/json');
        $saved = $controller->store($post);
        self::assertSame(201, $saved->getStatusCode());
        $savedData = $saved->getData(true)['data'];
        self::assertNull($savedData['user_id']);
        $this->assertDatabaseHas('employee_profiles', ['id' => $savedData['id'], 'name' => 'Saved through controller']);
        $update = Request::create('/employees/'.$savedData['id'], 'POST', ['name' => 'Reloaded controller edit', 'version' => 0]);
        $update->setLaravelSession($session);
        $update->headers->set('Accept', 'application/json');
        self::assertSame(200, $controller->update($update, $savedData['id'])->getStatusCode());
        self::assertStringContainsString('Reloaded controller edit', $controller->show($request, $savedData['id'])->render());
        app(EmployeeDirectory::class)->save($request, ['private_notes' => 'PRIVATE-UI-NOTE', 'version' => 1], $savedData['id']);
        $role = (string) Str::ulid();
        DB::table('access_roles')->insert(['id' => $role, 'company_id' => $company, 'name' => 'Self reader', 'version' => 0]);
        DB::table('access_role_permissions')->insert(['role_id' => $role, 'permission' => 'employees.read']);
        DB::table('access_user_roles')->insert(['user_id' => $user, 'role_id' => $role]);
        app(EmployeeDirectory::class)->save($request, ['user_id' => $user, 'version' => 2], $savedData['id']);
        DB::table('access_users')->where('id', $user)->update(['is_admin' => false]);
        $restricted = $controller->show($request, $savedData['id'])->render();
        self::assertStringNotContainsString('PRIVATE-UI-NOTE', $restricted);
        self::assertStringNotContainsString('/edit?locale=', $restricted);
        self::assertStringNotContainsString('/employees/create', $controller->index($request)->render());
        $session->forget('access_user_id');
        $this->expectException(AuthenticationException::class);
        $controller->index($request);
    }
}

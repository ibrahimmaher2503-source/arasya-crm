<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_companies', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->string('name', 200);
            $table->unsignedInteger('version')->default(0);
        });
        Schema::create('access_branches', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->string('name', 200);
            $table->string('code', 40);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(0);
            $table->foreign('company_id')->references('id')->on('access_companies')->restrictOnDelete();
            $table->unique(['company_id', 'code']);
            $table->unique(['id', 'company_id']);
        });
        Schema::create('access_departments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('branch_id', 26);
            $table->string('name', 200);
            $table->string('code', 40);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(0);
            $table->foreign(['branch_id', 'company_id'])->references(['id', 'company_id'])->on('access_branches')->restrictOnDelete();
            $table->unique(['company_id', 'branch_id', 'code'], 'access_dept_code_unique');
            $table->unique(['id', 'company_id', 'branch_id'], 'access_dept_scope_unique');
        });
        Schema::create('access_users', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('branch_id', 26);
            $table->char('department_id', 26);
            $table->string('name', 200);
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->boolean('is_admin')->default(false);
            $table->unsignedInteger('session_version')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->foreign(['department_id', 'company_id', 'branch_id'], 'access_user_scope_fk')->references(['id', 'company_id', 'branch_id'])->on('access_departments')->restrictOnDelete();
            $table->index(['company_id', 'branch_id']);
        });
        Schema::create('access_roles', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->string('name', 200);
            $table->unsignedInteger('version')->default(0);
            $table->foreign('company_id')->references('id')->on('access_companies')->restrictOnDelete();
            $table->unique(['company_id', 'name']);
        });
        Schema::create('access_role_permissions', function (Blueprint $table): void {
            $table->char('role_id', 26);
            $table->string('permission', 100);
            $table->primary(['role_id', 'permission']);
            $table->foreign('role_id')->references('id')->on('access_roles')->restrictOnDelete();
        });
        Schema::create('access_user_roles', function (Blueprint $table): void {
            $table->char('user_id', 26);
            $table->char('role_id', 26);
            $table->primary(['user_id', 'role_id']);
            $table->foreign('user_id')->references('id')->on('access_users')->restrictOnDelete();
            $table->foreign('role_id')->references('id')->on('access_roles')->restrictOnDelete();
        });
        Schema::create('access_user_permissions', function (Blueprint $table): void {
            $table->char('user_id', 26);
            $table->string('permission', 100);
            $table->boolean('allowed');
            $table->primary(['user_id', 'permission']);
            $table->foreign('user_id')->references('id')->on('access_users')->restrictOnDelete();
        });
        Schema::create('access_audit', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('actor_id', 26)->nullable();
            $table->string('action', 100);
            $table->string('subject_id', 100);
            $table->json('metadata');
            $table->timestamp('created_at');
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['access_audit', 'access_user_permissions', 'access_user_roles', 'access_role_permissions', 'access_roles', 'access_users', 'access_departments', 'access_branches', 'access_companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('branch_id', 26);
            $table->char('department_id', 26);
            $table->char('manager_id', 26)->nullable();
            $table->char('user_id', 26)->nullable()->unique('employee_user_unique');
            $table->string('code', 64);
            $table->string('name', 200);
            $table->string('role', 200);
            $table->date('hire_date');
            $table->string('status', 24)->default('active');
            $table->string('phone', 64)->nullable();
            $table->string('email', 254)->nullable();
            $table->text('private_notes')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('access_companies')->restrictOnDelete();
            $table->foreign(['branch_id', 'company_id'], 'employee_branch_scope_fk')
                ->references(['id', 'company_id'])->on('access_branches')->restrictOnDelete();
            $table->foreign(['department_id', 'company_id', 'branch_id'], 'employee_department_scope_fk')
                ->references(['id', 'company_id', 'branch_id'])->on('access_departments')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('access_users')->restrictOnDelete();
            $table->unique(['company_id', 'code'], 'employee_company_code_unique');
            $table->unique(['id', 'company_id'], 'employee_company_id_unique');
            $table->unique(['id', 'company_id', 'branch_id', 'department_id'], 'employee_reporting_scope_unique');
            $table->foreign(['manager_id', 'company_id', 'branch_id', 'department_id'], 'employee_manager_scope_fk')
                ->references(['id', 'company_id', 'branch_id', 'department_id'])->on('employee_profiles')->restrictOnDelete();
            $table->index(['company_id', 'branch_id', 'department_id', 'manager_id'], 'employee_scope_manager_idx');
        });

        Schema::create('employee_evidence', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('employee_id', 26);
            $table->string('job_id', 26);
            $table->char('actor_id', 26);
            $table->text('note');
            $table->dateTime('created_at');
            $table->foreign(['employee_id', 'company_id'], 'employee_evidence_profile_fk')
                ->references(['id', 'company_id'])->on('employee_profiles')->restrictOnDelete();
            $table->foreign('job_id')->references('id')->on('tasks_cycle')->restrictOnDelete();
            $table->foreign('actor_id')->references('id')->on('access_users')->restrictOnDelete();
            $table->index(['employee_id', 'created_at'], 'employee_evidence_time_idx');
        });

        Schema::create('employee_audit', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('employee_id', 26);
            $table->char('actor_id', 26);
            $table->string('action', 64);
            $table->json('metadata');
            $table->dateTime('created_at');
            $table->foreign(['employee_id', 'company_id'], 'employee_audit_profile_fk')
                ->references(['id', 'company_id'])->on('employee_profiles')->restrictOnDelete();
            $table->foreign('actor_id')->references('id')->on('access_users')->restrictOnDelete();
            $table->index(['employee_id', 'created_at'], 'employee_audit_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_audit');
        Schema::dropIfExists('employee_evidence');
        Schema::dropIfExists('employee_profiles');
    }
};

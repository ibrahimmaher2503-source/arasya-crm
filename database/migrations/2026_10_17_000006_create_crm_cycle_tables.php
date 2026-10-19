<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_users', function (Blueprint $table): void {
            $table->unique(['id', 'company_id'], 'crm_users_company_uq');
        });

        Schema::create('crm_customers', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('branch_id', 26);
            $table->char('department_id', 26);
            $table->string('name', 200);
            $table->string('organization', 200);
            $table->char('duplicate_key', 64)->nullable();
            $table->dateTime('created_at');

            $table->foreign('company_id', 'crm_customers_company_fk')
                ->references('id')->on('access_companies')->restrictOnDelete();
            $table->foreign(['branch_id', 'company_id'], 'crm_customers_branch_scope_fk')
                ->references(['id', 'company_id'])->on('access_branches')->restrictOnDelete();
            $table->foreign(['department_id', 'company_id', 'branch_id'], 'crm_customers_dept_scope_fk')
                ->references(['id', 'company_id', 'branch_id'])->on('access_departments')->restrictOnDelete();
            $table->unique(['id', 'company_id'], 'crm_customers_id_company_uq');
            $table->unique(['company_id', 'duplicate_key'], 'crm_customers_company_dup_uq');
        });

        Schema::create('crm_taxonomy', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->string('kind', 16);
            $table->string('label', 60);
            $table->char('replaces_id', 26)->nullable();
            $table->dateTime('created_at');

            $table->foreign('company_id', 'crm_tax_company_fk')
                ->references('id')->on('access_companies')->restrictOnDelete();
            $table->unique(['id', 'company_id'], 'crm_tax_id_company_uq');
            $table->foreign(['replaces_id', 'company_id'], 'crm_tax_replaces_scope_fk')
                ->references(['id', 'company_id'])->on('crm_taxonomy')->restrictOnDelete();
            $table->index(['company_id', 'kind', 'created_at'], 'crm_tax_company_kind_idx');
        });

        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('branch_id', 26);
            $table->char('department_id', 26);
            $table->string('name', 200);
            $table->string('organization', 200);
            $table->string('source', 60);
            $table->text('contacts');
            $table->char('duplicate_key', 64)->nullable();
            $table->boolean('consent')->default(false);
            $table->json('preferences');
            $table->text('notes')->nullable();
            $table->char('stage_id', 26)->nullable();
            $table->char('assignee_id', 26)->nullable();
            $table->char('customer_id', 26)->nullable();
            $table->boolean('archived')->default(false);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->foreign('company_id', 'crm_leads_company_fk')
                ->references('id')->on('access_companies')->restrictOnDelete();
            $table->foreign(['branch_id', 'company_id'], 'crm_leads_branch_scope_fk')
                ->references(['id', 'company_id'])->on('access_branches')->restrictOnDelete();
            $table->foreign(['department_id', 'company_id', 'branch_id'], 'crm_leads_dept_scope_fk')
                ->references(['id', 'company_id', 'branch_id'])->on('access_departments')->restrictOnDelete();
            $table->foreign(['assignee_id', 'company_id'], 'crm_leads_assignee_scope_fk')
                ->references(['id', 'company_id'])->on('access_users')->restrictOnDelete();
            $table->foreign(['stage_id', 'company_id'], 'crm_leads_stage_scope_fk')
                ->references(['id', 'company_id'])->on('crm_taxonomy')->restrictOnDelete();
            $table->foreign(['customer_id', 'company_id'], 'crm_leads_customer_scope_fk')
                ->references(['id', 'company_id'])->on('crm_customers')->restrictOnDelete();
            $table->unique(['id', 'company_id'], 'crm_leads_id_company_uq');
            $table->unique(['company_id', 'duplicate_key'], 'crm_leads_company_dup_uq');
            $table->index(['company_id', 'branch_id', 'department_id', 'created_at'], 'crm_leads_scope_time_idx');
            $table->index(['company_id', 'branch_id', 'department_id', 'assignee_id', 'archived', 'created_at'], 'crm_leads_assigned_idx');
        });

        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('lead_id', 26);
            $table->char('actor_id', 26);
            $table->string('kind', 16);
            $table->char('outcome_id', 26);
            $table->string('outcome_label', 60);
            $table->text('note')->nullable();
            $table->string('next_step', 200);
            $table->dateTime('occurred_at');
            $table->dateTime('next_follow_up')->nullable();
            $table->string('task_id', 26)->nullable();
            $table->dateTime('created_at');

            $table->foreign(['lead_id', 'company_id'], 'crm_activities_lead_scope_fk')
                ->references(['id', 'company_id'])->on('crm_leads')->restrictOnDelete();
            $table->foreign(['actor_id', 'company_id'], 'crm_activities_actor_scope_fk')
                ->references(['id', 'company_id'])->on('access_users')->restrictOnDelete();
            $table->foreign(['outcome_id', 'company_id'], 'crm_activities_outcome_scope_fk')
                ->references(['id', 'company_id'])->on('crm_taxonomy')->restrictOnDelete();
            $table->foreign('task_id', 'crm_activities_task_fk')
                ->references('id')->on('tasks_cycle')->restrictOnDelete();
            $table->index(['lead_id', 'created_at'], 'crm_activities_lead_time_idx');
        });

        Schema::create('crm_transfers', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('lead_id', 26);
            $table->char('sender_id', 26);
            $table->char('recipient_id', 26);
            $table->text('context');
            $table->string('state', 16)->default('pending');
            $table->unsignedInteger('requested_version');
            $table->dateTime('created_at');
            $table->dateTime('responded_at')->nullable();

            $table->foreign(['lead_id', 'company_id'], 'crm_transfers_lead_scope_fk')
                ->references(['id', 'company_id'])->on('crm_leads')->restrictOnDelete();
            $table->foreign(['sender_id', 'company_id'], 'crm_transfers_sender_scope_fk')
                ->references(['id', 'company_id'])->on('access_users')->restrictOnDelete();
            $table->foreign(['recipient_id', 'company_id'], 'crm_transfers_recipient_scope_fk')
                ->references(['id', 'company_id'])->on('access_users')->restrictOnDelete();
            $table->index(['company_id', 'recipient_id', 'state', 'created_at'], 'crm_transfers_inbox_idx');
        });

        Schema::create('crm_audit', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('lead_id', 26)->nullable();
            $table->char('actor_id', 26);
            $table->string('action', 60);
            $table->json('metadata');
            $table->dateTime('created_at');

            $table->foreign('company_id', 'crm_audit_company_fk')
                ->references('id')->on('access_companies')->restrictOnDelete();
            $table->foreign(['lead_id', 'company_id'], 'crm_audit_lead_scope_fk')
                ->references(['id', 'company_id'])->on('crm_leads')->restrictOnDelete();
            $table->foreign(['actor_id', 'company_id'], 'crm_audit_actor_scope_fk')
                ->references(['id', 'company_id'])->on('access_users')->restrictOnDelete();
            $table->index(['company_id', 'created_at'], 'crm_audit_company_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_audit');
        Schema::dropIfExists('crm_transfers');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_taxonomy');
        Schema::dropIfExists('crm_customers');

        Schema::table('access_users', function (Blueprint $table): void {
            $table->dropUnique('crm_users_company_uq');
        });
    }
};

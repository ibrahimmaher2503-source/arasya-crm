<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->uuid('submission_key')->nullable();
            $table->char('submission_hash', 64)->nullable();
            $table->unique(['company_id', 'created_by', 'submission_key'], 'crm_opp_submission_unique');
            $table->unique(['id', 'company_id'], 'crm_opp_identity_unique');
        });
        Schema::create('crm_opportunity_history', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('opportunity_id', 26);
            $table->char('actor_id', 26);
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->timestamp('created_at');
            $table->unique(['company_id', 'opportunity_id', 'version'], 'crm_opp_history_version_unique');
            $table->foreign(['opportunity_id', 'company_id'], 'crm_opp_history_opportunity_fk')
                ->references(['id', 'company_id'])->on('crm_opportunities')->restrictOnDelete();
            $table->foreign(['actor_id', 'company_id'], 'crm_opp_history_actor_fk')
                ->references(['id', 'company_id'])->on('access_users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Development rollback discards newly recorded history; existing opportunities remain.
        Schema::dropIfExists('crm_opportunity_history');
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->dropUnique('crm_opp_submission_unique');
            $table->dropUnique('crm_opp_identity_unique');
            $table->dropColumn(['submission_key', 'submission_hash']);
        });
    }
};

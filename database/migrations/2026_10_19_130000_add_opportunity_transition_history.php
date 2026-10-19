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
            $table->enum('closing_outcome', ['won', 'lost'])->nullable();
        });
        Schema::table('crm_opportunity_history', function (Blueprint $table): void {
            $table->string('action', 20)->default('saved');
            $table->string('from_stage', 20)->nullable();
            $table->boolean('reason_provided')->default(false);
            $table->uuid('transition_key')->nullable();
            $table->char('transition_hash', 64)->nullable();
            $table->unique(['company_id', 'actor_id', 'transition_key'], 'crm_opp_transition_unique');
        });
    }

    public function down(): void
    {
        // Development rollback discards transition metadata, retaining snapshots.
        Schema::table('crm_opportunity_history', function (Blueprint $table): void {
            $table->dropUnique('crm_opp_transition_unique');
            $table->dropColumn(['action', 'from_stage', 'reason_provided', 'transition_key', 'transition_hash']);
        });
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->dropColumn('closing_outcome');
        });
    }
};

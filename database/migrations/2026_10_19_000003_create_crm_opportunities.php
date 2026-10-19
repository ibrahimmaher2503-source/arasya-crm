<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('lead_id', 26);
            $table->char('created_by', 26);
            $table->string('title', 200);
            $table->string('amount', 19);
            $table->char('currency', 3);
            $table->string('stage', 20);
            $table->unsignedTinyInteger('probability');
            $table->date('expected_close')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->foreign(['lead_id', 'company_id'])->references(['id', 'company_id'])->on('crm_leads')->restrictOnDelete();
            $table->foreign(['created_by', 'company_id'])->references(['id', 'company_id'])->on('access_users')->restrictOnDelete();
            $table->index(['company_id', 'stage', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};

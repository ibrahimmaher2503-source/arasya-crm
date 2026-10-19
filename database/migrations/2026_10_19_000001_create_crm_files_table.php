<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_files', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('company_id', 26);
            $table->char('lead_id', 26);
            $table->char('uploader_id', 26);
            $table->string('name', 200);
            $table->string('path')->unique();
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->string('description', 500)->nullable();
            $table->string('category', 80);
            $table->dateTime('created_at');
            $table->foreign(['lead_id', 'company_id'])->references(['id', 'company_id'])->on('crm_leads')->restrictOnDelete();
            $table->foreign(['uploader_id', 'company_id'])->references(['id', 'company_id'])->on('access_users')->restrictOnDelete();
            $table->index(['company_id', 'lead_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_files');
    }
};

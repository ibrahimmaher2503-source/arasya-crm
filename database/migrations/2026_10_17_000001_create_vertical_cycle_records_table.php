<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vertical_cycle_records', function (Blueprint $table): void {
            $table->string('record_id', 26)->primary();
            $table->string('entity_id', 64);
            $table->string('branch_id', 64);
            $table->string('record_type', 100);
            $table->json('payload');
            $table->string('idempotency_key', 128)->unique();
            $table->string('audit_reference', 128);
            $table->timestamps();
            $table->index(['entity_id', 'branch_id', 'record_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vertical_cycle_records');
    }
};

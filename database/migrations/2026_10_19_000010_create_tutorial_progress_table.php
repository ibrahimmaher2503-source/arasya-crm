<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutorial_progress', function (Blueprint $table): void {
            $table->char('company_id', 26);
            $table->char('user_id', 26);
            $table->string('tour_id', 80);
            $table->unsignedInteger('version');
            $table->string('step_key', 80);
            $table->string('status', 20);
            $table->timestamps();
            $table->primary(['company_id', 'user_id', 'tour_id'], 'tutorial_progress_scope_primary');
            $table->foreign('company_id')->references('id')->on('access_companies')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('access_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutorial_progress');
    }
};

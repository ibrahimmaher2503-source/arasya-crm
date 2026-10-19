<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks_cycle', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('entity_id', 64);
            $table->string('department_id', 64);
            $table->string('owner_id', 64);
            $table->string('assignee_id', 64)->nullable();
            $table->string('title', 255);
            $table->string('priority', 16);
            $table->dateTime('due_at')->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('version')->default(0);
            $table->char('create_key_hash', 64)->unique('tsk_cy_create_uq');
            $table->char('create_payload_hash', 64);
            $table->timestamps();
            $table->index(['entity_id', 'department_id', 'owner_id', 'created_at'], 'tsk_cy_owner_scope_idx');
            $table->index(['entity_id', 'department_id', 'assignee_id', 'created_at'], 'tsk_cy_assignee_scope_idx');
        });

        Schema::create('tasks_cycle_audit', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('task_id', 26);
            $table->string('actor_id', 64);
            $table->string('action', 24);
            $table->string('request_key', 128);
            $table->char('payload_hash', 64);
            $table->string('receipt_id', 26)->unique('tsk_cy_audit_receipt_uq');
            $table->json('payload');
            $table->dateTime('created_at');
            $table->foreign('task_id', 'tsk_cy_audit_task_fk')->references('id')->on('tasks_cycle')->cascadeOnDelete();
            $table->unique(['task_id', 'request_key'], 'tsk_cy_audit_key_uq');
            $table->index(['task_id', 'created_at'], 'tsk_cy_audit_time_idx');
        });

        Schema::create('tasks_cycle_evidence', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('task_id', 26);
            $table->string('path', 512)->unique('tsk_cy_evidence_path_uq');
            $table->string('name', 255);
            $table->string('mime', 127);
            $table->unsignedInteger('size');
            $table->dateTime('created_at');
            $table->foreign('task_id', 'tsk_cy_evidence_task_fk')->references('id')->on('tasks_cycle')->cascadeOnDelete();
            $table->index(['task_id', 'created_at'], 'tsk_cy_evidence_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks_cycle_evidence');
        Schema::dropIfExists('tasks_cycle_audit');
        Schema::dropIfExists('tasks_cycle');
    }
};

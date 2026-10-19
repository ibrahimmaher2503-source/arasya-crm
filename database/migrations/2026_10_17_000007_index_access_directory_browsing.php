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
            $table->index(['company_id', 'name'], 'access_users_company_name_idx');
            $table->index(['company_id', 'branch_id', 'name'], 'access_users_branch_name_idx');
        });
        Schema::table('access_branches', function (Blueprint $table): void {
            $table->index(['company_id', 'name'], 'access_branches_company_name_idx');
        });
        Schema::table('access_departments', function (Blueprint $table): void {
            $table->index(['company_id', 'name'], 'access_depts_company_name_idx');
            $table->index(['company_id', 'branch_id', 'name'], 'access_depts_branch_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('access_users', function (Blueprint $table): void {
            $table->dropIndex('access_users_company_name_idx');
            $table->dropIndex('access_users_branch_name_idx');
        });
        Schema::table('access_branches', function (Blueprint $table): void {
            $table->dropIndex('access_branches_company_name_idx');
        });
        Schema::table('access_departments', function (Blueprint $table): void {
            $table->dropIndex('access_depts_company_name_idx');
            $table->dropIndex('access_depts_branch_name_idx');
        });
    }
};

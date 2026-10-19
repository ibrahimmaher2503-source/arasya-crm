<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_roles', function (Blueprint $table): void {
            $table->unique(['id', 'company_id'], 'access_role_company_id_unique');
        });

        Schema::create('access_department_roles', function (Blueprint $table): void {
            $table->char('company_id', 26);
            $table->char('branch_id', 26);
            $table->char('department_id', 26);
            $table->char('role_id', 26);
            $table->primary(['department_id', 'role_id']);
            $table->foreign(['department_id', 'company_id', 'branch_id'], 'access_dept_role_department_fk')
                ->references(['id', 'company_id', 'branch_id'])
                ->on('access_departments')
                ->restrictOnDelete();
            $table->foreign(['role_id', 'company_id'], 'access_dept_role_role_fk')
                ->references(['id', 'company_id'])
                ->on('access_roles')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_department_roles');
        Schema::table('access_roles', function (Blueprint $table): void {
            $table->dropUnique('access_role_company_id_unique');
        });
    }
};

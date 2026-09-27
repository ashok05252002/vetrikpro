<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * One row per promotion or revision: what changed, from when, and the
         * letter that told the person. The names are copied, so the history
         * still reads right after a designation is renamed.
         */
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('to_designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->string('from_designation_name')->nullable();
            $table->string('to_designation_name');
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->decimal('from_salary', 12, 2)->nullable();
            $table->decimal('to_salary', 12, 2);
            $table->date('effective_date');
            $table->text('note')->nullable();
            $table->string('letter_path')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
        });

        // Promoting people is its own permission; HR does it, as it handles salaries.
        $hr = DB::table('roles')->where('slug', 'hr')->value('id');

        if ($hr) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $hr, 'permission' => 'employees.promote']);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'employees.promote')->delete();
        DB::table('user_permission_overrides')->where('permission', 'employees.promote')->delete();
        Schema::dropIfExists('promotions');
    }
};

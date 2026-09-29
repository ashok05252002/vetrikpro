<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Interns are employees with employment_type "intern", managed from their
     * own Interns page. Instead of a salary they may be paid a monthly stipend:
     * has_stipend says whether, stipend how much.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('has_stipend')->default(false)->after('salary');
            $table->decimal('stipend', 12, 2)->nullable()->after('has_stipend');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['has_stipend', 'stipend']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master data that is in use is switched off, not deleted: an inactive
     * department or designation drops out of pickers but stays on every
     * record that already names it.
     */
    public function up(): void
    {
        foreach (['departments', 'designations'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('description');
            });
        }
    }

    public function down(): void
    {
        foreach (['departments', 'designations'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};

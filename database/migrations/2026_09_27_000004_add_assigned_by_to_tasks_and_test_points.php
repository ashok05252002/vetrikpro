<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who handed the work to its assignee. Set by the model whenever the
     * assignee changes, never typed. Rows assigned before this existed stay
     * null: guessing the creator would put words in someone's mouth.
     */
    public function up(): void
    {
        foreach (['tasks', 'test_points'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('assigned_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['tasks', 'test_points'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('assigned_by');
            });
        }
    }
};

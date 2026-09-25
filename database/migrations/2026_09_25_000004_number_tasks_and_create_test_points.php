<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-project numbers (T-12, TP-4) so work can be referred to by number —
 * a merge request lists "T-12, T-15" rather than titles. Existing tasks are
 * numbered in creation order within their project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->after('project_id');
        });

        foreach (DB::table('tasks')->distinct()->pluck('project_id') as $projectId) {
            $ids = DB::table('tasks')->where('project_id', $projectId)->orderBy('id')->pluck('id');

            foreach ($ids as $index => $id) {
                DB::table('tasks')->where('id', $id)->update(['number' => $index + 1]);
            }
        }

        Schema::table('tasks', function (Blueprint $table) {
            // The backstop: even a numbering race can never produce two T-12s.
            $table->unique(['project_id', 'number']);
        });

        Schema::create('test_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            // The task this point verifies, if any.
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('steps')->nullable();
            $table->text('expected_result')->nullable();
            $table->text('actual_result')->nullable();
            $table->string('status')->default('to_test');
            $table->string('priority')->default('medium');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Derived, like tasks.completed_at: set when a run reaches Passed or Failed.
            $table->foreignId('last_tested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_tested_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'number']);
            $table->index(['project_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_points');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'number']);
            $table->dropColumn('number');
        });
    }
};

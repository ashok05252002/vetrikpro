<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test runs: a named round of testing (e.g. "Release 1.2 regression") over a
 * chosen set of testing points, with one result per point. Each round keeps
 * its own results instead of overwriting the last one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });

        Schema::create('test_run_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_run_id')->constrained()->cascadeOnDelete();
            // Kept when the point is deleted: the run is a record of what was tested.
            $table->foreignId('test_point_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('point_number');
            $table->string('point_title');
            $table->string('result', 20)->default('not_run');
            $table->text('notes')->nullable();
            $table->foreignId('tested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tested_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['test_run_id', 'test_point_id']);
            $table->index(['test_point_id', 'test_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_run_results');
        Schema::dropIfExists('test_runs');
    }
};

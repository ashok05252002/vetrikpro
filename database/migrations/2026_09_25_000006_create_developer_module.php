<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branches registered by hand (created on GitHub, recorded here), the work
 * they carry, and merge requests reviewed by a project's dev admin.
 *
 * Nothing here talks to GitHub yet. A later integration would fill the same
 * rows — branch names are stored exactly as git knows them for that reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('base_branch', 200);
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();

            // One record per git branch per project.
            $table->unique(['project_id', 'name']);
            $table->index(['project_id', 'status']);
        });

        // The work a branch carries: its task points and testing points.
        Schema::create('branch_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'task_id']);
        });

        Schema::create('branch_test_point', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_point_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'test_point_id']);
        });

        Schema::create('merge_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('target_branch', 200);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            // The dev admin asked to review; null means any of them.
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('merged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('merged_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
            $table->index(['branch_id', 'status']);
            $table->index(['status', 'reviewer_id']);
        });

        // Append-only timeline: who did what to a merge request, and why.
        Schema::create('merge_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merge_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merge_request_events');
        Schema::dropIfExists('merge_requests');
        Schema::dropIfExists('branch_test_point');
        Schema::dropIfExists('branch_task');
        Schema::dropIfExists('branches');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which requirement points a task delivers. Many-to-many: one task may
     * cover several points and one point may need several tasks. A point
     * belongs to one requirement version; when a new version repeats a point
     * unchanged, its links are carried over (RequirementController).
     */
    public function up(): void
    {
        Schema::create('task_requirement_point', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_point_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_id', 'requirement_point_id']);
            $table->index('requirement_point_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_requirement_point');
    }
};

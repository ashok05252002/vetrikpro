<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every status change of a task or testing point: who moved it, from what,
 * to what, when. Append-only — rows are written by the models and never
 * edited, so the history can be trusted as a record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_changes', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Null on the first row: the status it was created in.
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_changes');
    }
};

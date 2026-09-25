<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A project's requirement documents: each is numbered (REQ-1, REQ-2…) and
 * keeps every version ever uploaded. A new version never replaces a file,
 * it adds one, so what a branch was built against can always be found.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });

        Schema::create('requirement_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            // Relative to the `documents` disk; never a URL.
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->text('change_note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['requirement_document_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_versions');
        Schema::dropIfExists('requirement_documents');
    }
};

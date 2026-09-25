<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Screenshots and photos attached to a testing point as evidence. Images
 * only, 2 MB each, on the private documents disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_point_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_point_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_point_attachments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Requirements stop being uploaded documents (REQ-n, any file type) and
     * become requirement points, kept in versions: V1, V1.1 … V1.10, V2, …
     * Each version is a complete list of points, entered by hand or imported
     * from the Excel template. The old documents and their files are removed
     * (Ashok's call, 2026-09-29: replace, not keep).
     */
    public function up(): void
    {
        foreach (DB::table('requirement_documents')->get(['id', 'project_id']) as $doc) {
            Storage::disk('documents')->deleteDirectory("projects/{$doc->project_id}/requirements/{$doc->id}");
        }

        Schema::dropIfExists('requirement_versions');
        Schema::dropIfExists('requirement_documents');

        Schema::create('requirement_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('major');
            $table->unsignedTinyInteger('minor'); // 0 … 10
            $table->string('source', 10); // excel | manual
            $table->string('file_name')->nullable(); // the uploaded workbook's name, for the record
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The sequence is enforced in code; this is the backstop.
            $table->unique(['project_id', 'major', 'minor']);
        });

        Schema::create('requirement_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number'); // Requirement S.No
            $table->string('module', 100);
            $table->text('description');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['requirement_version_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_points');
        Schema::dropIfExists('requirement_versions');
        // The old document tables are not recreated: their data is gone.
    }
};

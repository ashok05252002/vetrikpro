<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The daily summary email: who besides the owner gets each project's summary,
 * and when each address was last sent one, so the next email can say what
 * changed since then.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A recipient is either a person with an account (their email follows
        // their profile, and a disabled account stops receiving) or a plain
        // address for someone outside the app.
        Schema::create('project_summary_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
            $table->unique(['project_id', 'email']);
        });

        // One row per address. Only moves forward after a send succeeds, so a
        // failed day's changes are carried into the next email.
        Schema::create('summary_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamp('last_sent_at');
        });

        // The overdue email's "summary to project owners" became the project
        // summary; keep whatever the administrator had chosen.
        $owners = DB::table('settings')->where('key', 'notify.overdue.owners')->value('value');

        if ($owners !== null) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'notify.summary.projects'],
                ['value' => $owners, 'created_at' => now(), 'updated_at' => now()],
            );
            DB::table('settings')->where('key', 'notify.overdue.owners')->delete();
        }
    }

    public function down(): void
    {
        $projects = DB::table('settings')->where('key', 'notify.summary.projects')->value('value');

        if ($projects !== null) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'notify.overdue.owners'],
                ['value' => $projects, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        DB::table('settings')->where('key', 'like', 'notify.summary.%')->delete();

        Schema::dropIfExists('summary_deliveries');
        Schema::dropIfExists('project_summary_recipients');
    }
};

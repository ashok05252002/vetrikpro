<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A member's role is per project: dev admin on one, plain member on another.
        Schema::table('project_user', function (Blueprint $table) {
            $table->string('role')->default('member')->after('user_id');
            $table->index(['project_id', 'role']);
        });

        // Recorded by hand for now; the developer module reads them. A later
        // GitHub integration would fill the same columns.
        Schema::table('projects', function (Blueprint $table) {
            $table->string('repository_url')->nullable()->after('description');
            $table->string('default_branch')->default('main')->after('repository_url');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['repository_url', 'default_branch']);
        });

        Schema::table('project_user', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'role']);
            $table->dropColumn('role');
        });
    }
};

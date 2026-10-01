<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which letter was generated for someone: "offer" states the salary,
     * "welcome" does not (contract staff whose pay is not put in writing).
     * Null for an uploaded letter, or none.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('offer_letter_kind', 20)->nullable()->after('offer_letter_name');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('offer_letter_kind');
        });
    }
};

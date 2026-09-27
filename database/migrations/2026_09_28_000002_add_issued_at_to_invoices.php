<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Issuing and emailing are different events: an invoice handed over by hand
     * is issued but was never emailed. `sent_at` / `sent_to` now mean emailed
     * only; `issued_at` is when it got its number.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('issued_at')->nullable()->after('terms');
        });

        DB::table('invoices')->whereNotNull('number')->update(['issued_at' => DB::raw('sent_at')]);
        // Issued by hand: there was no email, so no sent date.
        DB::table('invoices')->whereNotNull('number')->whereNull('sent_to')->update(['sent_at' => null]);
    }

    public function down(): void
    {
        DB::table('invoices')->whereNotNull('number')->whereNull('sent_at')->update(['sent_at' => DB::raw('issued_at')]);

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('issued_at');
        });
    }
};

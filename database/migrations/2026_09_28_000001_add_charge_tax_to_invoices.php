<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether an invoice charges GST at all. Off, it carries no tax lines or
     * GST columns — the GSTINs and HSN/SAC codes still print.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('charge_tax')->default(true)->after('is_interstate');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('charge_tax');
        });
    }
};

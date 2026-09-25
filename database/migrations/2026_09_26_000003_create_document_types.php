<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The document checklist becomes configuration: which documents an employee
 * is asked for (Aadhaar, PAN…), which are required, and in what order.
 *
 * Replaces the fixed type enum on employee_documents. Every enum value
 * becomes a row with the same label, and existing uploads are pointed at
 * their row, so nothing already uploaded changes meaning.
 */
return new class extends Migration
{
    /** code => [name, required, system] in display order. */
    private const DEFAULTS = [
        'aadhaar' => ['Aadhaar card', true, false],
        'pan' => ['PAN card', true, false],
        'photo' => ['Passport-size photo', false, false],
        'id_proof' => ['ID proof', false, false],
        'address_proof' => ['Address proof', false, false],
        'education' => ['Education certificate', false, false],
        'experience' => ['Experience letter', false, false],
        'offer_letter' => ['Offer letter', false, false],
        // Uploaded by the employee during onboarding; the flow depends on it.
        'signed_offer_letter' => ['Signed offer letter', true, true],
        'contract' => ['Contract', false, false],
        'other' => ['Other', false, false],
    ];

    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('description')->nullable();
            // Asked of every new employee during onboarding.
            $table->boolean('is_required')->default(false);
            // Hidden types stay on documents already uploaded, but are not offered.
            $table->boolean('is_active')->default(true);
            // Shipped with the app and relied on by code; cannot be deleted.
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $order = 0;

        foreach (self::DEFAULTS as $code => [$name, $required, $system]) {
            DB::table('document_types')->insert([
                'name' => $name, 'code' => $code, 'is_required' => $required, 'is_system' => $system,
                'is_active' => true, 'sort_order' => $order++, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->foreignId('document_type_id')->nullable()->after('employee_id')->constrained()->restrictOnDelete();
        });

        foreach (DB::table('document_types')->pluck('id', 'code') as $code => $id) {
            DB::table('employee_documents')->where('type', $code)->update(['document_type_id' => $id]);
        }

        DB::table('employee_documents')->whereNull('document_type_id')
            ->update(['document_type_id' => DB::table('document_types')->where('code', 'other')->value('id')]);

        // New index first: on MySQL the old (employee_id, type) index backs the
        // employee_id foreign key and cannot be dropped until another covers it.
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->index(['employee_id', 'document_type_id']);
        });

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'type']);
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->string('type')->default('other')->after('employee_id');
        });

        $enum = ['id_proof', 'address_proof', 'education', 'experience', 'offer_letter', 'contract', 'other'];

        foreach (DB::table('document_types')->pluck('code', 'id') as $id => $code) {
            DB::table('employee_documents')->where('document_type_id', $id)->update(['type' => in_array($code, $enum, true) ? $code : 'other']);
        }

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->index(['employee_id', 'type']);
        });

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_type_id');
        });

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'document_type_id']);
        });

        Schema::dropIfExists('document_types');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service onboarding: HR creates the employee and sends an invite, the
 * employee sets a password and completes their own profile — bank details,
 * documents, the signed offer letter — and HR reviews it.
 *
 * onboarding_status is null for people who never went through onboarding
 * (everyone who existed before this), so nobody already working is suddenly
 * locked out of the portal behind a checklist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('onboarding_status')->nullable()->after('status');
            $table->timestamp('invited_at')->nullable()->after('onboarding_status');
            $table->timestamp('onboarding_submitted_at')->nullable()->after('invited_at');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_submitted_at');
            $table->foreignId('onboarding_reviewed_by')->nullable()->after('onboarding_completed_at')->constrained('users')->nullOnDelete();
            // What HR asked to be fixed when sending it back.
            $table->text('onboarding_note')->nullable()->after('onboarding_reviewed_by');

            // The letter HR sends with the invite; the signed copy comes back
            // as a document of the "signed offer letter" type.
            $table->string('offer_letter_path')->nullable()->after('onboarding_note');
            $table->string('offer_letter_name')->nullable()->after('offer_letter_path');

            $table->string('bank_account_name')->nullable()->after('address');
            // Encrypted by the model; text because ciphertext is long.
            $table->text('bank_account_number')->nullable()->after('bank_account_name');
            $table->string('bank_ifsc', 11)->nullable()->after('bank_account_number');
            $table->string('bank_name')->nullable()->after('bank_ifsc');
            $table->string('bank_branch')->nullable()->after('bank_name');

            $table->index('onboarding_status');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['onboarding_status']);
            $table->dropConstrainedForeignId('onboarding_reviewed_by');
            $table->dropColumn([
                'onboarding_status', 'invited_at', 'onboarding_submitted_at', 'onboarding_completed_at', 'onboarding_note',
                'offer_letter_path', 'offer_letter_name',
                'bank_account_name', 'bank_account_number', 'bank_ifsc', 'bank_name', 'bank_branch',
            ]);
        });
    }
};

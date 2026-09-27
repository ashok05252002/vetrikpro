<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounts: customers and products/services as master data, and GST
     * invoices raised against them.
     *
     * An invoice copies what it needs from its customer and products onto
     * itself, so editing a customer's address or a product's price later never
     * rewrites an invoice already sent. Money is decimal(12,2) throughout.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            // GST state code ("33" = Tamil Nadu): decides CGST+SGST or IGST.
            $table->string('state_code', 2)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 10)->default('service'); // product | service
            $table->string('code', 50)->nullable()->unique();
            $table->string('hsn_sac', 12)->nullable();
            $table->string('unit', 20)->default('nos');
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(18);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            // Given when the invoice is first issued; drafts have none, so a
            // deleted draft never leaves a gap in the series.
            $table->unsignedInteger('number')->nullable()->unique();
            $table->string('status', 12)->default('draft')->index(); // draft | sent | paid | cancelled
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            // The customer as billed, copied onto the invoice.
            $table->string('bill_name');
            $table->string('bill_email')->nullable();
            $table->text('bill_address')->nullable();
            $table->string('bill_gstin', 15)->nullable();
            $table->string('place_of_supply', 2)->nullable();
            $table->boolean('is_interstate')->default(false);

            $table->date('issue_date');
            $table->date('due_date')->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);       // quantity × cost
            $table->decimal('discount_total', 12, 2)->default(0); // quantity × (cost − discounted price)
            $table->decimal('taxable_total', 12, 2)->default(0);
            $table->decimal('cgst_total', 12, 2)->default(0);
            $table->decimal('sgst_total', 12, 2)->default(0);
            $table->decimal('igst_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('hsn_sac', 12)->nullable();
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20)->default('nos');
            $table->decimal('unit_price', 12, 2);       // cost
            $table->decimal('discounted_price', 12, 2); // what is actually charged per unit
            $table->decimal('gst_rate', 5, 2);
            $table->decimal('taxable', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('amount', 12, 2);           // taxable + tax
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // Invoices, customers and products are not in any built-in role but
        // the administrator's: who handles accounts is Ashok's call.
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('products');
        Schema::dropIfExists('customers');
    }
};

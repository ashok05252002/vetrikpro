<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renewals: anything that has to be paid again on a cycle — a domain, a
     * server, an SSL certificate, an AMC — for a customer or for the company.
     *
     * - `renewal_types` are the kinds (Domain, Server, AMC…), managed in
     *   Configuration hub → Renewal types. Each suggests a cycle and notice
     *   period for a new renewal. Seeded with the usual eight.
     * - `renewals` is the thing itself.
     * - `renewal_cycles` is one period: its due date, amount and how much of it
     *   has been paid. A renewal has at most one open (pending) cycle; paying it
     *   in full closes it and opens the next.
     * - `renewal_payments` are the payments against a cycle; several make a
     *   partial payment.
     * - `renewal_reminders` is the send log. One row per cycle, audience and
     *   day, so the daily reminder can never go out twice in a day.
     */
    public function up(): void
    {
        Schema::create('renewal_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('cycle_months')->default(12);
            $table->unsignedSmallInteger('remind_days')->default(15);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('renewal_types')->insert(array_map(fn (array $t, int $i) => [
            'name' => $t[0], 'cycle_months' => $t[1], 'remind_days' => $t[2], 'is_active' => true, 'sort_order' => $i,
            'created_at' => $now, 'updated_at' => $now,
        ], $types = [
            ['Domain', 12, 30],
            ['Hosting', 12, 15],
            ['Server / VPS', 1, 7],
            ['SSL certificate', 12, 15],
            ['Business email', 12, 15],
            ['AMC', 12, 30],
            ['Software licence', 12, 15],
            ['Other', 12, 15],
        ], array_keys($types)));

        Schema::create('renewals', function (Blueprint $table) {
            $table->id();
            // Null: the company's own renewal (our domain, our server).
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('renewal_type_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('identifier')->nullable();  // clientx.com, server IP, plan
            $table->string('provider')->nullable();    // GoDaddy, Hostinger, AWS…
            $table->unsignedSmallInteger('cycle_months')->default(12);
            $table->decimal('cost_price', 12, 2)->nullable();  // what we pay the vendor
            $table->decimal('sell_price', 12, 2)->nullable();  // what we charge the customer
            $table->unsignedSmallInteger('remind_days_before')->default(15);
            $table->boolean('repeat_daily')->default(true);
            $table->boolean('notify_customer')->default(false);
            $table->boolean('auto_renew')->default(false);
            // When the plan / service started; shown in the start confirmation.
            $table->date('start_date')->nullable();
            // Email the customer "your plan started on … next renewal on …" when added.
            $table->boolean('send_start_mail')->default(false);
            $table->timestamp('start_mail_sent_at')->nullable();
            $table->string('status', 12)->default('active'); // active | cancelled
            // Reminders are held until this date (the customer promised to pay).
            $table->date('snoozed_until')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('renewal_owners', function (Blueprint $table) {
            $table->foreignId('renewal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['renewal_id', 'user_id']);
        });

        Schema::create('renewal_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renewal_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->string('status', 12)->default('pending'); // pending | paid | written_off | skipped
            $table->date('closed_on')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });

        Schema::create('renewal_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renewal_cycle_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->string('mode', 12); // cash | upi | bank | cheque | card | other
            $table->string('reference')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('renewal_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renewal_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 10); // team | customer | start
            $table->date('sent_on');
            $table->json('recipients');
            $table->integer('days_left');
            $table->timestamps();

            $table->unique(['renewal_cycle_id', 'audience', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_reminders');
        Schema::dropIfExists('renewal_payments');
        Schema::dropIfExists('renewal_cycles');
        Schema::dropIfExists('renewal_owners');
        Schema::dropIfExists('renewals');
        Schema::dropIfExists('renewal_types');
    }
};

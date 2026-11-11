<?php

namespace Tests\Feature\Accounts;

use App\Enums\RenewalCycleStatus;
use App\Mail\RenewalReminderToCustomer;
use App\Mail\RenewalsDue;
use App\Mail\RenewalStarted;
use App\Models\Customer;
use App\Models\Renewal;
use App\Models\RenewalCycle;
use App\Models\RenewalReminder;
use App\Models\RenewalType;
use App\Models\Role;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RenewalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-10 09:00:00');
        $this->admin = User::factory()->admin()->create(['email' => 'owner@vetrik.test']);
        Mail::fake();
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::create(['name' => 'Client X', 'email' => 'accounts@clientx.test', ...$attributes]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'customer_id' => null,
            'renewal_type_id' => RenewalType::where('name', 'Domain')->value('id'),
            'title' => 'Client X domain',
            'identifier' => 'clientx.com',
            'provider' => 'GoDaddy',
            'cycle_months' => 12,
            'cost_price' => 800,
            'sell_price' => 1200,
            'remind_days_before' => 15,
            'repeat_daily' => true,
            'notify_customer' => false,
            'auto_renew' => false,
            'owner_ids' => [$this->admin->id],
            'start_date' => null,
            'send_start_mail' => false,
            'notes' => null,
            'due_date' => '2026-11-20',
            'amount' => null,
            ...$overrides,
        ];
    }

    private function create(array $overrides = []): Renewal
    {
        $this->actingAs($this->admin)->post(route('accounts.renewals.store'), $this->payload($overrides))->assertSessionHasNoErrors();

        return Renewal::latest('id')->first();
    }

    private function pay(Renewal $renewal, float $amount, string $on = '2026-11-10'): void
    {
        $this->actingAs($this->admin)->post(route('accounts.renewals.pay', $renewal), [
            'paid_on' => $on, 'amount' => $amount, 'mode' => 'upi',
        ])->assertSessionHasNoErrors();
    }

    private function remind(): void
    {
        $this->artisan('renewals:remind')->assertSuccessful();
    }

    public function test_adding_a_renewal_opens_its_first_period_at_the_selling_price()
    {
        $renewal = $this->create();

        $cycle = $renewal->openCycle;
        $this->assertSame('2026-11-20', $cycle->due_date->toDateString());
        $this->assertEquals(1200, $cycle->amount);
        $this->assertSame(Renewal::ACTIVE, $renewal->status);
    }

    public function test_no_reminder_before_the_notice_period_then_daily_until_paid()
    {
        $this->create(['due_date' => '2026-12-10']); // notice starts 25 Nov

        $this->remind();
        Mail::assertNothingSent();

        $this->travelTo('2026-11-25 10:00:00');
        $this->remind();
        Mail::assertSent(RenewalsDue::class, fn ($m) => $m->hasTo('owner@vetrik.test'));

        // Same day again: nothing new.
        $this->remind();
        Mail::assertSent(RenewalsDue::class, 1);

        // Every following day, past the due date too.
        $this->travelTo('2026-11-26 10:00:00');
        $this->remind();
        $this->travelTo('2026-12-15 10:00:00');
        $this->remind();
        Mail::assertSent(RenewalsDue::class, 3);
        $this->assertSame(3, RenewalReminder::count());
    }

    public function test_paying_in_full_stops_reminders_and_opens_the_next_period_from_the_due_date()
    {
        $renewal = $this->create(['due_date' => '2026-11-20']);
        $this->remind();
        Mail::assertSent(RenewalsDue::class, 1);

        // Paid late: the next due date still counts from 20 Nov, not the payment date.
        $this->travelTo('2026-11-25 10:00:00');
        $this->pay($renewal, 1200, '2026-11-25');

        $old = $renewal->cycles()->where('due_date', '2026-11-20')->first();
        $this->assertSame(RenewalCycleStatus::Paid, $old->status);
        $this->assertSame('2027-11-20', $renewal->fresh()->openCycle->due_date->toDateString());

        $this->remind();
        Mail::assertSent(RenewalsDue::class, 1);
    }

    public function test_a_part_payment_keeps_reminding_for_the_balance()
    {
        $renewal = $this->create(['due_date' => '2026-11-20', 'sell_price' => 10000]);

        $this->pay($renewal, 4000);
        $cycle = $renewal->fresh()->openCycle;
        $this->assertEquals(4000, $cycle->paid_total);
        $this->assertEquals(6000, $cycle->balance());
        $this->assertTrue($cycle->isPartlyPaid());

        $this->remind();
        Mail::assertSent(RenewalsDue::class, fn (RenewalsDue $m) => $m->items->first()['balance'] === 'Rs. 6,000.00');

        // The rest closes it.
        $this->pay($renewal, 6000);
        $this->assertSame('2027-11-20', $renewal->fresh()->openCycle->due_date->toDateString());
        $this->assertSame(2, $renewal->cycles()->count());
    }

    public function test_deleting_the_payment_that_closed_a_period_reopens_it()
    {
        $renewal = $this->create(['due_date' => '2026-11-20']);
        $this->pay($renewal, 1200);
        $payment = $renewal->cycles()->where('due_date', '2026-11-20')->first()->payments()->first();

        $this->actingAs($this->admin)->delete(route('accounts.renewals.payments.destroy', [$renewal, $payment]))->assertSessionHasNoErrors();

        $renewal->refresh();
        $this->assertSame(1, $renewal->cycles()->count());
        $this->assertSame('2026-11-20', $renewal->openCycle->due_date->toDateString());
        $this->assertEquals(0, $renewal->openCycle->paid_total);
    }

    public function test_writing_off_a_balance_closes_the_period()
    {
        $renewal = $this->create(['due_date' => '2026-11-20', 'sell_price' => 1000]);
        $this->pay($renewal, 950);

        $this->actingAs($this->admin)->post(route('accounts.renewals.write-off', $renewal))->assertSessionHasNoErrors();

        $this->assertSame(RenewalCycleStatus::WrittenOff, $renewal->cycles()->where('due_date', '2026-11-20')->first()->status);
        $this->assertSame('2027-11-20', $renewal->fresh()->openCycle->due_date->toDateString());
    }

    public function test_month_end_due_dates_do_not_overflow()
    {
        $renewal = $this->create(['due_date' => '2027-01-31', 'cycle_months' => 1, 'sell_price' => 500]);
        $this->pay($renewal, 500);

        $this->assertSame('2027-02-28', $renewal->fresh()->openCycle->due_date->toDateString());
    }

    public function test_without_daily_repeat_it_reminds_on_the_first_day_the_due_day_and_weekly()
    {
        $this->create(['due_date' => '2026-11-20', 'remind_days_before' => 10, 'repeat_daily' => false]);

        foreach (['2026-11-10', '2026-11-11', '2026-11-16', '2026-11-17', '2026-11-20', '2026-11-21', '2026-11-27'] as $day) {
            $this->travelTo("{$day} 10:00:00");
            $this->remind();
        }

        // 10 Nov (first), 17 Nov (a week on), 20 Nov (due day), 27 Nov (a week on).
        $this->assertSame(['2026-11-10', '2026-11-17', '2026-11-20', '2026-11-27'], RenewalReminder::orderBy('sent_on')->pluck('sent_on')->map->toDateString()->all());
    }

    public function test_cancelled_and_paused_renewals_are_not_reminded()
    {
        $cancelled = $this->create(['title' => 'Old server']);
        $paused = $this->create(['title' => 'Promised to pay']);

        $this->actingAs($this->admin)->post(route('accounts.renewals.cancel', $cancelled))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('accounts.renewals.snooze', $paused), ['until' => '2026-11-14'])->assertSessionHasNoErrors();

        $this->remind();
        Mail::assertNothingSent();
        $this->assertNull($cancelled->fresh()->openCycle);

        // The pause ends on its date.
        $this->travelTo('2026-11-14 10:00:00');
        $this->remind();
        Mail::assertSent(RenewalsDue::class, fn (RenewalsDue $m) => $m->items->pluck('title')->all() === ['Promised to pay']);
    }

    public function test_the_customer_is_emailed_only_with_both_switches_on_and_every_few_days()
    {
        $customer = $this->customer();
        $this->create(['customer_id' => $customer->id, 'notify_customer' => true, 'due_date' => '2026-11-20']);
        $this->create(['customer_id' => $customer->id, 'notify_customer' => false, 'title' => 'Hosting', 'due_date' => '2026-11-20']);

        $this->remind();
        Mail::assertSent(RenewalReminderToCustomer::class, fn ($m) => $m->hasTo('accounts@clientx.test') && $m->items->count() === 1);

        // Every 3 days by default.
        foreach (['2026-11-11', '2026-11-12', '2026-11-13'] as $day) {
            $this->travelTo("{$day} 10:00:00");
            $this->remind();
        }
        Mail::assertSent(RenewalReminderToCustomer::class, 2);

        // Master switch off: no more customer mail, the team still hears.
        app(Settings::class)->set(['notify.renewal.customer' => false]);
        $this->travelTo('2026-11-20 10:00:00');
        $this->remind();
        Mail::assertSent(RenewalReminderToCustomer::class, 2);
        Mail::assertSent(RenewalsDue::class, 5);
    }

    public function test_the_customer_email_never_carries_our_cost()
    {
        $customer = $this->customer();
        $renewal = $this->create(['customer_id' => $customer->id, 'notify_customer' => true]);

        $html = $this->actingAs($this->admin)->get(route('accounts.renewals.preview', [$renewal, 'customer']))->assertOk()->getContent();

        $this->assertStringContainsString('1,200.00', $html);
        $this->assertStringNotContainsString('800', $html);
        $this->assertStringNotContainsString('GoDaddy', $html);
    }

    public function test_extra_reminder_addresses_get_one_email_listing_everything()
    {
        app(Settings::class)->set(['notify.renewal.emails' => ['accounts@vetrik.test']]);
        $this->create(['title' => 'A']);
        $this->create(['title' => 'B', 'due_date' => '2026-11-05']); // overdue

        $this->remind();

        Mail::assertSent(RenewalsDue::class, fn ($m) => $m->hasTo('accounts@vetrik.test') && $m->items->pluck('title')->all() === ['B', 'A']);
        Mail::assertSent(RenewalsDue::class, 2); // the owner, and the extra address
    }

    public function test_reminders_can_be_switched_off()
    {
        app(Settings::class)->set(['notify.renewal.enabled' => false]);
        $this->create();

        $this->remind();
        Mail::assertNothingSent();
    }

    public function test_cost_is_hidden_and_kept_for_people_without_the_cost_permission()
    {
        $renewal = $this->create();
        $role = Role::create(['name' => 'Accounts', 'slug' => 'accounts']);
        foreach (['renewals.view', 'renewals.edit'] as $permission) {
            $role->permissionRows()->create(['permission' => $permission]);
        }
        $clerk = User::factory()->create(['role_id' => $role->id]);

        $page = $this->actingAs($clerk)->get(route('accounts.renewals.show', $renewal))->assertOk()->viewData('page')['props'];
        $this->assertNull($page['renewal']['cost_price']);

        $this->actingAs($clerk)->put(route('accounts.renewals.update', $renewal), $this->payload(['cost_price' => 1]))->assertSessionHasNoErrors();
        $this->assertEquals(800, $renewal->fresh()->cost_price);

        // No pay permission: cannot record a payment.
        $this->actingAs($clerk)->post(route('accounts.renewals.pay', $renewal), ['paid_on' => '2026-11-10', 'amount' => 1, 'mode' => 'upi'])->assertForbidden();
    }

    public function test_a_renewal_with_payments_cannot_be_deleted_and_its_customer_stays()
    {
        $customer = $this->customer();
        $renewal = $this->create(['customer_id' => $customer->id]);
        $this->pay($renewal, 100);

        $this->actingAs($this->admin)->delete(route('accounts.renewals.destroy', $renewal));
        $this->assertModelExists($renewal);

        $this->actingAs($this->admin)->delete(route('accounts.customers.destroy', $customer));
        $this->assertModelExists($customer);
    }

    public function test_the_list_and_detail_pages_render()
    {
        $renewal = $this->create(['due_date' => '2026-11-05']);

        $this->actingAs($this->admin)->get(route('accounts.renewals.index', ['due' => 'overdue']))->assertOk()
            ->assertInertia(fn ($page) => $page->component('accounts/renewals/index')->has('renewals.data', 1)->where('summary.overdue_count', 1));

        $this->actingAs($this->admin)->get(route('accounts.renewals.show', $renewal))->assertOk()
            ->assertInertia(fn ($page) => $page->component('accounts/renewals/show')->has('periods', 1));

        $this->actingAs($this->admin)->get(route('accounts.renewals.preview', [$renewal, 'team']))->assertOk();
    }

    public function test_restarting_a_cancelled_renewal_opens_a_new_period()
    {
        $renewal = $this->create();
        $this->actingAs($this->admin)->post(route('accounts.renewals.cancel', $renewal));
        $this->actingAs($this->admin)->post(route('accounts.renewals.reactivate', $renewal), ['due_date' => '2027-01-15'])->assertSessionHasNoErrors();

        $this->assertSame('2027-01-15', $renewal->fresh()->openCycle->due_date->toDateString());
        $this->assertSame(RenewalCycleStatus::Skipped, RenewalCycle::where('due_date', '2026-11-20')->first()->status);
    }

    public function test_several_responsible_people_each_get_the_reminder()
    {
        $other = User::factory()->create(['email' => 'second@vetrik.test']);
        $renewal = $this->create(['owner_ids' => [$this->admin->id, $other->id]]);

        $this->assertEqualsCanonicalizing([$this->admin->id, $other->id], $renewal->owners->pluck('id')->all());

        $this->remind();
        Mail::assertSent(RenewalsDue::class, fn ($m) => $m->hasTo('owner@vetrik.test'));
        Mail::assertSent(RenewalsDue::class, fn ($m) => $m->hasTo('second@vetrik.test'));

        // Editing replaces the list.
        $this->actingAs($this->admin)->put(route('accounts.renewals.update', $renewal), $this->payload(['owner_ids' => [$other->id]]))->assertSessionHasNoErrors();
        $this->assertSame([$other->id], $renewal->fresh()->owners->pluck('id')->all());
    }

    public function test_the_start_email_goes_to_the_customer_when_added_with_the_toggle_on()
    {
        $customer = $this->customer();
        $renewal = $this->create([
            'customer_id' => $customer->id, 'renewal_type_id' => RenewalType::where('name', 'AMC')->value('id'), 'title' => 'Client X AMC', 'identifier' => null,
            'start_date' => '2026-11-01', 'due_date' => '2027-11-01', 'send_start_mail' => true,
        ]);

        Mail::assertSent(RenewalStarted::class, function (RenewalStarted $m) {
            return $m->hasTo('accounts@clientx.test')
                && $m->plan['start'] === '1 November 2026'
                && $m->plan['due'] === '1 November 2027'
                && $m->plan['cycle'] === 'Yearly';
        });
        $this->assertNotNull($renewal->fresh()->start_mail_sent_at);
        $this->assertSame(1, RenewalReminder::where('audience', RenewalReminder::START)->count());

        // Not sent again on edit.
        $this->actingAs($this->admin)->put(route('accounts.renewals.update', $renewal), $this->payload([
            'customer_id' => $customer->id, 'start_date' => '2026-11-01', 'due_date' => '2027-11-01', 'send_start_mail' => true,
        ]))->assertSessionHasNoErrors();
        Mail::assertSent(RenewalStarted::class, 1);

        // Sent again on request.
        $this->actingAs($this->admin)->post(route('accounts.renewals.send-start', $renewal))->assertSessionHasNoErrors();
        Mail::assertSent(RenewalStarted::class, 2);
    }

    public function test_no_start_email_with_the_toggle_off_or_the_master_switch_off()
    {
        $customer = $this->customer();
        $this->create(['customer_id' => $customer->id, 'start_date' => '2026-11-01']);
        Mail::assertNotSent(RenewalStarted::class);

        app(Settings::class)->set(['notify.renewal.start' => false]);
        $renewal = $this->create(['customer_id' => $customer->id, 'start_date' => '2026-11-01', 'send_start_mail' => true]);
        Mail::assertNotSent(RenewalStarted::class);
        $this->assertNull($renewal->start_mail_sent_at);
    }

    public function test_the_start_email_needs_a_customer_and_a_start_date_before_the_due_date()
    {
        $this->actingAs($this->admin)->post(route('accounts.renewals.store'), $this->payload(['send_start_mail' => true, 'start_date' => '2026-11-01']))
            ->assertSessionHasErrors('send_start_mail');

        $customer = $this->customer();
        $this->actingAs($this->admin)->post(route('accounts.renewals.store'), $this->payload(['customer_id' => $customer->id, 'send_start_mail' => true]))
            ->assertSessionHasErrors('start_date');

        $this->actingAs($this->admin)->post(route('accounts.renewals.store'), $this->payload(['start_date' => '2026-12-01', 'due_date' => '2026-11-20']))
            ->assertSessionHasErrors('start_date');
    }

    public function test_the_start_email_preview_renders_without_our_cost()
    {
        $customer = $this->customer();
        $renewal = $this->create(['customer_id' => $customer->id, 'start_date' => '2025-11-20']);

        $html = $this->actingAs($this->admin)->get(route('accounts.renewals.preview', [$renewal, 'start']))->assertOk()->getContent();

        $this->assertStringContainsString('20 November 2025', $html);
        $this->assertStringContainsString('20 November 2026', $html);
        $this->assertStringNotContainsString('800', $html);
        Mail::assertNothingSent();
    }

    public function test_renewal_types_are_managed_in_the_configuration_hub()
    {
        $this->actingAs($this->admin)->get(route('admin.config.renewal-types.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/config/renewal-types')->has('types', 8));

        $this->actingAs($this->admin)->post(route('admin.config.renewal-types.store'), [
            'name' => 'Website maintenance', 'cycle_months' => 6, 'remind_days' => 10, 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $type = RenewalType::where('name', 'Website maintenance')->sole();

        // Usable on a renewal, then it cannot be deleted, only switched off.
        $renewal = $this->create(['renewal_type_id' => $type->id, 'identifier' => 'clientx.com']);
        $this->assertSame('Website maintenance clientx.com', $renewal->customerLabel());

        $this->actingAs($this->admin)->delete(route('admin.config.renewal-types.destroy', $type));
        $this->assertModelExists($type);

        $this->actingAs($this->admin)->put(route('admin.config.renewal-types.update', $type), [
            'name' => 'Website maintenance', 'cycle_months' => 6, 'remind_days' => 10, 'is_active' => false,
        ])->assertSessionHasNoErrors();

        // Switched off: not offered for a new renewal, but the existing one still saves with it.
        $this->actingAs($this->admin)->post(route('accounts.renewals.store'), $this->payload(['renewal_type_id' => $type->id]))
            ->assertSessionHasErrors('renewal_type_id');
        $this->actingAs($this->admin)->put(route('accounts.renewals.update', $renewal), $this->payload(['renewal_type_id' => $type->id]))
            ->assertSessionHasNoErrors();

        // An unused type can be deleted.
        $unused = RenewalType::where('name', 'Other')->sole();
        $this->actingAs($this->admin)->delete(route('admin.config.renewal-types.destroy', $unused));
        $this->assertModelMissing($unused);
    }
}

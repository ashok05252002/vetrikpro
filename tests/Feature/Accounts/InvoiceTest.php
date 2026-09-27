<?php

namespace Tests\Feature\Accounts;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceSent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\Accounts\InvoicePdf;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        app(Settings::class)->set(['company.state' => '33']); // Tamil Nadu
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::create(['name' => 'Acme Foods', 'email' => 'accounts@acme.test', 'state_code' => '33', ...$attributes]);
    }

    private function payload(Customer $customer, array $overrides = []): array
    {
        return [
            'customer_id' => $customer->id,
            'issue_date' => '2026-09-27',
            'due_date' => '2026-10-12',
            'items' => [
                ['description' => 'HACCP audit', 'quantity' => 2, 'unit' => 'nos', 'unit_price' => 1000, 'discounted_price' => 900, 'gst_rate' => 18],
                ['description' => 'Training', 'quantity' => 1, 'unit' => 'hrs', 'unit_price' => 500, 'discounted_price' => 500, 'gst_rate' => 5],
            ],
            ...$overrides,
        ];
    }

    public function test_a_draft_is_saved_with_totals_computed_on_the_server()
    {
        $customer = $this->customer();

        $this->actingAs($this->admin)->post(route('accounts.invoices.store'), [
            ...$this->payload($customer),
            'total' => 1, // ignored: totals are never taken from the form
        ])->assertRedirect();

        $invoice = Invoice::sole();
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertNull($invoice->number);
        $this->assertSame('Draft', $invoice->reference());
        $this->assertEquals(2500, $invoice->subtotal);
        $this->assertEquals(200, $invoice->discount_total);
        $this->assertEquals(2300, $invoice->taxable_total);
        // 1800 × 18% = 324, 500 × 5% = 25 → 349, split within the state.
        $this->assertEquals(174.5, $invoice->cgst_total);
        $this->assertEquals(174.5, $invoice->sgst_total);
        $this->assertEquals(0, $invoice->igst_total);
        $this->assertEquals(2649, $invoice->total);
        $this->assertSame('Acme Foods', $invoice->bill_name);
    }

    public function test_a_customer_in_another_state_is_charged_igst()
    {
        $customer = $this->customer(['state_code' => '29']); // Karnataka

        $this->actingAs($this->admin)->post(route('accounts.invoices.store'), $this->payload($customer));

        $invoice = Invoice::sole();
        $this->assertTrue($invoice->is_interstate);
        $this->assertEquals(349, $invoice->igst_total);
        $this->assertEquals(0, $invoice->cgst_total);
    }

    public function test_a_discounted_price_above_the_cost_is_refused()
    {
        $payload = $this->payload($this->customer());
        $payload['items'][0]['discounted_price'] = 1200;

        $this->actingAs($this->admin)->post(route('accounts.invoices.store'), $payload)->assertSessionHasErrors('items.0.discounted_price');
    }

    public function test_sending_issues_the_next_number_and_emails_the_pdf()
    {
        Mail::fake();
        $customer = $this->customer();
        $this->actingAs($this->admin)->post(route('accounts.invoices.store'), $this->payload($customer));
        $this->actingAs($this->admin)->post(route('accounts.invoices.store'), $this->payload($customer));
        [$first, $second] = Invoice::orderBy('id')->get();

        // Sent out of order: numbers follow issuing, not drafting.
        $this->actingAs($this->admin)->post(route('accounts.invoices.send', $second), ['to' => 'billing@acme.test', 'cc' => ['cfo@acme.test']])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('accounts.invoices.send', $first), ['to' => 'billing@acme.test'])->assertSessionHas('success');

        $this->assertSame('INV-0001', $second->fresh()->reference());
        $this->assertSame('INV-0002', $first->fresh()->reference());
        $this->assertSame(InvoiceStatus::Sent, $first->fresh()->status);
        $this->assertSame('billing@acme.test', $first->fresh()->sent_to);

        Mail::assertSent(InvoiceSent::class, fn (InvoiceSent $mail) => $mail->hasTo('billing@acme.test') && $mail->hasCc('cfo@acme.test')
            && str_starts_with($mail->attachments()[0]->attachWith(fn ($path) => '', fn ($data) => $data()), '%PDF'));
    }

    public function test_an_issued_invoice_cannot_be_edited_or_deleted_only_cancelled()
    {
        $customer = $this->customer();
        $this->actingAs($this->admin)->post(route('accounts.invoices.store'), $this->payload($customer));
        $invoice = Invoice::sole();
        $this->actingAs($this->admin)->post(route('accounts.invoices.mark-sent', $invoice));

        $this->actingAs($this->admin)->put(route('accounts.invoices.update', $invoice), $this->payload($customer))->assertStatus(409);
        $this->actingAs($this->admin)->delete(route('accounts.invoices.destroy', $invoice))->assertSessionHas('error');
        $this->assertNotNull($invoice->fresh());

        $this->actingAs($this->admin)->post(route('accounts.invoices.cancel', $invoice))->assertSessionHas('success');
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->fresh()->status);
        $this->assertSame(1, $invoice->fresh()->number);
    }

    public function test_marking_paid_records_the_date()
    {
        $this->actingAs($this->admin)->post(route('accounts.invoices.store'), $this->payload($this->customer()));
        $invoice = Invoice::sole();
        $this->actingAs($this->admin)->post(route('accounts.invoices.mark-sent', $invoice));

        $this->actingAs($this->admin)->post(route('accounts.invoices.mark-paid', $invoice), ['paid_on' => '2026-09-27'])->assertSessionHas('success');

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('2026-09-27', $invoice->fresh()->paid_at->toDateString());
    }

    public function test_the_pdf_downloads()
    {
        $this->actingAs($this->admin)->post(route('accounts.invoices.store'), $this->payload($this->customer()));

        $response = $this->actingAs($this->admin)->get(route('accounts.invoices.pdf', Invoice::sole()));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_an_invoiced_customer_and_product_cannot_be_deleted_only_switched_off()
    {
        $customer = $this->customer();
        $product = Product::create(['name' => 'Audit', 'type' => 'service', 'unit' => 'nos', 'price' => 1000, 'gst_rate' => 18]);
        $payload = $this->payload($customer);
        $payload['items'][0]['product_id'] = $product->id;
        $this->actingAs($this->admin)->post(route('accounts.invoices.store'), $payload);

        $this->actingAs($this->admin)->delete(route('accounts.customers.destroy', $customer))->assertSessionHas('error');
        $this->actingAs($this->admin)->delete(route('accounts.products.destroy', $product))->assertSessionHas('error');

        $this->actingAs($this->admin)->patch(route('accounts.products.active', $product), ['is_active' => false]);

        // Switched off: refused on a new invoice…
        $this->actingAs($this->admin)->post(route('accounts.invoices.store'), $payload)->assertSessionHasErrors('items.0.product_id');
        // …but kept by the draft that already has it.
        $this->actingAs($this->admin)->put(route('accounts.invoices.update', Invoice::first()), $payload)->assertSessionHasNoErrors();
    }

    public function test_accounts_are_closed_to_people_without_the_permissions()
    {
        $this->actingAs(User::factory()->create())->get(route('accounts.invoices.index'))->assertForbidden();
        $this->actingAs(User::factory()->hr()->create())->get(route('accounts.customers.index'))->assertForbidden();
    }

    public function test_a_gstin_fills_in_the_customer_state()
    {
        $this->actingAs($this->admin)->post(route('accounts.customers.store'), ['name' => 'Bengaluru Bakes', 'gstin' => '29abcde1234f1z5'])->assertSessionHasNoErrors();

        $customer = Customer::sole();
        $this->assertSame('29ABCDE1234F1Z5', $customer->gstin);
        $this->assertSame('29', $customer->state_code);
    }

    public function test_every_accounts_page_renders()
    {
        $customer = $this->customer();
        $this->actingAs($this->admin)->post(route('accounts.invoices.store'), $this->payload($customer));
        $invoice = Invoice::sole();

        $pages = [
            route('accounts.invoices.index') => 'accounts/invoices/index',
            route('accounts.invoices.create') => 'accounts/invoices/edit',
            route('accounts.invoices.show', $invoice) => 'accounts/invoices/show',
            route('accounts.invoices.edit', $invoice) => 'accounts/invoices/edit',
            route('accounts.customers.index') => 'accounts/customers',
            route('accounts.products.index') => 'accounts/products',
        ];

        foreach ($pages as $url => $component) {
            $this->actingAs($this->admin)->get($url)->assertOk()->assertInertia(fn ($page) => $page->component($component));
        }
    }

    public function test_an_invoice_without_tax_has_no_tax_lines_but_keeps_gstins()
    {
        $customer = $this->customer(['gstin' => '33ABCDE1234F1Z5']);

        $this->actingAs($this->admin)->post(route('accounts.invoices.store'), [...$this->payload($customer), 'charge_tax' => false])->assertSessionHasNoErrors();

        $invoice = Invoice::sole();
        $this->assertFalse($invoice->charge_tax);
        $this->assertEquals(0, $invoice->cgst_total + $invoice->sgst_total + $invoice->igst_total);
        $this->assertEquals(2300, $invoice->total);

        $html = app(InvoicePdf::class)->html($invoice);
        $this->assertStringNotContainsString('TAX INVOICE', $html);
        $this->assertStringNotContainsString('CGST', $html);
        $this->assertStringContainsString('33ABCDE1234F1Z5', $html);
    }
}

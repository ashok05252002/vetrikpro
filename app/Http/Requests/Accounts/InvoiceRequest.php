<?php

namespace App\Http\Requests\Accounts;

use App\Models\Customer;
use App\Models\Product;
use App\Support\IndianStates;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $invoice = $this->route('invoice');
        // Products already on this draft may stay on it after being switched off.
        $onInvoice = $invoice?->items()->pluck('product_id')->filter()->all() ?? [];

        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id', Customer::selectableRule($invoice?->customer_id)],
            'bill_email' => ['nullable', 'email', 'max:255'],
            'bill_address' => ['nullable', 'string', 'max:1000'],
            'place_of_supply' => ['nullable', Rule::in(array_map('strval', array_keys(IndianStates::ALL)))],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id', function (string $attribute, mixed $value, Closure $fail) use ($onInvoice) {
                if ($value !== null && ! in_array((int) $value, $onInvoice, true) && ! Product::whereKey($value)->where('is_active', true)->exists()) {
                    $fail('That product has been switched off.');
                }
            }],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:12'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit' => ['required', 'string', 'max:20'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'items.*.discounted_price' => ['required', 'numeric', 'min:0', 'lte:items.*.unit_price'],
            'items.*.gst_rate' => ['required', Rule::in(Product::GST_RATES)],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one line.',
            'items.min' => 'Add at least one line.',
            'items.*.description.required' => 'Describe the line.',
            'items.*.quantity.gt' => 'Quantity must be more than zero.',
            'items.*.discounted_price.lte' => 'The discounted price cannot be more than the cost.',
            'due_date.after_or_equal' => 'The due date cannot be before the invoice date.',
        ];
    }
}

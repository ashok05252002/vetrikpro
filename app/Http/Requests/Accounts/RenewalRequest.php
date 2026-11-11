<?php

namespace App\Http\Requests\Accounts;

use App\Models\Renewal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewalRequest extends FormRequest
{
    public const CYCLES = [1, 3, 6, 12, 24, 36, 60];

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function cycleOptions(): array
    {
        return array_map(fn (int $m) => ['value' => (string) $m, 'label' => (new Renewal(['cycle_months' => $m]))->cycleLabel()], self::CYCLES);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $renewal = $this->route('renewal');

        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where(fn ($q) => $renewal?->customer_id
                ? $q->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $renewal->customer_id))
                : $q->where('is_active', true))],
            'renewal_type_id' => ['required', 'integer', Rule::exists('renewal_types', 'id')->where(fn ($q) => $renewal
                ? $q->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $renewal->renewal_type_id))
                : $q->where('is_active', true))],
            'title' => ['required', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'cycle_months' => ['required', 'integer', Rule::in(self::CYCLES)],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'sell_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'remind_days_before' => ['required', 'integer', 'min:0', 'max:365'],
            'repeat_daily' => ['required', 'boolean'],
            'notify_customer' => ['required', 'boolean'],
            'auto_renew' => ['required', 'boolean'],
            'owner_ids' => ['present', 'array', 'max:20'],
            'owner_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'start_date' => ['nullable', 'date', 'required_if_accepted:send_start_mail'],
            'send_start_mail' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            // The open period. Required when there is one to set (new, or active).
            'due_date' => [$renewal && ! $renewal->isActive() ? 'nullable' : 'required', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'due_date.required' => 'Enter the next due (expiry) date.',
            'start_date.required_if_accepted' => 'Enter when the plan started; the start email says it.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['cost_price', 'sell_price', 'amount', 'customer_id', 'start_date'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }

        $this->merge([
            'owner_ids' => array_values(array_filter((array) $this->input('owner_ids', []), fn ($id) => $id !== '' && $id !== null)),
            'send_start_mail' => $this->boolean('send_start_mail'),
        ]);
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->boolean('notify_customer') && ! $this->filled('customer_id')) {
                $validator->errors()->add('notify_customer', 'Pick a customer to send reminders to.');
            }

            if ($this->boolean('send_start_mail') && ! $this->filled('customer_id')) {
                $validator->errors()->add('send_start_mail', 'Pick a customer to send the start email to.');
            }

            if ($this->filled('start_date') && $this->filled('due_date') && strtotime((string) $this->input('start_date')) >= strtotime((string) $this->input('due_date'))) {
                $validator->errors()->add('start_date', 'The plan must start before its next renewal date.');
            }
        }];
    }
}

<?php

namespace App\Http\Requests\Accounts;

use App\Support\IndianStates;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'state_code' => ['nullable', Rule::in(array_map('strval', array_keys(IndianStates::ALL)))],
            // 15 characters, the first two being the state code.
            'gstin' => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z0-9]{13}$/', function (string $attribute, mixed $value, Closure $fail) {
                $prefix = substr((string) $value, 0, 2);

                if (! isset(IndianStates::ALL[$prefix])) {
                    $fail("A GSTIN starts with its state code, and {$prefix} is not one. Check the first two digits.");
                } elseif ($this->filled('state_code') && $this->input('state_code') !== $prefix) {
                    $fail('This GSTIN is registered in '.IndianStates::name($prefix)." ({$prefix}), but the state chosen is ".IndianStates::name($this->input('state_code')).'.');
                }
            }],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('gstin')) {
            $this->merge(['gstin' => strtoupper(trim((string) $this->input('gstin')))]);
        }

        // A GSTIN names its state; fill the state from it when left blank — but
        // only with a real state code, so a mistyped GSTIN is reported on the
        // GSTIN, not on a State field the person never touched.
        $prefix = substr((string) $this->input('gstin'), 0, 2);

        if (! $this->filled('state_code') && isset(IndianStates::ALL[$prefix])) {
            $this->merge(['state_code' => $prefix]);
        }
    }

    public function messages(): array
    {
        return ['gstin.regex' => 'A GSTIN is 15 characters: the two-digit state code, then letters and digits.'];
    }
}

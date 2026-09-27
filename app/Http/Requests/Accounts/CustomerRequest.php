<?php

namespace App\Http\Requests\Accounts;

use App\Support\IndianStates;
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
            'gstin' => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z0-9]{13}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('gstin')) {
            $this->merge(['gstin' => strtoupper(trim((string) $this->input('gstin')))]);
        }

        // A GSTIN names its state; fill the state from it when left blank.
        if (! $this->filled('state_code') && $this->filled('gstin')) {
            $this->merge(['state_code' => substr((string) $this->input('gstin'), 0, 2)]);
        }
    }

    public function messages(): array
    {
        return ['gstin.regex' => 'A GSTIN is 15 characters: the two-digit state code, then letters and digits.'];
    }
}

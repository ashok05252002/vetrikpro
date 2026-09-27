<?php

namespace App\Http\Requests\Admin;

use App\Support\IndianStates;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:120'],
            'company_legal_name' => ['nullable', 'string', 'max:160'],
            'company_tax_id' => ['nullable', 'string', 'max:60'],
            'company_state' => ['nullable', Rule::in(array_map('strval', array_keys(IndianStates::ALL)))],
            'invoice_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'invoice_terms' => ['nullable', 'string', 'max:2000'],
            'invoice_bank_details' => ['nullable', 'string', 'max:1000'],

            'company_email' => ['nullable', 'email', 'max:160'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_website' => ['nullable', 'url', 'max:200'],
            'company_address' => ['nullable', 'string', 'max:500'],

            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'remove_logo' => ['boolean'],
            'logo_dark' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'remove_logo_dark' => ['boolean'],
            // `image` refuses .ico, which is what most favicons are.
            'favicon' => ['nullable', 'file', 'mimes:ico,png,svg', 'max:512'],
            'remove_favicon' => ['boolean'],

            'display_timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'display_date_format' => ['required', Rule::in(['dmy', 'mdy', 'ymd'])],
            'display_currency' => ['required', 'string', 'size:3', 'alpha'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'The company name cannot be empty — it is used across the whole app.',
            'logo.max' => 'The logo must be 2 MB or smaller.',
            'logo_dark.max' => 'The logo must be 2 MB or smaller.',
            'favicon.max' => 'The favicon must be 512 KB or smaller.',
            'favicon.mimes' => 'Use an ICO, PNG or SVG file for the favicon.',
            'display_currency.size' => 'Use a three-letter ISO currency code, such as INR or EUR.',
        ];
    }
}

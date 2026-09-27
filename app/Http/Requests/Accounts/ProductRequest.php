<?php

namespace App\Http\Requests\Accounts;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Product::TYPES))],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Product::class)->ignore($this->route('product')?->id)],
            'hsn_sac' => ['nullable', 'string', 'max:12'],
            'unit' => ['required', 'string', 'max:20'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'gst_rate' => ['required', Rule::in(Product::GST_RATES)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

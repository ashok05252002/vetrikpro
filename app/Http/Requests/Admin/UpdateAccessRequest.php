<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Role and overrides for the person behind an employee profile — the same
 * rules as the user form, reached from the profile's Access tab.
 */
class UpdateAccessRequest extends FormRequest
{
    use ValidatesAccess;

    public function authorize(): bool
    {
        return $this->user()->can('roles.manage')
            && $this->user()->canGrant($this->route('employee')->user->permissions());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->accessRules();
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateAccess($validator, $this->route('employee')->user)];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesAccess;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use ValidatesAccess;

    /**
     * Nobody edits an account that holds access they lack — otherwise HR could
     * reset the administrator's password and sign in as them.
     */
    public function authorize(): bool
    {
        return $this->user()->canGrant($this->route('user')->permissions());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class)->ignore($this->route('user')->id),
            ],
            ...$this->accessRules(),
            'is_active' => ['required', 'boolean'],
            // Optional on update: blank means "leave the current password alone".
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateAccess($validator, $this->route('user'))];
    }
}

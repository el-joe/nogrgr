<?php

namespace App\Modules\Affiliates\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * AFF-013b change password: the current one (guard affiliate) and a new confirmed one.
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:affiliate'],
            'password' => ['required', 'string', 'max:191', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => (string) __('affiliates::panel.profile.current_password'),
            'password' => (string) __('affiliates::panel.profile.new_password'),
        ];
    }

    public function password(): string
    {
        return (string) $this->validated('password');
    }
}

<?php

namespace App\Modules\Identity\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Central store login › reset password (WEB-024).
 */
class StoreLoginResetRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'confirmed', 'max:191', Password::min(8)->letters()->numbers()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => __('identity::auth.email'), 'password' => __('identity::auth.password')];
    }
}

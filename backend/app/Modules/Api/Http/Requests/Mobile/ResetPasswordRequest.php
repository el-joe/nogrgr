<?php

namespace App\Modules\Api\Http\Requests\Mobile;

use Illuminate\Validation\Rules\Password;

/**
 * Reset the password with the e-mailed code.
 */
class ResetPasswordRequest extends CheckResetCodeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'password' => ['required', 'string', 'max:191', 'confirmed', Password::min(8)]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...parent::attributes(), 'password' => __('identity::account.fields.new_password')];
    }
}

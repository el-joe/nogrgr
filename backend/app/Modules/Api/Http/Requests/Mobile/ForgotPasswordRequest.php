<?php

namespace App\Modules\Api\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Forgot password: the e-mail to send the reset code to.
 */
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:191']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => __('identity::account.fields.email')];
    }
}

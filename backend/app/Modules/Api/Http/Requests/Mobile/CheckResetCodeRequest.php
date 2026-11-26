<?php

namespace App\Modules\Api\Http\Requests\Mobile;

/**
 * Check a reset code before asking for the new password.
 */
class CheckResetCodeRequest extends ForgotPasswordRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['code' => preg_replace('/\D+/', '', (string) $this->input('code'))]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'code' => ['required', 'digits:6']];
    }
}

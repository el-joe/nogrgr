<?php

namespace App\Modules\Platform\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Wrong e-mail? Change it" on the verification page (WEB-017).
 */
class ChangeRegistrationEmailRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => __('platform::signup.form.email')];
    }
}

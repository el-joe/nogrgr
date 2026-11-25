<?php

namespace App\Modules\Platform\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Complete registration › account step (WEB-018): owner name + password. The token
 * travels in the body; the controller re-checks it before saving.
 */
class CompleteRegistrationAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:128'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('platform::register.account.name'),
            'password' => __('platform::register.account.password'),
            'terms' => __('platform::register.account.terms_attribute'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['terms.accepted' => __('platform::register.account.terms_required')];
    }
}

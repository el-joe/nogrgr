<?php

namespace App\Modules\Identity\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Central store login › forgot password (WEB-023).
 */
class StoreLoginForgotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        return ['email' => __('identity::auth.email')];
    }
}

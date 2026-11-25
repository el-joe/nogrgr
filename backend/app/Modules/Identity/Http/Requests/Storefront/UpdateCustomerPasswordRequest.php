<?php

namespace App\Modules\Identity\Http\Requests\Storefront;

use App\Modules\Identity\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Account › Profile › Change password: current password + new password confirmed.
 */
class UpdateCustomerPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') instanceof Customer;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:customer'],
            'password' => ['required', 'string', 'max:191', 'confirmed', 'different:current_password', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => __('identity::account.fields.current_password'),
            'password' => __('identity::account.fields.new_password'),
        ];
    }
}

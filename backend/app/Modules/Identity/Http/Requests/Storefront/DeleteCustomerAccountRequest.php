<?php

namespace App\Modules\Identity\Http\Requests\Storefront;

use App\Modules\Identity\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Account › Profile › Delete account: confirmed with the current password.
 */
class DeleteCustomerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') instanceof Customer;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password:customer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => __('identity::account.fields.password')];
    }
}

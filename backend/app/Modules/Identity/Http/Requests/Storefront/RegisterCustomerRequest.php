<?php

namespace App\Modules\Identity\Http\Requests\Storefront;

use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Modules\Identity\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Storefront registration: name, unique email, optional phone (intl-tel-input:
 * number + ISO2 country, unique, valid for the country), password min 8 + confirmation.
 */
class RegisterCustomerRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }

        $this->normalizePhoneInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique(Customer::class, 'email')],
            'phone_country' => ['required_with:phone', 'nullable', 'string', 'size:2', $this->phoneRegionRule()],
            'phone' => ['nullable', 'string', 'max:32', (new Phone)->countryField('phone_country'), Rule::unique(Customer::class, 'phone')],
            'password' => ['required', 'string', 'max:191', 'confirmed', Password::min(8)],
            'remember' => ['sometimes', 'boolean'],
            'redirect' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('identity::account.fields.name'),
            'email' => __('identity::account.fields.email'),
            'phone' => __('identity::account.fields.phone'),
            'phone_country' => __('identity::account.fields.phone_country'),
            'password' => __('identity::account.fields.password'),
        ];
    }
}

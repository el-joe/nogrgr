<?php

namespace App\Modules\Identity\Http\Requests\Storefront;

use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Modules\Identity\Models\Customer;
use App\Shared\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Account › Profile: name, email, phone, language.
 */
class UpdateCustomerProfileRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return $this->user('customer') instanceof Customer;
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
        $customer = $this->user('customer');
        $id = $customer instanceof Customer ? $customer->id : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique(Customer::class, 'email')->ignore($id)],
            'phone_country' => ['required_with:phone', 'nullable', 'string', 'size:2', $this->phoneRegionRule()],
            'phone' => ['nullable', 'string', 'max:32', (new Phone)->countryField('phone_country'), Rule::unique(Customer::class, 'phone')->ignore($id)],
            'locale' => ['nullable', 'string', Rule::in(array_keys(Locale::enabled()))],
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
            'locale' => __('identity::account.fields.locale'),
        ];
    }
}

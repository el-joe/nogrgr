<?php

namespace App\Modules\Identity\Http\Requests\Vendor;

use App\Modules\Identity\DTOs\VendorCustomerData;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Modules\Identity\Models\Customer;
use App\Shared\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Vendor › Customers create / edit (VEN-025a, Figma 84:107236): first + last name,
 * e-mail (unique in the store, required to subscribe to the newsletter), phone
 * (intl-tel-input number + ISO2 country, unique, required when creating), language.
 * Authorisation is the route's `customers.manage` permission.
 */
class CustomerRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone')) && trim($this->input('phone')) === '') {
            $this->merge(['phone' => null]);
        }

        $this->normalizePhoneInput();

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('customer');
        $creating = $id === null;

        return [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'required_if_accepted:newsletter', 'string', 'email', 'max:191', Rule::unique(Customer::class, 'email')->ignore($id)],
            'phone_country' => ['nullable', 'required_with:phone', 'string', 'size:2', $this->phoneRegionRule()],
            'phone' => [$creating ? 'required' : 'nullable', 'string', 'max:32', (new Phone)->countryField('phone_country'), Rule::unique(Customer::class, 'phone')->ignore($id)],
            'locale' => ['nullable', 'string', Rule::in(array_keys(Locale::enabled()))],
            'newsletter' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => __('identity::customers.fields.first_name'),
            'last_name' => __('identity::customers.fields.last_name'),
            'email' => __('identity::customers.fields.email'),
            'phone' => __('identity::customers.fields.phone'),
            'phone_country' => __('identity::customers.fields.phone_country'),
            'locale' => __('identity::customers.fields.locale'),
            'newsletter' => __('identity::customers.fields.newsletter'),
        ];
    }

    public function toData(): VendorCustomerData
    {
        return VendorCustomerData::fromRequest($this);
    }
}

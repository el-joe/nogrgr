<?php

namespace App\Modules\Identity\Http\Requests\Storefront;

use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Modules\Localization\Rules\StoreCountry;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * THE canonical address form (docs/plan/06-storefront-account-spec.md §1), used by the
 * storefront account page, checkout, the vendor panel and the mobile API. Authorisation
 * (whose address) is the caller's job; this request only validates the fields.
 *
 * `phone` must be valid for `phone_country` (libphonenumber) and is stored as E.164;
 * `country_id` must be an active central country the store sells to.
 */
class SaveCustomerAddressRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
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
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'label' => ['nullable', 'string', 'max:50'],
            'phone_country' => ['required', 'string', 'size:2', $this->phoneRegionRule()],
            'phone' => ['required', 'string', 'max:32', (new Phone)->countryField('phone_country')],
            'email' => ['nullable', 'string', 'email', 'max:120'],
            'address_line_1' => ['required', 'string', 'max:300'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country_id' => ['required', 'integer', new StoreCountry],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => __('identity::addresses.fields.full_name'),
            'label' => __('identity::addresses.fields.label'),
            'phone' => __('identity::addresses.fields.phone'),
            'phone_country' => __('identity::addresses.fields.phone_country'),
            'email' => __('identity::addresses.fields.email'),
            'address_line_1' => __('identity::addresses.fields.address_line_1'),
            'city' => __('identity::addresses.fields.city'),
            'state' => __('identity::addresses.fields.state'),
            'country_id' => __('identity::addresses.fields.country_id'),
            'latitude' => __('identity::addresses.fields.latitude'),
            'longitude' => __('identity::addresses.fields.longitude'),
            'is_default' => __('identity::addresses.fields.is_default'),
        ];
    }
}

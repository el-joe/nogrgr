<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Platform\Support\CheckoutDemoSamples;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * The canonical address fields (same rules as the storefront SaveCustomerAddressRequest)
 * for the session-backed checkout gallery; countries are the gallery's sample list.
 */
class CheckoutDemoAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone_country'))) {
            $this->merge(['phone_country' => strtoupper(trim($this->input('phone_country')))]);
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
            'phone_country' => ['required', 'string', 'size:2'],
            'phone' => ['required', 'string', 'max:32', (new Phone)->countryField('phone_country')],
            'email' => ['nullable', 'string', 'email', 'max:120'],
            'address_line_1' => ['required', 'string', 'max:300'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country_id' => ['required', 'integer', Rule::in(array_column(CheckoutDemoSamples::countries(), 'id'))],
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
        return collect(['full_name', 'label', 'phone', 'phone_country', 'email', 'address_line_1', 'city', 'state', 'country_id', 'latitude', 'longitude', 'is_default'])
            ->mapWithKeys(fn (string $field): array => [$field => __('identity::addresses.fields.'.$field)])
            ->all();
    }
}

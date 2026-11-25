<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Modules\Localization\Models\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Vendor profile › Edit store: store name, owner contact, default and selling countries.
 */
class VendorStoreInfoRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->can('vendors.manage');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('owner_email'))) {
            $this->merge(['owner_email' => Str::lower(trim($this->input('owner_email')))]);
        }

        $this->normalizePhoneInput('owner_phone', 'owner_phone_country');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'string', 'email', 'max:191'],
            'owner_phone_country' => ['required_with:owner_phone', 'nullable', 'string', 'size:2', $this->phoneRegionRule()],
            'owner_phone' => ['nullable', 'string', 'max:32', (new Phone)->countryField('owner_phone_country')],
            'default_country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'selling_country_ids' => ['nullable', 'array', 'max:250'],
            'selling_country_ids.*' => ['integer', 'distinct', Rule::exists(Country::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('platform::vendors.fields.store_name'),
            'owner_name' => __('platform::vendors.fields.owner_name'),
            'owner_email' => __('platform::vendors.fields.owner_email'),
            'owner_phone' => __('platform::vendors.fields.owner_phone'),
            'owner_phone_country' => __('platform::vendors.fields.owner_phone'),
            'default_country_id' => __('platform::vendors.fields.default_country'),
            'selling_country_ids' => __('platform::vendors.fields.countries'),
            'selling_country_ids.*' => __('platform::vendors.fields.countries'),
        ];
    }

    /**
     * Input for VendorStoreData.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return [
            'name' => trim((string) $data['name']),
            'owner_name' => trim((string) $data['owner_name']),
            'owner_email' => (string) $data['owner_email'],
            'owner_phone' => $data['owner_phone'] ?? null,
            'default_country_id' => isset($data['default_country_id']) ? (int) $data['default_country_id'] : null,
            'selling_country_ids' => array_values(array_map('intval', (array) ($data['selling_country_ids'] ?? []))),
        ];
    }
}

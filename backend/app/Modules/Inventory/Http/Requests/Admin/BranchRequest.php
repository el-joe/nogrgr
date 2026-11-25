<?php

namespace App\Modules\Inventory\Http\Requests\Admin;

use App\Modules\Catalog\Support\CatalogLocales;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Localization\Models\City;
use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Models\ShippingZone;
use App\Shared\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone as PhoneRule;

/**
 * Branch create / edit (ADM-030a): translated name, unique code, country and city,
 * address, contact phone (intl-tel-input, stored E.164), pickup hours, default-for
 * countries and served shipping zones (zones of the rules covering the branch country).
 */
class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $country = $this->input('phone_country');
        $phone = $this->input('phone');

        if (is_string($country)) {
            $this->merge(['phone_country' => strtoupper(trim($country))]);
        }

        if (is_string($phone) && trim($phone) !== '') {
            $this->merge(['phone' => Phone::e164($phone, is_string($country) ? strtoupper($country) : null) ?? trim($phone)]);
        }

        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branch = $this->route('branch');
        $countryId = (int) $this->input('country_id');

        return [
            ...app(CatalogLocales::class)->rules('name', ['string', 'max:120']),
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique(Branch::class, 'code')->ignore($branch instanceof Branch ? $branch->id : null)],
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')],
            'city_id' => ['nullable', 'integer', Rule::exists(City::class, 'id')->where('country_id', $countryId)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'phone' => ['required', 'string', 'max:32', (new PhoneRule)->countryField('phone_country')],
            'pickup_hours' => ['nullable', 'string', 'max:120'],
            'allows_pickup' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'default_country_ids' => ['nullable', 'array'],
            'default_country_ids.*' => ['integer', 'distinct', Rule::exists(Country::class, 'id')],
            'zone_ids' => ['nullable', 'array', 'max:200'],
            'zone_ids.*' => ['integer', 'distinct', Rule::exists(ShippingZone::class, 'id')->where(static fn ($query) => $query->whereIn('shipping_rule_id', static fn ($rules) => $rules->select('shipping_rule_id')->from('shipping_rule_countries')->where('country_id', $countryId)))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('inventory::branches.fields.name'),
            'code' => __('inventory::branches.fields.code'),
            'country_id' => __('inventory::branches.fields.country'),
            'city_id' => __('inventory::branches.fields.city'),
            'address' => __('inventory::branches.fields.address'),
            'phone' => __('inventory::branches.fields.phone'),
            'pickup_hours' => __('inventory::branches.fields.pickup_hours'),
            'default_country_ids' => __('inventory::branches.fields.default_for'),
            'zone_ids' => __('inventory::branches.fields.zones'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $text = fn (string $key): ?string => $this->filled($key) ? trim((string) $this->validated($key)) : null;

        return [
            'name' => app(CatalogLocales::class)->clean($this->validated('name')),
            'code' => (string) $this->validated('code'),
            'country_id' => (int) $this->validated('country_id'),
            'city_id' => $this->filled('city_id') ? (int) $this->validated('city_id') : null,
            'address' => $text('address'),
            'phone' => $text('phone'),
            'pickup_hours' => $text('pickup_hours'),
            'allows_pickup' => $this->boolean('allows_pickup'),
            'is_active' => $this->boolean('is_active'),
            'default_country_ids' => array_map('intval', (array) ($this->validated('default_country_ids') ?? [])),
            'zone_ids' => array_map('intval', (array) ($this->validated('zone_ids') ?? [])),
        ];
    }
}

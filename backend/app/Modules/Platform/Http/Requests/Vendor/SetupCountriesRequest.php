<?php

namespace App\Modules\Platform\Http\Requests\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\DTOs\SetupCountriesData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Wizard step 1 (countries): at least one active country, the default one among them.
 */
class SetupCountriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'country_ids' => ['required', 'array', 'min:1', 'max:250', $this->activeCountries(...)],
            'country_ids.*' => ['integer', 'min:1', 'distinct'],
            'default_country_id' => ['required', 'integer', 'in:'.implode(',', array_map('intval', (array) $this->input('country_ids', [])))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'country_ids' => (string) __('platform::setup.countries.label'),
            'default_country_id' => (string) __('platform::setup.countries.default'),
        ];
    }

    public function toData(): SetupCountriesData
    {
        /** @var VendorStaff $staff */
        $staff = $this->user('vendor');

        return new SetupCountriesData(
            countryIds: array_values(array_unique(array_map('intval', (array) $this->validated('country_ids')))),
            defaultCountryId: (int) $this->validated('default_country_id'),
            submittedBy: (string) $staff->name,
        );
    }

    private function activeCountries(string $attribute, mixed $value, Closure $fail): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $value))));

        if ($ids === [] || app(CountryService::class)->active($ids)->count() !== count($ids)) {
            $fail('validation.exists')->translate();
        }
    }
}

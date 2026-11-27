<?php

namespace App\Modules\Storefront\Http\Requests\Admin;

use App\Modules\Localization\Models\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ADM-086 Countries modal: the countries a theme may be used in (none = every country).
 */
class ThemeCountriesRequest extends FormRequest
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
            'country_ids' => ['nullable', 'array'],
            'country_ids.*' => ['integer', 'distinct', Rule::exists(Country::class, 'id')],
        ];
    }

    /**
     * @return list<int>
     */
    public function countryIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('country_ids', [])));
    }
}

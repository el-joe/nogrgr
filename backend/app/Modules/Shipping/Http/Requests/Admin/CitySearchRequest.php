<?php

namespace App\Modules\Shipping\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Select2 remote search for zone cities: ?country=<id>[,<id>…]&q=<term>.
 */
class CitySearchRequest extends FormRequest
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
            'country' => ['required', 'string', 'max:2000', 'regex:/^\d+(,\d+)*$/'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return list<int>
     */
    public function countryIds(): array
    {
        return array_values(array_unique(array_map('intval', explode(',', (string) $this->validated('country')))));
    }
}

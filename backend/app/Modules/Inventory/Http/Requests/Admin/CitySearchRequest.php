<?php

namespace App\Modules\Inventory\Http\Requests\Admin;

use App\Modules\Localization\Models\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Select2 city / zone lookup of the branch form: ?country=…&q=…
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
            'country' => ['required', 'integer', Rule::exists(Country::class, 'id')],
            'q' => ['nullable', 'string', 'max:80'],
        ];
    }
}

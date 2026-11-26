<?php

namespace App\Modules\Identity\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * City options of the vendor address modal (VEN-025c): `country_id` of one of the
 * store's selling countries (checked by the controller) and the typed term `q`.
 */
class CustomerCitiesRequest extends FormRequest
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
            'country_id' => ['required', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

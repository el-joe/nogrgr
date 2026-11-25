<?php

namespace App\Modules\Commerce\Http\Requests\StoreApi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cart country switch; the store-country rule is checked by CartService (translated
 * "not sold in your country" message).
 */
class SwitchCountryRequest extends FormRequest
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
            'country_id' => ['required', 'integer', 'min:1'],
            'city_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

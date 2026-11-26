<?php

namespace App\Modules\Storefront\Http\Requests\StoreApi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Header search suggestions: `q` (results from 2 characters), `limit` 1–10 products.
 */
class AutocompleteRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }
}

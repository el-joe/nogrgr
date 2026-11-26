<?php

namespace App\Modules\Storefront\Http\Requests\StoreApi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Product details: optional preselected `variant`.
 */
class ProductShowRequest extends FormRequest
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
        return ['variant' => ['nullable', 'integer', 'min:1']];
    }
}
